<?php
/**
 * The branded error page body. Included by render_error_page() with $code and
 * $meta already in scope, inside an output buffer and a try/catch — so if the
 * database is what failed, the caller quietly swaps in the standalone page
 * instead of failing twice.
 */

/** @var int   $code */
/** @var array $meta */

$storeRow = store();
$phone    = trim((string)($storeRow['phone'] ?? ''));

$pageKey = 'home';
$noIndex = true;                       // never index an error, whatever links here

// page_meta() reads the home page's title from the settings table, which is
// not what a visitor on a 404 should see in their tab or their history.
$titleOverride = $code . ' · ' . $meta['title'];

$hero = [
    'eyebrow' => $meta['eyebrow'],
    'title'   => $meta['title'],
    'text'    => $meta['text'],
    'image'   => 'assets/img/bg-stone.jpg',
    'compact' => true,
    'ornament' => false,
];

require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/hero.php';
?>

<section class="section section--cream">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <p class="u-error__code" aria-hidden="true"><?= (int)$code ?></p>

        <?php if ($code < 500): ?>
          <h2 class="u-error__lead">Try one of these instead</h2>
        <?php else: ?>
          <h2 class="u-error__lead">While we sort this out</h2>
        <?php endif; ?>

        <div class="u-error__links">
          <a class="btn-u btn-gold" href="<?= e(url('menu.php')) ?>">
            <i class="fa-solid fa-book-open" aria-hidden="true"></i> See the menu
          </a>
          <a class="btn-u btn-outline-char" href="<?= e(url('order.php')) ?>">
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order online
          </a>
          <a class="btn-u btn-outline-char" href="<?= e(url('locations.php')) ?>">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i> Find us
          </a>
          <a class="btn-u btn-outline-char" href="<?= e(url('contact.php')) ?>">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i> Contact us
          </a>
        </div>

        <p class="u-error__note">
          <?php if ($phone !== ''): ?>
            Hungry now and out of patience? Call us on
            <a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a> &mdash;
          <?php endif; ?>
          or head <a href="<?= e(url('index.php')) ?>">back to the home page</a>.
        </p>

      </div>
    </div>
  </div>
</section>

<?php require APP_ROOT . '/includes/footer.php'; ?>
