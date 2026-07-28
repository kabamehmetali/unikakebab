<?php
/**
 * Admin · Your account
 * Name, username, email and password for the signed-in administrator.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

$admin   = current_admin();
$adminId = (int)$admin['id'];

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');

    // ---------------------------------------------------------------
    //  Your details
    // ---------------------------------------------------------------
    if ($action === 'details') {
        $fullName = mb_substr(trim((string)($_POST['full_name'] ?? '')), 0, 120);
        $username = mb_substr(trim((string)($_POST['username'] ?? '')), 0, 60);
        $email    = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 150);

        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Please enter your name.';
        }
        if ($username === '') {
            $errors[] = 'Please enter a username.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $errors[] = 'The username may only use letters, numbers, dots, dashes and underscores (3–60 characters).';
        } elseif (db_one('SELECT id FROM admins WHERE username = ? AND id <> ?', [$username, $adminId])) {
            $errors[] = 'That username is already taken.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address does not look right.';
        }

        if ($errors) {
            old_set($_POST);
            flash(e(implode(' ', $errors)), 'error');
            redirect('admin/account.php');
        }

        db_run(
            'UPDATE admins SET full_name = ?, username = ?, email = ? WHERE id = ?',
            [$fullName, $username, ($email === '' ? null : $email), $adminId]
        );

        flash('Your details were saved.', 'success');
        redirect('admin/account.php');
    }

    // ---------------------------------------------------------------
    //  Change password
    // ---------------------------------------------------------------
    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $errors = [];

        if (!password_verify($current, (string)$admin['password_hash'])) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'The new password must be at least 8 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'The two new passwords do not match.';
        }
        if ($new !== '' && $current !== '' && $new === $current) {
            $errors[] = 'The new password needs to be different from the current one.';
        }

        if ($errors) {
            flash(e(implode(' ', $errors)), 'error');
            redirect('admin/account.php#password');
        }

        db_run(
            'UPDATE admins SET password_hash = ? WHERE id = ?',
            [password_hash($new, PASSWORD_DEFAULT), $adminId]
        );

        flash('Your password was changed. Use it the next time you sign in.', 'success');
        redirect('admin/account.php');
    }

    redirect('admin/account.php');
}

// =====================================================================
//  Page
// =====================================================================
$stillDefault = password_verify('admin123', (string)$admin['password_hash']);

$title     = 'Your Account';
$subtitle  = 'Sign-in details for the admin panel';
$activeNav = 'account';
require __DIR__ . '/includes/header.php';
?>

<?php if ($stillDefault): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      You are still using the default password (<code>admin123</code>).
      Anyone who knows it can edit your website — <a href="#password">change it now</a>.
    </span>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ========================= details ========================= -->
  <div class="col-12 col-lg-6">
    <div class="a-card">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-user"></i>Your details</h2>
          <p class="a-card__sub">The name shown in the top corner of the panel</p>
        </div>
      </div>

      <form method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="details">

        <div class="a-card__body">
          <div class="a-field">
            <label class="a-field__label" for="full_name">Name <span class="req">*</span></label>
            <input class="a-input" type="text" id="full_name" name="full_name" maxlength="120" required
                   value="<?= e(old('full_name', $admin['full_name'])) ?>">
          </div>

          <div class="a-field">
            <label class="a-field__label" for="username">Username <span class="req">*</span></label>
            <input class="a-input" type="text" id="username" name="username" maxlength="60" required
                   autocomplete="username" value="<?= e(old('username', $admin['username'])) ?>">
            <p class="a-field__hint">This is what you type on the sign-in screen.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="email">Email</label>
            <input class="a-input" type="email" id="email" name="email" maxlength="150"
                   value="<?= e(old('email', $admin['email'] ?? '')) ?>">
            <p class="a-field__hint">Optional — used only as a contact for you, never shown on the website.</p>
          </div>
        </div>

        <div class="a-card__foot">
          <button class="a-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save details</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ========================= password ========================= -->
  <div class="col-12 col-lg-6">
    <div class="a-card" id="password">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-key"></i>Change password</h2>
          <p class="a-card__sub">Pick something only you would guess</p>
        </div>
      </div>

      <form method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">

        <div class="a-card__body">
          <div class="a-field">
            <label class="a-field__label" for="current_password">Current password <span class="req">*</span></label>
            <input class="a-input" type="password" id="current_password" name="current_password" required
                   autocomplete="current-password">
          </div>

          <div class="a-field">
            <label class="a-field__label" for="new_password">New password <span class="req">*</span></label>
            <input class="a-input" type="password" id="new_password" name="new_password" required
                   minlength="8" autocomplete="new-password">
            <p class="a-field__hint">At least 8 characters. A short phrase you will remember beats a scrambled word.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="confirm_password">Repeat new password <span class="req">*</span></label>
            <input class="a-input" type="password" id="confirm_password" name="confirm_password" required
                   minlength="8" autocomplete="new-password">
          </div>
        </div>

        <div class="a-card__foot">
          <button class="a-btn a-btn--dark" type="submit"><i class="fa-solid fa-key"></i> Change password</button>
        </div>
      </form>
    </div>

    <!-- ========================= activity ========================= -->
    <div class="a-card">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-clock-rotate-left"></i>Account activity</h2>
        </div>
      </div>

      <div class="a-card__body">
        <div class="a-field">
          <span class="a-field__label">Last signed in</span>
          <p class="a-mb0">
            <?= !empty($admin['last_login_at'])
                  ? e(date('j F Y \a\t g:i a', strtotime((string)$admin['last_login_at'])))
                  : '<span class="a-muted">This is your first session</span>' ?>
          </p>
        </div>

        <div class="a-field a-mb0">
          <span class="a-field__label">Account created</span>
          <p class="a-mb0"><?= e(date('j F Y', strtotime((string)$admin['created_at']))) ?></p>
        </div>
      </div>

      <div class="a-card__foot">
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('logout.php')) ?>">
          <i class="fa-solid fa-right-from-bracket"></i> Sign out
        </a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
