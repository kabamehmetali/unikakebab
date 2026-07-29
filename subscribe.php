<?php
/**
 * Newsletter sign-up handler. Always redirects back to the page it came from.
 *
 * Every outcome — joined, already on the list, rejected — is reported through
 * nl_notice(), which the sign-up band prints inside itself. Nothing here ever
 * claims somebody is subscribed unless their row has been read back out of the
 * subscribers table afterwards.
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

// "Register a new email" — drop the remembered sign-up and show the form
// again. This only forgets the browser; the address stays on the list, so
// it is deliberately not an unsubscribe.
if ((string)($_POST['action'] ?? '') === 'forget') {
    newsletter_forget();
    redirect($back);
}

$name  = trim((string)($_POST['name'] ?? ''));
$email = mb_strtolower(trim((string)($_POST['email'] ?? '')));

// Anything left over from an attempt whose answer was never displayed — the
// band only lives on the home page — must not be mistaken for this one.
nl_notices_take();

/** Report a failed attempt inside the band, keeping what was typed. */
$reject = static function (array $messages) use ($name, $email, $back): void {
    foreach ($messages as $message) {
        nl_notice($message, 'error');
    }
    old_set(['nl_name' => $name, 'nl_email' => $email]);
    redirect($back);
};

// Bots fill in every field they can find, including the hidden one. A person
// cannot reach it: it is parked off-screen and readonly, so anything in it was
// put there by a script. A tripped trap is answered like any other rejection —
// nobody is ever told they joined when no row was written.
if (trim((string)($_POST['nl_hp'] ?? '')) !== '') {
    $reject(['We could not process that sign-up. Please try again.']);
}

$errors = [];

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
    $reject($errors);
}

// ---------------------------------------------------------------------
//  Write the row
//
//  A database that is down or out of space must not read as a sign-up, so
//  the write is reported honestly and the visitor keeps what they typed.
// ---------------------------------------------------------------------
$isNew = false;

try {
    $existing = db_one('SELECT id, name, is_active FROM subscribers WHERE email = ?', [$email]);

    if ($existing && (int)$existing['is_active'] === 1) {
        // Already active. Still take the name — it is the most recent thing
        // they have told us, and it is what the next email will greet.
        if (trim((string)$existing['name']) !== $name) {
            db_run('UPDATE subscribers SET name = ? WHERE id = ?', [$name, (int)$existing['id']]);
        }
        nl_notice('You are already on the list — good taste.', 'info');
    } elseif ($existing) {
        db_run('UPDATE subscribers SET is_active = 1, name = ? WHERE id = ?', [$name, (int)$existing['id']]);
        nl_notice('Welcome back! You are subscribed again.', 'success');
        $isNew = true;
    } else {
        db_run(
            'INSERT INTO subscribers (name, email, ip_address) VALUES (?, ?, ?)',
            [$name, $email, client_ip()]
        );
        nl_notice('You are on the list. Watch your inbox for the next special.', 'success');
        $isNew = true;
    }
} catch (PDOException $e) {
    error_log('Newsletter sign-up failed for ' . $email . ': ' . $e->getMessage());

    nl_notices_take();                   // drop anything queued before the failure
    $reject(['Something went wrong at our end, so you are not on the list yet. Please try again.']);
}

// Read the row back. The confirmation panel and the remembered sign-up are
// only ever shown on the strength of what is actually in the table.
try {
    $saved = db_one('SELECT id FROM subscribers WHERE email = ? AND is_active = 1', [$email]);
} catch (PDOException $e) {
    error_log('Newsletter sign-up check failed for ' . $email . ': ' . $e->getMessage());
    $saved = null;
}

if (!$saved) {
    error_log('Newsletter sign-up for ' . $email . ' left no active row.');

    nl_notices_take();
    $reject(['Something went wrong at our end, so you are not on the list yet. Please try again.']);
}

// Remember the sign-up on this browser either way — somebody who was
// already subscribed is just as entitled to the "you are on the list"
// panel as somebody who joined a second ago.
newsletter_remember($email, $name);

// The welcome email goes out straight away, but it is never allowed to
// break the sign-up: whoever just joined is on the list either way, and
// the reason for a failure is waiting in Admin → Campaigns → Recent sends.
if ($isNew) {
    send_welcome_email(['name' => $name, 'email' => $email]);
}

redirect($back);
