<?php
/**
 * Email — SMTP delivery, template rendering and the newsletter plumbing.
 *
 * Deliberately has no third-party dependency: the site ships without a
 * composer step, so the SMTP conversation is spoken directly over a socket
 * the same way send_sms() talks to Twilio over cURL.
 *
 * Everything that leaves the building goes through email_wrap(), which is
 * what guarantees the rule the whole list depends on — no email is ever
 * built without an unsubscribe link in the footer.
 */

// =====================================================================
//  Configuration
// =====================================================================

/** The SMTP mailbox and the addresses shown to the reader. */
function mail_config(): array
{
    $user = trim(setting('smtp_user'));

    return [
        'host'       => trim(setting('smtp_host')),
        'port'       => (int)setting('smtp_port', '465'),
        'secure'     => strtolower(trim(setting('smtp_secure', 'ssl'))),   // ssl | tls | none
        'user'       => $user,
        'pass'       => (string)setting('smtp_pass'),
        'verify'     => setting('smtp_verify_cert', '1') === '1',
        'from_email' => trim(setting('mail_from_email')) ?: $user,
        'from_name'  => trim(setting('mail_from_name', setting('site_name', 'Unika Kebab'))),
        'reply_to'   => trim(setting('mail_reply_to')),
        'timeout'    => 20,
    ];
}

/** Enough of the mailbox filled in to attempt a send? */
function mail_is_configured(): bool
{
    $cfg = mail_config();
    return $cfg['host'] !== '' && $cfg['port'] > 0 && $cfg['from_email'] !== '';
}

/** Configured *and* switched on. */
function mail_is_enabled(): bool
{
    return setting('email_enabled', '1') === '1' && mail_is_configured();
}

/** How many recipients one batch of a campaign send handles. */
function mail_batch_size(): int
{
    return max(1, min(200, (int)setting('email_batch_size', '20')));
}

/**
 * Everything standing between the admin and a working mailbox, in plain
 * English. An empty list means the setup is complete.
 */
function mail_problems(): array
{
    $cfg      = mail_config();
    $problems = [];

    if ($cfg['host'] === '')                       { $problems[] = 'The outgoing mail server (SMTP host) is empty.'; }
    if ($cfg['port'] <= 0)                         { $problems[] = 'The SMTP port is not a number.'; }
    if ($cfg['from_email'] === '')                 { $problems[] = 'There is no "from" address for outgoing mail.'; }
    if ($cfg['user'] !== '' && $cfg['pass'] === ''){ $problems[] = 'The mailbox has a username but no password.'; }
    if (!extension_loaded('openssl') && $cfg['secure'] !== 'none') {
        $problems[] = 'PHP is built without OpenSSL, so an encrypted connection is not possible.';
    }
    if (setting('email_enabled', '1') !== '1')     { $problems[] = 'Sending is switched off in Settings.'; }

    return $problems;
}

// =====================================================================
//  Unsubscribe links
//
//  The token is an HMAC of the address rather than a column on the row,
//  which matters here: unsubscribing *deletes* the subscriber, and a
//  stored token would die with it. This way a second click on the same
//  link still lands on a friendly "you are already off the list" page.
// =====================================================================

/** A random key kept in settings, created the first time it is needed. */
function app_secret(): string
{
    static $secret = null;

    if ($secret === null) {
        $secret = trim(setting('app_secret'));
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            setting_set('app_secret', $secret);
        }
    }

    return $secret;
}

function unsubscribe_token(string $email): string
{
    return substr(hash_hmac('sha256', mb_strtolower(trim($email)), app_secret()), 0, 32);
}

function unsubscribe_token_check(string $email, string $token): bool
{
    return hash_equals(unsubscribe_token($email), trim($token));
}

/** The one-click link that rides along in the footer of every email. */
function unsubscribe_url(string $email): string
{
    return abs_url('unsubscribe.php')
         . '?e=' . rawurlencode(trim($email))
         . '&t=' . unsubscribe_token($email);
}

/**
 * Take somebody off the list for good.
 * Their pending sends go with them, so a campaign already half-way
 * through the queue never reaches an address that just opted out.
 */
