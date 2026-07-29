<?php
/**
 * Newsletter sign-up handler. Always redirects back to the page it came from.
 */
require __DIR__ . '/includes/app.php';

// The page the form sat on, as a bare page name such as "menu". Anything that
// is not a real page here falls back to the home page.
$returnTo = basename((string)($_POST['return_to'] ?? ''), '.php');
if (!preg_match('/^[a-z0-9_-]+$/i', $returnTo) || !file_exists(APP_ROOT . '/' . $returnTo . '.php')) {
    $returnTo = 'index';
}
$back = $returnTo . '.php#newsletter';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}

csrf_require();

$name   = trim((string)($_POST['name'] ?? ''));
$email  = trim((string)($_POST['email'] ?? ''));
$errors = [];

// Bots fill in every field they can find, including the hidden one.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    flash('Thanks for subscribing!', 'success');
    redirect($back);
}

if ($name === '') {
    $errors[] = 'Please tell us your name.';
} elseif (mb_strlen($name) > 120) {
    $errors[] = 'That name is a little too long.';
}

if ($email === '') {
    $errors[] = 'Please enter your email address.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors[] = 'That email address does not look right.';
}

if ($errors) {
    $_SESSION['nl_errors'] = $errors;
    old_set(['nl_name' => $name, 'nl_email' => $email]);
    redirect($back);
}

$existing = db_one('SELECT id, is_active FROM subscribers WHERE email = ?', [$email]);
$isNew    = false;

if ($existing) {
    if ((int)$existing['is_active'] === 1) {
        flash('You are already on the list — good taste.', 'info');
    } else {
        db_run('UPDATE subscribers SET is_active = 1, name = ? WHERE id = ?', [$name, (int)$existing['id']]);
        flash('Welcome back! You are subscribed again.', 'success');
        $isNew = true;
    }
} else {
    db_run(
        'INSERT INTO subscribers (name, email, ip_address) VALUES (?, ?, ?)',
        [$name, $email, client_ip()]
    );
    flash('You are on the list. Watch your inbox for the next special.', 'success');
    $isNew = true;
}

// The welcome email goes out straight away, but it is never allowed to
// break the sign-up: whoever just joined is on the list either way, and
// the reason for a failure is waiting in Admin → Campaigns → Recent sends.
if ($isNew) {
    send_welcome_email(['name' => $name, 'email' => $email]);
}

redirect($back);
