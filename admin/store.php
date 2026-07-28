<?php
/**
 * Admin — Store Info
 *
 * One form that owns the single `store` row (id = 1) and the seven
 * `store_hours` rows: name, photo, contact details, address, map
 * coordinates, opening hours, social links and the directions notes
 * that show up on the public Locations page.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

// ---------------------------------------------------------------------
//  Small local helpers
// ---------------------------------------------------------------------

/** "9:30" or "09:30:00" → "09:30:00". Blank or nonsense → null. */
function store_time_or_null($value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^(\d{1,2}):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
        return null;
    }
    $hour = (int)$m[1];
    if ($hour > 23) {
        return null;
    }
    return sprintf('%02d:%s:%s', $hour, $m[2], $m[3] ?? '00');
}

/** "09:30:00" → "09:30" for <input type="time">. */
function store_time_input($value): string
{
    $value = trim((string)$value);
    return $value === '' ? '' : substr($value, 0, 5);
}

/** A real, public http(s) address — anything else is rejected. */
function store_is_web_url(string $value): bool
{
    return (bool)preg_match('#^https?://#i', $value)
        && filter_var($value, FILTER_VALIDATE_URL) !== false;
}

/** The five social columns, in the order they are shown. */
function store_social_fields(): array
{
    return [
        'facebook'  => ['Facebook',  'fa-brands fa-facebook-f', 'https://facebook.com/unikakebab'],
        'instagram' => ['Instagram', 'fa-brands fa-instagram',  'https://instagram.com/unikakebab'],
        'twitter'   => ['X',         'fa-brands fa-x-twitter',  'https://x.com/unikakebab'],
        'tiktok'    => ['TikTok',    'fa-brands fa-tiktok',     'https://tiktok.com/@unikakebab'],
        'youtube'   => ['YouTube',   'fa-brands fa-youtube',    'https://youtube.com/@unikakebab'],
    ];
}

