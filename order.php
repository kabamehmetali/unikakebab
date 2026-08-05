<?php
require __DIR__ . '/includes/app.php';

require_methods(['GET']);          // a read-only page; anything else is a 405

$store   = store();
$links   = order_links();
$address = store_address();
$mapUrl  = store_map_share_url(
    'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim(($store['name'] ?? '') . ' ' . $address))
);
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
        'title' => 'Collect at your convenience',
        'text'  => 'When you arrive, provide the name on your order and collect your freshly prepared meal from the counter. Condiments are available on request.',
    ],
];

$pageKey = 'order';
$breadcrumbs = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'Order Online', 'url' => 'order.php'],
];
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
        Choose delivery through one of our trusted partners or pickup directly from the restaurant.
        Each option gives you access to the same freshly prepared menu.
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
                <?= order_link_icon($link) ?>
              </span>
              <span>
                <span class="order-card__title d-block"><?= e($link['label']) ?></span>
                <?php $orderNote = order_link_note($link); ?>
                <?php if ($orderNote !== ''): ?>
                  <span class="order-card__note d-block"><?= e($orderNote) ?></span>
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
          Online ordering is currently being updated. In the meantime, please call the restaurant
          to arrange pickup.
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
            For catering, dietary requirements, or larger orders, call us directly. Our team will
            be pleased to help.
          <?php else: ?>
            For catering, dietary requirements, or larger orders, send us a message and we will
            confirm the details before we begin preparing your order.
          <?php endif; ?>
        </p>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
          <span class="status-chip status-chip--dark <?= $openNow ? 'status-chip--open' : 'status-chip--closed' ?>">
            <span class="status-chip__dot" aria-hidden="true"></span><?= e($openText) ?>
          </span>
          <?php if ($address !== ''): ?>
            <a class="cta-band__text cta-band__address" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener">
              <i class="fa-solid fa-location-dot text-gold" aria-hidden="true"></i>
              Pickup at <?= e($address) ?>
            </a>
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
      <h2 class="section-title">Pickup made simple</h2>
      <p class="section-text">
        Ordering ahead makes collection straightforward at <?= e($shopName) ?>. Here is what to expect
        from confirmation to pickup.
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
