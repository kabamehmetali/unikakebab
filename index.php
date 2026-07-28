<?php
/**
 * Unika Kebab — home page.
 * Renders its own richer hero (does not use includes/hero.php).
 */
require __DIR__ . '/includes/app.php';

// ---------------------------------------------------------------------
// Hero content
// ---------------------------------------------------------------------
$heroImage  = img(setting('hero_image', 'assets/img/hero-home.jpg'), 'assets/img/hero-home.jpg');
$heroLogo   = setting('hero_show_logo', '1') === '1';
$heroTitle  = setting('hero_title', 'Skewers with a story');
$heroAccent = setting('hero_title_accent');

// Escape first, then wrap the accent inside the already-escaped string so
// nothing unescaped can ever reach the page.
$titleHtml = e($heroTitle);
$accentEsc = e($heroAccent);
if ($accentEsc !== '') {
    $pos = strpos($titleHtml, $accentEsc);
    if ($pos !== false) {
        $titleHtml = substr($titleHtml, 0, $pos)
            . '<span class="hero__accent">' . $accentEsc . '</span>'
            . substr($titleHtml, $pos + strlen($accentEsc));
    }
}

$heroBtn1Text = setting('hero_btn1_text');
$heroBtn1Link = setting('hero_btn1_link', 'menu');
$heroBtn2Text = setting('hero_btn2_text');
$heroBtn2Link = setting('hero_btn2_link', 'order');

// Flash messages live inside the hero (newsletter sign-ups bounce back here).
ob_start();
require __DIR__ . '/includes/flash.php';
$flashHtml = trim(ob_get_clean());

// ---------------------------------------------------------------------
// Page data
// ---------------------------------------------------------------------
$features = home_features();

$dishes = featured_items(6);
if (!$dishes) {
    // Nothing flagged as featured — fall back to the first six live items.
    $dishes = db_all(
        "SELECT i.*, c.name AS category_name, c.slug AS category_slug
           FROM menu_items i
           JOIN menu_categories c ON c.id = i.category_id
          WHERE i.is_active = 1 AND c.is_active = 1
          ORDER BY c.sort_order, c.id, i.sort_order, i.id
          LIMIT 6"
    );
}

$storeRow  = store();
$aboutText = setting('home_about_text');
$ornament  = url('assets/img/ornament.png');

$pageKey = 'home';
require __DIR__ . '/includes/header.php';
?>

<!-- ================= hero ================= -->
<section class="hero hero--home">
  <div class="hero__bg" style="background-image:url('<?= e($heroImage) ?>')"></div>
  <div class="hero__veil"></div>

  <div class="hero__inner">
    <div class="container">

      <?php if ($flashHtml !== ''): ?>
        <div class="row justify-content-center">
          <div class="col-lg-8 text-start"><?= $flashHtml ?></div>
        </div>
      <?php endif; ?>

      <?php if ($heroLogo): ?>
        <img class="hero__logo"
             src="<?= e(img(setting('logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>"
             alt="<?= e(setting('site_name', 'Unika Kebab') . ' ' . setting('site_tagline', 'Charcoal Grill')) ?>">
      <?php endif; ?>

      <?php if (setting('hero_eyebrow') !== ''): ?>
        <span class="eyebrow"><?= e(setting('hero_eyebrow')) ?></span>
      <?php endif; ?>

      <h1 class="hero__title"><?= $titleHtml ?></h1>

      <?php if (setting('hero_subtitle') !== ''): ?>
        <p class="hero__text"><?= e(setting('hero_subtitle')) ?></p>
      <?php endif; ?>

      <?php if ($heroBtn1Text !== '' || $heroBtn2Text !== ''): ?>
        <div class="hero__actions">
          <?php if ($heroBtn1Text !== ''): ?>
            <a class="btn-u btn-gold" href="<?= e(url($heroBtn1Link)) ?>">
              <i class="fa-solid fa-utensils" aria-hidden="true"></i> <?= e($heroBtn1Text) ?>
            </a>
          <?php endif; ?>
          <?php if ($heroBtn2Text !== ''): ?>
            <a class="btn-u btn-outline-char" href="<?= e(url($heroBtn2Link)) ?>">
              <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> <?= e($heroBtn2Text) ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <a class="hero__cue" href="#why" aria-label="Scroll to see why people choose Unika">
    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
  </a>
