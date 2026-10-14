<?php
/**
 * Unika Kebab — neighbourhood landing page.
 *
 * One script behind every /kebab-in-<area>-toronto URL; the slug arrives from
 * the rewrite in .htaccess and the copy comes from the neighbourhoods table,
 * so a new area is a row in the admin panel rather than a new file.
 *
 * There is exactly one restaurant, at 133 Jefferson Ave. These pages exist for
 * people in a nearby area who want to get to it or order pickup from it — the
 * copy says so plainly, and nothing here implies a second address. That
 * transparency is the whole difference between a local page Google keeps and a
 * doorway page it throws away.
 */
require __DIR__ . '/includes/app.php';   // first, so canonical_path_redirect()
                                          // still gets to 301 a mixed-case slug

require_methods(['GET']);          // a read-only page; anything else is a 405

// The rewrite only ever sends a lowercase-matched slug, but the script is also
// reachable directly as /neighbourhood, which would otherwise render a blank,
// indexable, near-duplicate page. No slug and no row both mean 404.
$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$nb   = $slug === '' ? null : neighbourhood($slug);

if (!$nb) {
    render_error_page(404);        // exits
}

$slug     = (string)$nb['slug'];
$area     = trim((string)$nb['name']);
$store    = store();
$siteName = setting('site_name', 'Unika Kebab');
$city     = trim((string)($store['city'] ?? 'Toronto'));
$phone    = trim((string)($store['phone'] ?? ''));
$address  = store_address();
$ornament = url('assets/img/ornament.png');

[$openNow, $openText] = store_open_status();

$directions = store_map_share_url(
    'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($address !== '' ? $address : $siteName)
);

/** Split a textarea into paragraphs on blank lines. */
$paras = static function (?string $text): array {
    $text = trim((string)$text);
    if ($text === '') {
        return [];
    }
    return array_values(array_filter(array_map(
        'trim',
        preg_split('/\R\s*\R/', $text) ?: []
    ), static fn(string $p): bool => $p !== ''));
};

// --- the four logistics facts ----------------------------------------
$facts = [];
foreach ([1, 2, 3, 4] as $n) {
    $text = trim((string)($nb["fact{$n}_text"] ?? ''));
    if ($text === '') {
        continue;
    }
    $facts[] = [
        'icon'  => trim((string)($nb["fact{$n}_icon"] ?? '')) ?: 'fa-solid fa-location-dot',
        'label' => trim((string)($nb["fact{$n}_label"] ?? '')),
        'text'  => $text,
    ];
}

// --- the dishes this page calls out ----------------------------------
// Matched to the live menu so each pick carries its real price and photo, and
// so a dish taken off the menu quietly drops out of the page rather than
// advertising something the kitchen no longer makes.
//
// Stored as "Category::Dish", because three dishes exist twice under the same
// name — Adana Kebab, Beef Kofte Kebap and Chicken Shawarma are each both a
// sandwich and a plate, at different prices. Matching on the name alone
// returns whichever sorts first, which would print $11.99 beside a sentence
// describing the $19.99 plate.
$picks = [];
foreach ([1, 2, 3] as $n) {
    $dish = trim((string)($nb["pick{$n}_dish"] ?? ''));
    $note = trim((string)($nb["pick{$n}_note"] ?? ''));
    if ($dish === '') {
        continue;
    }

    $category = '';
    if (str_contains($dish, '::')) {
        [$category, $dish] = array_map('trim', explode('::', $dish, 2));
    }

    $sql = "SELECT i.*, c.name AS category_name
              FROM menu_items i
              JOIN menu_categories c ON c.id = i.category_id
             WHERE i.is_active = 1 AND c.is_active = 1 AND i.name = ?";
    $args = [$dish];

    if ($category !== '') {
        $sql   .= ' AND c.name = ?';
        $args[] = $category;
    }

    $item = db_one($sql . ' ORDER BY c.sort_order, i.sort_order LIMIT 1', $args);

    // A category that has been renamed should not silently blank the pick.
    if (!$item && $category !== '') {
        $item = db_one(
            "SELECT i.*, c.name AS category_name
               FROM menu_items i
               JOIN menu_categories c ON c.id = i.category_id
              WHERE i.is_active = 1 AND c.is_active = 1 AND i.name = ?
           ORDER BY c.sort_order, i.sort_order LIMIT 1",
            [$dish]
        );
    }

    if ($item) {
        $picks[] = ['item' => $item, 'note' => $note];
    }
}

// --- FAQ --------------------------------------------------------------
$faqs = [];
foreach ([1, 2, 3] as $n) {
    $q = trim((string)($nb["faq{$n}_q"] ?? ''));
    $a = trim((string)($nb["faq{$n}_a"] ?? ''));
    if ($q !== '' && $a !== '') {
        $faqs[] = ['q' => $q, 'a' => $a];
    }
}

