<?php
/**
 * The shareable newsletter sign-up page.
 *
 * A single address the owner can paste into an Instagram bio, a WhatsApp
 * broadcast or a QR code on a table card, that opens on one thing: a photo, a
 * reason to sign up, and a name-and-email form.
 *
 * It shares the site shell rather than being a bare landing page — somebody who
 * arrives from a link with no idea who we are should be one tap from the menu —
 * but nothing above the form competes with it.
 *
 * The form itself is includes/newsletter.php, the same band the home page uses,
 * so there is one sign-up flow to maintain: one honeypot, one CSRF check, one
 * "you are already on the list" panel. It reads the page it sits on from
 * SCRIPT_NAME, so subscribe.php returns here on its own.
 */
require __DIR__ . '/includes/app.php';

require_methods(['GET']);

$storeRow  = store();
$siteName  = setting('site_name', 'Unika Kebab');
$heroImage = img(setting('newsletter_image', 'assets/img/hero-home.jpg'), 'assets/img/hero-home.jpg');
$heroAlt   = alt_text(
    setting('newsletter_image_alt'),
    'Charcoal-grilled skewers at ' . $siteName
);

// What somebody gets for handing over their address. Kept here rather than in
// the settings table because it is the argument for signing up, not copy that
// changes with the seasons — and a page that cannot say why is a page nobody
// fills in.
$perks = [
    ['icon' => 'fa-solid fa-percent',            'text' => 'Offers that never make it to the menu board'],
    ['icon' => 'fa-solid fa-fire-flame-curved',  'text' => 'First word on new skewers and seasonal plates'],
    ['icon' => 'fa-solid fa-envelope-open-text', 'text' => 'One short email a month — never more'],
];

$pageKey = 'join';
$breadcrumbs = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'Join the list', 'url' => 'join.php'],
];
require __DIR__ . '/includes/header.php';
?>

<section class="join">
  <div class="join__media">
    <img src="<?= e($heroImage) ?>" alt="<?= e($heroAlt) ?>" fetchpriority="high" decoding="async">
    <div class="join__veil" aria-hidden="true"></div>
  </div>

  <div class="join__inner">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">

          <div class="join__card">

            <div class="join__head">
              <img class="join__logo"
                   src="<?= e(img(setting('logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>"
                   alt="<?= e(alt_text(setting('logo_alt'), $siteName)) ?>"
                   width="150" height="139">

              <span class="eyebrow"><?= e(setting('newsletter_eyebrow', 'The Unika list')) ?></span>
              <h1 class="join__title">
                <?= e(setting('newsletter_share_title', setting('newsletter_title', 'Specials before anyone else'))) ?>
              </h1>
              <p class="join__text">
                <?= e(setting('newsletter_share_text', setting(
                    'newsletter_text',
                    'One short email a month: new skewers, seasonal plates and the occasional free baklava. No spam, unsubscribe any time.'
                ))) ?>
              </p>
            </div>

            <ul class="join__perks">
              <?php foreach ($perks as $perk): ?>
                <li>
                  <i class="<?= e($perk['icon']) ?>" aria-hidden="true"></i>
                  <span><?= e($perk['text']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="join__form">
              <?php require __DIR__ . '/includes/newsletter.php'; ?>
            </div>

          </div>

          <p class="join__foot">
            <a href="<?= e(url('menu.php')) ?>">See the menu</a>
            <span aria-hidden="true">·</span>
            <a href="<?= e(url('order.php')) ?>">Order online</a>
            <?php if (store_address() !== ''): ?>
              <span aria-hidden="true">·</span>
              <a href="<?= e(url('locations.php')) ?>">Find us</a>
            <?php endif; ?>
          </p>

        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
