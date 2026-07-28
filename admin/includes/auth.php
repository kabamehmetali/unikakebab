<?php
/**
 * Admin session handling.
 * Every protected admin page starts with:
 *   require __DIR__ . '/../includes/app.php';
 *   require __DIR__ . '/includes/auth.php';
 *   require_admin();
 */

/** Is somebody signed in? */
function admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** The signed-in admin row, or null. */
function current_admin(): ?array
{
    static $admin = null;
    if ($admin === null && admin_logged_in()) {
        $admin = db_one('SELECT * FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
    }
    return $admin ?: null;
}

/** Bounce guests to the login screen, remembering where they were headed. */
function require_admin(): void
{
    if (!admin_logged_in()) {
        $_SESSION['admin_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . admin_url('login.php'));
        exit;
    }

    // The account may have been deleted while the session was still alive.
    if (!current_admin()) {
        admin_logout();
        header('Location: ' . admin_url('login.php'));
        exit;
    }
}

/** Verify credentials and start an admin session. */
function admin_login(string $username, string $password): bool
{
    $admin = db_one('SELECT * FROM admins WHERE username = ?', [trim($username)]);

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    db_run('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int)$admin['id']]);

    return true;
}

/** Drop the admin session (keeps any flash messages). */
function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_redirect']);
    session_regenerate_id(true);
}

/**
 * Simple brute-force brake: 5 failures locks the form for 5 minutes.
 * Returns the seconds remaining, or 0 when the form is usable.
 */
function login_lock_seconds(): int
{
    $attempts = (int)($_SESSION['login_fails'] ?? 0);
    $lastFail = (int)($_SESSION['login_last_fail'] ?? 0);

    if ($attempts < 5) {
        return 0;
    }
    $remaining = 300 - (time() - $lastFail);
    if ($remaining <= 0) {
        unset($_SESSION['login_fails'], $_SESSION['login_last_fail']);
        return 0;
    }
    return $remaining;
}

function login_fail_record(): void
{
    $_SESSION['login_fails']     = (int)($_SESSION['login_fails'] ?? 0) + 1;
    $_SESSION['login_last_fail'] = time();
}

function login_fail_clear(): void
{
    unset($_SESSION['login_fails'], $_SESSION['login_last_fail']);
}