// =====================================================================
//  Save
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (($_POST['action'] ?? '') === 'copy_monday') ? 'copy_monday' : 'save';
    $errors = [];
    $days   = day_names();

    $field = static function (string $key, int $max): string {
        return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
    };
    $orNull = static function (string $value): ?string {
        return $value === '' ? null : $value;
    };

    // ---- store details ----------------------------------------------
    $name     = $field('name', 150);
    $tagline  = $field('tagline', 200);
    $email    = $field('email', 150);
    $phone    = $field('phone', 40);
    $whatsapp = $field('whatsapp', 40);

    // ---- address -----------------------------------------------------
    $line1   = $field('address_line1', 200);
    $line2   = $field('address_line2', 200);
    $city    = $field('city', 100);
    $prov    = $field('province', 100);
    $postal  = $field('postal_code', 20);
    $country = $field('country', 100);

    // ---- directions --------------------------------------------------
    $directions = mb_substr(trim((string)($_POST['directions_note'] ?? '')), 0, 2000);
    $parking    = mb_substr(trim((string)($_POST['parking_note'] ?? '')), 0, 2000);

    if ($name === '') {
        $errors[] = 'The store name cannot be empty.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right — check for a missing @ or domain.';
    }

    // ---- map ---------------------------------------------------------
    $latRaw = trim((string)($_POST['latitude'] ?? ''));
    $lngRaw = trim((string)($_POST['longitude'] ?? ''));
    $lat    = null;
    $lng    = null;

    if ($latRaw !== '') {
        if (!is_numeric($latRaw)) {
            $errors[] = 'Latitude must be a plain number, for example 43.6820000.';
        } elseif ((float)$latRaw < -90 || (float)$latRaw > 90) {
            $errors[] = 'Latitude has to sit between -90 and 90.';
        } else {
            $lat = round((float)$latRaw, 7);
        }
    }
    if ($lngRaw !== '') {
        if (!is_numeric($lngRaw)) {
            $errors[] = 'Longitude must be a plain number, for example -79.3300000.';
        } elseif ((float)$lngRaw < -180 || (float)$lngRaw > 180) {
            $errors[] = 'Longitude has to sit between -180 and 180.';
        } else {
            $lng = round((float)$lngRaw, 7);
        }
    }

    $zoom = (int)($_POST['map_zoom'] ?? 16);
    if ($zoom < 12 || $zoom > 19) {
        $zoom = 16;
    }

    $mapShareUrl = trim((string)($_POST['map_share_url'] ?? ''));
    $mapShareScheme = strtolower((string)parse_url($mapShareUrl, PHP_URL_SCHEME));
    if ($mapShareUrl !== '' && (!filter_var($mapShareUrl, FILTER_VALIDATE_URL) || !in_array($mapShareScheme, ['http', 'https'], true))) {
        $errors[] = 'The Google Maps share link must be a complete http:// or https:// address.';
    }

    // ---- social links -------------------------------------------------
    $socials = [];
    foreach (store_social_fields() as $key => $meta) {
        $value = $field($key, 255);
        if ($value !== '' && !store_is_web_url($value)) {
            $errors[] = 'The ' . $meta[0] . ' link must be a full web address starting with https:// — for example ' . $meta[2] . '.';
        }
        $socials[$key] = $orNull($value);
    }

    // ---- opening hours -------------------------------------------------
    $posted = is_array($_POST['hours'] ?? null) ? $_POST['hours'] : [];
    $hours  = [];
    for ($i = 0; $i < 7; $i++) {
        $row = is_array($posted[$i] ?? null) ? $posted[$i] : [];
        $hours[$i] = [
            'closed' => !empty($row['closed']) ? 1 : 0,
            'open'   => store_time_or_null($row['open'] ?? ''),
            'close'  => store_time_or_null($row['close'] ?? ''),
            'note'   => mb_substr(trim((string)($row['note'] ?? '')), 0, 120),
        ];
    }

    // "Copy Monday to every weekday" — Tuesday through Friday.
    if ($action === 'copy_monday') {
        for ($i = 1; $i <= 4; $i++) {
            $hours[$i]['closed'] = $hours[0]['closed'];
            $hours[$i]['open']   = $hours[0]['open'];
            $hours[$i]['close']  = $hours[0]['close'];
        }
    }

    foreach ($hours as $i => $day) {
        if ($day['closed']) {
            $hours[$i]['open']  = null;   // a closed day never keeps times
            $hours[$i]['close'] = null;
            continue;
        }
        if ($day['open'] === null || $day['close'] === null) {
            $errors[] = $days[$i] . ' needs both an opening and a closing time, or the Closed switch turned on.';
        }
    }

    // ---- photo ---------------------------------------------------------
    $currentPhoto = trim((string)(store()['photo'] ?? ''));
    $uploadError  = null;
    $newPhoto     = save_upload(
        $_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE],
        'store',
        'store',
        $uploadError
    );
    if ($uploadError !== null) {
        $errors[] = $uploadError;
    }

    $photo = $currentPhoto;
    if ($newPhoto !== null) {
        $photo = $newPhoto;
    } elseif (!empty($_POST['remove_photo'])) {
        $photo = '';
    }

    // ---- bail out on any problem ----------------------------------------
    if ($errors) {
        if ($newPhoto !== null) {
            delete_upload($newPhoto);           // do not leave an orphan file
        }
        old_set($_POST);
        flash(implode('<br>', $errors), 'error');
        redirect('admin/store.php');
    }

    // ---- write ------------------------------------------------------------
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!db_one('SELECT id FROM store WHERE id = 1')) {
            db_run('INSERT INTO store (id, name) VALUES (1, ?)', [$name]);
        }

        db_run(
            'UPDATE store SET
                name = ?, tagline = ?, photo = ?,
                email = ?, phone = ?, whatsapp = ?,
                address_line1 = ?, address_line2 = ?, city = ?, province = ?, postal_code = ?, country = ?,
                latitude = ?, longitude = ?, map_zoom = ?,
                facebook = ?, instagram = ?, twitter = ?, tiktok = ?, youtube = ?,
                directions_note = ?, parking_note = ?
             WHERE id = 1',
            [
                $name, $orNull($tagline), $orNull($photo),
                $orNull($email), $orNull($phone), $orNull($whatsapp),
                $orNull($line1), $orNull($line2), $orNull($city), $orNull($prov), $orNull($postal), $orNull($country),
                $lat, $lng, $zoom,
                $socials['facebook'], $socials['instagram'], $socials['twitter'], $socials['tiktok'], $socials['youtube'],
                $orNull($directions), $orNull($parking),
            ]
        );

        setting_set('store_map_share_url', $mapShareUrl);

        db_run('DELETE FROM store_hours WHERE store_id = 1');
        foreach ($hours as $i => $day) {
            db_run(
                'INSERT INTO store_hours (store_id, day_index, is_closed, open_time, close_time, note)
                 VALUES (1, ?, ?, ?, ?, ?)',
                [$i, $day['closed'], $day['open'], $day['close'], $orNull($day['note'])]
            );
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($newPhoto !== null) {
            delete_upload($newPhoto);
        }
        old_set($_POST);
        flash('The store details could not be saved. Please try again in a moment.', 'error');
        redirect('admin/store.php');
    }

    // The old file is only removed once the new details are safely stored.
    if ($currentPhoto !== '' && $photo !== $currentPhoto) {
        delete_upload($currentPhoto);
    }

    flash($action === 'copy_monday'
        ? 'Saved. Monday’s hours now apply to Tuesday through Friday as well.'
        : 'Store info saved.');
    redirect('admin/store.php');
}

