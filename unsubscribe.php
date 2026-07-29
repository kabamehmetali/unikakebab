<?php
/**
 * Leaving the newsletter.
 *
 * The link in every email carries the address and a signature of it, so no
 * session is needed and nobody can guess their way to somebody else's row.
 *
 * Two ways in:
 *   1. A person clicks Unsubscribe, lands here and confirms with one button.
 *      The confirm step exists because inboxes and security scanners follow
 *      links on their own — a plain GET that deleted the row would empty the
 *      list without anybody having asked.
 *   2. Gmail and Outlook show their own Unsubscribe control at the top of
 *      the message and POST "List-Unsubscribe=One-Click" here (RFC 8058).
 *      That is a real instruction from the reader, so it is acted on at once.
 *
 * Either way the row is deleted outright — not flagged — along with any
 * campaign still queued for that address.
 */
require __DIR__ . '/includes/app.php';

$email = trim((string)($_GET['e'] ?? $_POST['e'] ?? ''));
$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));

$linkValid = $email !== ''
          && filter_var($email, FILTER_VALIDATE_EMAIL)
          && unsubscribe_token_check($email, $token);

$removed  = false;   // we just deleted a row
$wasThere = false;   // …and there was one to delete

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // PHP rewrites some characters in incoming field names, so accept both spellings.
    $oneClick = ($_POST['List-Unsubscribe'] ?? $_POST['List_Unsubscribe'] ?? '') === 'One-Click';

    if ($linkValid) {
        $wasThere = unsubscribe_email($email);
        $removed  = true;

        // If this is the browser that signed up, the remembered sign-up is
        // now wrong. Clearing it here means the sign-up form is waiting on
        // the home page rather than a panel claiming they are subscribed.
        $remembered = newsletter_remembered();
        if ($remembered !== null && strcasecmp($remembered['email'], $email) === 0) {
            newsletter_forget();
        }

        if ($oneClick) {
            // The mail client wants a status code, not a web page.
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            exit("Unsubscribed. The address has been deleted.\n");
        }
    } elseif ($oneClick) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        exit("That unsubscribe link is not valid.\n");
    }
}

// A second click on the same link — the row went with the first one. Say so
// rather than asking somebody to confirm leaving a list they already left.
$stillListed = $linkValid && db_one('SELECT id FROM subscribers WHERE email = ?', [$email]) !== null;
$showDone    = $removed || ($linkValid && !$stillListed);

$pageKey = 'unsubscribe';
$noIndex = true;
$hero = [
    'eyebrow'  => setting('unsubscribe_hero_eyebrow', 'Mailing list'),
    'title'    => setting('unsubscribe_hero_title', 'Leaving the list'),
    'text'     => setting('unsubscribe_hero_text'),
    'image'    => 'assets/img/hero-home.jpg',
    'crumb'    => 'Unsubscribe',
    'compact'  => true,
    'ornament' => false,
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<section class="section section--cream">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8 col-xl-7">

        <?php if ($showDone): ?>
          <!-- ===================== done ===================== -->
          <div class="form-card text-center" data-reveal>
            <span class="eyebrow">All done</span>
            <h2 class="section-title">You are off the list</h2>
            <p class="section-text">
              <?php if ($wasThere): ?>
                <strong><?= e($email) ?></strong> has been deleted from our mailing list.
                We keep no copy of it, so this is the last email you will get from us.
              <?php else: ?>
                <strong><?= e($email) ?></strong> was not on our list, so there is nothing left to remove.
                Either way, you will not hear from us again.
              <?php endif; ?>
            </p>

            <div class="alert-note alert-note--success" role="status">
              <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
              <span>Address deleted. Nothing further to do.</span>
            </div>

            <p class="section-text">
              Changed your mind, or unsubscribed by accident? You can join again any time —
              the sign-up sits at the bottom of the home page.
            </p>

            <div class="hero__actions">
              <a class="btn-u btn-gold" href="<?= e(url('index.php')) ?>#newsletter">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Join again
              </a>
              <a class="btn-u btn-outline-char" href="<?= e(url('menu.php')) ?>">
                <i class="fa-solid fa-utensils" aria-hidden="true"></i> Back to the menu
              </a>
            </div>
          </div>

        <?php elseif ($linkValid): ?>
          <!-- ===================== confirm ===================== -->
          <div class="form-card text-center" data-reveal>
            <span class="eyebrow">One last check</span>
            <h2 class="section-title">Unsubscribe <?= e($email) ?>?</h2>
            <p class="section-text">
              Press the button and we delete this address from our records — not hidden,
              not paused, deleted. Any newsletter still going out stops for you immediately.
            </p>

            <form method="post" action="<?= e(url('unsubscribe.php')) ?>" data-guard>
              <input type="hidden" name="e" value="<?= e($email) ?>">
              <input type="hidden" name="t" value="<?= e($token) ?>">

              <div class="hero__actions">
                <button class="btn-u btn-gold" type="submit">
                  <i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i>
                  Yes, unsubscribe me
                </button>
                <a class="btn-u btn-outline-char" href="<?= e(url('index.php')) ?>">
                  <i class="fa-solid fa-xmark" aria-hidden="true"></i> Keep me on the list
                </a>
              </div>
            </form>

            <p class="newsletter__note">
              <i class="fa-solid fa-lock" aria-hidden="true"></i>
              We never sell or share the list. One email a month is all we ever send.
            </p>
          </div>

        <?php else: ?>
          <!-- ===================== bad link ===================== -->
          <div class="form-card text-center" data-reveal>
            <span class="eyebrow">Hmm</span>
            <h2 class="section-title">That link does not look right</h2>

            <div class="alert-note alert-note--error" role="alert">
              <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
              <span>The unsubscribe link is incomplete or has been altered on its way here.</span>
            </div>

            <p class="section-text">
              Email programs sometimes break long links across two lines. Open the email again and
              click <strong>Unsubscribe</strong> at the very bottom, or reply to it and ask us to
              take you off — we will do it by hand the same day.
            </p>

            <div class="hero__actions">
              <a class="btn-u btn-gold" href="<?= e(url('contact.php')) ?>">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i> Message us
              </a>
              <a class="btn-u btn-outline-char" href="<?= e(url('index.php')) ?>">
                <i class="fa-solid fa-house" aria-hidden="true"></i> Home page
              </a>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
