<?php
/**
 * Public site shell — <head>, navbar and the opening <main> tag.
 *
 * A page sets these before including this file:
 *   $pageKey   string  meta lookup key: home | menu | contact | locations | order
 *   $bodyClass string  optional extra class on <body>
 *   $pageCss   string  optional stylesheet name inside assets/css/pages/
 *   $noIndex   bool    keep the page out of search results
 *   $titleOverride string  a title the settings table has no entry for
 */

$pageKey   = $pageKey   ?? 'home';
$bodyClass = $bodyClass ?? '';
$pageCss   = $pageCss   ?? '';
$noIndex   = $noIndex   ?? false;

$meta      = page_meta($pageKey);

// Error pages borrow the home page's shell but must not borrow its title.
if (isset($titleOverride) && trim((string)$titleOverride) !== '') {
    $meta['title']    = (string)$titleOverride;
    $meta['og_title'] = (string)$titleOverride;
}
$siteName  = setting('site_name', 'Unika Kebab');
$tagline   = setting('site_tagline', 'Charcoal Grill');
$storeRow  = store();
$navOrders = order_links();
$navMapUrl = store_map_share_url(
    'https://www.google.com/maps/search/?api=1&query='
    . urlencode(trim(($storeRow['name'] ?? '') . ' ' . store_address(' ')))
);

// A page can force itself out of the index regardless of what the admin
// panel says — used by one-off pages such as the unsubscribe confirmation.
$robots    = $noIndex ? 'noindex, nofollow' : $meta['robots'];
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="<?= e(str_replace('_', '-', setting('seo_locale', 'en_CA'))) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['desc']) ?>">
<?php if ($meta['keywords'] !== ''): ?>
<meta name="keywords" content="<?= e($meta['keywords']) ?>">
<?php endif; ?>
<meta name="author" content="<?= e(setting('seo_author', 'Mehmet Ali KABA')) ?>">
<meta name="publisher" content="<?= e(setting('seo_publisher', $siteName)) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="googlebot" content="<?= e($robots) ?>">
<meta name="theme-color" content="#12100e">
<meta name="format-detection" content="telephone=no">
<?php if (!$noIndex): ?>
<link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>

<!-- Open Graph -->
<?php /* "article" is for a piece of writing with an author and a date. A menu,
         a contact page and an ordering page are none of those, and mislabelling
         them costs the richer treatment Facebook gives a business page. */ ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="<?= e(setting('seo_locale', 'en_CA')) ?>">
<meta property="og:url" content="<?= e($meta['canonical']) ?>">
<meta property="og:title" content="<?= e($meta['og_title']) ?>">
<meta property="og:description" content="<?= e($meta['og_desc']) ?>">
<meta property="og:image" content="<?= e($meta['og_image']) ?>">
<meta property="og:image:alt" content="<?= e($meta['og_image_alt']) ?>">
<?php if (setting('seo_fb_app_id') !== ''): ?>
<meta property="fb:app_id" content="<?= e(setting('seo_fb_app_id')) ?>">
<?php endif; ?>

<!-- Twitter / X -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($meta['og_title']) ?>">
<meta name="twitter:description" content="<?= e($meta['og_desc']) ?>">
<meta name="twitter:image" content="<?= e($meta['og_image']) ?>">
<meta name="twitter:image:alt" content="<?= e($meta['og_image_alt']) ?>">
<?php if (setting('seo_twitter_site') !== ''): ?>
<meta name="twitter:site" content="<?= e(setting('seo_twitter_site')) ?>">
<?php endif; ?>
<?php if (setting('seo_twitter_creator') !== ''): ?>
<meta name="twitter:creator" content="<?= e(setting('seo_twitter_creator')) ?>">
<?php endif; ?>

<!-- Local business signals -->
<?php if (!empty($storeRow['latitude']) && !empty($storeRow['longitude'])): ?>
<meta name="geo.position" content="<?= e($storeRow['latitude'] . ';' . $storeRow['longitude']) ?>">
<meta name="ICBM" content="<?= e($storeRow['latitude'] . ', ' . $storeRow['longitude']) ?>">
<?php endif; ?>
<?php if (!empty($storeRow['city'])): ?>
<meta name="geo.placename" content="<?= e($storeRow['city']) ?>">
<?php endif; ?>
<?php if (!empty($storeRow['province'])): ?>
<meta name="geo.region" content="<?= e(store_country_code() . '-' . $storeRow['province']) ?>">
<?php endif; ?>

<!-- Search console ownership -->
<?php if (setting('seo_google_verification') !== ''): ?>
<meta name="google-site-verification" content="<?= e(setting('seo_google_verification')) ?>">
<?php endif; ?>
<?php if (setting('seo_bing_verification') !== ''): ?>
<meta name="msvalidate.01" content="<?= e(setting('seo_bing_verification')) ?>">
<?php endif; ?>
<?php if (setting('seo_pinterest_verification') !== ''): ?>
<meta name="p:domain_verify" content="<?= e(setting('seo_pinterest_verification')) ?>">
<?php endif; ?>

<?php $icons = site_icons(); ?>
<!-- Icons. Google only reads a favicon it can fetch, that is square and whose
     side is a multiple of 48, so the 48px file is the one declared for it. -->