// --- sibling areas ----------------------------------------------------
$siblings = neighbourhood_siblings($nb);

// These pages sit under Locations in the breadcrumb, so that is the nav item
// that should light up — without this the whole navbar reads as unselected.
$GLOBALS['navActive'] = 'locations.php';

$pageKey = 'nb-' . $slug;          // must match the key seo_pages() registers,
                                    // or page_meta() hands this page the home
                                    // page's canonical and it never gets indexed
$breadcrumbs = [
    ['name' => 'Home',      'url' => 'index.php'],
    ['name' => 'Locations', 'url' => 'locations.php'],
    ['name' => $area,       'url' => $slug],
];

$hero = [
    'eyebrow'  => trim((string)($nb['hero_eyebrow'] ?? '')),
    'title'    => trim((string)($nb['hero_title'] ?? '')) ?: ('Kebab in ' . $area),
    'text'     => trim((string)($nb['hero_text'] ?? '')),
    'image'    => trim((string)($nb['hero_image'] ?? '')) ?: 'assets/img/bg-stone.jpg',
    'crumb'    => $area,
    'ornament' => false,
    'compact'  => true,
    'flush'    => true,
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<!-- ================= the area ================= -->
<section class="section section--cream section--tight-top">
  <div class="container">

    <img class="ornament ornament--top" src="<?= e($ornament) ?>"
         alt="" aria-hidden="true" width="720" height="124">

    <div class="row g-5 align-items-start">

      <div class="col-lg-7">
        <div data-reveal>
          <?php if (trim((string)$nb['intro_title']) !== ''): ?>
            <span class="eyebrow eyebrow--start"><?= e($area) ?></span>
            <h2 class="section-title"><?= e($nb['intro_title']) ?></h2>
          <?php endif; ?>

          <?php foreach ($paras($nb['intro_text']) as $p): ?>
            <p class="section-text section-text--start"><?= e($p) ?></p>
          <?php endforeach; ?>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <a class="btn-u btn-gold" href="<?= e(url('order.php')) ?>">
              <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order for pickup
            </a>
            <a class="btn-u btn-outline-char" href="<?= e(url('menu.php')) ?>">
              <i class="fa-solid fa-utensils" aria-hidden="true"></i> See the full menu
            </a>
          </div>
        </div>
      </div>

      <!-- Where the shop actually is. Said plainly, on every one of these
           pages, because the honest version is also the one that ranks. -->
      <div class="col-lg-5">
        <div class="info-card" data-reveal data-reveal-delay="1">
          <div class="info-card__icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></div>
          <div class="info-card__label">Where we actually are</div>
          <h2 class="display fs-4 mb-1"><?= e($siteName) ?></h2>
          <p class="text-muted-warm mb-3">
            One kitchen, in Liberty Village — not a second branch in <?= e($area) ?>.
          </p>

          <span class="status-chip <?= $openNow ? 'status-chip--open' : 'status-chip--closed' ?>">
            <span class="status-chip__dot" aria-hidden="true"></span><?= e($openText) ?>
          </span>

          <?php if ($address !== ''): ?>
            <a class="info-card__value info-card__address-link mt-3 mb-0" href="<?= e($directions) ?>"
               target="_blank" rel="noopener"><?= e($address) ?></a>
          <?php endif; ?>

          <div class="d-flex flex-wrap gap-2 mt-4">
            <a class="btn-u btn-gold btn-u--sm" href="<?= e($directions) ?>" target="_blank" rel="noopener">
              <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Directions
            </a>
            <?php if ($phone !== ''): ?>
              <a class="btn-u btn-outline-char btn-u--sm" href="<?= e(phone_href($phone)) ?>">
                <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($phone) ?>
              </a>
            <?php endif; ?>
          </div>

          <p class="info-card__note mt-3 mb-0">
            <a class="link-arrow" href="<?= e(url('locations.php')) ?>">Hours, parking and the full address</a>
          </p>
        </div>
      </div>

    </div>
  </div>
</section>


<?php if ($paras($nb['travel_text']) || $facts): ?>
<!-- ================= getting here ================= -->
<section class="section section--cream-2">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">From <?= e($area) ?></span>
      <h2 class="section-title"><?= e(trim((string)$nb['travel_title']) ?: ('Getting here from ' . $area)) ?></h2>
      <?php foreach ($paras($nb['travel_text']) as $p): ?>
        <p class="section-text"><?= e($p) ?></p>
      <?php endforeach; ?>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <?php if ($facts): ?>
      <div class="row g-4">
        <?php foreach ($facts as $i => $fact): ?>
          <div class="col-lg-3 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 4) + 1 ?>">
            <div class="info-card h-100">
              <div class="info-card__icon"><i class="<?= e($fact['icon']) ?>" aria-hidden="true"></i></div>
              <?php if ($fact['label'] !== ''): ?>
                <div class="info-card__label"><?= e($fact['label']) ?></div>
              <?php endif; ?>
              <p class="info-card__note mb-0"><?= e($fact['text']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>
<?php endif; ?>


<?php if ($picks): ?>
<!-- ================= what to order ================= -->
<section class="section section--stone">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Off the coals</span>
      <h2 class="section-title"><?= e(trim((string)$nb['picks_title']) ?: ('What ' . $area . ' orders')) ?></h2>
      <?php if (trim((string)$nb['picks_text']) !== ''): ?>
        <p class="section-text"><?= e($nb['picks_text']) ?></p>
      <?php endif; ?>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($picks as $i => $pick): ?>
        <?php $item = $pick['item']; ?>
        <div class="col-lg-4 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 3) + 1 ?>">
          <article class="dish-card h-100">
            <div class="dish-card__media">
              <img src="<?= e(menu_item_img($item['image'])) ?>"
                   alt="<?= e(menu_item_alt($item, (string)($item['category_name'] ?? ''))) ?>"
                   loading="lazy" decoding="async">
            </div>
            <div class="dish-card__body">
              <div class="dish-card__head">
                <h3 class="dish-card__name"><?= e($item['name']) ?></h3>
                <span class="dish-card__dots" aria-hidden="true"></span>
                <span class="dish-card__price"><?= e(money($item['price'])) ?></span>
              </div>
              <span class="dish-card__cat"><?= e($item['category_name'] ?? '') ?></span>
              <?php if ($pick['note'] !== ''): ?>
                <p class="dish-card__text"><?= e($pick['note']) ?></p>
              <?php endif; ?>
              <?php if (!empty($item['is_vegetarian']) || !empty($item['is_spicy'])): ?>
                <div class="chips">
                  <?php if (!empty($item['is_vegetarian'])): ?><span class="chip chip--veg">Vegetarian</span><?php endif; ?>
                  <?php if (!empty($item['is_spicy'])): ?><span class="chip chip--spicy">Spicy</span><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-5" data-reveal>
      <a class="btn-u btn-char" href="<?= e(url('menu.php')) ?>">
        <i class="fa-solid fa-utensils" aria-hidden="true"></i> The whole menu
      </a>
    </div>

  </div>
</section>
<?php endif; ?>


<?php if ($faqs): ?>
<!-- ================= questions ================= -->
<section class="section section--cream">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Before you set off</span>
      <h2 class="section-title">Questions from <?= e($area) ?></h2>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($faqs as $i => $faq): ?>
        <div class="col-lg-10" data-reveal data-reveal-delay="<?= (int)($i % 3) + 1 ?>">
          <div class="info-card">
            <div class="info-card__icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></div>
            <h3 class="info-card__value h5 mb-2"><?= e($faq['q']) ?></h3>
            <p class="info-card__note mb-0"><?= e($faq['a']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<?php if ($siblings): ?>
<!-- ================= nearby areas ================= -->
<section class="section section--cream-2">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Close by</span>
      <h2 class="section-title">Coming from somewhere else?</h2>
      <p class="section-text">
        The shop is the same one kitchen on Jefferson Ave whichever direction you walk in from —
        these are the routes in from the areas either side of <?= e($area) ?>.
      </p>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($siblings as $i => $sibling): ?>
        <div class="col-lg-4 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 3) + 1 ?>">
          <a class="info-card h-100 d-block text-decoration-none" href="<?= e(url($sibling['slug'])) ?>">
            <div class="info-card__icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></div>
            <div class="info-card__label">Getting here from</div>
            <h3 class="info-card__value h5 mb-2"><?= e($sibling['name']) ?></h3>
            <?php if (trim((string)$sibling['hero_text']) !== ''): ?>
              <p class="info-card__note mb-0"><?= e(excerpt($sibling['hero_text'], 110)) ?></p>
            <?php endif; ?>
            <span class="link-arrow mt-3 d-inline-block">Read the route</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ================= close ================= -->
<section class="cta-band">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="eyebrow eyebrow--start text-gold">From <?= e($area) ?></span>
        <h2 class="cta-band__title">
          <?= e(trim((string)$nb['closing_title']) ?: ('Worth the walk from ' . $area)) ?>
        </h2>
        <?php if (trim((string)$nb['closing_text']) !== ''): ?>
          <p class="cta-band__text"><?= e($nb['closing_text']) ?></p>
        <?php endif; ?>
        <?php if ($address !== ''): ?>
          <p class="cta-band__address mb-0"><?= e($address) ?></p>
        <?php endif; ?>
      </div>
      <div class="col-lg-4">
        <div class="location-cta__actions d-flex flex-wrap gap-2 justify-content-lg-end">
          <a class="btn-u btn-gold btn-u--lg" href="<?= e(url('order.php')) ?>">
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order for pickup
          </a>
          <?php if ($phone !== ''): ?>
            <a class="btn-u btn-outline-gold" href="<?= e(phone_href($phone)) ?>">
              <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($phone) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
