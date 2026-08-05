<?php
/**
 * Admin shell — <head>, sidebar, topbar and the opening content area.
 *
 * A page sets these before including this file:
 *   $title     string  page heading
 *   $subtitle  string  small line under the heading
 *   $activeNav string  dashboard|home|categories|items|store|campaigns|templates|
 *                      subscribers|messages|settings|account
 */

$title     = $title     ?? 'Dashboard';
$subtitle  = $subtitle  ?? '';
$activeNav = $activeNav ?? '';

$admin   = current_admin();
$unread  = (int)(db_one('SELECT COUNT(*) AS n FROM contact_messages WHERE is_read = 0')['n'] ?? 0);
$sending = (int)(db_one("SELECT COUNT(*) AS n FROM email_campaigns WHERE status = 'sending'")['n'] ?? 0);

/** Sidebar link helper. */
function a_nav(string $key, string $href, string $icon, string $label, string $active, int $count = 0): void
{
    $isActive = $key === $active ? ' is-active' : '';
    echo '<li><a class="a-side__link' . $isActive . '" href="' . e(admin_url($href)) . '">'
       . '<i class="' . e($icon) . '" aria-hidden="true"></i><span>' . e($label) . '</span>'
       . ($count > 0 ? '<span class="a-side__count">' . $count . '</span>' : '')
       . '</a></li>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(setting('site_name', 'Unika Kebab')) ?> Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= e(site_icon('ico')) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(site_icon('favicon-32')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(site_icon('apple-touch')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap-grid.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= e(asset('admin/assets/css/admin.css')) ?>" rel="stylesheet">
</head>
<body>

<div class="a-shell">

  <!-- ================= sidebar ================= -->
  <aside class="a-side" id="aSide">
    <a class="a-side__brand" href="<?= e(admin_url('index.php')) ?>">
      <img src="<?= e(img(setting('logo', 'assets/img/logo.png'), 'assets/img/logo.png')) ?>" alt="">
      <span>
        <strong><?= e(setting('site_name', 'Unika Kebab')) ?></strong>
        <span>Admin panel</span>
      </span>
    </a>

    <ul class="a-side__nav">
      <li class="a-side__group">Overview</li>
      <?php a_nav('dashboard', 'index.php', 'fa-solid fa-gauge-high', 'Dashboard', $activeNav); ?>

      <li class="a-side__group">Website</li>
      <?php
        a_nav('home',       'home.php',       'fa-solid fa-house',        'Home Page',   $activeNav);
        a_nav('categories', 'categories.php', 'fa-solid fa-layer-group',  'Categories',  $activeNav);
        a_nav('items',      'items.php',      'fa-solid fa-burger',       'Menu Items',  $activeNav);
        a_nav('order-links', 'order-links.php', 'fa-solid fa-bag-shopping', 'Order Links', $activeNav);
        a_nav('store',      'store.php',      'fa-solid fa-store',        'Store Info',  $activeNav);
      ?>

      <li class="a-side__group">Email marketing</li>
      <?php
        a_nav('campaigns',   'campaigns.php',   'fa-solid fa-paper-plane', 'Campaigns',   $activeNav, $sending);
        a_nav('templates',   'templates.php',   'fa-solid fa-file-lines',  'Templates',   $activeNav);
        a_nav('subscribers', 'subscribers.php', 'fa-solid fa-users',       'Subscribers', $activeNav);
      ?>

      <li class="a-side__group">Customers</li>
      <?php
        a_nav('messages', 'messages.php', 'fa-solid fa-envelope-open-text', 'Messages', $activeNav, $unread);
      ?>

      <li class="a-side__group">System</li>
      <?php
        a_nav('settings', 'settings.php', 'fa-solid fa-sliders',    'Settings', $activeNav);
        a_nav('account',  'account.php',  'fa-solid fa-user-shield', 'Account',  $activeNav);
      ?>
    </ul>

    <div class="a-side__foot">
      <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View website
      </a>
      <a href="<?= e(admin_url('logout.php')) ?>">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Sign out
      </a>
    </div>
  </aside>

  <div class="a-backdrop" id="aBackdrop"></div>

  <!-- ================= main ================= -->
  <div class="a-main">
    <header class="a-top">
      <button class="a-burger" id="aBurger" type="button" aria-label="Toggle menu">
        <i class="fa-solid fa-bars"></i>
      </button>
      <div>
        <h1 class="a-top__title"><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><p class="a-top__sub"><?= e($subtitle) ?></p><?php endif; ?>
      </div>
      <div class="a-top__right">
        <a class="a-btn a-btn--ghost a-btn--sm a-nowrap" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-eye"></i> <span class="d-none d-sm-inline">View site</span>
        </a>
        <span class="a-user">
          <span class="a-user__avatar"><?= e(strtoupper(substr($admin['full_name'] ?? 'A', 0, 1))) ?></span>
          <span class="d-none d-md-inline"><?= e($admin['full_name'] ?? 'Admin') ?></span>
        </span>
      </div>
    </header>

    <div class="a-body">
      <?php foreach (flash_take() as $msg): ?>
        <div class="a-alert a-alert--<?= e($msg['type']) ?>">
          <i class="fa-solid <?= $msg['type'] === 'success' ? 'fa-circle-check' : ($msg['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info') ?>"></i>
          <span><?= $msg['message'] /* messages are built in code, not user input */ ?></span>
          <button class="a-alert__close" type="button" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>
      <?php endforeach; ?>
