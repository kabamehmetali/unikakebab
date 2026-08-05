<?php
/**
 * Error responses.
 *
 * Three things have to be true of every one of these, and only the third is
 * about the visitor:
 *
 *   · the status line must be honest. A "sorry, nothing here" page served with
 *     200 is a soft 404, and a site that answers 200 to everything teaches a
 *     crawler that it cannot trust anything the site says about what exists.
 *   · the response must carry whatever header the code requires — Allow on a
 *     405, Retry-After on a 429 or 503 — or the code is only decoration.
 *   · it should look like the rest of the site and offer a way onwards.
 *
 * The rich page needs the database (store details, settings, the navbar). A
 * 500 is very often the database itself, so render_error_page() falls back to
 * a self-contained page that needs nothing at all rather than failing inside
 * its own error handler.
 */

// =====================================================================
//  What each code means
// =====================================================================

/**
 * Copy for every status this site can answer with.
 *
 *   title    the <title> and the hero heading
 *   eyebrow  the small line above it
 *   text     one sentence explaining what happened
 *   retry    default Retry-After in seconds, where the code takes one
 */
function http_error_catalog(): array
{
    return [
        400 => [
            'title'   => 'That request did not make sense',
            'eyebrow' => 'Bad request',
            'text'    => 'Something in the address or the form was malformed, so we could not read it. Try again from the page you came from.',
        ],
        401 => [
            'title'   => 'You need to sign in',
            'eyebrow' => 'Not signed in',
            'text'    => 'This page is only available once you have signed in.',
        ],
        403 => [
            'title'   => 'That page is not yours to open',
            'eyebrow' => 'Forbidden',
            'text'    => 'You do not have permission to view this. If you followed a link from our site, please let us know.',
        ],
        404 => [
            'title'   => 'That page is off the menu',
            'eyebrow' => 'Page not found',
            'text'    => 'The link you followed has moved or never existed. Everything we do serve is a click away.',
        ],
        405 => [
            'title'   => 'That is not how this page works',
            'eyebrow' => 'Method not allowed',
            'text'    => 'This address does not accept the kind of request that was sent to it.',
        ],
        408 => [
            'title'   => 'That request timed out',
            'eyebrow' => 'Timed out',
            'text'    => 'Your browser took too long to finish sending. Check your connection and try again.',
            'retry'   => 30,
        ],
        410 => [
            'title'   => 'That page has been taken down',
            'eyebrow' => 'Gone',
            'text'    => 'This page is gone for good rather than merely moved. Have a look at what is on now instead.',
        ],
        413 => [
            'title'   => 'That file is too large',
            'eyebrow' => 'Upload too big',
            'text'    => 'The file you sent is bigger than we can accept. Try a smaller one.',
        ],
        414 => [
            'title'   => 'That address is too long',
            'eyebrow' => 'URI too long',
            'text'    => 'The web address sent to us is longer than we can handle.',
        ],
        429 => [
            'title'   => 'Slow down a moment',
            'eyebrow' => 'Too many requests',
            'text'    => 'That is more requests than we allow in one go. Wait a minute and try again — nothing is broken.',
            'retry'   => 60,
        ],
        431 => [
            'title'   => 'Your browser sent too much',
            'eyebrow' => 'Headers too large',
            'text'    => 'The request headers were larger than we accept. Clearing this site\'s cookies usually fixes it.',
        ],
        500 => [
            'title'   => 'Something went wrong at our end',
            'eyebrow' => 'Server error',
            'text'    => 'This one is on us, not on you. It has been logged and we are looking at it — please try again in a moment.',
        ],
        501 => [
            'title'   => 'We cannot do that yet',
            'eyebrow' => 'Not implemented',
            'text'    => 'The server does not support what was asked of it.',
        ],
        502 => [
            'title'   => 'We could not reach a service we rely on',
            'eyebrow' => 'Bad gateway',
            'text'    => 'Something we depend on answered badly. Please try again shortly.',
            'retry'   => 60,
        ],
        503 => [
            'title'   => 'Back in a few minutes',
            'eyebrow' => 'Briefly unavailable',
            'text'    => 'The site is down for a short piece of maintenance. Nothing is lost — try again shortly.',
            'retry'   => 300,
        ],
        504 => [
            'title'   => 'That took too long',
            'eyebrow' => 'Gateway timeout',
            'text'    => 'A service we rely on did not answer in time. Please try again shortly.',
            'retry'   => 60,
        ],
    ];
}

