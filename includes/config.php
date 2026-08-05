<?php
/**
 * Unika Kebab — application configuration.
 * Everything site-wide that is not editable from the admin panel lives here.
 */

// ---- Database -------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'unikakebab_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Paths ----------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));            // .../htdocs/unikakebab
define('UPLOAD_DIR', APP_ROOT . '/uploads');     // physical upload folder
define('UPLOAD_URL', 'uploads');                 // path stored in the database

// ---- Base URL -------------------------------------------------------
// Works when the site sits in a sub-folder (/unikakebab) and at a domain root.
if (!defined('BASE_URL')) {
    $base = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($docRoot && $appRoot && strpos(str_replace('\\', '/', $appRoot), str_replace('\\', '/', $docRoot)) === 0) {
        $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    } elseif (!empty($_SERVER['SCRIPT_NAME'])) {
        // Fallback: derive from the running script, stripping a trailing /admin.
        $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $base = preg_replace('#/admin$#', '', $dir);
    }

    $base = rtrim($base, '/');
    if ($base === '/' || $base === '.') {
        $base = '';
    }
    define('BASE_URL', $base);
}

// ---- Error reporting ------------------------------------------------
// Debug mode follows the host rather than a flag somebody has to remember to
// flip: a stack trace on a live page hands out file paths, table names and
// occasionally credentials, and "flip this before deploying" is exactly the
// line that gets missed. Set DEBUG_MODE yourself before this file loads if you
// need to force it either way.
if (!defined('DEBUG_MODE')) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = (string)strtok($host, ':');                    // drop any :8080

    define('DEBUG_MODE', $host === ''                       // command line
        || $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || substr($host, -6) === '.local'
        || substr($host, -5) === '.test');
}

// Errors are always collected — includes/errors.php turns them into a 500 and
// a log line. Only whether they are printed at the visitor changes.
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set('America/Toronto');

// ---- Session --------------------------------------------------------
// robots.txt, sitemap.xml and the manifest declare APP_STATELESS before they
// bootstrap. They have no forms, no flash messages and no visitor to remember,
// and a session would hand every crawler a Set-Cookie header plus PHP's
// automatic "Cache-Control: no-store" — telling Google not to keep the two
// files it is meant to keep longest.
if (!defined('APP_STATELESS') && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
