<?php
/**
 * Admin sign-in.
 * Standalone page — it deliberately does NOT use admin/includes/header.php,
 * because that file renders the signed-in shell (sidebar, topbar, user chip).
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';

// Already signed in? Straight to the dashboard.
if (admin_logged_in()) {
    redirect('admin/index.php');
}

/**
 * Turn the remembered REQUEST_URI into a safe site-relative path.
 * Anything that is not a plain path inside this site's /admin/ folder is dropped.
 */
function login_safe_redirect(string $wanted): ?string
{
    $wanted = trim($wanted);
    if ($wanted === '') {
        return null;
    }
    // No header injection, no protocol-relative URLs, no absolute URLs.
    if (strpbrk($wanted, "\r\n") !== false) {
        return null;
    }
    if (strncmp($wanted, '//', 2) === 0) {
        return null;
    }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $wanted)) {
        return null;
    }

    $prefix = BASE_URL . '/admin/';
    if (strncmp($wanted, $prefix, strlen($prefix)) !== 0) {
        return null;
    }

    // Strip BASE_URL so redirect() can add it back exactly once.
    $relative = ltrim(substr($wanted, strlen(BASE_URL)), '/');   // "admin/items?id=3"
    if ($relative === '' || strpos($relative, '..') !== false) {
        return null;
    }

    // Never bounce back to the login or logout screens.
    $file = strtolower(basename((string)parse_url($relative, PHP_URL_PATH), '.php'));
    if ($file === 'login' || $file === 'logout') {
        return null;
    }

    return $relative;
}

// ---------------------------------------------------------------------
//  Sign in
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $locked = login_lock_seconds();
    if ($locked > 0) {
        $minutes = max(1, (int)ceil($locked / 60));
        flash(
            'Too many failed attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.',
            'error'
        );
        redirect('admin/login.php');
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        old_set(['username' => $username]);
        flash('Please fill in both your username and your password.', 'error');
        redirect('admin/login.php');
    }

    if (admin_login($username, $password)) {
        login_fail_clear();

        $wanted = (string)($_SESSION['admin_redirect'] ?? '');
        unset($_SESSION['admin_redirect']);
        $target = login_safe_redirect($wanted) ?? 'admin/index.php';

        $admin = current_admin();
        $name  = trim((string)($admin['full_name'] ?? ''));
        $first = $name !== '' ? preg_split('/\s+/', $name)[0] : $username;

        flash('Welcome back, ' . e($first) . '. The grill is all yours.');
        redirect($target);
    }

    // Same message whether or not the username exists.
    login_fail_record();
    old_set(['username' => $username]);

    if (login_lock_seconds() > 0) {
        flash('Too many failed attempts. The sign-in form is locked for 5 minutes.', 'error');
    } else {
        flash('Those details do not match an account. Please check and try again.', 'error');
    }
    redirect('admin/login.php');
}

// ---------------------------------------------------------------------
//  Page data
// ---------------------------------------------------------------------
$locked      = login_lock_seconds();
$lockMinutes = $locked > 0 ? max(1, (int)ceil($locked / 60)) : 0;
$messages    = flash_take();
$siteName    = setting('site_name', 'Unika Kebab');
$tagline     = setting('site_tagline', 'Charcoal Grill');

// Only offer the starter credentials while they are genuinely still in place:
// exactly one account, and its password is still "admin123".
$showDefaultHint = false;
$accounts        = db_all('SELECT password_hash FROM admins');
if (count($accounts) === 1 && password_verify('admin123', (string)$accounts[0]['password_hash'])) {
    $showDefaultHint = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e($siteName) ?> Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(img(setting('favicon', 'assets/img/favicon.png'), 'assets/img/favicon.png')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= e(admin_url('assets/css/admin.css')) ?>" rel="stylesheet">
</head>
<body>

<main class="a-login">
  <div class="a-login__card">

    <img src="<?= e(img(setting('logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>"
         alt="<?= e($siteName) ?><?= $tagline !== '' ? ' — ' . e($tagline) : '' ?>">

    <h1><?= e($siteName) ?></h1>
    <p class="sub">Sign in to the admin panel</p>

    <?php foreach ($messages as $msg): ?>
      <?php
        $type = $msg['type'] ?? 'success';
        $icon = $type === 'success' ? 'fa-circle-check' : ($type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
      ?>
      <div class="a-alert a-alert--<?= e($type) ?>">
        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
        <span><?= $msg['message'] /* built in code, already escaped where needed */ ?></span>
        <button class="a-alert__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
      </div>
    <?php endforeach; ?>

    <?php if ($locked > 0): ?>
      <div class="a-alert a-alert--warn">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        <span>Sign-in is paused for <?= (int)$lockMinutes ?> more minute<?= $lockMinutes === 1 ? '' : 's' ?> after too many failed attempts.</span>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('login.php')) ?>" data-guard>
      <?= csrf_field() ?>

      <div class="a-field">
        <label class="a-field__label" for="username">Username</label>
        <input class="a-input"
               id="username"
               name="username"
               type="text"
               value="<?= e(old('username')) ?>"
               autocomplete="username"
               autocapitalize="none"
               spellcheck="false"
               required
               <?= $locked > 0 ? 'disabled' : 'autofocus' ?>>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="password">Password</label>
        <input class="a-input"
               id="password"
               name="password"
               type="password"
               autocomplete="current-password"
               required
               <?= $locked > 0 ? 'disabled' : '' ?>>
      </div>

      <button class="a-btn a-btn--block" type="submit" <?= $locked > 0 ? 'disabled' : '' ?>>
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign in
      </button>
    </form>

    <?php if ($showDefaultHint): ?>
      <p class="a-login__hint">
        <i class="fa-solid fa-key" aria-hidden="true"></i>
        First time here? Sign in with <strong>admin</strong> / <strong>admin123</strong>,
        then change the password under Account.
      </p>
    <?php endif; ?>

    <a class="a-login__back" href="<?= e(url('index.php')) ?>">
      <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to the website
    </a>

  </div>
</main>

<script src="<?= e(admin_url('assets/js/admin.js')) ?>"></script>
</body>
</html>