</section>


<?php if ($features): ?>
<!-- ================= why unika ================= -->
<section class="section section--cream-2" id="why">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <?php if (setting('home_features_eyebrow') !== ''): ?>
        <span class="eyebrow"><?= e(setting('home_features_eyebrow', 'Why Unika')) ?></span>
      <?php endif; ?>
      <h2 class="section-title"><?= e(setting('home_features_title', 'Small kitchen, serious standards')) ?></h2>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <div class="row g-4">
      <?php foreach ($features as $i => $feature): ?>
        <div class="col-lg-3 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 4) + 1 ?>">
          <article class="feature-card">
            <span class="feature-card__icon">
              <i class="<?= e($feature['icon'] !== '' ? $feature['icon'] : 'fa-solid fa-fire') ?>" aria-hidden="true"></i>
            </span>
            <h3 class="feature-card__title"><?= e($feature['title']) ?></h3>
            <?php if (trim((string)$feature['text']) !== ''): ?>
              <p class="feature-card__text"><?= e($feature['text']) ?></p>
            <?php endif; ?>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</section>
<?php endif; ?>


<!-- ================= our kitchen ================= -->
<section class="section section--cream"<?= $features ? '' : ' id="why"' ?>>
  <div class="container">
    <div class="row align-items-center g-5">

      <div class="col-lg-6" data-reveal>
        <!-- the badge deliberately overhangs the frame, so it sits outside .media-frame (which clips) -->
        <div class="position-relative">
          <div class="media-frame media-frame--wide">
            <img src="<?= e(img(setting('home_about_image', 'assets/img/bg-parchment.jpg'), 'assets/img/hero-home.jpg')) ?>"
                 alt="<?= e(setting('home_about_title', 'Inside the Unika Kebab kitchen')) ?>" loading="lazy">
            <span class="media-frame__outline" aria-hidden="true"></span>
          </div>
          <div class="media-badge">
            <strong>100%</strong>
            <span>Halal kitchen</span>
          </div>
        </div>
      </div>

      <div class="col-lg-6" data-reveal data-reveal-delay="2">
        <?php if (setting('home_about_eyebrow') !== ''): ?>
          <span class="eyebrow eyebrow--start"><?= e(setting('home_about_eyebrow', 'Our kitchen')) ?></span>
        <?php endif; ?>

        <h2 class="section-title"><?= e(setting('home_about_title', 'Fire, smoke and a little patience')) ?></h2>

        <?php if (trim($aboutText) !== ''): ?>
          <p class="section-text section-text--start"><?= nl2br(e($aboutText)) ?></p>
        <?php else: ?>
          <p class="section-text section-text--start">
            Everything at Unika starts on the charcoal — kofte shaped by hand each morning,
            chicken marinated overnight, bread warmed on the same grill.
          </p>
        <?php endif; ?>

        <?php if (setting('home_about_btn_text') !== ''): ?>
          <a class="btn-u btn-outline-char mt-4" href="<?= e(url(setting('home_about_btn_link', 'menu'))) ?>">
            <?= e(setting('home_about_btn_text')) ?>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>


