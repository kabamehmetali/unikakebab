<?php
/**
 * Public site shell — <head>, navbar and the opening <main> tag.
 *
 * A page sets these before including this file:
 *   $pageKey   string  meta lookup key: home | menu | contact | locations | order
 *   $bodyClass string  optional extra class on <body>
 *   $pageCss   string  optional stylesheet name inside assets/css/pages/
 */

$pageKey   = $pageKey   ?? 'home';
$bodyClass = $bodyClass ?? '';
$pageCss   = $pageCss   ?? '';

$meta      = page_meta($pageKey);
$siteName  = setting('site_name', 'Unika Kebab');
$tagline   = setting('site_tagline', 'Charcoal Grill');
$storeRow  = store();
$navOrders = order_links();
[$isOpen, $openMsg] = store_open_status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= e($meta['title']) ?></title>
<meta name="description" content="<?= e($meta['desc']) ?>">
<?php if ($meta['keywords'] !== ''): ?>
<meta name="keywords" content="<?= e($meta['keywords']) ?>">
<?php endif; ?>
<meta name="theme-color" content="#12100e">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($meta['title']) ?>">
<meta property="og:description" content="<?= e($meta['desc']) ?>">
<meta property="og:image" content="<?= e(img(setting('og_image', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(img(setting('favicon', 'assets/img/favicon.png'), 'assets/img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(img(setting('favicon', 'assets/img/favicon.png'), 'assets/img/favicon.png')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
<?php if ($pageCss !== '' && file_exists(APP_ROOT . '/assets/css/pages/' . $pageCss)): ?>
<link href="<?= e(url('assets/css/pages/' . $pageCss)) ?>" rel="stylesheet">
<?php endif; ?>

<script type="application/ld+json">
<?= json_encode([
    '@context'  => 'https://schema.org',
    '@type'     => 'Restaurant',
    'name'      => $siteName,
    'image'     => rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/') . img(setting('logo', 'assets/img/logo.png')),
    'servesCuisine' => 'Turkish, Middle Eastern, Kebab',
    'telephone' => $storeRow['phone'] ?? '',
    'email'     => $storeRow['email'] ?? '',
    'address'   => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => trim(($storeRow['address_line1'] ?? '') . ' ' . ($storeRow['address_line2'] ?? '')),
        'addressLocality' => $storeRow['city'] ?? '',
        'addressRegion'   => $storeRow['province'] ?? '',
        'postalCode'      => $storeRow['postal_code'] ?? '',
        'addressCountry'  => $storeRow['country'] ?? 'CA',
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
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
           alt="<?= e($siteName . ' ' . $tagline) ?>">
    </a>

    <button class="u-nav__toggler navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNav">
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
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="orderMenu">
            <?php foreach ($navOrders as $link): ?>
              <li>
                <a class="u-order-item" href="<?= e(url($link['url'])) ?>"
                   <?= preg_match('#^https?://#i', $link['url']) ? 'target="_blank" rel="noopener"' : '' ?>>
                  <i class="<?= e($link['icon']) ?>" aria-hidden="true"></i>
                  <span>
                    <?= e($link['label']) ?>
                    <?php if (!empty($link['note'])): ?><small><?= e($link['note']) ?></small><?php endif; ?>
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
    </div>
  </nav>
</header>

<main id="main">
