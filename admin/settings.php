<?php
/**
 * Admin · Settings
 * Brand, per-page SEO, Google Maps and Twilio SMS.
 *
 * API keys are shown masked (first four and last four characters). Saving the form
 * without touching a key leaves the stored value alone — see secret_value() below.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** Fields that are stored but never shown in full. */
const SECRET_KEYS = ['google_maps_api_key', 'google_recaptcha_secret_key', 'twilio_account_sid', 'twilio_auth_token', 'smtp_pass'];

/** The pages that get their own title / description / keywords. */
const META_PAGES = [
    'home'      => ['Home page', 'fa-solid fa-house'],
    'menu'      => ['Menu page', 'fa-solid fa-burger'],
    'contact'   => ['Contact page', 'fa-solid fa-envelope'],
    'locations' => ['Locations page', 'fa-solid fa-location-dot'],
    'order'     => ['Order online page', 'fa-solid fa-bag-shopping'],
];

/**
 * The banner headings on the inner pages. The home page has its own richer
 * editor under “Home Page”, so it is not repeated here.
 */
const HERO_PAGES = [
    'menu'      => ['Menu page', 'fa-solid fa-burger', 'menu'],
    'contact'   => ['Contact page', 'fa-solid fa-envelope', 'contact'],
    'locations' => ['Locations page', 'fa-solid fa-location-dot', 'locations'],
    'order'     => ['Order online page', 'fa-solid fa-bag-shopping', 'order'],
];

/**
 * Decide what to store for a secret field.
 * An empty box, or the mask itself coming back unchanged, means "keep what we have".
 */
