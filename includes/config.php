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
// Flip DEBUG_MODE to false before putting the site on a live server.
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

date_default_timezone_set('America/Toronto');

// ---- Session --------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
