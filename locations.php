<?php
require __DIR__ . '/includes/app.php';

$store    = store();
$socials  = store_socials();
$grouped  = store_hours_days();
$dayShort = day_names(true);
$todayIdx = (int)date('N') - 1;
[$openNow, $openText] = store_open_status();

$shopName    = trim((string)($store['name'] ?? '')) !== '' ? $store['name'] : setting('site_name', 'Unika Kebab');
$shopTagline = trim((string)($store['tagline'] ?? '')) !== '' ? $store['tagline'] : setting('site_tagline', '');
$addressOne  = store_address();

// Address broken into readable lines for the card.
$cityLine = trim(
    trim((string)($store['city'] ?? '')) .
    (trim((string)($store['province'] ?? '')) !== '' || trim((string)($store['postal_code'] ?? '')) !== ''
        ? ', ' . trim(($store['province'] ?? '') . ' ' . ($store['postal_code'] ?? ''))
        : ''),
    ' ,'
);
$addressLines = array_values(array_filter([
    $store['address_line1'] ?? '',
    $store['address_line2'] ?? '',
    $cityLine,
    $store['country'] ?? '',
], static fn($line) => trim((string)$line) !== ''));

// --- maps -----------------------------------------------------------
$mapsKey   = trim(setting('google_maps_api_key'));
$lat       = trim((string)($store['latitude'] ?? ''));
$lng       = trim((string)($store['longitude'] ?? ''));
$hasCoords = $lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng);
$mapZoom   = (int)($store['map_zoom'] ?? 16);
$mapZoom   = ($mapZoom >= 1 && $mapZoom <= 21) ? $mapZoom : 16;

$mapQuery    = $hasCoords ? $lat . ',' . $lng : $addressOne;
$searchTerm  = trim($shopName . ' ' . $addressOne);
$hasMap      = $mapQuery !== '';
$embedUrl    = 'https://www.google.com/maps/embed/v1/place?key=' . urlencode($mapsKey)
             . '&q=' . urlencode($mapQuery) . '&zoom=' . $mapZoom;
$searchUrl   = store_map_share_url(
    'https://www.google.com/maps/search/?api=1&query=' . urlencode($searchTerm !== '' ? $searchTerm : $shopName)
);
$directions  = store_map_share_url(
    'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($addressOne !== '' ? $addressOne : $searchTerm)
);

$allClosed = true;
foreach ($grouped as $row) {
    if (empty($row['closed'])) {
        $allClosed = false;
        break;
    }
}

// --- getting here / parking notes ------------------------------------
$notes = [];
if (trim((string)($store['directions_note'] ?? '')) !== '') {
    $notes[] = ['icon' => 'fa-solid fa-route', 'label' => 'Getting here', 'text' => $store['directions_note']];
}
if (trim((string)($store['parking_note'] ?? '')) !== '') {
    $notes[] = ['icon' => 'fa-solid fa-square-parking', 'label' => 'Parking', 'text' => $store['parking_note']];
}