function secret_value(string $key, ?string $submitted): string
{
    $stored    = setting($key, '');
    $submitted = trim((string)$submitted);

    if ($submitted === '' || $submitted === mask_secret($stored)) {
        return $stored;
    }
    return $submitted;
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? 'save');

    // ---------------------------------------------------------------
    //  Send a test SMS
    // ---------------------------------------------------------------
    if ($action === 'test_sms') {
        $to = trim(setting('twilio_to_number'));

        if (setting('sms_enabled', '0') !== '1') {
            flash('Switch “Forward messages by SMS” on and save before sending a test.', 'error');
            redirect('admin/settings.php#twilio');
        }
        if ($to === '' || trim(setting('twilio_from_number')) === '') {
            flash('Fill in both the Twilio from-number and the number that receives alerts, then save.', 'error');
            redirect('admin/settings.php#twilio');
        }

        $result = send_sms($to, 'Unika Kebab: this is a test message from your admin panel. SMS forwarding works.');
        flash(
            'Test SMS ' . e($result['status']) . ' — ' . e($result['detail']),
            $result['status'] === 'sent' ? 'success' : 'error'
        );
        redirect('admin/settings.php#twilio');
    }

    // ---------------------------------------------------------------
    //  Send a test email through the mailbox above
    // ---------------------------------------------------------------
    if ($action === 'test_email') {
        $to = mb_substr(trim((string)($_POST['test_email'] ?? '')), 0, 150);

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('“' . e($to) . '” is not a valid email address.', 'error');
            redirect('admin/settings.php#smtp');
        }
        if ($problems = mail_problems()) {
            flash('Email is not ready yet — ' . e($problems[0]), 'error');
            redirect('admin/settings.php#smtp');
        }

        $result = mail_send_template(
            ['name' => (string)(current_admin()['full_name'] ?? 'there'), 'email' => $to],
            'Test email from your ' . setting('site_name', 'Unika Kebab') . ' admin panel',
            '<h2>Your mailbox works.</h2>'
            . '<p>This message went out through <strong>' . e(setting('smtp_host')) . '</strong> on port '
            . e(setting('smtp_port')) . ', which means welcome emails and campaigns can go out too.</p>'
            . '<p>The footer below is added to every email automatically — that unsubscribe button is '
            . 'what keeps you out of spam folders, so it is not optional and cannot be removed.</p>'
            . '<p><strong>The {{site_name}} admin panel</strong></p>',
            'If you are reading this, outgoing email is working.',
            ['kind' => 'test']
        );

        flash(
            $result['ok']
                ? 'Test email sent to ' . e($to) . '. Check the inbox — and the spam folder, just in case.'
                : 'The test email failed — ' . e($result['detail']),
            $result['ok'] ? 'success' : 'error'
        );
        redirect('admin/settings.php#smtp');
    }

    // ---------------------------------------------------------------
    //  Save everything
    // ---------------------------------------------------------------
    $secureChoice = strtolower(trim((string)($_POST['smtp_secure'] ?? 'ssl')));

    $values = [
        'site_name'          => mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 120),
        'site_tagline'       => mb_substr(trim((string)($_POST['site_tagline'] ?? '')), 0, 120),
        'site_url'           => rtrim(mb_substr(trim((string)($_POST['site_url'] ?? '')), 0, 255), '/'),
        'footer_about'       => trim((string)($_POST['footer_about'] ?? '')),
        'footer_copyright'   => trim((string)($_POST['footer_copyright'] ?? '')),
        'google_recaptcha_site_key' => mb_substr(trim((string)($_POST['google_recaptcha_site_key'] ?? '')), 0, 255),
        'twilio_from_number' => mb_substr(trim((string)($_POST['twilio_from_number'] ?? '')), 0, 40),
        'twilio_to_number'   => mb_substr(trim((string)($_POST['twilio_to_number'] ?? '')), 0, 40),
        'sms_enabled'        => empty($_POST['sms_enabled']) ? '0' : '1',

        // --- outgoing email -----------------------------------------
        'smtp_host'             => mb_substr(trim((string)($_POST['smtp_host'] ?? '')), 0, 150),
        'smtp_port'             => (string)max(0, min(65535, (int)($_POST['smtp_port'] ?? 465))),
        'smtp_secure'           => in_array($secureChoice, ['ssl', 'tls', 'none'], true) ? $secureChoice : 'ssl',
        'smtp_user'             => mb_substr(trim((string)($_POST['smtp_user'] ?? '')), 0, 150),
        'smtp_verify_cert'      => empty($_POST['smtp_verify_cert']) ? '0' : '1',
        'mail_from_email'       => mb_substr(trim((string)($_POST['mail_from_email'] ?? '')), 0, 150),
        'mail_from_name'        => mb_substr(trim((string)($_POST['mail_from_name'] ?? '')), 0, 120),
        'mail_reply_to'         => mb_substr(trim((string)($_POST['mail_reply_to'] ?? '')), 0, 150),
        'email_footer_note'     => mb_substr(trim((string)($_POST['email_footer_note'] ?? '')), 0, 255),
        'email_batch_size'      => (string)max(1, min(200, (int)($_POST['email_batch_size'] ?? 20))),
        'email_enabled'         => empty($_POST['email_enabled']) ? '0' : '1',
        'welcome_email_enabled' => empty($_POST['welcome_email_enabled']) ? '0' : '1',
    ];

    if ($values['site_name'] === '') {
        $values['site_name'] = 'Unika Kebab';
    }

    // Per-page SEO meta.
    foreach (array_keys(META_PAGES) as $page) {
        $values["meta_{$page}_title"]    = mb_substr(trim((string)($_POST["meta_{$page}_title"] ?? '')), 0, 200);
        $values["meta_{$page}_desc"]     = mb_substr(trim((string)($_POST["meta_{$page}_desc"] ?? '')), 0, 400);
        $values["meta_{$page}_keywords"] = mb_substr(trim((string)($_POST["meta_{$page}_keywords"] ?? '')), 0, 300);
    }

    // Banner headings on the inner pages.
    foreach (array_keys(HERO_PAGES) as $page) {
        $values["{$page}_hero_eyebrow"] = mb_substr(trim((string)($_POST["{$page}_hero_eyebrow"] ?? '')), 0, 120);
        $values["{$page}_hero_title"]   = mb_substr(trim((string)($_POST["{$page}_hero_title"] ?? '')), 0, 150);
        $values["{$page}_hero_text"]    = mb_substr(trim((string)($_POST["{$page}_hero_text"] ?? '')), 0, 400);
    }

    // The contact page has an extra intro above its form.
    $values['contact_intro_title'] = mb_substr(trim((string)($_POST['contact_intro_title'] ?? '')), 0, 150);
    $values['contact_intro_text']  = mb_substr(trim((string)($_POST['contact_intro_text'] ?? '')), 0, 500);

    // Secrets: only overwrite when the admin actually typed a new value.
    foreach (SECRET_KEYS as $key) {
        $values[$key] = secret_value($key, $_POST[$key] ?? null);
    }

    // Brand images.
    $uploadErrors = [];
    foreach (['logo' => 'logo', 'footer_logo' => 'footer-logo', 'favicon' => 'favicon', 'og_image' => 'share'] as $field => $prefix) {
        $error    = null;
        $uploaded = save_upload($_FILES[$field] ?? [], 'site', $prefix, $error);

        if ($error !== null) {
            $uploadErrors[] = ucfirst(str_replace('_', ' ', $field)) . ': ' . $error;
            continue;
        }
        if ($uploaded !== null) {
            delete_upload(setting($field, ''));      // drop the file we are replacing
            $values[$field] = $uploaded;
        }
    }

    setting_set_many($values);

    if ($uploadErrors) {
        flash(e(implode(' ', $uploadErrors)), 'error');
    }
    flash('Settings saved.', 'success');
    redirect('admin/settings.php');
}