// =====================================================================
//  Render
// =====================================================================
$store   = store();
$hoursDb = store_hours();
$days    = day_names();
$socialF = store_social_fields();
$hasOld  = (string)old('__form', '') === '1';

/** A field value: whatever was typed on a failed save, otherwise the saved value. */
$v = static function (string $key, $saved = '') use ($hasOld): string {
    return $hasOld ? (string)old($key, '') : (string)($saved ?? '');
};
$oldHours = is_array(old('hours', [])) ? old('hours', []) : [];

$hourVal = static function (int $i, string $key, $saved = '') use ($hasOld, $oldHours): string {
    if ($hasOld) {
        return isset($oldHours[$i][$key]) ? (string)$oldHours[$i][$key] : '';
    }
    return (string)($saved ?? '');
};
$hourClosed = static function (int $i, $saved) use ($hasOld, $oldHours): bool {
    if ($hasOld) {
        return !empty($oldHours[$i]['closed']);
    }
    return !empty($saved);
};

$photoPath   = trim((string)($store['photo'] ?? ''));
$addressText = store_address();
$latValue    = $v('latitude', $store['latitude'] ?? '');
$lngValue    = $v('longitude', $store['longitude'] ?? '');
$zoomValue   = (int)($v('map_zoom', $store['map_zoom'] ?? 16) ?: 16);
$mapShareValue = $hasOld
    ? trim((string)old('map_share_url', ''))
    : setting('store_map_share_url');

$mapKey   = trim(setting('google_maps_api_key'));
$mapQuery = ($latValue !== '' && $lngValue !== '')
    ? $latValue . ',' . $lngValue
    : $addressText;
$mapSrc = ($mapKey !== '' && $mapQuery !== '')
    ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapKey)
        . '&q=' . rawurlencode($mapQuery)
        . '&zoom=' . ($zoomValue >= 12 && $zoomValue <= 19 ? $zoomValue : 16)
    : '';

$zoomLabels = [
    12 => '12 — whole neighbourhood',
    13 => '13 — a few blocks around',
    14 => '14 — the local area',
    15 => '15 — nearby streets',
    16 => '16 — street level (recommended)',
    17 => '17 — the block itself',
    18 => '18 — the storefront',
    19 => '19 — as close as it goes',
];

