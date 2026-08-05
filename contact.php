<?php
/**
 * Contact Us — message form, store details, map and a catering call to action.
 */
require __DIR__ . '/includes/app.php';

require_methods(['GET', 'POST']);   // the form posts back to this page

$backTo = 'contact.php#contact-form';

// =====================================================================
//  POST — save the message, optionally forward it by SMS, then redirect
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_require();

    // Bots fill in every field they can find, including the hidden one. A
    // person cannot reach it, so anything in it was put there by a script.
    // The submission is binned — but never with a thank-you, because a real
    // sender must never be told a message was sent that nobody will read.
    if (trim((string)($_POST['ct_hp'] ?? '')) !== '') {
        old_set($_POST);
        $_SESSION['contact_errors'] = ['We could not process that message. Please try again.'];
        redirect($backTo);
    }

    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $errors = [];

    if ($name === '') {
        $errors[] = 'Please tell us your name so we know who we are replying to.';
    } elseif (mb_strlen($name) > 120) {
        $errors[] = 'That name is longer than 120 characters — please shorten it.';
    }

    if ($email === '') {
        $errors[] = 'Please add an email address so we can write back.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $errors[] = 'That email address does not look right — please check it once more.';
    }

    if ($phone !== '' && mb_strlen($phone) > 40) {
        $errors[] = 'That phone number is too long — 40 characters is plenty.';
    }

    if ($subject !== '' && mb_strlen($subject) > 180) {
        $errors[] = 'Please keep the subject under 180 characters.';
    }

    if ($message === '') {
        $errors[] = 'Please write us a message — even a line or two helps.';
    } elseif (mb_strlen($message) < 10) {
        $errors[] = 'That message is very short. Give us a little more to go on (at least 10 characters).';
    } elseif (mb_strlen($message) > 4000) {
        $errors[] = 'That message is over 4,000 characters. Please trim it a little.';
    }

    if ($errors) {
        old_set($_POST);
        $_SESSION['contact_errors'] = $errors;
        redirect($backTo);
    }

    if (recaptcha_is_enabled()) {
        [$recaptchaOk, $recaptchaMessage] = recaptcha_verify(
            (string)($_POST['g-recaptcha-response'] ?? ''),
            'contact_message'
        );
        if (!$recaptchaOk) {
            old_set($_POST);
            $_SESSION['contact_errors'] = [$recaptchaMessage];
            redirect($backTo);
        }
    }

    // --- 1. The message is saved first. Nothing below may lose it. -----
    db_run(
        'INSERT INTO contact_messages (name, email, phone, subject, message, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)',
        [
            $name,
            $email,
            ($phone   !== '' ? $phone   : null),
            ($subject !== '' ? $subject : null),
            $message,
            client_ip(),
        ]
    );
    $messageId = (int)db()->lastInsertId();

    // --- 2. Optional Twilio forward. Never fatal, never blocking. -----
    if (setting('sms_enabled', '0') === '1') {
        $smsBody = contact_sms_body($name, $email, $phone, $subject, $message);

        try {
            $sms = send_sms(trim(setting('twilio_to_number')), $smsBody);
        } catch (Throwable $e) {
            $sms = ['status' => 'failed', 'detail' => 'SMS error: ' . $e->getMessage()];
        }

        try {
            db_run(
                'UPDATE contact_messages SET sms_status = ?, sms_detail = ? WHERE id = ?',
                [
                    mb_substr((string)($sms['status'] ?? 'failed'), 0, 20),
                    mb_substr((string)($sms['detail'] ?? ''), 0, 255),
                    $messageId,
                ]
            );
        } catch (Throwable $e) {
            // The message row is already safe — the SMS log is a nice-to-have.
        }
    }

    flash(
        'Thanks ' . $name . ' — your message just landed with the kitchen. '
        . 'We answer most notes the same day we are open.',
        'success'
    );
    redirect($backTo);
}

// =====================================================================
//  GET — render the page
// =====================================================================
$contactErrors = $_SESSION['contact_errors'] ?? [];
unset($_SESSION['contact_errors']);

$s           = store();
$socials     = store_socials();
$hoursGroups = store_hours_days();
$dayShort    = day_names(true);
$todayIdx    = (int)date('N') - 1;
[$isOpen, $openMsg] = store_open_status();

$addressText = store_address();
$addressName = trim((string)($s['name'] ?? ''));

// Google Maps: a place search string and, when a key is stored, an embed URL.
$searchQuery = trim($addressName . ' ' . store_address(' '));
if ($searchQuery === '') {
    $searchQuery = $addressText;
}
$searchLink = store_map_share_url(
    $searchQuery !== ''
        ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($searchQuery)
        : ''
);

