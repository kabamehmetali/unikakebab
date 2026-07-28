<?php
/**
 * Newsletter sign-up band (name + email). Posts to subscribe.php.
 * Include it inside a .container on any page.
 */
$nlErrors = $_SESSION['nl_errors'] ?? [];
unset($_SESSION['nl_errors']);
?>
<div class="newsletter" id="newsletter" data-reveal>
  <div class="row align-items-center g-4">

    <div class="col-lg-5">
      <span class="eyebrow eyebrow--start"><?= e(setting('newsletter_eyebrow', 'The Unika list')) ?></span>
      <h2 class="newsletter__title"><?= e(setting('newsletter_title', 'Specials before anyone else')) ?></h2>
      <p class="newsletter__text"><?= e(setting('newsletter_text')) ?></p>
    </div>

    <div class="col-lg-7">
      <?php foreach ($nlErrors as $error): ?>
        <div class="alert-note alert-note--error" role="alert">
          <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
          <span><?= e($error) ?></span>
        </div>
      <?php endforeach; ?>

      <form class="newsletter__form" action="<?= e(url('subscribe.php')) ?>" method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e(basename($_SERVER['SCRIPT_NAME'] ?? 'index.php', '.php')) ?>">

        <div class="hp-field" aria-hidden="true">
          <label for="nl-website">Leave this empty</label>
          <input type="text" id="nl-website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <label class="visually-hidden" for="nl-name">Your name</label>
        <input class="newsletter__input" type="text" id="nl-name" name="name"
               placeholder="Your name" value="<?= e(old('nl_name')) ?>" maxlength="120" required>

        <label class="visually-hidden" for="nl-email">Email address</label>
        <input class="newsletter__input" type="email" id="nl-email" name="email"
               placeholder="you@email.com" value="<?= e(old('nl_email')) ?>" maxlength="150" required>

        <button class="btn-u btn-gold" type="submit">
          <?= e(setting('newsletter_btn_text', 'Join the list')) ?>
        </button>
      </form>

      <p class="newsletter__note">
        <i class="fa-solid fa-lock" aria-hidden="true"></i>
        We keep your address to ourselves. Unsubscribe with one click, any time.
      </p>
    </div>
  </div>
</div>