function unsubscribe_email(string $email): bool
{
    $row = db_one('SELECT id FROM subscribers WHERE email = ?', [$email]);

    db_run(
        'DELETE FROM email_queue WHERE email = ? AND status = ?',
        [$email, 'pending']
    );

    if (!$row) {
        return false;                    // already gone — still a success for the reader
    }

    db_run('DELETE FROM subscribers WHERE id = ?', [(int)$row['id']]);
    return true;
}

// =====================================================================
//  Templates
// =====================================================================

/** Every template, newest edit first, with the welcome one pinned to the top. */
function email_templates(): array
{
    return db_all(
        "SELECT * FROM email_templates
          ORDER BY (kind = 'welcome') DESC, name"
    );
}

function email_template(int $id): ?array
{
    return $id > 0 ? db_one('SELECT * FROM email_templates WHERE id = ?', [$id]) : null;
}

/** The template sent automatically to a new subscriber, if there is one. */
function welcome_template(): ?array
{
    return db_one("SELECT * FROM email_templates WHERE kind = 'welcome' ORDER BY id LIMIT 1");
}

// =====================================================================
//  Placeholders
// =====================================================================

/**
 * The {{tokens}} an admin can drop into a subject or body, with the
 * short description shown beside them in the editor.
 */
function email_placeholder_help(): array
{
    return [
        'first_name'      => 'First name only, or “there” when we do not know it',
        'name'            => 'The name they signed up with',
        'email'           => 'Their email address',
        'site_name'       => 'Your restaurant name',
        'site_tagline'    => 'The tagline under the name',
        'phone'           => 'Store phone number',
        'address'         => 'Full store address on one line',
        'menu_url'        => 'Link to the menu page',
        'order_url'       => 'Link to the order online page',
        'contact_url'     => 'Link to the contact page',
        'locations_url'   => 'Link to the locations page',
        'site_url'        => 'Link to the home page',
        'unsubscribe_url' => 'Their personal unsubscribe link',
        'year'            => 'The current year',
        'date'            => 'Today’s date',
    ];
}

/**
 * Placeholder values for one reader.
 * $person is a subscriber row, or anything with name/email keys.
 */
function email_vars(array $person = []): array
{
    $shop  = store();
    $name  = trim((string)($person['name'] ?? ''));
    $email = trim((string)($person['email'] ?? ''));
    $first = $name !== '' ? (preg_split('/\s+/', $name)[0] ?? $name) : '';

    return [
        'first_name'      => $first !== '' ? $first : 'there',
        'name'            => $name !== '' ? $name : 'there',
        'email'           => $email,
        'site_name'       => setting('site_name', 'Unika Kebab'),
        'site_tagline'    => setting('site_tagline', ''),
        'phone'           => (string)($shop['phone'] ?? ''),
        'address'         => store_address(),
        'store_email'     => (string)($shop['email'] ?? ''),
        'menu_url'        => abs_url('menu.php'),
        'order_url'       => abs_url('order.php'),
        'contact_url'     => abs_url('contact.php'),
        'locations_url'   => abs_url('locations.php'),
        'site_url'        => abs_url(),
        'unsubscribe_url' => $email !== '' ? unsubscribe_url($email) : abs_url('unsubscribe.php'),
        'year'            => date('Y'),
        'date'            => date('j F Y'),
    ];
}

/**
 * Swap {{tokens}} for their values.
 * Values are escaped by default, because most of them end up in HTML.
 */
function email_render(string $text, array $vars, bool $escape = true): string
{
    $map = [];
    foreach ($vars as $key => $value) {
        $map['{{' . $key . '}}'] = $escape ? e($value) : (string)$value;
    }

    return strtr($text, $map);
}

// =====================================================================
//  Turning admin-written HTML into email-safe HTML
// =====================================================================

/**
 * Email clients throw away <style> blocks, so the look has to live on
 * each tag. The admin writes ordinary HTML — <h2>, <p>, <ul> — and this
 * paints the house style onto anything that has not been styled by hand.
 * A style written by the admin still wins: it is appended last.
 */