/** Copy for one code, falling back to a sane generic 4xx / 5xx page. */
function http_error_meta(int $code): array
{
    $catalog = http_error_catalog();
    if (isset($catalog[$code])) {
        return $catalog[$code] + ['retry' => null];
    }

    return $code >= 500
        ? $catalog[500] + ['retry' => null]
        : $catalog[400] + ['retry' => null];
}

/** Every status this site knows how to render. */
function http_error_codes(): array
{
    return array_keys(http_error_catalog());
}

// =====================================================================
//  Sending one
// =====================================================================

/**
 * Headers a given status is required to carry.
 *
 * A 405 without Allow and a 503 without Retry-After are both technically
 * malformed, and a crawler reading a 503 with no Retry-After will guess — very
 * often by backing off much further than the outage deserves.
 */
function http_error_headers(int $code, array $meta, array $options = []): void
{
    if (headers_sent()) {
        return;
    }

    http_response_code($code);

    // No error page belongs in an index, whatever a stray link says. The header
    // covers the responses that never reach the HTML — a HEAD request, or the
    // fallback page below.
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Cache-Control: no-store, max-age=0', true);

    if ($code === 405) {
        $allow = $options['allow'] ?? ['GET', 'HEAD'];
        header('Allow: ' . implode(', ', $allow), true);
    }

    $retry = $options['retry'] ?? $meta['retry'] ?? null;
    if ($retry !== null && in_array($code, [408, 429, 502, 503, 504], true)) {
        header('Retry-After: ' . (int)$retry, true);
    }

    if ($code === 401 && !empty($options['challenge'])) {
        header('WWW-Authenticate: ' . $options['challenge'], true);
    }
}

/**
 * A complete HTML page that depends on nothing — no database, no settings, no
 * stylesheet. This is what a 500 caused by the database itself has to fall
 * back to, so it deliberately inlines everything it needs.
 */