<!-- ================= off the coals ================= -->
<section class="section section--stone">
  <div class="container">

    <div class="section-head text-center" data-reveal>
      <?php if (setting('home_menu_eyebrow') !== ''): ?>
        <span class="eyebrow"><?= e(setting('home_menu_eyebrow', 'From the grill')) ?></span>
      <?php endif; ?>
      <h2 class="section-title"><?= e(setting('home_menu_title', 'Favourites off the coals')) ?></h2>
      <?php if (setting('home_menu_text') !== ''): ?>
        <p class="section-text"><?= e(setting('home_menu_text')) ?></p>
      <?php endif; ?>
      <img class="ornament" src="<?= e($ornament) ?>" alt="" aria-hidden="true" width="720" height="124">
    </div>

    <?php if ($dishes): ?>
      <div class="row g-4">
        <?php foreach ($dishes as $i => $dish): ?>
          <?php
            $price = money($dish['price'] ?? null, $dish['price_note'] ?? null);
            $desc  = trim((string)($dish['description'] ?? ''));
            $badge = trim((string)($dish['badge'] ?? ''));
            $spicy = !empty($dish['is_spicy']);
            $veg   = !empty($dish['is_vegetarian']);
          ?>
          <div class="col-lg-4 col-md-6" data-reveal data-reveal-delay="<?= (int)($i % 3) + 1 ?>">
            <article class="dish-card">
              <div class="dish-card__media">
                <img src="<?= e(menu_item_img($dish['image'] ?? null)) ?>" alt="<?= e($dish['name']) ?>" loading="lazy">
                <?php if (!empty($dish['category_name'])): ?>
                  <span class="dish-card__cat"><?= e($dish['category_name']) ?></span>
                <?php endif; ?>
              </div>

              <div class="dish-card__body">
                <div class="dish-card__head">
                  <h3 class="dish-card__name"><?= e($dish['name']) ?></h3>
                  <span class="dish-card__dots" aria-hidden="true"></span>
                  <?php if ($price !== ''): ?>
                    <span class="dish-card__price"><?= e($price) ?></span>
                  <?php endif; ?>
                </div>

                <?php if ($desc !== ''): ?>
                  <p class="dish-card__text"><?= e($desc) ?></p>
                <?php endif; ?>

                <?php if ($badge !== '' || $spicy || $veg): ?>
                  <div class="mt-auto">
                    <div class="chips">
                      <?php if ($badge !== ''): ?>
                        <span class="chip chip--gold"><?= e($badge) ?></span>
                      <?php endif; ?>
                      <?php if ($spicy): ?>
                        <span class="chip chip--spicy"><i class="fa-solid fa-pepper-hot" aria-hidden="true"></i> Spicy</span>
                      <?php endif; ?>
                      <?php if ($veg): ?>
                        <span class="chip chip--veg"><i class="fa-solid fa-leaf" aria-hidden="true"></i> Vegetarian</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </article>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="menu-empty" data-reveal>
        <p class="mb-2"><strong>The grill is being restocked.</strong></p>
        <p class="mb-0">Our menu is getting an update right now — call the shop and we will tell you exactly what is on the coals today.</p>
      </div>
    <?php endif; ?>

    <?php if (setting('home_menu_btn_text') !== ''): ?>
      <div class="text-center mt-5" data-reveal>
        <a class="btn-u btn-char" href="<?= e(url(setting('home_menu_btn_link', 'menu'))) ?>">
          <?= e(setting('home_menu_btn_text')) ?>
          <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
      </div>
    <?php endif; ?>

  </div>
</section>


<!-- ================= call to action ================= -->
<section class="cta-band">
  <div class="container">
    <div class="row align-items-center g-4">

      <div class="col-lg-8" data-reveal>
        <?php if (setting('home_cta_eyebrow') !== ''): ?>
          <span class="eyebrow eyebrow--start text-gold"><?= e(setting('home_cta_eyebrow', 'Hungry now?')) ?></span>
        <?php endif; ?>
        <h2 class="cta-band__title"><?= e(setting('home_cta_title', 'Your kebab is twelve minutes away')) ?></h2>
        <?php if (setting('home_cta_text') !== ''): ?>
          <p class="cta-band__text"><?= e(setting('home_cta_text')) ?></p>
        <?php endif; ?>
      </div>

      <div class="col-lg-4 text-lg-end" data-reveal data-reveal-delay="2">
        <div class="d-flex flex-wrap gap-3 justify-content-lg-end">
          <?php if (setting('home_cta_btn_text') !== ''): ?>
            <a class="btn-u btn-gold btn-u--lg" href="<?= e(url(setting('home_cta_btn_link', 'order'))) ?>">
              <?= e(setting('home_cta_btn_text')) ?>
            </a>
          <?php endif; ?>

          <?php if (!empty($storeRow['phone'])): ?>
            <a class="btn-u btn-outline-gold" href="<?= e(phone_href($storeRow['phone'])) ?>">
              <i class="fa-solid fa-phone" aria-hidden="true"></i> <?= e($storeRow['phone']) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ================= newsletter ================= -->
<section class="section section--cream">
  <div class="container">
    <?php require __DIR__ . '/includes/newsletter.php'; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