function email_inline_styles(string $html): string
{
    $body = 'font-family:Helvetica,Arial,sans-serif;';

    $styles = [
        'h1'         => $body . 'margin:0 0 16px;font-size:27px;line-height:1.25;font-weight:700;color:#12100e;',
        'h2'         => $body . 'margin:0 0 14px;font-size:23px;line-height:1.3;font-weight:700;color:#12100e;',
        'h3'         => $body . 'margin:26px 0 10px;font-size:18px;line-height:1.35;font-weight:700;color:#12100e;',
        'p'          => $body . 'margin:0 0 16px;font-size:16px;line-height:1.65;color:#3a332c;',
        'ul'         => $body . 'margin:0 0 16px;padding-left:22px;font-size:16px;line-height:1.65;color:#3a332c;',
        'ol'         => $body . 'margin:0 0 16px;padding-left:22px;font-size:16px;line-height:1.65;color:#3a332c;',
        'li'         => 'margin:0 0 8px;',
        'a'          => 'color:#cb8c0e;text-decoration:underline;',
        'a.button'   => $body . 'display:inline-block;margin:6px 0 18px;padding:14px 30px;background:#f4b007;'
                      . 'color:#12100e;font-size:16px;font-weight:700;text-decoration:none;border-radius:999px;',
        'blockquote' => $body . 'margin:0 0 18px;padding:14px 18px;background:#fff8ec;border-left:4px solid #f4b007;'
                      . 'font-size:16px;line-height:1.6;color:#3a332c;font-style:italic;',
        'hr'         => 'margin:26px 0;border:0;border-top:1px solid #e6e1d9;',
        'table'      => $body . 'width:100%;margin:0 0 18px;border-collapse:collapse;font-size:15px;color:#3a332c;',
        'th'         => 'padding:9px 12px;border:1px solid #e6e1d9;background:#fff8ec;text-align:left;font-weight:700;color:#12100e;',
        'td'         => 'padding:9px 12px;border:1px solid #e6e1d9;text-align:left;',
        'img'        => 'max-width:100%;height:auto;border:0;border-radius:12px;',
        'strong'     => 'color:#12100e;',
    ];

    $tags = ['h1', 'h2', 'h3', 'p', 'ul', 'ol', 'li', 'a', 'blockquote', 'hr', 'table', 'th', 'td', 'img', 'strong'];

    return (string)preg_replace_callback(
        '/<(' . implode('|', $tags) . ')\b([^>]*)>/i',
        function (array $m) use ($styles): string {
            $tag   = strtolower($m[1]);
            $attrs = $m[2];

            // <hr /> and <img /> keep their closing slash.
            $close = '';
            if (substr(rtrim($attrs), -1) === '/') {
                $attrs = rtrim(rtrim($attrs), '/');
                $close = ' /';
            }

            $style = $styles[$tag];
            if ($tag === 'a' && preg_match('/\bclass\s*=\s*["\']?[^"\']*\bbutton\b/i', $attrs)) {
                $style = $styles['a.button'];
            }

            // Hand-written styles are kept and applied after ours, so they win.
            if (preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $attrs, $own)) {
                $attrs = str_replace($own[0], '', $attrs);
                $style .= rtrim(trim($own[2]), ';') . ';';
            }

            return '<' . $tag . rtrim($attrs) . ' style="' . $style . '"' . $close . '>';
        },
        $html
    );
}

/**
 * Wrap finished content in the branded shell: charcoal header, cream
 * body, store details and — always, without exception — the unsubscribe
 * footer. Nothing else in the codebase builds an email body.
 */