function http_error_fallback_html(int $code, array $meta): string
{
    $title = htmlspecialchars($meta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text  = htmlspecialchars($meta['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $home  = defined('BASE_URL') ? (BASE_URL . '/') : '/';
    $home  = htmlspecialchars($home, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{$code} · {$title}</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center;
         background: #fff8ec; color: #1a1613; text-align: center;
         font: 400 1rem/1.6 "Outfit", system-ui, -apple-system, "Segoe UI", sans-serif; }
  main { max-width: 34rem; padding: 2.5rem 1.5rem; }
  .code { font-size: 3.5rem; font-weight: 800; letter-spacing: -0.02em; color: #f4b007; margin: 0; }
  h1 { font-size: 1.5rem; margin: 0.5rem 0 1rem; }
  p { color: #6d6259; margin: 0 0 2rem; }
  a { display: inline-block; padding: 0.9rem 1.9rem; border-radius: 999px;
      background: #f4b007; color: #12100e; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>
<main>
  <p class="code">{$code}</p>
  <h1>{$title}</h1>
  <p>{$text}</p>
  <a href="{$home}">Back to the home page</a>
</main>
</body>
</html>
HTML;
}

/**
 * Answer with $code and stop.
 *
 * $options
 *   allow      string[]  the methods a 405 should advertise
 *   retry      int       Retry-After, overriding the catalogue default
 *   challenge  string    WWW-Authenticate value for a 401
 *   log        string    a line for the server log; never shown to the visitor
 */
function render_error_page(int $code, array $options = []): void
{
    static $rendering = false;

    $meta = http_error_meta($code);

    if (!empty($options['log'])) {
        error_log('[' . $code . '] ' . $options['log']);
    }

    // An error raised while drawing an error page must not recurse. The second
    // one gets the page that cannot fail.
    if ($rendering) {
        if (!headers_sent()) {
            http_error_headers($code, $meta, $options);
        }
        echo http_error_fallback_html($code, $meta);
        exit;
    }
    $rendering = true;

    http_error_headers($code, $meta, $options);

    // Anything half-printed before the failure would land above our page.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;                                   // status and headers, no body
    }

    // The full page needs the database. When that is what broke, fall back.
    try {
        ob_start();
        require APP_ROOT . '/includes/error-page.php';
        $html = ob_get_clean();
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        error_log('Error page could not be rendered: ' . $e->getMessage());
        $html = http_error_fallback_html($code, $meta);
    }

    echo $html;
    exit;
}

// =====================================================================
//  Catching the ones nobody meant to raise
// =====================================================================

/**
 * Route uncaught exceptions, fatal errors and promoted warnings to a 500.
 *
 * With DEBUG_MODE on this keeps out of the way entirely, so a developer still
 * sees the real stack trace. With it off, a visitor gets the branded page and
 * the detail goes to the server log where it belongs — a PHP notice printed
 * into a live page leaks file paths and, occasionally, credentials.
 */
function install_error_handlers(): void
{
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        return;
    }

    set_exception_handler(static function (Throwable $e): void {
        render_error_page(500, [
            'log' => get_class($e) . ': ' . $e->getMessage()
                   . ' in ' . $e->getFile() . ':' . $e->getLine(),
        ]);
    });

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;                        // suppressed with @, or filtered
        }
        // Only the fatal family stops the page; a deprecation should not.
        if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            render_error_page(500, ['log' => $message . ' in ' . $file . ':' . $line]);
        }
        error_log('PHP ' . $severity . ': ' . $message . ' in ' . $file . ':' . $line);

        return true;
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        render_error_page(500, [
            'log' => $error['message'] . ' in ' . $error['file'] . ':' . $error['line'],
        ]);
    });
}

// =====================================================================
//  Guards a page can call
// =====================================================================

/**
 * Refuse anything but the methods a page actually handles.
 *
 * Answering a PUT to the contact form with the contact form is how a page ends
 * up indexed under a method it does not support; 405 with an Allow header is
 * the correct answer and costs one line.
 */
function require_methods(array $allowed): void
{
    $allowed = array_map('strtoupper', $allowed);
    if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
        $allowed[] = 'HEAD';                     // HEAD is a GET without a body
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, $allowed, true)) {
        return;
    }

    if ($method === 'OPTIONS' && !headers_sent()) {
        http_response_code(204);
        header('Allow: ' . implode(', ', $allowed));
        exit;
    }

    render_error_page(405, ['allow' => $allowed]);
}

/**
 * Take the site off the air with a 503 while the owner works on it.
 *
 * 503 rather than a holding page served with 200: it is the one status that
 * tells a crawler "come back later, keep what you already have", so a morning
 * of maintenance does not cost the site its listings.
 */
function check_maintenance_mode(): void
{
    // The admin panel and the error pages themselves stay up regardless — the
    // owner has to be able to sign in and turn maintenance back off. The
    // session is read directly because auth.php has not loaded yet at the
    // point in the bootstrap where this runs.
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/admin/') !== false || basename($script) === 'error.php') {
        return;
    }
    if (!empty($_SESSION['admin_id'])) {
        return;
    }

    if (setting('maintenance_mode', '0') !== '1') {
        return;
    }

    render_error_page(503, ['retry' => (int)setting('maintenance_retry_after', '900')]);
}