// =====================================================================
//  Page
// =====================================================================
$smsReady = setting('sms_enabled', '0') === '1'
         && trim(setting('twilio_from_number')) !== ''
         && trim(setting('twilio_to_number')) !== ''
         && trim(setting('twilio_account_sid')) !== ''
         && trim(setting('twilio_auth_token')) !== '';

$mapsKey = trim(setting('google_maps_api_key'));
$recaptchaReady = recaptcha_is_enabled();

$mailProblems = mail_problems();
$mailReady    = $mailProblems === [];
$testDefault  = trim((string)(current_admin()['email'] ?? ''));
$subscribers  = (int)(db_one('SELECT COUNT(*) AS n FROM subscribers WHERE is_active = 1')['n'] ?? 0);

/** Render one masked secret field. */
function secret_field(string $key, string $label, string $hint): void
{
    $stored = setting($key, '');
    $masked = mask_secret($stored);
    ?>
    <div class="a-field">
      <label class="a-field__label" for="<?= e($key) ?>"><?= e($label) ?></label>
      <div class="a-secret">
        <input class="a-input" type="text" id="<?= e($key) ?>" name="<?= e($key) ?>"
               value="<?= e($masked) ?>" data-secret="<?= e($stored) ?>"
               autocomplete="off" spellcheck="false"
               placeholder="Paste a new key to replace the stored one">
        <?php if ($stored !== ''): ?>
          <button class="a-secret__toggle" type="button"><i class="fa-solid fa-eye"></i> Show</button>
        <?php endif; ?>
      </div>
      <p class="a-field__hint"><?= e($hint) ?></p>
      <span class="a-secret__stored">
        <?php if ($stored !== ''): ?>
          <i class="fa-solid fa-lock"></i> Stored: <code><?= e($masked) ?></code>
        <?php else: ?>
          <i class="fa-solid fa-circle-exclamation"></i> Not set yet
        <?php endif; ?>
      </span>
    </div>
    <?php
}

$title     = 'Settings';
$subtitle  = 'Brand, search engine text and the keys that power email, maps and SMS';
$activeNav = 'settings';
require __DIR__ . '/includes/header.php';
?>

