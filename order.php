<?php
require __DIR__ . '/includes/app.php';

$store   = store();
$links   = order_links();
$address = store_address();
[$openNow, $openText] = store_open_status();

$phone     = trim((string)($store['phone'] ?? ''));
$email     = trim((string)($store['email'] ?? ''));
$shopName  = trim((string)($store['name'] ?? '')) !== '' ? $store['name'] : setting('site_name', 'Unika Kebab');

$steps = [
    [
        'icon'  => 'fa-solid fa-mobile-screen-button',
        'title' => 'Order ahead',
        'text'  => $phone !== ''
            ? 'Pick a platform above or call ' . $phone . '. Tell us the name on the order and roughly when you want it — anytime between opening and last call.'
            : 'Pick a platform above and check out as usual. Tell us the name on the order and roughly when you want it — anytime between opening and last call.',
    ],
    [
        'icon'  => 'fa-solid fa-fire-flame-curved',
        'title' => 'We fire the grill',
        'text'  => 'Nothing is cooked in advance. Your skewers only touch the coals once the ticket lands, and the pita goes on the same grill so it picks up the smoke.',
    ],
    [
        'icon'  => 'fa-solid fa-bag-shopping',
        'title' => 'Collect in about 12 minutes',
        'text'  => 'Walk in, give your name at the counter and take the bag — wrapped, sauced on the side and still hot. Extra pickles and taratur are on the counter.',
    ],
];

$pageKey = 'order';
$hero = [
    'eyebrow' => setting('order_hero_eyebrow', 'Pickup & delivery'),
    'title'   => setting('order_hero_title', 'Order online'),
    'text'    => setting('order_hero_text'),
    'image'   => 'assets/img/hero-home.jpg',
    'crumb'   => 'Order Online',
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<!-- ===================== ordering options ===================== -->
<section class="section section--cream">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Choose your platform</span>
      <h2 class="section-title">Where would you like to order?</h2>
      <p class="section-text">
        Delivery through our partners, or pickup straight from the counter. Prices and portions are
        the same wherever you order — pickup is simply the fastest way to eat it while it is still hot.
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <?php if ($links): ?>
      <div class="row g-4">
        <?php foreach ($links as $i => $link): ?>
          <?php $external = (bool)preg_match('#^https?://#i', (string)$link['url']); ?>
          <div class="col-lg-6" data-reveal data-reveal-delay="<?= (int)($i % 4) + 1 ?>">
            <a class="order-card" href="<?= e(url($link['url'])) ?>"
               <?= $external ? 'target="_blank" rel="noopener"' : '' ?>>
              <span class="order-card__icon">
                <i class="<?= e($link['icon']) ?>" aria-hidden="true"></i>
              </span>
              <span>
                <span class="order-card__title d-block"><?= e($link['label']) ?></span>
                <?php if (!empty($link['note'])): ?>
                  <span class="order-card__note d-block"><?= e($link['note']) ?></span>
                <?php endif; ?>
              </span>
              <span class="order-card__go">
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              </span>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="menu-empty" data-reveal>
        <i class="fa-solid fa-phone-flip fa-2x text-gold" aria-hidden="true"></i>
        <p class="mt-3 mb-4">
          Our online ordering partners are being set up. Until they are live, the quickest way to
          eat is to call the shop — we will have your skewers wrapped and waiting at the counter.
        </p>
        <?php if ($phone !== ''): ?>
          <a class="btn-u btn-gold" href="<?= e(phone_href($phone)) ?>">
            <i class="fa-solid fa-phone" aria-hidden="true"></i> Call <?= e($phone) ?>
          </a>
        <?php elseif ($email !== ''): ?>
          <a class="btn-u btn-gold" href="mailto:<?= e($email) ?>">
            <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email us
          </a>
        <?php else: ?>
          <a class="btn-u btn-gold" href="<?= e(url('contact.php')) ?>">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send us a message
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- ===================== prefer to call? ===================== -->
<section class="cta-band" data-reveal>
  <div class="container">
    <div class="row align-items-center g-4">

      <div class="col-lg-7">
        <span class="eyebrow">Prefer to call?</span>
        <h2 class="cta-band__title">Talk to the grill directly</h2>
        <p class="cta-band__text">
          <?php if ($phone !== ''): ?>
            Big orders, allergies or a tray for the office — pick up the phone and we will sort it
            out in a minute. We answer between the skewers, so give it a couple of rings.
          <?php else: ?>
            Big orders, allergies or a tray for the office — send us a message and we will confirm
            the timing before we start cooking.
          <?php endif; ?>
        </p>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
          <span class="status-chip status-chip--dark <?= $openNow ? 'status-chip--open' : 'status-chip--closed' ?>">
            <span class="status-chip__dot" aria-hidden="true"></span><?= e($openText) ?>
          </span>
          <?php if ($address !== ''): ?>
            <span class="cta-band__text">
              <i class="fa-solid fa-location-dot text-gold" aria-hidden="true"></i>
              Pickup at <?= e($address) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5 text-lg-end">
        <?php if ($phone !== ''): ?>
          <a class="btn-u btn-gold btn-u--lg" href="<?= e(phone_href($phone)) ?>">
            <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($phone) ?>
          </a>
        <?php else: ?>
          <a class="btn-u btn-gold btn-u--lg" href="<?= e(url('contact.php')) ?>">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Message us
          </a>
        <?php endif; ?>
        <p class="cta-band__text mt-3 mb-0">
          <a class="text-gold" href="<?= e(url('menu.php')) ?>">See the full menu</a>
          before you order.
        </p>
      </div>

    </div>
  </div>
</section>

<!-- ===================== how pickup works ===================== -->
<section class="section section--cream-2">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">How pickup works</span>
      <h2 class="section-title">Three steps, twelve minutes</h2>
      <p class="section-text">
        No apps to learn and no waiting around at <?= e($shopName) ?> — here is exactly what happens
        between your order and your first bite.
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4">
      <?php foreach ($steps as $i => $step): ?>
        <div class="col-md-4" data-reveal data-reveal-delay="<?= (int)($i + 1) ?>">
          <div class="feature-card">
            <div class="feature-card__icon"><i class="<?= e($step['icon']) ?>" aria-hidden="true"></i></div>
            <h3 class="feature-card__title"><?= e($step['title']) ?></h3>
            <p class="feature-card__text"><?= e($step['text']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