<link rel="icon" href="<?= e($icons['ico']) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e($icons['favicon-16']) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e($icons['favicon-32']) ?>">
<link rel="icon" type="image/png" sizes="48x48" href="<?= e($icons['favicon-48']) ?>">
<link rel="icon" type="image/png" sizes="96x96" href="<?= e($icons['favicon-96']) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e($icons['favicon-192']) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e($icons['apple-touch']) ?>">
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">
<meta name="apple-mobile-web-app-title" content="<?= e(setting('site_short_name', $siteName)) ?>">
<meta name="application-name" content="<?= e(setting('site_short_name', $siteName)) ?>">
<meta name="msapplication-TileColor" content="#12100e">
<meta name="msapplication-TileImage" content="<?= e($icons['favicon-192']) ?>">
<?php if (setting('seo_sitemap_enabled', '1') === '1'): ?>
<link rel="sitemap" type="application/xml" title="Sitemap" href="<?= e(url('sitemap.xml')) ?>">
<?php endif; ?>

<!-- Fonts (Anton, Outfit), Bootstrap 5.3.3 and Font Awesome 6.5.2 are served
     from assets/vendor/ rather than their CDNs, so the page is styled even
     where a content security policy keeps every other origin out. -->
<link href="<?= e(asset('assets/vendor/fonts/fonts.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/vendor/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
<?php if ($pageCss !== '' && file_exists(APP_ROOT . '/assets/css/pages/' . $pageCss)): ?>
<link href="<?= e(asset('assets/css/pages/' . $pageCss)) ?>" rel="stylesheet">
<?php endif; ?>

<script type="application/ld+json">
<?= json_encode(seo_structured_data($pageKey, $meta, $breadcrumbs), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
</head>

<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<!-- ================= navbar ================= -->
<header class="u-nav">
  <nav class="navbar navbar-expand-lg container" aria-label="Main navigation">

    <a class="u-nav__brand navbar-brand p-0" href="<?= e(url('index.php')) ?>">
      <img class="u-nav__logo"
           src="<?= e(img(setting('logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>"
           alt="<?= e(alt_text(setting('logo_alt'), $siteName . ' ' . $tagline)) ?>"
           fetchpriority="high">
    </a>

    <button class="u-nav__toggler" type="button" id="navToggle"
            aria-controls="mainNav" aria-expanded="false" aria-label="Open menu">
      <span class="u-nav__burger" aria-hidden="true"><i></i><i></i><i></i></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end u-nav__panel" id="mainNav">
      <div class="u-nav__panel-inner">
      <ul class="navbar-nav align-items-lg-center mb-0">
        <li class="nav-item">
          <a class="nav-link u-nav__link<?= nav_active('index.php') ?>" href="<?= e(url('index.php')) ?>">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link u-nav__link<?= nav_active('menu.php') ?>" href="<?= e(url('menu.php')) ?>">Menu</a>
        </li>
        <li class="nav-item">
          <a class="nav-link u-nav__link<?= nav_active('locations.php') ?>" href="<?= e(url('locations.php')) ?>">Locations</a>
        </li>
        <li class="nav-item">
          <a class="nav-link u-nav__link<?= nav_active('contact.php') ?>" href="<?= e(url('contact.php')) ?>">Contact Us</a>
        </li>

        <li class="nav-item u-nav__cta dropdown">
          <a class="btn-u btn-gold btn-u--sm" href="<?= e(url('order.php')) ?>"
             id="orderMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Order Online
            <i class="fa-solid fa-chevron-down u-nav__cta-caret" aria-hidden="true"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orderMenu">
            <?php foreach ($navOrders as $link): ?>
              <li>
                <a class="u-order-item" href="<?= e(url($link['url'])) ?>"
                   <?= preg_match('#^https?://#i', $link['url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                  <?= order_link_icon($link) ?>
                  <span>
                    <?= e($link['label']) ?>
                    <?php $orderNote = order_link_note($link); ?>
                    <?php if ($orderNote !== ''): ?><small><?= e($orderNote) ?></small><?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
            <?php if (!$navOrders): ?>
              <li><span class="u-order-item text-muted-warm">Ordering links coming soon</span></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="u-order-item" href="<?= e(url('order.php')) ?>">
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                <span>All ordering options</span>
              </a>
            </li>
          </ul>
        </li>

      </ul>

      <!-- Shown only inside the full-screen mobile panel. -->
      <div class="u-nav__foot d-lg-none">
        <?php if (!empty($storeRow['phone'])): ?>
          <a class="u-nav__foot-link" href="<?= e(phone_href($storeRow['phone'])) ?>">
            <i class="fa-solid fa-phone" aria-hidden="true"></i><?= e($storeRow['phone']) ?>
          </a>
        <?php endif; ?>
        <?php if (store_address() !== ''): ?>
          <a class="u-nav__foot-address" href="<?= e($navMapUrl) ?>" target="_blank" rel="noopener">
            <?= e(store_address()) ?>
          </a>
        <?php endif; ?>
        <?php $navSocials = store_socials(); ?>
        <?php if ($navSocials): ?>
          <div class="u-nav__socials">
            <?php foreach ($navSocials as $social): ?>
              <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener"
                 aria-label="<?= e($siteName . ' on ' . $social['label']) ?>">
                <i class="<?= e($social['icon']) ?>" aria-hidden="true"></i>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      </div>
    </div>
  </nav>
</header>

<main id="main">