<form method="post" enctype="multipart/form-data" data-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <!-- ======================= brand ======================= -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-palette"></i>Brand &amp; identity</h2>
        <p class="a-card__sub">The name and images used across the site and in browser tabs</p>
      </div>
    </div>

    <div class="a-card__body">
      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="site_name">Site name</label>
          <input class="a-input" type="text" id="site_name" name="site_name" maxlength="120"
                 value="<?= e(setting('site_name')) ?>">
          <p class="a-field__hint">Used in the browser tab, the footer and social previews.</p>
        </div>
        <div class="a-field">
          <label class="a-field__label" for="site_tagline">Tagline</label>
          <input class="a-input" type="text" id="site_tagline" name="site_tagline" maxlength="120"
                 value="<?= e(setting('site_tagline')) ?>">
          <p class="a-field__hint">Sits under the name — “Charcoal Grill”.</p>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-6">
          <div class="a-field">
            <span class="a-field__label">Logo</span>
            <div class="a-media">
              <img class="a-media__preview" id="logoPreview" src="<?= e(img(setting('logo'))) ?>" alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="logo" accept="image/*" data-preview="#logoPreview">
                <p class="a-field__hint">Shown in the navbar and home hero. A transparent PNG works best.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="a-field">
            <span class="a-field__label">Footer logo</span>
            <div class="a-media">
              <img class="a-media__preview a-media__preview--dark" id="footerLogoPreview"
                   src="<?= e(img(setting('footer_logo'), setting('logo', 'assets/img/logo.png'))) ?>"
                   alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="footer_logo" accept="image/*" data-preview="#footerLogoPreview">
                <p class="a-field__hint">Shown only on the dark footer. Use a light or white transparent logo for the best contrast.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="a-field">
            <span class="a-field__label">Favicon</span>
            <div class="a-media">
              <img class="a-media__preview" id="favPreview" src="<?= e(img(setting('favicon'))) ?>" alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="favicon" accept="image/*" data-preview="#favPreview">
                <p class="a-field__hint">The small icon in the browser tab. Square, at least 180×180.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="a-field">
            <span class="a-field__label">Share image</span>
            <div class="a-media">
              <img class="a-media__preview" id="ogPreview" src="<?= e(img(setting('og_image'))) ?>" alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="og_image" accept="image/*" data-preview="#ogPreview">
                <p class="a-field__hint">The picture that appears when someone shares a link on WhatsApp or Facebook.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- =================== page headings =================== -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-heading"></i>Page headings</h2>
        <p class="a-card__sub">
          The banner at the top of each page.
          The home page has its own editor under <a href="<?= e(admin_url('home.php')) ?>">Home Page</a>.
        </p>
      </div>
    </div>

    <div class="a-card__body">
      <?php foreach (HERO_PAGES as $page => [$label, $icon, $file]): ?>
        <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend">
            <i class="<?= e($icon) ?>"></i> <?= e($label) ?>
            <a class="a-small ms-auto" href="<?= e(url($file)) ?>" target="_blank" rel="noopener">View</a>
          </legend>

          <div class="a-grid-2">
            <div class="a-field">
              <label class="a-field__label" for="<?= e($page) ?>_hero_eyebrow">Small line above</label>
              <input class="a-input" type="text" id="<?= e($page) ?>_hero_eyebrow" name="<?= e($page) ?>_hero_eyebrow"
                     maxlength="120" value="<?= e(setting("{$page}_hero_eyebrow")) ?>">
            </div>
            <div class="a-field">
              <label class="a-field__label" for="<?= e($page) ?>_hero_title">Heading</label>
              <input class="a-input" type="text" id="<?= e($page) ?>_hero_title" name="<?= e($page) ?>_hero_title"
                     maxlength="150" value="<?= e(setting("{$page}_hero_title")) ?>">
            </div>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="<?= e($page) ?>_hero_text">Intro sentence</label>
            <textarea class="a-textarea" id="<?= e($page) ?>_hero_text" name="<?= e($page) ?>_hero_text"
                      rows="2" maxlength="400"><?= e(setting("{$page}_hero_text")) ?></textarea>
          </div>

          <?php if ($page === 'contact'): ?>
            <div class="a-field">
              <label class="a-field__label" for="contact_intro_title">Heading above the form</label>
              <input class="a-input" type="text" id="contact_intro_title" name="contact_intro_title"
                     maxlength="150" value="<?= e(setting('contact_intro_title')) ?>">
            </div>
            <div class="a-field">
              <label class="a-field__label" for="contact_intro_text">Text above the form</label>
              <textarea class="a-textarea" id="contact_intro_text" name="contact_intro_text"
                        rows="2" maxlength="500"><?= e(setting('contact_intro_text')) ?></textarea>
            </div>
          <?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ======================= SEO ======================= -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-magnifying-glass"></i>Search engine text</h2>
        <p class="a-card__sub">What Google shows for each page. Titles work best under 60 characters, descriptions under 160.</p>
      </div>
    </div>

    <div class="a-card__body">
      <?php foreach (META_PAGES as $page => [$label, $icon]): ?>
        <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="<?= e($icon) ?>"></i> <?= e($label) ?></legend>

          <div class="a-field">
            <label class="a-field__label" for="meta_<?= e($page) ?>_title">Title</label>
            <input class="a-input" type="text" id="meta_<?= e($page) ?>_title" name="meta_<?= e($page) ?>_title"
                   maxlength="200" value="<?= e(setting("meta_{$page}_title")) ?>">
          </div>

          <div class="a-field">
            <label class="a-field__label" for="meta_<?= e($page) ?>_desc">Description</label>
            <textarea class="a-textarea" id="meta_<?= e($page) ?>_desc" name="meta_<?= e($page) ?>_desc"
                      rows="2" maxlength="400"
                      data-counter="#count_<?= e($page) ?>" data-counter-ideal="160"><?= e(setting("meta_{$page}_desc")) ?></textarea>
            <p class="a-field__hint"><span id="count_<?= e($page) ?>"></span></p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="meta_<?= e($page) ?>_keywords">Keywords</label>
            <input class="a-input" type="text" id="meta_<?= e($page) ?>_keywords" name="meta_<?= e($page) ?>_keywords"
                   maxlength="300" value="<?= e(setting("meta_{$page}_keywords")) ?>">
            <p class="a-field__hint">Comma separated. Optional — most search engines ignore this now.</p>
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ======================= email ======================= -->
  <div class="a-card" id="smtp">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-envelope-circle-check"></i>Outgoing email</h2>
        <p class="a-card__sub">
          The mailbox that sends the welcome email and your
          <a href="<?= e(admin_url('campaigns.php')) ?>">campaigns</a> —
          <?= $subscribers ?> active subscriber<?= $subscribers === 1 ? '' : 's' ?> right now
        </p>
      </div>
      <div class="a-card__actions">
        <?php if ($mailReady): ?>
          <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Ready</span>
        <?php else: ?>
          <span class="a-badge a-badge--off"><i class="fa-solid fa-circle-minus"></i> Not ready</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="a-card__body">
      <?php if ($mailProblems): ?>
        <div class="a-alert a-alert--warn">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <span><?= e(implode(' ', $mailProblems)) ?></span>
        </div>
      <?php endif; ?>

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend"><i class="fa-solid fa-server"></i> Mail server</legend>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label" for="smtp_host">Outgoing server (SMTP)</label>
            <input class="a-input" type="text" id="smtp_host" name="smtp_host" maxlength="150"
                   value="<?= e(setting('smtp_host')) ?>" placeholder="mail.unikakebab.com"
                   autocomplete="off" spellcheck="false">
            <p class="a-field__hint">From your hosting control panel — usually <code>mail.yourdomain.com</code>.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="smtp_port">Port</label>
            <input class="a-input a-w-sm" type="number" id="smtp_port" name="smtp_port" min="1" max="65535"
                   value="<?= e(setting('smtp_port', '465')) ?>">
            <p class="a-field__hint">465 goes with SSL, 587 with STARTTLS.</p>
          </div>
        </div>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label" for="smtp_secure">Encryption</label>
            <?php $secure = strtolower(setting('smtp_secure', 'ssl')); ?>
            <select class="a-select" id="smtp_secure" name="smtp_secure">
              <option value="ssl"  <?= $secure === 'ssl'  ? 'selected' : '' ?>>SSL / TLS — port 465</option>
              <option value="tls"  <?= $secure === 'tls'  ? 'selected' : '' ?>>STARTTLS — port 587</option>
              <option value="none" <?= $secure === 'none' ? 'selected' : '' ?>>None — port 25 (not recommended)</option>
            </select>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="smtp_user">Mailbox username</label>
            <input class="a-input" type="text" id="smtp_user" name="smtp_user" maxlength="150"
                   value="<?= e(setting('smtp_user')) ?>" placeholder="noreply@unikakebab.com"
                   autocomplete="off" spellcheck="false">
            <p class="a-field__hint">Almost always the full email address of the mailbox.</p>
          </div>
        </div>

        <?php secret_field(
            'smtp_pass',
            'Mailbox password',
            'Stored masked. Leave it alone when you change other settings and it stays exactly as it is.'
        ); ?>

        <label class="a-switch">
          <input type="checkbox" name="smtp_verify_cert" value="1" <?= setting('smtp_verify_cert', '1') === '1' ? 'checked' : '' ?>>
          <span class="a-switch__track"></span>
          <span>Check the server's security certificate</span>
        </label>
        <p class="a-field__hint">
          Leave this on. Turn it off only if the test below fails with a certificate error — some shared
          hosts serve mail under a different name than the one you connect to.
        </p>
      </fieldset>

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend"><i class="fa-solid fa-at"></i> How the email looks</legend>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label" for="mail_from_name">Sender name</label>
            <input class="a-input" type="text" id="mail_from_name" name="mail_from_name" maxlength="120"
                   value="<?= e(setting('mail_from_name')) ?>" placeholder="Unika Kebab">
            <p class="a-field__hint">The name in the reader's inbox.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="mail_from_email">Sender address</label>
            <input class="a-input" type="email" id="mail_from_email" name="mail_from_email" maxlength="150"
                   value="<?= e(setting('mail_from_email')) ?>" placeholder="noreply@unikakebab.com">
            <p class="a-field__hint">Must belong to the same domain as the mailbox above, or mail gets filtered.</p>
          </div>
        </div>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label" for="mail_reply_to">Replies go to</label>
            <input class="a-input" type="email" id="mail_reply_to" name="mail_reply_to" maxlength="150"
                   value="<?= e(setting('mail_reply_to')) ?>" placeholder="info@unikakebab.ca">
            <p class="a-field__hint">Where a customer's reply lands. Leave empty to use the sender address.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="site_url">Public website address</label>
            <input class="a-input" type="url" id="site_url" name="site_url" maxlength="255"
                   value="<?= e(setting('site_url')) ?>" placeholder="https://unikakebab.com">
            <p class="a-field__hint">
              Used for the logo and every link inside an email. Empty works while you are testing locally,
              but set it before the first real campaign — otherwise the links point at
              <code><?= e(site_base_url() ?: 'nowhere') ?></code>.
            </p>
          </div>
        </div>

        <div class="a-field">
          <label class="a-field__label" for="email_footer_note">Footer line</label>
          <input class="a-input" type="text" id="email_footer_note" name="email_footer_note" maxlength="255"
                 value="<?= e(setting('email_footer_note')) ?>">
          <p class="a-field__hint">
            Sits above the unsubscribe button in every email. Saying why they are hearing from you
            is what stops people reaching for the spam button.
          </p>
        </div>
      </fieldset>

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend"><i class="fa-solid fa-sliders"></i> Sending</legend>

        <label class="a-switch">
          <input type="checkbox" name="email_enabled" value="1" <?= setting('email_enabled', '1') === '1' ? 'checked' : '' ?>>
          <span class="a-switch__track"></span>
          <span>Send email from this website</span>
        </label>
        <p class="a-field__hint">The master switch. Off means nothing goes out, tests included.</p>

        <label class="a-switch">
          <input type="checkbox" name="welcome_email_enabled" value="1" <?= setting('welcome_email_enabled', '1') === '1' ? 'checked' : '' ?>>
          <span class="a-switch__track"></span>
          <span>Welcome every new subscriber automatically</span>
        </label>
        <p class="a-field__hint">
          Sent the moment somebody joins from the website. Edit the wording under
          <a href="<?= e(admin_url('templates.php')) ?>">Templates</a>.
        </p>

        <div class="a-field">
          <label class="a-field__label" for="email_batch_size">Emails per batch</label>
          <input class="a-input a-w-sm" type="number" id="email_batch_size" name="email_batch_size" min="1" max="200"
                 value="<?= e(setting('email_batch_size', '20')) ?>">
          <p class="a-field__hint">
            How many a campaign sends before pausing for breath. 20 suits most shared hosting.
            Lower it if your host complains about the rate.
          </p>
        </div>
      </fieldset>
    </div>
  </div>

  <!-- ===================== reCAPTCHA ===================== -->
  <div class="a-card" id="recaptcha">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-shield-halved"></i>Google reCAPTCHA</h2>
        <p class="a-card__sub">Protects the admin sign-in and the public Contact Us form from automated submissions</p>
      </div>
      <div class="a-card__actions">
        <?php if ($recaptchaReady): ?>
          <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Active</span>
        <?php else: ?>
          <span class="a-badge a-badge--off"><i class="fa-solid fa-circle-minus"></i> Not configured</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="a-card__body">
      <div class="a-alert a-alert--info">
        <i class="fa-solid fa-circle-info"></i>
        <span>Use reCAPTCHA v3 score-based keys, and add this website's domain in the Google reCAPTCHA console.</span>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="google_recaptcha_site_key">Site key</label>
        <input class="a-input" type="text" id="google_recaptcha_site_key" name="google_recaptcha_site_key"
               maxlength="255" value="<?= e(setting('google_recaptcha_site_key')) ?>"
               autocomplete="off" spellcheck="false" placeholder="Paste the public site key">
        <p class="a-field__hint">This public key is sent to visitors’ browsers for invisible reCAPTCHA v3 verification.</p>
      </div>

      <?php secret_field(
          'google_recaptcha_secret_key',
          'Secret key',
          'Kept masked in this panel and used only by the server to verify reCAPTCHA v3 scores.'
      ); ?>
    </div>
  </div>

  <!-- ======================= maps ======================= -->
  <div class="a-card" id="maps">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-map-location-dot"></i>Google Maps</h2>
        <p class="a-card__sub">Powers the map on the Contact and Locations pages</p>
      </div>
      <div class="a-card__actions">
        <?php if ($mapsKey !== ''): ?>
          <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Key stored</span>
        <?php else: ?>
          <span class="a-badge a-badge--off"><i class="fa-solid fa-circle-minus"></i> No key</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="a-card__body">
      <?php if ($mapsKey === ''): ?>
        <div class="a-alert a-alert--info">
          <i class="fa-solid fa-circle-info"></i>
          <span>Without a key the map is replaced by a button that opens Google Maps in a new tab — nothing breaks.</span>
        </div>
      <?php endif; ?>

      <?php secret_field(
          'google_maps_api_key',
          'Google Maps API key',
          'The key needs the “Maps Embed API” enabled in your Google Cloud console. Set the position of the pin under Store Info.'
      ); ?>
    </div>
  </div>

  <!-- ======================= twilio ======================= -->
  <div class="a-card" id="twilio">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-comment-sms"></i>Twilio SMS</h2>
        <p class="a-card__sub">Forward contact form messages to your mobile as a text</p>
      </div>
      <div class="a-card__actions">
        <?php if ($smsReady): ?>
          <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Ready</span>
        <?php else: ?>
          <span class="a-badge a-badge--off"><i class="fa-solid fa-circle-minus"></i> Not active</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="a-card__body">
      <div class="a-alert a-alert--info">
        <i class="fa-solid fa-circle-info"></i>
        <span>Messages are always saved to your inbox. SMS forwarding is an extra — if a text fails, the message is still safe.</span>
      </div>

      <div class="a-grid-2">
        <?php secret_field('twilio_account_sid', 'Account SID', 'Starts with AC — find it on your Twilio console dashboard.'); ?>
        <?php secret_field('twilio_auth_token', 'Auth token', 'The primary auth token that sits beside the SID in Twilio.'); ?>
      </div>

      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="twilio_from_number">Send from</label>
          <input class="a-input" type="text" id="twilio_from_number" name="twilio_from_number" maxlength="40"
                 value="<?= e(setting('twilio_from_number')) ?>" placeholder="+14165550100">
          <p class="a-field__hint">Your Twilio number, in full international format.</p>
        </div>

        <div class="a-field">
          <label class="a-field__label" for="twilio_to_number">Send alerts to</label>
          <input class="a-input" type="text" id="twilio_to_number" name="twilio_to_number" maxlength="40"
                 value="<?= e(setting('twilio_to_number')) ?>" placeholder="+14165550184">
          <p class="a-field__hint">The mobile that should receive a text for every new message.</p>
        </div>
      </div>

      <label class="a-switch">
        <input type="checkbox" name="sms_enabled" value="1" <?= setting('sms_enabled', '0') === '1' ? 'checked' : '' ?>>
        <span class="a-switch__track"></span>
        <span>Forward messages by SMS</span>
      </label>
    </div>
  </div>

  <!-- ======================= footer ======================= -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-shoe-prints"></i>Footer</h2>
        <p class="a-card__sub">The text at the bottom of every page</p>
      </div>
    </div>

    <div class="a-card__body">
      <div class="a-field">
        <label class="a-field__label" for="footer_about">About text</label>
        <textarea class="a-textarea" id="footer_about" name="footer_about" rows="3"><?= e(setting('footer_about')) ?></textarea>
        <p class="a-field__hint">Two short sentences beside the logo in the footer.</p>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="footer_copyright">Copyright line</label>
        <input class="a-input" type="text" id="footer_copyright" name="footer_copyright" maxlength="200"
               value="<?= e(setting('footer_copyright')) ?>">
        <p class="a-field__hint">The year is added automatically in front of this.</p>
      </div>
    </div>

    <div class="a-sticky-save">
      <button class="a-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save settings</button>
      <a class="a-btn a-btn--ghost" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-eye"></i> View website
      </a>
      <span class="a-muted a-small">API keys you have not retyped stay exactly as they are.</span>
    </div>
  </div>
