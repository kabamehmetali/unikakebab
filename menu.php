<?php
/**
 * Unika Kebab — full menu.
 * Categories and items come straight from the admin panel; the page stays
 * readable whether the owner has added photos and prices yet or not.
 */
require __DIR__ . '/includes/app.php';

$pageKey = 'menu';
$hero = [
    'eyebrow'  => setting('menu_hero_eyebrow'),
    'title'    => setting('menu_hero_title', 'The menu'),
    'text'     => setting('menu_hero_text'),
    'image'    => 'assets/img/hero-menu.jpg',
    'crumb'    => 'Menu',
    'ornament' => false,      // the category bar opens the next section instead
];

// ---------------------------------------------------------------------
// Build the blocks once so the pills, the anchors and the body agree.
// A category is skipped only when it has nothing at all to say.
// ---------------------------------------------------------------------
$blocks    = [];
$usedIds   = [];
$itemCount = 0;

foreach (menu_categories() as $cat) {
    $items = menu_items((int)$cat['id']);
    $desc  = trim((string)($cat['description'] ?? ''));

    if (!$items && $desc === '') {
        continue;
    }

    $slug   = trim((string)($cat['slug'] ?? ''));
    $anchor = 'cat-' . ($slug !== '' ? slugify($slug) : slugify((string)$cat['name']));
    if (isset($usedIds[$anchor])) {                       // never emit a duplicate id
        $anchor .= '-' . (int)$cat['id'];
    }
    $usedIds[$anchor] = true;

    $itemCount += count($items);
    $blocks[]   = [
        'name'   => (string)$cat['name'],
        'desc'   => $desc,
        'items'  => $items,
        'anchor' => $anchor,
    ];
}

$storeRow   = store();
$phone      = trim((string)($storeRow['phone'] ?? ''));
$hasOrdering = (bool)order_links();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<!-- ================= the menu itself ================= -->
<section class="section section--parchment<?= $blocks ? ' section--menu' : '' ?>"
         data-menu-view data-view="grid">
  <div class="container">

    <?php if ($blocks): ?>
      <!-- category bar + layout switch — centred, and they follow you down the page -->
      <div class="menu-toolbar">
        <nav class="menu-nav" aria-label="Menu categories">
          <div class="menu-nav__scroll">
            <?php foreach ($blocks as $i => $block): ?>
              <a class="menu-pill<?= $i === 0 ? ' is-active' : '' ?>" href="#<?= e($block['anchor']) ?>">
                <?= e($block['name']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </nav>

        <div class="view-toggle" role="group" aria-label="Menu layout">
          <button class="view-toggle__btn is-active" type="button" data-view-btn="grid"
                  aria-pressed="true" title="Show photos">
            <i class="fa-solid fa-images" aria-hidden="true"></i>
            <span class="visually-hidden">Show menu with photos</span>
          </button>
          <button class="view-toggle__btn" type="button" data-view-btn="list"
                  aria-pressed="false" title="Show as a list">
            <i class="fa-solid fa-list" aria-hidden="true"></i>
            <span class="visually-hidden">Show menu as a list</span>
          </button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!$blocks): ?>
      <div class="menu-empty">
        <i class="fa-solid fa-fire-flame-curved fa-2x text-gold" aria-hidden="true"></i>
        <p class="mt-3 mb-2">The grill is on, but the menu is being rewritten right now.</p>
        <?php if ($phone !== ''): ?>
          <p class="mb-0">
            Call us on <a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a> and we will
            read you today's skewers, plates and sides.
          </p>
        <?php else: ?>
          <p class="mb-0">Drop in and we will talk you through today's skewers, plates and sides.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($blocks as $block): ?>
      <div class="menu-block" id="<?= e($block['anchor']) ?>" data-reveal>

        <div class="menu-block__head">
          <h2 class="menu-block__title"><?= e($block['name']) ?></h2>
          <?php if ($block['items'] && $block['desc'] !== ''): ?>
            <p class="menu-block__text"><?= e($block['desc']) ?></p>
          <?php endif; ?>
          <img class="ornament ornament--alt" src="<?= e(url('assets/img/ornament-alt.png')) ?>"
               alt="" aria-hidden="true" width="560" height="98">
        </div>

        <?php if ($block['items']): ?>
          <div class="row g-3 g-lg-4">
            <?php foreach ($block['items'] as $item): ?>
              <?php
                $image = trim((string)($item['image'] ?? ''));
                $text  = trim((string)($item['description'] ?? ''));
                $badge = trim((string)($item['badge'] ?? ''));
                $price = money($item['price'] ?? null, $item['price_note'] ?? null);
                $spicy = !empty($item['is_spicy']);
                $veg   = !empty($item['is_vegetarian']);
              ?>
              <div class="col-lg-6 menu-col">
                <div class="menu-row" role="button" tabindex="0"
                     aria-haspopup="dialog"
                     aria-label="<?= e($item['name']) ?> — see details"
                     data-dish
                     data-dish-name="<?= e($item['name']) ?>"
                     data-dish-image="<?= e(menu_item_img($image)) ?>"
                     data-dish-price="<?= e($price) ?>"
                     data-dish-text="<?= e($text) ?>"
                     data-dish-category="<?= e($block['name']) ?>"
                     data-dish-badge="<?= e($badge) ?>"
                     data-dish-spicy="<?= $spicy ? '1' : '0' ?>"
                     data-dish-veg="<?= $veg ? '1' : '0' ?>">

                  <?php /* In list view a photo-less item stays a clean text row;
                            in photo view it uses the configured placeholder. */ ?>
                  <div class="menu-row__thumb"<?= $image === '' ? ' data-placeholder="1"' : '' ?>>
                    <img src="<?= e(menu_item_img($image)) ?>" alt="<?= e($item['name']) ?>"
                         loading="lazy" width="88" height="88">
                  </div>

                  <div class="menu-row__body">
                    <div class="menu-row__head">
                      <h3 class="menu-row__name"><?= e($item['name']) ?></h3>
                      <span class="menu-row__dots" aria-hidden="true"></span>
                      <?php if ($price !== ''): ?>
                        <span class="menu-row__price"><?= e($price) ?></span>
                      <?php else: ?>
                        <span class="menu-row__price text-muted-warm">Ask in store</span>
                      <?php endif; ?>
                    </div>

                    <?php if ($text !== ''): ?>
                      <p class="menu-row__text"><?= e($text) ?></p>
                    <?php endif; ?>

                    <?php if ($badge !== '' || $spicy || $veg): ?>
                      <div class="chips">
                        <?php if ($badge !== ''): ?>
                          <span class="chip chip--gold"><?= e($badge) ?></span>
                        <?php endif; ?>
                        <?php if ($spicy): ?>
                          <span class="chip chip--spicy">
                            <i class="fa-solid fa-pepper-hot" aria-hidden="true"></i> Spicy
                          </span>
                        <?php endif; ?>
                        <?php if ($veg): ?>
                          <span class="chip chip--veg">
                            <i class="fa-solid fa-leaf" aria-hidden="true"></i> Vegetarian
                          </span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>

                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="menu-empty">
            <?php if ($block['desc'] !== ''): ?>
              <p class="mb-2"><?= e($block['desc']) ?></p>
            <?php endif; ?>
            <p class="mb-0">
              We are still pricing this section up — ask at the counter and we will
              add whatever you fancy to your order.
            </p>
          </div>
        <?php endif; ?>

      </div>
    <?php endforeach; ?>

    <div class="alert-note alert-note--info mt-5 mb-0" data-reveal>
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <span>
        Every cut of meat we grill is halal, and the kofte, marinades and sauces are made
        in our own kitchen. Tell us about any allergy when you order — we will tell you
        exactly what is in the dish, sesame, dairy and nuts included.
      </span>
    </div>

  </div>