$pageKey = 'locations';
$breadcrumbs = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'Locations', 'url' => 'locations.php'],
];
$hero = [
    'eyebrow' => setting('locations_hero_eyebrow', 'Find us'),
    'title'   => setting('locations_hero_title', 'Our location'),
    'text'    => setting('locations_hero_text'),
    'image'    => 'assets/img/bg-stone.jpg',
    'crumb'    => 'Locations',
    'ornament' => false,      // it opens the content section below instead
    'compact'  => true,       // the address and map matter more than the banner
    'flush'    => true,       // so that ornament sits evenly between the two
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<!-- ===================== the shop + map ===================== -->
<section class="section section--cream section--tight-top">
  <div class="container">

    <img class="ornament ornament--top" src="<?= e(url('assets/img/ornament.png')) ?>"
         alt="" aria-hidden="true" width="720" height="124">

    <div class="row g-5 align-items-start">

      <!-- ---------- details ---------- -->
      <div class="col-lg-6">

        <div class="info-card" data-reveal>
          <div class="info-card__icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></div>
          <div class="info-card__label">The shop</div>
          <h2 class="display fs-2 mb-1"><?= e($shopName) ?></h2>
          <?php if ($shopTagline !== ''): ?>
            <p class="text-muted-warm mb-3"><?= e($shopTagline) ?></p>
          <?php endif; ?>

          <span class="status-chip <?= $openNow ? 'status-chip--open' : 'status-chip--closed' ?>">
            <span class="status-chip__dot" aria-hidden="true"></span><?= e($openText) ?>
          </span>

          <?php if ($addressLines): ?>
            <a class="info-card__value info-card__address-link mt-3 mb-0" href="<?= e($directions) ?>"
               target="_blank" rel="noopener" aria-label="Open <?= e($shopName) ?> in Google Maps">
              <?php foreach ($addressLines as $i => $line): ?>
                <?= e($line) ?><?= $i < count($addressLines) - 1 ? '<br>' : '' ?>
              <?php endforeach; ?>
            </a>
          <?php else: ?>
            <p class="info-card__note mt-3 mb-0">
              We are finalising the street address for this location. Give us a call and we will
              walk you to the door.
            </p>
          <?php endif; ?>

          <?php if ($addressOne !== '' || !empty($store['phone'])): ?>
            <div class="d-flex flex-wrap gap-2 mt-4">
              <?php if ($addressOne !== ''): ?>
                <a class="btn-u btn-gold" href="<?= e($directions) ?>" target="_blank" rel="noopener">
                  <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Get directions
                </a>
              <?php endif; ?>
              <?php if (!empty($store['phone'])): ?>
                <a class="btn-u btn-outline-char" href="<?= e(phone_href($store['phone'])) ?>">
                  <i class="fa-solid fa-phone" aria-hidden="true"></i> Call the shop
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="map-frame mt-4" data-reveal data-reveal-delay="1">
          <?php if ($mapsKey !== '' && $hasMap): ?>
            <iframe src="<?= e($embedUrl) ?>"
                    title="Google map showing <?= e($shopName . ($addressOne !== '' ? ' at ' . $addressOne : '')) ?>"
                    loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
          <?php else: ?>
            <div class="map-fallback">
              <div>
                <i class="fa-solid fa-map-location-dot fa-2x text-gold" aria-hidden="true"></i>
                <p class="mt-3 mb-3">
                  <?php if ($addressOne !== ''): ?>
                    The interactive map is not switched on yet — open our pin in Google Maps instead.
                  <?php else: ?>
                    Our map pin is being set up. Call the shop and we will give you directions.
                  <?php endif; ?>
                </p>
                <?php if ($addressOne !== ''): ?>
                  <a class="btn-u btn-outline-char btn-u--sm" href="<?= e($searchUrl) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-map-pin" aria-hidden="true"></i> Open in Google Maps
                  </a>
                <?php elseif (!empty($store['phone'])): ?>
                  <a class="btn-u btn-outline-char btn-u--sm" href="<?= e(phone_href($store['phone'])) ?>">
                    <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($store['phone']) ?>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- ---------- contact cards + shop photo ---------- -->
      <div class="col-lg-6">
        <?php if (!empty($store['phone']) || !empty($store['email'])): ?>
          <div class="row g-4">
            <?php if (!empty($store['phone'])): ?>
              <div class="col-sm-6" data-reveal data-reveal-delay="1">
                <div class="info-card">
                  <div class="info-card__icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></div>
                  <div class="info-card__label">Phone</div>
                  <p class="info-card__value">
                    <a href="<?= e(phone_href($store['phone'])) ?>"><?= e($store['phone']) ?></a>
                  </p>
                  <p class="info-card__note">Best for pickup orders and anything urgent.</p>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($store['email'])): ?>
              <div class="col-sm-6" data-reveal data-reveal-delay="2">
                <div class="info-card">
                  <div class="info-card__icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></div>
                  <div class="info-card__label">Email</div>
                  <p class="info-card__value">
                    <a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a>
                  </p>
                  <p class="info-card__note">Catering, large trays and party platters.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="info-card mt-4" data-reveal data-reveal-delay="3">
          <div class="info-card__icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></div>
          <div class="info-card__label">Opening hours</div>
          <ul class="hours-list">
            <?php foreach ($grouped as $row): ?>
              <li class="<?= $row['is_today'] ? 'is-today' : '' ?><?= $row['closed'] ? ' is-closed-day' : '' ?>">
                <span class="hours-list__day">
                  <?= e($row['day']) ?>
                  <?php if ($row['is_today']): ?>
                    <span class="hours-list__dot" aria-hidden="true"></span>
                    <span class="visually-hidden">(today)</span>
                  <?php endif; ?>
                  <?php if ($row['note'] !== ''): ?>
                    <span class="hours-list__note"><?= e($row['note']) ?></span>
                  <?php endif; ?>
                </span>
                <span class="hours-list__time <?= $row['closed'] ? 'is-closed' : '' ?>"><?= e($row['hours']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="info-card__note">
            <?php if ($allClosed): ?>
              Our hours are being updated right now — please call before you set off.
            <?php else: ?>
              The grill stays lit until closing time, so late orders are welcome.
            <?php endif; ?>
          </p>
        </div>

        <?php if (!empty($store['photo'])): ?>
          <div class="media-frame media-frame--wide mt-4" data-reveal>
            <img src="<?= e(img($store['photo'])) ?>"
                 alt="<?= e(alt_text($store['photo_alt'] ?? null, trim($shopName . ' ' . $shopTagline) . ' shopfront')) ?>"
                 loading="lazy" decoding="async">
            <span class="media-frame__outline" aria-hidden="true"></span>
          </div>
        <?php endif; ?>

      </div>

    </div>
  </div>
</section>

<?php if ($notes): ?>
<!-- ===================== getting here / parking ===================== -->
<section class="section section--cream-2">
  <div class="container">
    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Before you set off</span>
      <h2 class="section-title">Finding the door</h2>
      <p class="section-text">
        A couple of things that make the trip easier — where we sit on the street, and where to leave the car.
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($notes as $i => $note): ?>
        <div class="col-md-6" data-reveal data-reveal-delay="<?= (int)($i + 1) ?>">
          <div class="info-card">
            <div class="info-card__icon"><i class="<?= e($note['icon']) ?>" aria-hidden="true"></i></div>
            <div class="info-card__label"><?= e($note['label']) ?></div>
            <p class="info-card__value"><?= e($note['text']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===================== come and eat with us ===================== -->
<section class="section section--dark">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7" data-reveal>
        <span class="eyebrow">Come and eat with us</span>
        <h2 class="section-title">Pull up a stool by the coals</h2>
        <p class="section-text section-text--start">
          There are a handful of seats along the window, the smell of charcoal in the air and a
          grill you can watch from the counter. Eat in, take it away, or order ahead and collect —
          either way you get the same skewers straight off the fire.
        </p>

        <?php if ($socials): ?>
          <div class="social-row">
            <?php foreach ($socials as $social): ?>
              <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener"
                 aria-label="<?= e($shopName . ' on ' . $social['label']) ?>" title="<?= e($social['label']) ?>">
                <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-5" data-reveal data-reveal-delay="1">
        <div class="location-cta__actions">
          <a class="btn-u btn-outline-gold btn-u--lg" href="<?= e(url('order.php')) ?>">
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order online
          </a>
          <?php if (!empty($store['phone'])): ?>
            <p class="location-cta__call">
              Or call <a class="text-gold" href="<?= e(phone_href($store['phone'])) ?>"><?= e($store['phone']) ?></a>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
