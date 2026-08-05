<?php
/**
 * Bootstrap — every page starts with:  require __DIR__ . '/includes/app.php';
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/brands.php';
require_once __DIR__ . '/mailer.php';

// From here on an uncaught exception or a fatal error is a branded 500 with
// the detail in the log, rather than a stack trace printed at the visitor.
// With DEBUG_MODE on this does nothing, so development is unaffected.
install_error_handlers();

// www or not, http or https: one address, and a 301 from the rest. The
// canonical tag only suggests; this decides. No-op until a public address is
// set under Settings, so local development is unaffected.
canonical_host_redirect();
canonical_path_redirect();

// A site the owner has taken down answers 503, not a holding page served
// with 200 — that is the difference between "come back later" and "this site
// is now one page of apology" as far as a crawler is concerned.
check_maintenance_mode();

// Pull "old" form input out of the session once per request so a refresh
// never shows stale values.
$GLOBALS['__old'] = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

// Resolve the remembered newsletter sign-up now, while nothing has been
// printed yet: a subscriber who has since left needs their cookie cleared,
// and setcookie() cannot run once the page has started. No cookie, no work
// — the lookup only happens for somebody who actually signed up here.
newsletter_state();
