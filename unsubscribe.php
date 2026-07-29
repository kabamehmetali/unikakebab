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
// The banner follows the state of the page. Only the question needs wording an
// admin might want to soften, so that is the one taken from Settings — a
// banner still saying "confirm below" over a finished unsubscribe reads as a
// page that has not noticed what just happened.
$hero = [
    'eyebrow'  => setting('unsubscribe_hero_eyebrow', 'Mailing list'),
    'title'    => setting('unsubscribe_hero_title', 'Leaving the list'),
    'text'     => setting('unsubscribe_hero_text'),
    'image'    => 'assets/img/hero-home.jpg',
    'crumb'    => 'Unsubscribe',
    'compact'  => true,
    'ornament' => false,
];

if ($showDone) {
    $hero['eyebrow'] = 'Mailing list';
    $hero['title']   = 'Off the list';
    $hero['text']    = 'Nothing more to do — the details are below.';
} elseif (!$linkValid) {
    $hero['eyebrow'] = 'Mailing list';
    $hero['title']   = 'Unsubscribe';
    $hero['text']    = 'We could not read that link, so nothing has been changed.';
}
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<section class="section section--cream">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-lg-8 col-xl-7">

        <?php if ($showDone): ?>
          <!-- ===================== done ===================== -->
          <div class="unsub unsub--done" data-reveal>
            <span class="unsub__mark" aria-hidden="true"><i class="fa-solid fa-check"></i></span>

            <span class="eyebrow">All done</span>
            <h2 class="unsub__title">You are off the list</h2>

            <p class="unsub__lead">
              <?= $wasThere
                  ? 'Deleted from our mailing list — not hidden, not paused. This was the last email you will get from us.'
                  : 'That address was not on our list, so there was nothing left to remove. Either way, you will not hear from us again.' ?>
            </p>

            <span class="unsub__chip">
              <i class="fa-solid fa-envelope" aria-hidden="true"></i>
              <?= e($email) ?>
            </span>

            <div class="hero__actions">
              <a class="btn-u btn-gold" href="<?= e(url('index.php')) ?>#newsletter">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Join again
              </a>
              <a class="btn-u btn-outline-char" href="<?= e(url('menu.php')) ?>">
                <i class="fa-solid fa-utensils" aria-hidden="true"></i> Back to the menu
              </a>
            </div>

            <p class="unsub__note">
              <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
              Unsubscribed by accident? Joining again takes one line on the home page —
              nothing was kept, so you start fresh.
            </p>
          </div>

        <?php elseif ($linkValid): ?>
          <!-- ===================== confirm ===================== -->
          <div class="unsub" data-reveal>
            <span class="unsub__mark" aria-hidden="true"><i class="fa-solid fa-envelope-open-text"></i></span>

            <span class="eyebrow">One last check</span>
            <h2 class="unsub__title">Ready to unsubscribe?</h2>

            <p class="unsub__lead">
              Press the button and this address comes off straight away. Anything still
              going out stops for you immediately.
            </p>

            <span class="unsub__chip">
              <i class="fa-solid fa-envelope" aria-hidden="true"></i>
              <?= e($email) ?>
            </span>

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

            <ul class="unsub__facts">
              <li class="unsub__fact">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <span><strong>Deleted, not paused</strong> The row is removed from our records the moment you press it.</span>
              </li>
              <li class="unsub__fact">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                <span><strong>Never sold or shared</strong> The list has only ever been used to send our own emails.</span>
              </li>
              <li class="unsub__fact">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                <span><strong>Come back any time</strong> The sign-up sits at the bottom of the home page.</span>
              </li>
            </ul>
          </div>

        <?php else: ?>
          <!-- ===================== bad link ===================== -->
          <div class="unsub unsub--invalid" data-reveal>
            <span class="unsub__mark" aria-hidden="true"><i class="fa-solid fa-link-slash"></i></span>

            <span class="eyebrow">Hmm</span>
            <h2 class="unsub__title">That link looks broken</h2>

            <p class="unsub__lead">
              The unsubscribe link is incomplete or was altered on its way here, so we cannot
              tell which address to remove. Nothing has been changed.
            </p>

            <p class="unsub__lead">
              Email programs sometimes break long links across two lines. Open the email again and
              press <strong>Unsubscribe</strong> at the very bottom — or write to us and we will
              take you off by hand the same day.
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