$lat     = trim((string)($s['latitude']  ?? ''));
$lng     = trim((string)($s['longitude'] ?? ''));
$mapsKey = trim(setting('google_maps_api_key'));

$embedQuery = '';
if ($lat !== '' && $lng !== '' && (float)$lat !== 0.0 && (float)$lng !== 0.0) {
    $embedQuery = $lat . ',' . $lng;
} elseif ($addressText !== '') {
    $embedQuery = $searchQuery;
}

$mapZoom = (int)($s['map_zoom'] ?? 16);
if ($mapZoom < 1 || $mapZoom > 21) {
    $mapZoom = 16;
}

$embedUrl = ($mapsKey !== '' && $embedQuery !== '')
    ? 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode($mapsKey)
      . '&q=' . rawurlencode($embedQuery) . '&zoom=' . $mapZoom
    : '';

$hasDetails = ($addressText !== '' || !empty($s['phone']) || !empty($s['email']));
$recaptchaEnabled = recaptcha_is_enabled();
$recaptchaSiteKey = setting('google_recaptcha_site_key');

$mapTitle = 'Map showing ' . ($addressName !== '' ? $addressName : 'our restaurant')
          . ($addressText !== '' ? ' at ' . $addressText : '');

$pageKey = 'contact';
$breadcrumbs = [
    ['name' => 'Home', 'url' => 'index.php'],
    ['name' => 'Contact Us', 'url' => 'contact.php'],
];
$hero = [
    'eyebrow' => setting('contact_hero_eyebrow', 'Say hello'),
    'title'   => setting('contact_hero_title', 'Contact us'),
    'text'    => setting('contact_hero_text'),
    'image'    => 'assets/img/hero-contact.jpg',
    'crumb'    => 'Contact',
    'ornament' => false,      // it opens the content section below instead
    'compact'  => true,       // get people to the form quickly
    'flush'    => true,       // so that ornament sits evenly between the two
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero.php';
?>

<?php if ($recaptchaEnabled): ?>
  <script src="https://www.google.com/recaptcha/api.js?render=<?= e(rawurlencode($recaptchaSiteKey)) ?>" async defer></script>
<?php endif; ?>

<!-- ================= form + store details ================= -->
<section class="section section--cream section--tight-top">
  <div class="container">

    <img class="ornament ornament--top" src="<?= e(url('assets/img/ornament.png')) ?>"
         alt="" aria-hidden="true" width="720" height="124">

    <div class="row g-5">

      <!-- ---------- the form ---------- -->
      <div class="col-lg-7 d-flex flex-column gap-4">
        <div class="form-card" id="contact-form" data-reveal>

          <span class="eyebrow eyebrow--start">Send a note</span>
          <h2 class="section-title"><?= e(setting('contact_intro_title', 'We read every message')) ?></h2>
          <p class="section-text section-text--start mb-4">
            <?= e(setting('contact_intro_text', 'Fill in the form and it lands with the kitchen straight away. For anything urgent, calling is quickest.')) ?>
          </p>

          <?php require __DIR__ . '/includes/flash.php'; ?>

          <?php foreach ($contactErrors as $error): ?>
            <div class="alert-note alert-note--error" role="alert">
              <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
              <span><?= e($error) ?></span>
            </div>
          <?php endforeach; ?>

          <form action="<?= e(url('contact.php')) ?>" method="post" id="contact-message-form" data-guard novalidate>
            <?= csrf_field() ?>

            <?php /* Bot trap. Readonly and off-screen, so no browser, password
                     manager or autofill can reach it — only a script can, which
                     is the whole point. The inline styles keep it hidden even if
                     the stylesheet has not arrived yet. */ ?>
            <div class="hp-field" aria-hidden="true"
                 style="position:absolute;left:-5000px;width:1px;height:0;overflow:hidden">
              <label for="ct-hp">Leave this field empty</label>
              <input type="text" id="ct-hp" name="ct_hp" value=""
                     tabindex="-1" autocomplete="off" readonly>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="field">
                  <label class="field__label" for="ct-name">Your name <span class="req">*</span></label>
                  <input class="field__input" type="text" id="ct-name" name="name"
                         value="<?= e(old('name')) ?>" maxlength="120"
                         placeholder="John Doe" autocomplete="name" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="field">
                  <label class="field__label" for="ct-email">Email <span class="req">*</span></label>
                  <input class="field__input" type="email" id="ct-email" name="email"
                         value="<?= e(old('email')) ?>" maxlength="150"
                         placeholder="you@email.com" autocomplete="email" required>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="field">
                  <label class="field__label" for="ct-phone">Phone</label>
                  <input class="field__input" type="tel" id="ct-phone" name="phone"
                         value="<?= e(old('phone')) ?>" maxlength="40"
                         placeholder="(416) 555-0184" autocomplete="tel">
                  <p class="field__hint">Optional — quickest way to sort a big order.</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="field">
                  <label class="field__label" for="ct-subject">Subject</label>
                  <input class="field__input" type="text" id="ct-subject" name="subject"
                         value="<?= e(old('subject')) ?>" maxlength="180"
                         placeholder="Catering for 40 people">
                  <p class="field__hint">Optional — catering, a large order, feedback…</p>
                </div>
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="ct-message">Your message <span class="req">*</span></label>
              <textarea class="field__textarea" id="ct-message" name="message"
                        maxlength="4000" rows="7"
                        placeholder="Tell us the date, how many people and what you are craving — kofte platters, chicken shish, a tray of baklava…"
                        required><?= e(old('message')) ?></textarea>
              <p class="field__hint">At least 10 characters. We reply to every message that is not a robot.</p>
            </div>

            <?php if ($recaptchaEnabled): ?>
              <div class="recaptcha-wrap">
                <input type="hidden" id="contact-recaptcha-token" name="g-recaptcha-response"
                       data-recaptcha-site-key="<?= e($recaptchaSiteKey) ?>">
                <p class="recaptcha-v3-note" id="contact-recaptcha-status" role="status">
                  This site is protected by reCAPTCHA and the Google
                  <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
                  and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">Terms of Service</a> apply.
                </p>
              </div>
            <?php endif; ?>

            <button class="btn-u btn-gold btn-u--lg" type="submit">
              <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send message
            </button>
          </form>

          <?php if ($recaptchaEnabled): ?>
            <script>
            (function () {
              var form = document.getElementById('contact-message-form');
              var token = document.getElementById('contact-recaptcha-token');
              var status = document.getElementById('contact-recaptcha-status');
              if (!form || !token) return;

              form.addEventListener('submit', function (event) {
                if (form.dataset.recaptchaSubmitting === '1') return;
                event.preventDefault();
                event.stopImmediatePropagation();

                if (!window.grecaptcha) {
                  if (status) status.textContent = 'Security verification is still loading. Please try again in a moment.';
                  return;
                }

                form.dataset.recaptchaSubmitting = '1';
                if (status) status.textContent = 'Verifying security…';
                window.grecaptcha.ready(function () {
                  window.grecaptcha.execute(token.dataset.recaptchaSiteKey, { action: 'contact_message' }).then(function (value) {
                    token.value = value;
                    form.submit();
                  }).catch(function () {
                    form.dataset.recaptchaSubmitting = '0';
                    if (status) status.textContent = 'Security verification could not start. Please try again.';
                  });
                });
              });
            }());
            </script>
          <?php endif; ?>
        </div>

        <?php if ($addressText !== ''): ?>
          <div class="info-card" data-reveal data-reveal-delay="1">
            <div class="info-card__icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></div>
            <div class="info-card__label">Come and see us</div>
            <a class="info-card__value info-card__address-link" href="<?= e($searchLink) ?>"
               target="_blank" rel="noopener" aria-label="Open our address in Google Maps">
              <?= e(store_address(', ')) ?>
            </a>
            <?php if ($searchLink !== ''): ?>
              <p class="info-card__note">
                <a class="link-arrow" href="<?= e($searchLink) ?>" target="_blank" rel="noopener">
                  Open in Google Maps <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ---------- store details ---------- -->
      <div class="col-lg-5">
        <div class="d-flex flex-column gap-4">

          <?php if (!empty($s['phone'])): ?>
            <div class="info-card" data-reveal data-reveal-delay="1">
              <div class="info-card__icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></div>
              <div class="info-card__label">Call the grill</div>
              <p class="info-card__value">
                <a href="<?= e(phone_href($s['phone'])) ?>"><?= e($s['phone']) ?></a>
              </p>
              <p class="info-card__note">Fastest for same-day orders and anything with a deadline.</p>
            </div>
          <?php endif; ?>

          <?php if (!empty($s['email'])): ?>
            <div class="info-card" data-reveal data-reveal-delay="2">
              <div class="info-card__icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></div>
              <div class="info-card__label">Email us</div>
              <p class="info-card__value">
                <a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a>
              </p>
              <p class="info-card__note">Best for catering quotes, invoices and menus in writing.</p>
            </div>
          <?php endif; ?>

          <?php if (!$hasDetails): ?>
            <div class="info-card" data-reveal>
              <div class="info-card__icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></div>
              <div class="info-card__label">Contact details</div>
              <p class="info-card__value">We are updating our address and phone number.</p>
              <p class="info-card__note">Use the form on the left in the meantime — it reaches the kitchen either way.</p>
            </div>
          <?php endif; ?>

          <div class="info-card" data-reveal data-reveal-delay="4">
            <div class="info-card__icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></div>
            <div class="info-card__label">Opening hours</div>
            <ul class="hours-list">
              <?php foreach ($hoursGroups as $group): ?>
                <li class="<?= $group['is_today'] ? 'is-today' : '' ?><?= $group['closed'] ? ' is-closed-day' : '' ?>">
                  <span class="hours-list__day">
                    <?= e($group['day']) ?>
                    <?php if ($group['is_today']): ?>
                      <span class="hours-list__dot" aria-hidden="true"></span>
                      <span class="visually-hidden">(today)</span>
                    <?php endif; ?>
                  </span>
                  <span class="hours-list__time <?= $group['closed'] ? 'is-closed' : '' ?>"><?= e($group['hours']) ?></span>
                </li>
              <?php endforeach; ?>
              <?php if (!$hoursGroups): ?>
                <li><span class="hours-list__day">Hours</span><span class="hours-list__time">Call to check</span></li>
              <?php endif; ?>
            </ul>
            <p class="info-card__note">
              <span class="status-chip <?= $isOpen ? 'status-chip--open' : 'status-chip--closed' ?>">
                <span class="status-chip__dot" aria-hidden="true"></span><?= e($openMsg) ?>
              </span>
            </p>
          </div>

          <?php if ($socials): ?>
            <div class="info-card" data-reveal data-reveal-delay="4">
              <div class="info-card__icon"><i class="fa-solid fa-fire-flame-curved" aria-hidden="true"></i></div>
              <div class="info-card__label">Follow the smoke</div>
              <p class="info-card__note mb-3">Tonight's skewers, weekend specials and the odd tray of baklava.</p>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($socials as $social): ?>
                  <a class="btn-u btn-outline-char btn-u--sm"
                     href="<?= e($social['url']) ?>" target="_blank" rel="noopener">
                    <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i> <?= e($social['label']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- ================= map ================= -->
<section class="section section--cream-2">
  <div class="container">
    <div class="section-head text-center">
      <span class="eyebrow">On the map</span>
      <h2 class="section-title">Where the coals are</h2>
      <p class="section-text">
        <?php if ($addressText !== ''): ?>
          <a class="map-address-link" href="<?= e($searchLink) ?>" target="_blank" rel="noopener">
            <?= e($addressText) ?>
          </a>
        <?php else: ?>
          Our address is on its way — call us and we will point you to the door.
        <?php endif; ?>
      </p>
      <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>"
           alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="map-frame" data-reveal>
      <?php if ($embedUrl !== ''): ?>
        <iframe src="<?= e($embedUrl) ?>"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                title="<?= e($mapTitle) ?>"
                allowfullscreen></iframe>
      <?php else: ?>
        <div class="map-fallback">
          <div>
            <i class="fa-solid fa-map-location-dot fa-2x text-gold" aria-hidden="true"></i>
            <p class="mt-3 mb-3">
              <?php if ($addressText !== ''): ?>
                The live map is switched off, but Google Maps will take you straight to the door.
              <?php else: ?>
                We are adding our map shortly. Give us a call and we will talk you in.
              <?php endif; ?>
            </p>
            <?php if ($searchLink !== ''): ?>
              <a class="btn-u btn-outline-char" href="<?= e($searchLink) ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-diamond-turn-right" aria-hidden="true"></i> Get directions
              </a>
            <?php elseif (!empty($s['phone'])): ?>
              <a class="btn-u btn-outline-char" href="<?= e(phone_href($s['phone'])) ?>">
                <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($s['phone']) ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ================= catering CTA ================= -->
<section class="cta-band">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <span class="eyebrow eyebrow--start text-gold">Catering &amp; large orders</span>
        <h2 class="cta-band__title">Feeding a crowd? We have skewers for that</h2>
        <p class="cta-band__text">
          Office lunches, birthdays, football nights — trays of kofte and shish tavuk,
          warm pita, rice, salads and sauces, packed hot and ready to travel.
          Tell us the headcount and the time; we will handle the fire.
        </p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <?php if (!empty($s['phone'])): ?>
          <a class="btn-u btn-gold btn-u--lg" href="<?= e(phone_href($s['phone'])) ?>">
            <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($s['phone']) ?>
          </a>
        <?php else: ?>
          <a class="btn-u btn-gold btn-u--lg" href="#contact-form">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Ask about catering
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
