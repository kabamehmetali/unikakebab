<?php
/**
 * The single entry point for every error response.
 *
 * Reached three ways:
 *   · the catch-all rewrite in .htaccess, for anything that matched no page
 *   · the ErrorDocument lines in .htaccess, for the codes Apache raises itself
 *     (it sets REDIRECT_STATUS to the original code before handing over)
 *   · directly from PHP, via render_error_page()
 *
 * The status is never taken from a query string a visitor controls without
 * being checked against the list of codes this site actually answers with —
 * otherwise /error?code=200 would turn every 404 into a soft one, which is the
 * exact failure this file exists to prevent.
 */
// An error response is an answer, not a signpost. Without this the URL
// canonicalisers in the bootstrap would turn a 403 on /README.md into a 301
// to /readme.md and only then refuse it — two round trips to say "no", and a
// redirect chain in front of every error a crawler meets.
define('APP_NO_REDIRECT', true);

require __DIR__ . '/includes/app.php';

/** The first of the candidates that is a status we know how to render. */
$resolve = static function (array $candidates): ?int {
    $known = http_error_codes();
    foreach ($candidates as $candidate) {
        $code = (int)$candidate;
        if (in_array($code, $known, true)) {
            return $code;
        }
    }
    return null;
};

$code = $resolve([
    $_SERVER['REDIRECT_STATUS'] ?? null,   // set by Apache's ErrorDocument
    $_GET['code']               ?? null,   // set by our own rewrites
]) ?? 404;

$options = [];

// Apache records what was actually asked for before the internal redirect, so
// a 404 in the log names the missing page rather than naming this file.
$original = (string)($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '');
if ($original !== '' && $code >= 400) {
    $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
    $options['log'] = $original . ($referer !== '' ? ' (from ' . $referer . ')' : '');
}

render_error_page($code, $options);