</form>

<!-- the two test buttons live in their own forms so they never save the page by accident -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-envelope-open-text"></i>Test your email setup</h2>
      <p class="a-card__sub">One real email through the mailbox above — the fastest way to prove it works</p>
    </div>
  </div>

  <div class="a-card__body">
    <?php if (!$mailReady): ?>
      <div class="a-alert a-alert--warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Fill in the server, port and mailbox above and save — then this button works.</span>
      </div>
    <?php endif; ?>

    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test_email">

      <div class="a-flex">
        <label class="visually-hidden" for="settingsTestEmail">Send the test to</label>
        <input class="a-input a-w-md" type="email" id="settingsTestEmail" name="test_email" maxlength="150" required
               value="<?= e($testDefault) ?>" placeholder="you@email.com">
        <button class="a-btn a-btn--dark" type="submit" <?= $mailReady ? '' : 'disabled' ?>>
          <i class="fa-solid fa-paper-plane"></i> Send test email
        </button>
      </div>
      <p class="a-field__hint">
        Save the page first if you have just changed anything above. Whatever the mail server replies —
        including a refused password — is shown at the top of this page word for word.
      </p>
    </form>
  </div>
</div>

<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-paper-plane"></i>Test your SMS setup</h2>
      <p class="a-card__sub">Sends one text to <?= e(setting('twilio_to_number') ?: 'the number above') ?></p>
    </div>
  </div>

  <div class="a-card__body">
    <?php if (!$smsReady): ?>
      <div class="a-alert a-alert--warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Fill in the SID, token and both numbers, switch forwarding on, and save — then the test button will work.</span>
      </div>
    <?php endif; ?>

    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test_sms">
      <button class="a-btn a-btn--dark" type="submit" <?= $smsReady ? '' : 'disabled' ?>>
        <i class="fa-solid fa-comment-sms"></i> Send test message
      </button>
      <p class="a-field__hint">Twilio's exact reply is shown at the top of the page, so credential problems are easy to spot.</p>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
