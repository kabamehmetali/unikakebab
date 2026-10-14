<?php
require __DIR__ . '/includes/app.php';

require_methods(['GET']);          // a read-only page; anything else is a 405

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
$pageCss = 'locations.css';
$pageJs  = 'locations.js';
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

<?php
// --- the Toronto zone directory --------------------------------------
$zoneGroups  = toronto_zones_by_district();
$zoneDefault = toronto_zones()[0] ?? null;      // nearest zone leads the map
$zoneCount   = count(toronto_zones());
?>
<?php if ($zoneGroups): ?>
<!-- ================= getting here from across Toronto ================= -->
<?php /* Moved off the footer and given room to work. One kitchen, and an
         honest answer to "how far is that from me?" for every neighbourhood
         in the city — the distances are computed from the City of Toronto's
         own boundary data, and the real routing is handed to Google Maps
         rather than guessed at. */ ?>
<section class="section section--cream-2" id="zones">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Getting here</span>
      <h2 class="section-title">How far are we from your part of Toronto?</h2>
      <p class="section-text">
        There is one Unika Kebab and it is the one on Jefferson Ave, in Liberty Village.
        Pick your neighbourhood and the map will draw the route. All
        <?= (int)$zoneCount ?> of the city's official neighbourhoods are here, nearest first.
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>"
           alt="" aria-hidden="true" width="720" height="124">
    </div>

    <?php
      // The embed URL is templated once here rather than written out on
      // every row: repeating it per row added roughly 30 KB to the page and
      // printed the Maps key 158 times over. {o} is swapped for the origin
      // by assets/js/locations.js when a neighbourhood is picked.
      $embedTemplate = $zoneDefault ? zone_embed_url($zoneDefault) : '';
      if ($embedTemplate !== '' && !empty($zoneDefault['latitude'])) {
          $embedTemplate = str_replace(
              urlencode($zoneDefault['latitude'] . ',' . $zoneDefault['longitude']),
              '{o}',
              $embedTemplate
          );
      }
    ?>
    <div class="zone-locator" data-reveal data-zone-embed-template="<?= e($embedTemplate) ?>">

      <!-- picker -->
      <div class="zone-picker">
        <div class="zone-tabs" role="tablist" aria-label="Toronto districts">
          <?php foreach (array_keys($zoneGroups) as $i => $district): ?>
            <button class="zone-tab<?= $i === 0 ? ' is-active' : '' ?>" type="button"
                    role="tab" id="zt-<?= e(slugify($district)) ?>"
                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-controls="zp-<?= e(slugify($district)) ?>"
                    data-zone-tab="<?= e(slugify($district)) ?>">
              <?= e($district) ?>
              <span><?= count($zoneGroups[$district]) ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <?php $panelIndex = 0; ?>
        <?php foreach ($zoneGroups as $district => $districtZones): ?>
          <?php $groupId = slugify((string)$district); ?>
          <ul class="zone-list<?= $panelIndex === 0 ? ' is-active' : '' ?>"
              id="zp-<?= e($groupId) ?>" role="tabpanel"
              aria-labelledby="zt-<?= e($groupId) ?>"
              data-zone-panel="<?= e($groupId) ?>"
              <?= $panelIndex === 0 ? '' : 'hidden' ?>>
            <?php foreach ($districtZones as $zone): ?>
              <?php
                $km       = $zone['distance_km'] !== null ? (float)$zone['distance_km'] : null;
                $pageSlug = trim((string)($zone['page_slug'] ?? ''));
                $hasPage  = $pageSlug !== '' && neighbourhood($pageSlug) !== null;
              ?>
              <li>
                <?php /* A real link, so the list still does something useful with
                         JavaScript off: every row opens directions in Maps. The
                         script below intercepts the click and redraws the map
                         inline instead. */ ?>
                <a class="zone-row" href="<?= e(zone_directions_url($zone)) ?>"
                   target="_blank" rel="noopener"
                   data-zone-origin="<?= e($zone['latitude'] . ',' . $zone['longitude']) ?>"
                   data-zone-name="<?= e($zone['name']) ?>"
                   data-zone-km="<?= $km !== null ? e(number_format($km, 1)) : '' ?>"
                   data-zone-band="<?= e(zone_travel_band($km)) ?>"
                   data-zone-page="<?= $hasPage ? e(url($pageSlug)) : '' ?>">
                  <span class="zone-row__name">
                    <?= e($zone['name']) ?>
                    <?php if ($hasPage): ?>
                      <em class="zone-row__flag" title="We have written up the route from here">route</em>
                    <?php endif; ?>
                  </span>
                  <?php if ($km !== null): ?>
                    <span class="zone-row__km"><?= e(number_format($km, 1)) ?> km</span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php $panelIndex++; ?>
        <?php endforeach; ?>
      </div>

      <!-- map -->
      <div class="zone-map">
        <?php $firstEmbed = $zoneDefault ? zone_embed_url($zoneDefault) : ''; ?>
        <?php if ($firstEmbed !== ''): ?>
          <div class="zone-map__frame">
            <iframe id="zoneMap" src="<?= e($firstEmbed) ?>"
                    title="Driving route to <?= e($shopName) ?>"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen></iframe>
          </div>
        <?php else: ?>
          <div class="zone-map__frame zone-map__frame--empty">
            <div class="map-fallback">
              <i class="fa-solid fa-map-location-dot fa-2x text-gold" aria-hidden="true"></i>
              <p class="mt-3 mb-0">
                Add a Google Maps key under Settings and the route will draw here.
                The distances and directions links below work either way.
              </p>
            </div>
          </div>
        <?php endif; ?>

        <div class="zone-readout" id="zoneReadout">
          <div>
            <span class="zone-readout__label">From</span>
            <strong id="zoneName"><?= e($zoneDefault['name'] ?? '') ?></strong>
            <?php /* "in a straight line" is not padding: the map beside this is
                     showing Google's real driving distance, which for anywhere
                     past the Don is close to double the number on the left. Two
                     unexplained distances side by side reads as a bug. */ ?>
            <span class="zone-readout__meta">
              <span id="zoneKm"><?= $zoneDefault && $zoneDefault['distance_km'] !== null
                  ? e(number_format((float)$zoneDefault['distance_km'], 1)) . ' km' : '' ?></span>
              in a straight line
              <span aria-hidden="true">·</span>
              <span id="zoneBand"><?= e(zone_travel_band(
                  $zoneDefault && $zoneDefault['distance_km'] !== null
                      ? (float)$zoneDefault['distance_km'] : null)) ?></span>
            </span>
          </div>
          <div class="zone-readout__actions">
            <a class="btn-u btn-gold btn-u--sm" id="zoneDirections" target="_blank" rel="noopener"
               href="<?= $zoneDefault ? e(zone_directions_url($zoneDefault)) : '#' ?>">
              <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Directions
            </a>
            <a class="btn-u btn-outline-char btn-u--sm" id="zonePage" hidden href="#">
              <i class="fa-solid fa-route" aria-hidden="true"></i> Read the route
            </a>
          </div>
        </div>

        <p class="zone-note">
          The kilometres on the left are measured in a straight line from the centre of each
          neighbourhood, using the City of Toronto's published boundaries. The map is showing
          the real driving route, which is always longer — there is a lake in the way to the
          south and a valley to the east. Trust the map for time, the list for “roughly how far”.
        </p>
      </div>

    </div>
  </div>