function email_wrap(string $contentHtml, array $vars, string $preheader = ''): string
{
    $siteName    = (string)($vars['site_name'] ?? setting('site_name', 'Unika Kebab'));
    $tagline     = (string)($vars['site_tagline'] ?? '');
    $address     = (string)($vars['address'] ?? '');
    $phone       = (string)($vars['phone'] ?? '');
    $siteUrl     = (string)($vars['site_url'] ?? abs_url());
    $unsubscribe = (string)($vars['unsubscribe_url'] ?? abs_url('unsubscribe.php'));
    $logo        = abs_url(setting('logo', 'assets/img/logo.png'));
    $note        = trim(setting('email_footer_note', 'You are receiving this because you joined the ' . $siteName . ' list.'));

    $content = email_inline_styles($contentHtml);

    // Sits in the inbox preview line, invisible once the email is opened.
    $hidden = $preheader === '' ? '' :
        '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all">'
        . e($preheader) . str_repeat('&#8199;&#847;', 60) . '</div>';

    return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>' . e($siteName) . '</title>
<style type="text/css">
  body { margin:0; padding:0; width:100% !important; }
  img { -ms-interpolation-mode:bicubic; }
  a { color:#cb8c0e; }
  @media only screen and (max-width:620px) {
    .uk-wrap { width:100% !important; }
    .uk-pad  { padding-left:22px !important; padding-right:22px !important; }
    .uk-head { padding:26px 22px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#f2ede4;">
' . $hidden . '
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f2ede4;">
  <tr>
    <td align="center" style="padding:26px 12px;">

      <table role="presentation" class="uk-wrap" cellpadding="0" cellspacing="0" border="0" width="600"
             style="width:600px;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;
                    box-shadow:0 2px 10px rgba(18,16,14,0.06);">

        <!-- header -->
        <tr>
          <td class="uk-head" align="center" style="background:#12100e;padding:30px 36px;">
            <a href="' . e($siteUrl) . '" style="text-decoration:none;">
              <img src="' . e($logo) . '" alt="' . e($siteName) . '" width="120"
                   style="display:block;margin:0 auto 10px;width:120px;max-width:120px;height:auto;border:0;" />
            </a>
            <div style="font-family:Helvetica,Arial,sans-serif;font-size:19px;font-weight:700;
                        letter-spacing:.08em;text-transform:uppercase;color:#f4b007;">' . e($siteName) . '</div>'
            . ($tagline === '' ? '' : '
            <div style="font-family:Helvetica,Arial,sans-serif;font-size:12px;letter-spacing:.22em;
                        text-transform:uppercase;color:rgba(255,248,236,.62);padding-top:5px;">' . e($tagline) . '</div>') . '
          </td>
        </tr>
        <tr><td style="height:4px;background:#f4b007;line-height:4px;font-size:0;">&nbsp;</td></tr>

        <!-- content -->
        <tr>
          <td class="uk-pad" style="padding:34px 40px 12px;font-family:Helvetica,Arial,sans-serif;
                                    font-size:16px;line-height:1.65;color:#3a332c;">
            ' . $content . '
          </td>
        </tr>

        <!-- store strip -->
        <tr>
          <td class="uk-pad" style="padding:8px 40px 30px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                   style="background:#fff8ec;border-radius:14px;">
              <tr>
                <td align="center" style="padding:18px 22px;font-family:Helvetica,Arial,sans-serif;
                                          font-size:14px;line-height:1.6;color:#6d6259;">'
                  . ($address === '' ? '' : '<div style="color:#12100e;font-weight:700;">' . e($address) . '</div>')
                  . ($phone === '' ? '' : '<div style="padding-top:3px;">'
                      . '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone)) . '"
                             style="color:#cb8c0e;text-decoration:none;">' . e($phone) . '</a></div>') . '
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- footer: the unsubscribe button is part of the shell, so no
             email can be built without one -->
        <tr>
          <td align="center" style="background:#12100e;padding:26px 34px 30px;">
            <div style="font-family:Helvetica,Arial,sans-serif;font-size:13px;line-height:1.6;
                        color:rgba(255,248,236,.55);padding-bottom:14px;">' . e($note) . '</div>

            <a href="' . e($unsubscribe) . '"
               style="display:inline-block;padding:11px 26px;border:1px solid rgba(255,248,236,.34);
                      border-radius:999px;font-family:Helvetica,Arial,sans-serif;font-size:14px;
                      font-weight:700;color:#fff8ec;text-decoration:none;">Unsubscribe</a>

            <div style="font-family:Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;
                        color:rgba(255,248,236,.4);padding-top:16px;">
              &copy; ' . e(date('Y')) . ' ' . e($siteName) . ' &middot;
              <a href="' . e($siteUrl) . '" style="color:rgba(255,248,236,.55);text-decoration:underline;">'
              . e(preg_replace('#^https?://#i', '', $siteUrl)) . '</a>
            </div>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>';
}

/** A readable plain-text version, for clients that will not show HTML. */
function email_html_to_text(string $html): string
{
    // Keep the destination of every link — a bare "click here" is useless in text.
    $text = preg_replace_callback(
        '#<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
        function (array $m): string {
            $label = trim(strip_tags($m[3]));
            $href  = trim($m[2]);
            if ($label === '' || strcasecmp($label, $href) === 0) {
                return $href;
            }
            return $label . ' (' . $href . ')';
        },
        $html
    );

    $text = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr)\s*/?>#i', "\n", (string)$text);
    $text = preg_replace('#<(hr)\s*/?>#i', "\n----------------------\n", (string)$text);
    $text = preg_replace('#<li\b[^>]*>#i', '  • ', (string)$text);
    $text = preg_replace('#<(td|th)\b[^>]*>#i', "\t", (string)$text);
    $text = strip_tags((string)$text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Tidy the whitespace the tags left behind.
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/ ?\n ?/", "\n", (string)$text);
    $text = preg_replace("/\n{3,}/", "\n\n", (string)$text);

    return trim((string)$text);
}

/**
 * Build one finished email for one reader.
 * Returns ['subject', 'html', 'text', 'unsubscribe_url'].
 */
function email_compose(array $person, string $subject, string $bodyHtml, string $preheader = ''): array
{
    $vars = email_vars($person);

    $subject   = trim(email_render($subject, $vars, false));
    $preheader = trim(email_render($preheader, $vars, false));
    $content   = email_render($bodyHtml, $vars);

    $html = email_wrap($content, $vars, $preheader);

    $text = email_html_to_text($content);
    if ($preheader !== '') {
        $text = $preheader . "\n\n" . $text;
    }
    $text .= "\n\n----------------------\n"
           . trim(setting('email_footer_note', '')) . "\n"
           . 'Unsubscribe: ' . $vars['unsubscribe_url'];

    return [
        'subject'         => $subject,
        'html'            => $html,
        'text'            => trim($text),
        'unsubscribe_url' => $vars['unsubscribe_url'],
    ];
}

// =====================================================================
//  MIME
// =====================================================================

/** Encode a header value that is not plain ASCII (RFC 2047). */
function mime_encode_header(string $text): string
{
    $text = trim(preg_replace('/[\r\n]+/', ' ', $text) ?? '');

    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }

    // Split on character boundaries so a multi-byte letter is never cut in half.
    $out   = [];
    $chunk = '';
    $len   = mb_strlen($text, 'UTF-8');

    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        if (strlen(base64_encode($chunk . $char)) > 45) {
            $out[]  = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
            $chunk = '';
        }
        $chunk .= $char;
    }
    if ($chunk !== '') {
        $out[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
    }

    return implode("\r\n ", $out);
}

/**
 * Strip anything that could start a new line.
 * A newline smuggled into an address turns one header into two, or one SMTP
 * command into two — this is the single place that has to get it right.
 */
function mail_one_line(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

/** "Unika Kebab" <noreply@unikakebab.com> */
function mime_address(string $email, string $name = ''): string
{
    $email = mail_one_line($email);
    $name  = mail_one_line($name);

    if ($name === '') {
        return $email;
    }
    if (preg_match('/^[\x20-\x7E]*$/', $name)) {
        // Commas, dots and quotes have a meaning of their own in a header.
        if (preg_match('/[",.:;<>@()\[\]\\\\]/', $name)) {
            $name = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
        }
        return $name . ' <' . $email . '>';
    }

    return mime_encode_header($name) . ' <' . $email . '>';
}

/**
 * The complete message, headers and all, ready to hand to DATA.
 * Both parts are base64 encoded, which sidesteps every line-length and
 * leading-dot rule SMTP has.
 */
function mime_message(array $msg): string
{
    $cfg      = mail_config();
    $boundary = '=_unika_' . bin2hex(random_bytes(12));
    $domain   = substr(strrchr($cfg['from_email'], '@') ?: '@localhost', 1);

    $headers = [
        'Date'         => date('r'),
        'From'         => mime_address($msg['from_email'], $msg['from_name'] ?? ''),
        'To'           => mime_address($msg['to_email'], $msg['to_name'] ?? ''),
        'Subject'      => mime_encode_header((string)$msg['subject']),
        'Message-ID'   => '<' . bin2hex(random_bytes(10)) . '.' . time() . '@' . $domain . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer'     => 'Unika Kebab Admin',
    ];

    if (!empty($msg['reply_to'])) {
        $headers['Reply-To'] = mime_address($msg['reply_to']);
    }

    // Gmail and Outlook put a real "Unsubscribe" control at the top of the
    // message when these two headers are present. The POST variant is what
    // makes that control safe: a scanner following links cannot trigger it.
    if (!empty($msg['unsubscribe_url'])) {
        $headers['List-Unsubscribe']      = '<' . $msg['unsubscribe_url'] . '>'
            . ($cfg['from_email'] !== '' ? ', <mailto:' . $cfg['from_email'] . '?subject=unsubscribe>' : '');
        $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
    }

    $out = '';
    foreach ($headers as $key => $value) {
        $out .= $key . ': ' . $value . "\r\n";
    }

    $out .= "\r\n"
          . "This is a message in MIME format.\r\n"
          . '--' . $boundary . "\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode((string)$msg['text']), 76, "\r\n")
          . '--' . $boundary . "\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode((string)$msg['html']), 76, "\r\n")
          . '--' . $boundary . "--\r\n";

    return $out;
}

// =====================================================================
//  The SMTP conversation
// =====================================================================

class SmtpMailer
{
    private array $cfg;
    /** @var resource|null */
    private $sock = null;
    private array $caps = [];

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? mail_config();
    }

    public function isOpen(): bool
    {
        return is_resource($this->sock);
    }

    /** Connect, greet, secure the line and sign in. Throws on any refusal. */
    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $cfg    = $this->cfg;
        $secure = $cfg['secure'];
        $scheme = $secure === 'ssl' ? 'ssl://' : 'tcp://';

        $context = stream_context_create(['ssl' => [
            'verify_peer'       => $cfg['verify'],
            'verify_peer_name'  => $cfg['verify'],
            'allow_self_signed' => !$cfg['verify'],
            'SNI_enabled'       => true,
        ]]);

        $errNo  = 0;
        $errStr = '';
        $sock   = @stream_socket_client(
            $scheme . $cfg['host'] . ':' . $cfg['port'],
            $errNo,
            $errStr,
            $cfg['timeout'],
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($sock === false) {
            throw new RuntimeException(sprintf(
                'Could not reach %s on port %d — %s',
                $cfg['host'],
                $cfg['port'],
                $errStr !== '' ? $errStr : 'no answer'
            ));
        }

        $this->sock = $sock;
        stream_set_timeout($this->sock, $cfg['timeout']);

        [$code, $reply] = $this->read();
        if ($code !== 220) {
            $this->close();
            throw new RuntimeException('The mail server refused the connection: ' . $reply);
        }

        $this->hello();

        if ($secure === 'tls') {
            $this->talk('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->sock, true, $this->cryptoMethod())) {
                $this->close();
                throw new RuntimeException('The server accepted STARTTLS but the secure handshake failed. '
                    . 'Check the port, or switch the certificate check off if this host uses a shared certificate.');
            }
            $this->hello();          // capabilities are re-read once the line is encrypted
        }

        if ($cfg['user'] !== '') {
            $this->authenticate();
        }
    }

    public function close(): void
    {
        if (!$this->isOpen()) {
            return;
        }

        try {
            $this->write('QUIT');
            $this->read();
        } catch (Throwable $e) {
            // The connection is going away regardless.
        }

        @fclose($this->sock);
        $this->sock = null;
        $this->caps = [];
    }

    /**
     * Hand one message over. A refusal aimed at a single address (a dead
     * mailbox, say) resets the session instead of dropping it, so the rest
     * of the batch carries on down the same connection.
     */
    public function send(string $fromEmail, string $toEmail, string $raw): void
    {
        if (!$this->isOpen()) {
            $this->open();
        }

        try {
            $this->talk('MAIL FROM:<' . mail_one_line($fromEmail) . '>', [250]);
            $this->talk('RCPT TO:<' . mail_one_line($toEmail) . '>', [250, 251]);
            $this->talk('DATA', [354]);

            // Dot-stuffing: a line that is just "." would end the message early.
            $body = preg_replace('/^\./m', '..', $raw);
            $this->write($body . "\r\n.");

            [$code, $reply] = $this->read();
            if ($code !== 250) {
                throw new RuntimeException('The server would not accept the message: ' . $reply);
            }
        } catch (RuntimeException $e) {
            try {
                if ($this->isOpen()) {
                    $this->talk('RSET', [250, 220]);
                }
            } catch (Throwable $ignored) {
                $this->close();      // the line itself is gone; the next send reconnects
            }
            throw $e;
        }
    }

    // -----------------------------------------------------------------

    private function hello(): void
    {
        $domain = substr(strrchr($this->cfg['from_email'], '@') ?: '@localhost', 1);
        $domain = $domain !== '' ? $domain : 'localhost';

        try {
            $reply = $this->talk('EHLO ' . $domain, [250]);
        } catch (RuntimeException $e) {
            $reply = $this->talk('HELO ' . $domain, [250]);   // a very old server
        }

        $this->caps = [];
        foreach (preg_split('/\r?\n/', $reply) ?: [] as $line) {
            $line = trim(substr($line, 4));
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/[ \t]+/', $line) ?: [];
            $name  = strtoupper(array_shift($parts) ?? '');
            $this->caps[$name] = array_map('strtoupper', $parts);
        }
    }

    private function authenticate(): void
    {
        $user  = $this->cfg['user'];
        $pass  = $this->cfg['pass'];
        $offer = $this->caps['AUTH'] ?? [];

        // No AUTH line in the greeting usually means "not on this port".
        $useLogin = $offer === [] || in_array('LOGIN', $offer, true);

        try {
            if ($useLogin) {
                $this->talk('AUTH LOGIN', [334]);
                $this->talk(base64_encode($user), [334], 'username');
                $this->talk(base64_encode($pass), [235], 'password');
            } else {
                $this->talk('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235], 'AUTH PLAIN');
            }
        } catch (RuntimeException $e) {
            $this->close();
            throw new RuntimeException('The mailbox rejected the username or password — ' . $e->getMessage());
        }
    }

    /** Every TLS version PHP was built with, newest first. */
    private function cryptoMethod(): int
    {
        $method = 0;
        foreach (['STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT', 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT'] as $name) {
            if (defined($name)) {
                $method |= (int)constant($name);
            }
        }
        return $method !== 0 ? $method : STREAM_CRYPTO_METHOD_TLS_CLIENT;
    }

    private function write(string $line): void
    {
        if (!$this->isOpen()) {
            throw new RuntimeException('The connection to the mail server is closed.');
        }
        if (@fwrite($this->sock, $line . "\r\n") === false) {
            throw new RuntimeException('The connection to the mail server was lost while sending.');
        }
    }

    /** @return array{0:int,1:string} */
    private function read(): array
    {
        $lines = [];

        while (true) {
            $line = @fgets($this->sock, 2048);

            if ($line === false) {
                $meta = is_resource($this->sock) ? stream_get_meta_data($this->sock) : ['timed_out' => false];
                throw new RuntimeException(!empty($meta['timed_out'])
                    ? 'The mail server stopped responding.'
                    : 'The connection to the mail server was lost.');
            }

            $lines[] = rtrim($line, "\r\n");

            // "250-CAPABILITY" continues, "250 CAPABILITY" is the last line.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $last = end($lines) ?: '';
        return [(int)substr($last, 0, 3), implode("\n", $lines)];
    }

    /**
     * Send one command and insist on one of the expected reply codes.
     * $label stands in for the command in error messages, so a base64
     * password never reaches a flash message or the log.
     */
    private function talk(string $command, array $expect, string $label = ''): string
    {
        $this->write($command);
        [$code, $reply] = $this->read();

        if (!in_array($code, $expect, true)) {
            throw new RuntimeException(($label !== '' ? $label : $command) . ' → ' . trim($reply));
        }

        return $reply;
    }
}

// =====================================================================
//  Sending
// =====================================================================

/** Record what happened, so the admin can see it without opening a mailbox. */
function mail_log(string $kind, string $email, string $subject, string $status, string $detail = '', ?int $campaignId = null): void
{
    db_run(
        'INSERT INTO email_log (kind, campaign_id, email, subject, status, detail)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            mb_substr($kind, 0, 20),
            $campaignId,
            mb_substr($email, 0, 150),
            mb_substr($subject, 0, 200),
            $status === 'sent' ? 'sent' : 'failed',
            $detail === '' ? null : mb_substr($detail, 0, 255),
        ]
    );
}

/**
 * Send one composed email.
 *
 * $opts:
 *   mailer       an already-open SmtpMailer, to reuse one connection for a batch
 *   kind         welcome | campaign | test — what goes in the log
 *   campaign_id  ties the log line to a campaign
 *   log          false to skip the log entry
 *   config       overrides on top of mail_config(), e.g. a shorter timeout
 *
 * Returns ['ok' => bool, 'detail' => string].
 */
function mail_send(string $toEmail, string $toName, array $composed, array $opts = []): array
{
    $kind       = (string)($opts['kind'] ?? 'campaign');
    $campaignId = isset($opts['campaign_id']) ? (int)$opts['campaign_id'] : null;
    $shouldLog  = ($opts['log'] ?? true) !== false;
    $subject    = (string)$composed['subject'];

    $finish = function (bool $ok, string $detail) use ($shouldLog, $kind, $toEmail, $subject, $campaignId): array {
        if ($shouldLog) {
            mail_log($kind, $toEmail, $subject, $ok ? 'sent' : 'failed', $detail, $campaignId);
        }
        return ['ok' => $ok, 'detail' => $detail];
    };

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return $finish(false, 'That is not a valid email address.');
    }
    if (!mail_is_enabled()) {
        $problems = mail_problems();
        return $finish(false, $problems ? $problems[0] : 'Email sending is not set up yet.');
    }

    $cfg = array_merge(mail_config(), (array)($opts['config'] ?? []));
    $raw = mime_message([
        'from_email'      => $cfg['from_email'],
        'from_name'       => $cfg['from_name'],
        'to_email'        => $toEmail,
        'to_name'         => $toName,
        'reply_to'        => $cfg['reply_to'],
        'subject'         => $subject,
        'html'            => $composed['html'],
        'text'            => $composed['text'],
        'unsubscribe_url' => $composed['unsubscribe_url'] ?? '',
    ]);

    /** @var SmtpMailer|null $shared */
    $shared = $opts['mailer'] ?? null;
    $mailer = $shared instanceof SmtpMailer ? $shared : new SmtpMailer($cfg);

    try {
        $mailer->send($cfg['from_email'], $toEmail, $raw);
        $result = $finish(true, 'Accepted by ' . $cfg['host']);
    } catch (Throwable $e) {
        $result = $finish(false, $e->getMessage());
    }

    if (!$shared instanceof SmtpMailer) {
        $mailer->close();
    }

    return $result;
}

/** Compose and send in one step — the shape most callers want. */
function mail_send_template(array $person, string $subject, string $bodyHtml, string $preheader = '', array $opts = []): array
{
    $composed = email_compose($person, $subject, $bodyHtml, $preheader);

    return mail_send(
        (string)($person['email'] ?? ''),
        (string)($person['name'] ?? ''),
        $composed,
        $opts
    );
}

/**
 * The automatic welcome, sent the moment somebody joins the list.
 * Never fatal: a subscriber is on the list whether or not the mail server
 * happened to be reachable that second.
 */
function send_welcome_email(array $subscriber): array
{
    if (setting('welcome_email_enabled', '1') !== '1') {
        return ['ok' => false, 'detail' => 'The welcome email is switched off in Settings.'];
    }

    $template = welcome_template();
    if (!$template) {
        return ['ok' => false, 'detail' => 'No welcome template has been set up yet.'];
    }

    try {
        return mail_send_template(
            $subscriber,
            (string)$template['subject'],
            (string)$template['body_html'],
            (string)($template['preheader'] ?? ''),
            // Somebody is waiting on a page load for this one, so an
            // unreachable mail server gives up quickly instead of holding
            // the sign-up form open for the full timeout.
            ['kind' => 'welcome', 'config' => ['timeout' => 8]]
        );
    } catch (Throwable $e) {
        mail_log('welcome', (string)($subscriber['email'] ?? ''), (string)$template['subject'], 'failed', $e->getMessage());
        return ['ok' => false, 'detail' => $e->getMessage()];
    }
}
