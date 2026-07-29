<?php
/**
 * XML sitemap, served at /sitemap.xml through a rewrite in .htaccess.
 *
 * Built from seo_pages() so it can never drift out of step with the meta
 * editor in the admin panel: a page that is not public, or that the admin
 * has marked noindex, is left out.
 *
 * <lastmod> comes from the newest content that actually shows on the page —
 * the menu moves when an item is edited, locations when the store row is —
 * which is a far more honest signal than stamping every URL with today.
 */
require __DIR__ . '/includes/app.php';

if (setting('seo_sitemap_enabled', '1') !== '1') {
    http_response_code(404);
    exit;
}

/** Newest updated_at / created_at across a table, as a W3C date. */
function sitemap_stamp(string $sql): ?string
{
    try {
        $row = db_one($sql);
    } catch (PDOException $e) {
        return null;                      // a missing column must not 500 the sitemap
    }

    $stamp = $row['ts'] ?? null;
    if (!$stamp) {
        return null;
    }
    $time = strtotime((string)$stamp);

    return $time ? date('c', $time) : null;
}

$menuStamp  = sitemap_stamp('SELECT GREATEST(
                                 COALESCE(MAX(i.created_at), 0),
                                 COALESCE(MAX(c.created_at), 0)
                             ) AS ts
                             FROM menu_items i
                             JOIN menu_categories c ON c.id = i.category_id');
$storeStamp = sitemap_stamp('SELECT MAX(updated_at) AS ts FROM store');
$siteStamp  = sitemap_stamp('SELECT MAX(updated_at) AS ts FROM settings');

$lastmod = [
    'home'      => $siteStamp,
    'menu'      => $menuStamp ?: $siteStamp,
    'locations' => $storeStamp ?: $siteStamp,
    'order'     => $siteStamp,
    'contact'   => $siteStamp,
];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach (seo_pages() as $key => $page): ?>
<?php
    if (empty($page['public'])) {
        continue;
    }
    // Respect the admin's own robots setting rather than advertising a URL
    // we have just told crawlers to ignore.
    $meta = page_meta($key);
    if (stripos($meta['robots'], 'noindex') !== false) {
        continue;
    }
?>
  <url>
    <loc><?= e($meta['canonical']) ?></loc>
<?php if (!empty($lastmod[$key])): ?>
    <lastmod><?= e($lastmod[$key]) ?></lastmod>
<?php endif; ?>
    <changefreq><?= e($page['changefreq']) ?></changefreq>
    <priority><?= e($page['priority']) ?></priority>
    <image:image>
      <image:loc><?= e($meta['og_image']) ?></image:loc>
      <image:title><?= e($meta['title']) ?></image:title>
    </image:image>
  </url>
<?php endforeach; ?>
</urlset>
