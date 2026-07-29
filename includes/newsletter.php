<?php
/**
 * Newsletter sign-up band (name + email). Posts to subscribe.php.
 * Include it inside a .container on any page.
 *
 * Somebody who has already signed up on this browser gets a confirmation
 * panel instead of the form — see newsletter_state() in includes/functions.php
 * — with a button that forgets them so another address can be entered.
 *
 * The result of a sign-up is printed here, inside the band, rather than in the
 * page's flash area: the visitor is looking at this section — that is where the
 * redirect drops them — so an answer at the top of the page would be missed.
 */
$nlNotices = nl_notices_take();
$nlFailed  = false;
foreach ($nlNotices as $notice) {
    if ($notice['type'] === 'error') {
        $nlFailed = true;
    }
}

$nlState  = newsletter_state();
$nlReturn = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php', '.php');

// Somebody halfway through fixing a rejected address must not be bounced
// back to the confirmation panel before they have finished.
if ($nlFailed) {
    $nlState = null;
}

$nlIcons = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'];
?>
<div class="newsletter" id="newsletter" data-reveal>
  <div class="row align-items-center g-4">

    <div class="col-lg-5">
      <span class="eyebrow eyebrow--start"><?= e(setting('newsletter_eyebrow', 'The Unika list')) ?></span>
      <h2 class="newsletter__title">
        <?= $nlState
            ? 'You&rsquo;re on the list'
            : e(setting('newsletter_title', 'Specials before anyone else')) ?>
      </h2>
      <p class="newsletter__text">
        <?= $nlState
            ? 'Nothing else to do — the next batch of specials lands in your inbox.'
            : e(setting('newsletter_text')) ?>
      </p>
    </div>

    <div class="col-lg-7">

      <?php foreach ($nlNotices as $notice): ?>
        <?php $nlType = isset($nlIcons[$notice['type']]) ? $notice['type'] : 'info'; ?>
        <div class="alert-note alert-note--<?= e($nlType) ?>" role="<?= $nlType === 'error' ? 'alert' : 'status' ?>">
          <i class="fa-solid <?= e($nlIcons[$nlType]) ?>" aria-hidden="true"></i>
          <span><?= e($notice['message']) ?></span>
        </div>
      <?php endforeach; ?>

      <?php if ($nlState): ?>

        <!-- ============ already signed up on this browser ============ -->
        <div class="nl-done" role="status">
          <span class="nl-done__check" aria-hidden="true">
            <i class="fa-solid fa-check"></i>
          </span>

          <div class="nl-done__body">
            <p class="nl-done__title">
              <?= $nlState['name'] !== ''
                  ? 'Registered successfully, ' . e($nlState['name'])
                  : 'Registered successfully' ?>
            </p>
            <p class="nl-done__email">
              <i class="fa-solid fa-envelope" aria-hidden="true"></i>
              <?= e($nlState['email']) ?>
            </p>
          </div>

          <form class="nl-done__reset" action="<?= e(url('subscribe.php')) ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="forget">
            <input type="hidden" name="return_to" value="<?= e($nlReturn) ?>">
            <button class="btn-u btn-outline-gold btn-u--sm" type="submit">
              <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
              Register a new email
            </button>
          </form>
        </div>

        <p class="newsletter__note">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          Registering another address leaves this one on the list. To come off it, use the
          unsubscribe link at the bottom of any email we send.
        </p>

      <?php else: ?>

        <!-- ===================== sign-up form ===================== -->
        <form class="newsletter__form" action="<?= e(url('subscribe.php')) ?>" method="post" data-guard>
          <?= csrf_field() ?>
          <input type="hidden" name="return_to" value="<?= e($nlReturn) ?>">

          <?php /* Bot trap. It is readonly and off-screen so no browser, password
                   manager or autofill can put anything in it — only a script can,
                   which is the whole point. The inline styles keep it hidden even
                   if the stylesheet has not arrived yet. */ ?>
          <div class="hp-field" aria-hidden="true"
               style="position:absolute;left:-5000px;width:1px;height:0;overflow:hidden">
            <label for="nl-hp">Leave this field empty</label>
            <input type="text" id="nl-hp" name="nl_hp" value=""
                   tabindex="-1" autocomplete="off" readonly>
          </div>

          <label class="visually-hidden" for="nl-name">Your name</label>
          <input class="newsletter__input" type="text" id="nl-name" name="name"
                 placeholder="Your name" value="<?= e(old('nl_name')) ?>"
                 maxlength="120" autocomplete="given-name" required>

          <label class="visually-hidden" for="nl-email">Email address</label>
          <input class="newsletter__input" type="email" id="nl-email" name="email"
                 placeholder="you@email.com" value="<?= e(old('nl_email')) ?>"
                 maxlength="150" autocomplete="email" required>

          <button class="btn-u btn-gold" type="submit">
            <?= e(setting('newsletter_btn_text', 'Join the list')) ?>
          </button>
        </form>

        <p class="newsletter__note">
          <i class="fa-solid fa-lock" aria-hidden="true"></i>
          We keep your address to ourselves. Unsubscribe with one click, any time.
        </p>

      <?php endif; ?>
    </div>
  </div>
</div>
