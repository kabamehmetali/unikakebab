<?php
/**
 * Bootstrap — every page starts with:  require __DIR__ . '/includes/app.php';
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Pull "old" form input out of the session once per request so a refresh
// never shows stale values.
$GLOBALS['__old'] = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