</section>

<?php if ($hasOrdering || $phone !== ''): ?>
<!-- ================= order call to action ================= -->
<section class="cta-band">
  <div class="container text-center">
    <span class="eyebrow">Twelve minutes on the coals</span>
    <h2 class="cta-band__title">Order it hot</h2>
    <p class="cta-band__text">
      <?= e($itemCount > 0
            ? 'Pick your skewer, we will fire the grill the moment your order lands.'
            : 'Give us a call and we will talk you through what is on the grill today.') ?>
    </p>

    <div class="d-flex flex-column flex-sm-row justify-content-center align-items-stretch gap-3 mt-4">
      <?php if ($hasOrdering): ?>
        <a class="btn-u btn-gold btn-u--lg" href="<?= e(url('order.php')) ?>">
          <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order online
        </a>
      <?php endif; ?>
      <?php if ($phone !== ''): ?>
        <a class="btn-u btn-outline-gold btn-u--lg" href="<?= e(phone_href($phone)) ?>">
          <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($phone) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($blocks): ?>
<!-- ================= dish popup ================= -->
<div class="dish-modal" id="dishModal" hidden>
  <div class="dish-modal__backdrop" data-dish-close></div>

  <div class="dish-modal__panel" role="dialog" aria-modal="true" aria-labelledby="dishModalTitle">
    <button class="dish-modal__close" type="button" data-dish-close aria-label="Close">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>

    <div class="dish-modal__media" id="dishModalMedia">
      <img id="dishModalImage" src="<?= e(menu_item_img('')) ?>" alt="">
    </div>

    <div class="dish-modal__body">
      <span class="dish-modal__cat" id="dishModalCat"></span>
      <h2 class="dish-modal__title" id="dishModalTitle"></h2>
      <p class="dish-modal__price" id="dishModalPrice"></p>
      <p class="dish-modal__text" id="dishModalText"></p>
      <div class="chips" id="dishModalChips"></div>

      <div class="dish-modal__actions">
        <?php if ($hasOrdering): ?>
          <a class="btn-u btn-gold" href="<?= e(url('order.php')) ?>">
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order online
          </a>
        <?php endif; ?>
        <?php if ($phone !== ''): ?>
          <a class="btn-u btn-outline-char" href="<?= e(phone_href($phone)) ?>">
            <i class="fa-solid fa-phone" aria-hidden="true"></i> Call to order
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