</section>
<?php endif; ?>


<?php $areas = neighbourhoods(); ?>
<?php if ($areas): ?>
<!-- ================= the areas we serve ================= -->
<?php /* The hub for the neighbourhood pages. Without a real section linking
         them from a page that is itself linked from every header and footer,
         those pages are orphans — which is what happened to /join, and an
         orphan is a page Google finds late and trusts less. */ ?>
<section class="section section--cream">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <span class="eyebrow">Coming from further out</span>
      <h2 class="section-title">Getting here from nearby neighbourhoods</h2>
      <p class="section-text">
        There is one Unika Kebab, and it is the one on Jefferson Ave. These are the
        routes in — the streetcar to take, roughly how long the walk is, and where to
        leave the car — written out for the areas most of our regulars come from.
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>"
           alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4 justify-content-center">
      <?php foreach ($areas as $i => $area): ?>
        <div class="col-lg-4 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 3) + 1 ?>">
          <a class="info-card h-100 d-block text-decoration-none" href="<?= e(url($area['slug'])) ?>">
            <div class="info-card__icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></div>
            <div class="info-card__label">Getting here from</div>
            <h3 class="info-card__value h5 mb-2"><?= e($area['name']) ?></h3>
            <?php if (trim((string)$area['hero_text']) !== ''): ?>
              <p class="info-card__note mb-0"><?= e(excerpt($area['hero_text'], 120)) ?></p>
            <?php endif; ?>
            <span class="link-arrow mt-3 d-inline-block">Read the route</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