$title     = 'Store Info';
$subtitle  = 'Address, contact details, opening hours and social links';
$activeNav = 'store';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$store): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>There is no store record yet. Fill in the details below and press <strong>Save store info</strong> — the record is created on the first save.</span>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(admin_url('store.php')) ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="__form" value="1">
  <!-- Pressing Enter in a text field saves rather than triggering the copy button. -->
  <button type="submit" name="action" value="save" hidden aria-hidden="true" tabindex="-1">Save</button>

  <!-- ============================================================
       1. Store details
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-store"></i> Store details</h2>
        <p class="a-card__sub">The name, photo and the ways a customer can reach the grill.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="f_name">Store name <span class="req">*</span></label>
          <input class="a-input" type="text" id="f_name" name="name" maxlength="150" required
                 value="<?= e($v('name', $store['name'] ?? 'Unika Kebab')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_tagline">Tagline</label>
          <input class="a-input" type="text" id="f_tagline" name="tagline" maxlength="200"
                 placeholder="Charcoal Grill"
                 value="<?= e($v('tagline', $store['tagline'] ?? '')) ?>">
          <p class="a-field__hint">Two or three words that sit under the name in the footer.</p>
        </div>
      </div>

      <div class="a-field">
        <span class="a-field__label">Store photo</span>
        <div class="a-media">
          <img class="a-media__preview" id="storePhotoPreview" alt=""
               src="<?= e(img($photoPath, 'assets/img/placeholder.svg')) ?>">
          <div class="a-media__body">
            <input type="file" name="photo" id="f_photo" accept="image/*" data-preview="#storePhotoPreview">
            <p class="a-field__hint">
              A shot of the shopfront or the grill. JPG, PNG or WebP up to 8 MB — it is resized and
              converted to WebP automatically.
            </p>
            <?php if ($photoPath !== ''): ?>
              <label class="a-check">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remove this photo when I save</span>
              </label>
            <?php else: ?>
              <p class="a-small a-muted a-mb0">No photo uploaded yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="a-grid-3">
        <div class="a-field">
          <label class="a-field__label" for="f_email"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Email</label>
          <input class="a-input" type="email" id="f_email" name="email" maxlength="150"
                 placeholder="info@unikakebab.ca"
                 value="<?= e($v('email', $store['email'] ?? '')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_phone"><i class="fa-solid fa-phone" aria-hidden="true"></i> Phone</label>
          <input class="a-input" type="tel" id="f_phone" name="phone" maxlength="40"
                 placeholder="(416) 555-0184"
                 value="<?= e($v('phone', $store['phone'] ?? '')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_whatsapp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</label>
          <input class="a-input" type="tel" id="f_whatsapp" name="whatsapp" maxlength="40"
                 placeholder="(416) 555-0184"
                 value="<?= e($v('whatsapp', $store['whatsapp'] ?? '')) ?>">
          <p class="a-field__hint">Leave blank to hide the WhatsApp button.</p>
        </div>
      </div>

    </div>
  </div>

  <!-- ============================================================
       2. Address
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-location-dot"></i> Address</h2>
        <p class="a-card__sub">Used in the footer, on the Contact page and in the map search.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-field">
        <label class="a-field__label" for="f_line1">Street address</label>
        <input class="a-input" type="text" id="f_line1" name="address_line1" maxlength="200"
               placeholder="1245 Danforth Avenue"
               value="<?= e($v('address_line1', $store['address_line1'] ?? '')) ?>">
      </div>

      <div class="a-field">
        <label class="a-field__label" for="f_line2">Unit / suite <span class="a-muted">(optional)</span></label>
        <input class="a-input" type="text" id="f_line2" name="address_line2" maxlength="200"
               placeholder="Unit 3"
               value="<?= e($v('address_line2', $store['address_line2'] ?? '')) ?>">
      </div>

      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="f_city">City</label>
          <input class="a-input" type="text" id="f_city" name="city" maxlength="100" placeholder="Toronto"
                 value="<?= e($v('city', $store['city'] ?? '')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_prov">Province / state</label>
          <input class="a-input" type="text" id="f_prov" name="province" maxlength="100" placeholder="ON"
                 value="<?= e($v('province', $store['province'] ?? '')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_postal">Postal code</label>
          <input class="a-input" type="text" id="f_postal" name="postal_code" maxlength="20" placeholder="M4J 1M5"
                 value="<?= e($v('postal_code', $store['postal_code'] ?? '')) ?>">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_country">Country</label>
          <input class="a-input" type="text" id="f_country" name="country" maxlength="100" placeholder="Canada"
                 value="<?= e($v('country', $store['country'] ?? '')) ?>">
        </div>
      </div>

      <div class="a-field a-mb0">
        <span class="a-field__label">How the saved address reads on the website</span>
        <input class="a-input" type="text" readonly aria-readonly="true"
               value="<?= e($addressText) ?>"
               placeholder="Fill in the fields above and save to build the address line.">
        <p class="a-field__hint">
          <?php if ($addressText !== ''): ?>
            This is the exact line shown in the footer and on the Locations page. It refreshes when you save.
            <a href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addressText)) ?>" target="_blank" rel="noopener">Check it on Google Maps</a>.
          <?php else: ?>
            Nothing to show yet — the address line is built from the fields above.
          <?php endif; ?>
        </p>
      </div>

    </div>
  </div>

  <!-- ============================================================
       3. Map
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-map-location-dot"></i> Map</h2>
        <p class="a-card__sub">Where the pin drops on the Locations page.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-grid-3">
        <div class="a-field">
          <label class="a-field__label" for="f_lat">Latitude</label>
          <input class="a-input" type="text" inputmode="decimal" id="f_lat" name="latitude"
                 placeholder="43.6820000" value="<?= e($latValue) ?>">
          <p class="a-field__hint">Between -90 and 90.</p>
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_lng">Longitude</label>
          <input class="a-input" type="text" inputmode="decimal" id="f_lng" name="longitude"
                 placeholder="-79.3300000" value="<?= e($lngValue) ?>">
          <p class="a-field__hint">Between -180 and 180.</p>
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_zoom">Zoom level</label>
          <select class="a-select" id="f_zoom" name="map_zoom">
            <?php foreach ($zoomLabels as $z => $label): ?>
              <option value="<?= (int)$z ?>" <?= $zoomValue === $z ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <p class="a-field__hint">
        Leave both coordinates blank to let Google find the shop from the address instead.
        Right-click the shop in Google Maps and choose the numbers at the top of the menu to copy them.
      </p>

      <div class="a-field">
        <label class="a-field__label" for="f_map_share">Google Maps share link <span class="a-muted">(optional)</span></label>
        <input class="a-input" type="url" id="f_map_share" name="map_share_url" maxlength="500"
               placeholder="https://maps.app.goo.gl/..." value="<?= e($mapShareValue) ?>">
        <p class="a-field__hint">Used by the footer, Contact and Locations page buttons so visitors open your exact map pin.</p>
      </div>

      <?php if ($mapKey === ''): ?>
        <div class="a-alert a-alert--info a-mt">
          <i class="fa-solid fa-circle-info"></i>
          <span>
            No Google Maps key is saved yet, so there is no preview here and the Locations page falls back
            to a plain address card. Add one under
            <a href="<?= e(admin_url('settings.php')) ?>">Settings → Integrations</a>.
          </span>
        </div>
      <?php elseif ($mapSrc === ''): ?>
        <div class="a-alert a-alert--info a-mt">
          <i class="fa-solid fa-circle-info"></i>
          <span>A Google Maps key is saved. Add coordinates or an address above and save to see the preview.</span>
        </div>
      <?php else: ?>
        <p class="a-small a-muted a-mt a-mb0">
          <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
          Live preview of the saved location — the key lives in
          <a href="<?= e(admin_url('settings.php')) ?>">Settings</a>.
        </p>
        <iframe class="a-mt" title="Map preview of the saved store location"
                src="<?= e($mapSrc) ?>"
                width="100%" height="280" frameborder="0" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
      <?php endif; ?>

    </div>
  </div>

  <!-- ============================================================
       4. Opening hours
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-clock"></i> Opening hours</h2>
        <p class="a-card__sub">Drives the “Open now” chip in the header and the hours list on every page.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="row g-2 d-none d-md-flex">
        <div class="col-md-2"><span class="a-field__label">Day</span></div>
        <div class="col-md-2"><span class="a-field__label">Closed all day</span></div>
        <div class="col-md-2"><span class="a-field__label">Opens</span></div>
        <div class="col-md-2"><span class="a-field__label">Closes</span></div>
        <div class="col-md-4"><span class="a-field__label">Note (optional)</span></div>
      </div>

      <?php foreach ($days as $i => $dayName): ?>
        <?php
          $row    = $hoursDb[$i] ?? [];
          $closed = $hourClosed($i, $row['is_closed'] ?? 0);
          $open   = $hourVal($i, 'open',  store_time_input($row['open_time'] ?? ''));
          $close  = $hourVal($i, 'close', store_time_input($row['close_time'] ?? ''));
          $note   = $hourVal($i, 'note',  $row['note'] ?? '');
        ?>
        <div class="row g-2 align-items-center a-field<?= $i === 6 ? ' a-mb0' : '' ?>">
          <div class="col-12 col-md-2">
            <strong><?= e($dayName) ?></strong>
          </div>
          <div class="col-12 col-md-2">
            <label class="a-switch">
              <input type="checkbox" name="hours[<?= (int)$i ?>][closed]" value="1" <?= $closed ? 'checked' : '' ?>>
              <span class="a-switch__track"></span>
              <span class="d-md-none">Closed all day</span>
              <span class="d-none d-md-inline visually-hidden"><?= e($dayName) ?> closed</span>
            </label>
          </div>
          <div class="col-6 col-md-2">
            <label class="a-field__label d-md-none" for="h<?= (int)$i ?>_open">Opens</label>
            <input class="a-input" type="time" id="h<?= (int)$i ?>_open"
                   name="hours[<?= (int)$i ?>][open]" value="<?= e($open) ?>"
                   aria-label="<?= e($dayName) ?> opening time">
          </div>
          <div class="col-6 col-md-2">
            <label class="a-field__label d-md-none" for="h<?= (int)$i ?>_close">Closes</label>
            <input class="a-input" type="time" id="h<?= (int)$i ?>_close"
                   name="hours[<?= (int)$i ?>][close]" value="<?= e($close) ?>"
                   aria-label="<?= e($dayName) ?> closing time">
          </div>
          <div class="col-12 col-md-4">
            <label class="a-field__label d-md-none" for="h<?= (int)$i ?>_note">Note</label>
            <input class="a-input" type="text" id="h<?= (int)$i ?>_note" maxlength="120"
                   name="hours[<?= (int)$i ?>][note]" value="<?= e($note) ?>"
                   placeholder="<?= $i === 4 ? 'Grill runs late on Fridays' : 'Kitchen closes 20 min early' ?>"
                   aria-label="<?= e($dayName) ?> note">
          </div>
        </div>
      <?php endforeach; ?>

      <p class="a-field__hint">
        Times are 24-hour on the way in and shown as “11:00 am – 10:00 pm” on the website.
        For a midnight close use 23:59 — it prints as 12:00 am.
      </p>

    </div>
    <div class="a-card__foot">
      <button class="a-btn a-btn--ghost a-btn--sm" type="submit" name="action" value="copy_monday">
        <i class="fa-solid fa-copy"></i> Copy Monday to every weekday
      </button>
      <span class="a-small a-muted">Saves the page and applies Monday’s times to Tuesday through Friday.</span>
    </div>
  </div>

  <!-- ============================================================
       5. Social media
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-share-nodes"></i> Social media</h2>
        <p class="a-card__sub">Only the links you fill in appear in the footer.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-grid-2">
        <?php foreach ($socialF as $key => $meta): ?>
          <div class="a-field">
            <label class="a-field__label" for="f_<?= e($key) ?>">
              <i class="<?= e($meta[1]) ?>" aria-hidden="true"></i> <?= e($meta[0]) ?>
            </label>
            <input class="a-input" type="url" id="f_<?= e($key) ?>" name="<?= e($key) ?>" maxlength="255"
                   placeholder="<?= e($meta[2]) ?>"
                   value="<?= e($v($key, $store[$key] ?? '')) ?>">
          </div>
        <?php endforeach; ?>
      </div>

      <p class="a-field__hint a-mb0">
        Paste the full address including <code>https://</code>. Anything shorter is rejected so the
        footer never links to a dead page.
      </p>

    </div>
  </div>

  <!-- ============================================================
       6. Directions & parking
       ============================================================ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-diamond-turn-right"></i> Directions &amp; parking</h2>
        <p class="a-card__sub">Two short paragraphs printed beside the map on the Locations page.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="f_directions">Getting here</label>
          <textarea class="a-textarea" id="f_directions" name="directions_note" rows="4"
                    placeholder="Two minutes east of Greenwood station, south side of Danforth — look for the gold skewer sign."><?= e($v('directions_note', $store['directions_note'] ?? '')) ?></textarea>
          <p class="a-field__hint">Landmarks beat street names. Mention the nearest transit stop.</p>
        </div>
        <div class="a-field">
          <label class="a-field__label" for="f_parking">Parking</label>
          <textarea class="a-textarea" id="f_parking" name="parking_note" rows="4"
                    placeholder="Free green-P parking on Monarch Park Avenue, plus street parking after 6pm."><?= e($v('parking_note', $store['parking_note'] ?? '')) ?></textarea>
          <p class="a-field__hint">Where to leave the car, and after what time it is free.</p>
        </div>
      </div>

      <div class="a-sticky-save">
        <button class="a-btn" type="submit" name="action" value="save">
          <i class="fa-solid fa-floppy-disk"></i> Save store info
        </button>
        <a class="a-btn a-btn--ghost" href="<?= e(url('locations.php')) ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> View locations page
        </a>
        <span class="a-small a-muted a-nowrap">Everything on this page saves together.</span>
      </div>

    </div>
  </div>

</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
