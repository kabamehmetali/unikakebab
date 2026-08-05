<?php
/**
 * Public site footer — store details straight from the admin panel.
 */
$s         = store();
$socials   = store_socials();
$hours     = store_hours_days();
$siteName  = setting('site_name', 'Unika Kebab');
$footerLogo = setting('footer_logo', setting('logo', 'assets/img/logo.png'));

$mapsQuery = urlencode(trim(($s['name'] ?? '') . ' ' . store_address(' ')));
$mapsUrl   = store_map_share_url('https://www.google.com/maps/search/?api=1&query=' . $mapsQuery);
?>
</main>

<footer class="u-foot">
  <div class="container">
    <div class="row g-4 g-lg-5">

      <!-- brand -->
      <div class="col-lg-4 col-md-6">
        <img class="u-foot__logo"
             src="<?= e(img($footerLogo, setting('logo', 'assets/img/logo.png'))) ?>"
             alt="<?= e(alt_text(setting('footer_logo_alt', setting('logo_alt')), $siteName)) ?>"
             loading="lazy" decoding="async">
        <p class="u-foot__about"><?= e(setting('footer_about')) ?></p>

        <?php if ($socials): ?>
          <div class="social-row">
            <?php foreach ($socials as $social): ?>
              <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener"
                 aria-label="<?= e($siteName . ' on ' . $social['label']) ?>" title="<?= e($social['label']) ?>">
                <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- links -->
      <div class="col-lg-2 col-md-6 col-6">
        <h2 class="u-foot__title">Explore</h2>
        <ul class="u-foot__links">
          <li><a href="<?= e(url('index.php')) ?>">Home</a></li>
          <li><a href="<?= e(url('menu.php')) ?>">Menu</a></li>
          <li><a href="<?= e(url('contact.php')) ?>">Contact Us</a></li>
          <li><a href="<?= e(url('locations.php')) ?>">Locations</a></li>
          <li><a href="<?= e(url('order.php')) ?>">Order Online</a></li>
        </ul>
      </div>

      <!-- contact -->
      <div class="col-lg-3 col-md-6 col-6">
        <h2 class="u-foot__title">Find us</h2>
        <ul class="u-foot__contact">
          <?php if (store_address() !== ''): ?>
            <li>
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              <span>
                <a class="u-foot__address" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener">
                  <strong><?= e($s['address_line1'] ?? '') ?></strong>
                  <?= e(trim(($s['city'] ?? '') . ', ' . ($s['province'] ?? '') . ' ' . ($s['postal_code'] ?? ''), ' ,')) ?>
                  <br>
                  Get directions
                </a>
              </span>
            </li>
          <?php endif; ?>

          <?php if (!empty($s['phone'])): ?>
            <li>
              <i class="fa-solid fa-phone" aria-hidden="true"></i>
              <span><strong>Call us</strong><a href="<?= e(phone_href($s['phone'])) ?>"><?= e($s['phone']) ?></a></span>
            </li>
          <?php endif; ?>

          <?php if (!empty($s['email'])): ?>
            <li>
              <i class="fa-solid fa-envelope" aria-hidden="true"></i>
              <span><strong>Email</strong><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></span>
            </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- hours -->
      <div class="col-lg-3 col-md-6">
        <h2 class="u-foot__title">Opening hours</h2>
        <ul class="hours-list">
          <?php foreach ($hours as $row): ?>
            <li class="<?= $row['is_today'] ? 'is-today' : '' ?><?= $row['closed'] ? ' is-closed-day' : '' ?>">
              <span class="hours-list__day">
                <?= e($row['short']) ?>
                <?php if ($row['is_today']): ?>
                  <span class="hours-list__dot" aria-hidden="true"></span>
                  <span class="visually-hidden">(today)</span>
                <?php endif; ?>
              </span>
              <span class="hours-list__time <?= $row['closed'] ? 'is-closed' : '' ?>"><?= e($row['hours']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="u-foot__bar">
      <div class="row align-items-center g-2">
        <div class="col-md-7">
          &copy; <span data-year><?= date('Y') ?></span> <?= e(setting('footer_copyright', $siteName . '. All rights reserved.')) ?>
        </div>
        <div class="col-md-5 text-md-end">
          <span class="u-foot__credit">
            Designed by
            <a href="https://creativetorontonian.ca" target="_blank" rel="noopener noreferrer">
              Creative Torontonian
            </a>
          </span>
        </div>
      </div>
    </div>
  </div>
</footer>

<button class="to-top" type="button" aria-label="Back to top">
  <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('assets/js/main.js')) ?>"></script>
<?php if (!empty($pageJs) && file_exists(APP_ROOT . '/assets/js/' . $pageJs)): ?>
<script src="<?= e(asset('assets/js/' . $pageJs)) ?>"></script>
<?php endif; ?>
</body>
</html>
