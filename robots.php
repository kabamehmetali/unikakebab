<?php
/**
 * robots.txt, served through a rewrite in .htaccess.
 *
 * Generated rather than static so the Sitemap: line always points at the
 * real host — the same file works on localhost, on staging and in production
 * without anyone remembering to edit it.
 */
require __DIR__ . '/includes/app.php';

header('Content-Type: text/plain; charset=utf-8');

$isLive = trim(setting('site_url')) !== '';
?>
# robots.txt — <?= setting('site_name', 'Unika Kebab') . "\n" ?>

User-agent: *
<?php if (!$isLive): ?>
# No public address is configured yet (Admin → Settings → SEO), so this is
# treated as a staging copy and kept out of the index entirely.
Disallow: /
<?php else: ?>
Disallow: /admin/
Disallow: /includes/
Disallow: /sql/
Disallow: /vendor/
Disallow: /subscribe.php
Disallow: /unsubscribe.php
Disallow: /*?*_csrf=

# Ordering links are just outbound redirects — no reason to crawl them.
Allow: /assets/
Allow: /uploads/

<?php if (setting('seo_sitemap_enabled', '1') === '1'): ?>
Sitemap: <?= abs_url('sitemap.xml') . "\n" ?>
<?php endif; ?>
<?php endif; ?>
