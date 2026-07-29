<?php
/**
 * Bootstrap — every page starts with:  require __DIR__ . '/includes/app.php';
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/brands.php';
require_once __DIR__ . '/mailer.php';

// Pull "old" form input out of the session once per request so a refresh
// never shows stale values.
$GLOBALS['__old'] = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

// Resolve the remembered newsletter sign-up now, while nothing has been
// printed yet: a subscriber who has since left needs their cookie cleared,
// and setcookie() cannot run once the page has started. No cookie, no work
// — the lookup only happens for somebody who actually signed up here.
newsletter_state();
