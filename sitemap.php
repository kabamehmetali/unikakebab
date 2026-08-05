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
// No forms, no flash messages, nobody to remember: skip the session so
// the sitemap is not served with a Set-Cookie and a no-store header.
define('APP_STATELESS', true);

require __DIR__ . '/includes/app.php';

if (setting('seo_sitemap_enabled', '1') !== '1') {
    http_response_code(404);
    exit;
}

/**
 * Newest timestamp a query can find, as a W3C date.
 *
 * A lastmod in the future is worse than no lastmod at all — Google treats the
 * whole file as untrustworthy and starts ignoring every date in it — so a clock
 * skew between MySQL and PHP is clamped to now rather than published.
 */
function sitemap_stamp(string $sql, array $params = []): ?string
{
    try {
        $row = db_one($sql, $params);
    } catch (PDOException $e) {
        return null;                      // a missing column must not 500 the sitemap
    }

    $stamp = $row['ts'] ?? null;
    if (!$stamp) {
        return null;
    }

    $time = strtotime((string)$stamp);
    if (!$time) {
        return null;
    }

    return date('c', min($time, time()));
}

/**
 * When the settings behind one page were last touched.
 *
 * Deliberately not MAX(updated_at) across the whole settings table: that table
 * also holds the SMTP password, the Twilio token and the Maps key, so saving a
 * credential would tell Google every page had been rewritten. Only the keys
 * that actually print on the page count.
 */
function sitemap_settings_stamp(array $prefixes): ?string
{
    $where  = implode(' OR ', array_fill(0, count($prefixes), 'setting_key LIKE ?'));
    $params = array_map(static fn(string $p): string => $p . '%', $prefixes);

    return sitemap_stamp("SELECT MAX(updated_at) AS ts FROM settings WHERE $where", $params);
}

// menu_items and menu_categories only carry created_at in the shipped schema,
// so the first query is tried and quietly falls back. Add an updated_at column
// with ON UPDATE CURRENT_TIMESTAMP to either table and this starts reporting
// edits and repricing as well as additions, with no code change.
$menuStamp = sitemap_stamp('SELECT GREATEST(
                                COALESCE(MAX(i.updated_at), 0),
                                COALESCE(MAX(c.updated_at), 0),
                                COALESCE(MAX(i.created_at), 0),
                                COALESCE(MAX(c.created_at), 0)
                            ) AS ts
                            FROM menu_items i
                            JOIN menu_categories c ON c.id = i.category_id')
          ?? sitemap_stamp('SELECT GREATEST(
                                COALESCE(MAX(i.created_at), 0),
                                COALESCE(MAX(c.created_at), 0)
                            ) AS ts
                            FROM menu_items i
                            JOIN menu_categories c ON c.id = i.category_id');

$storeStamp = sitemap_stamp('SELECT MAX(updated_at) AS ts FROM store');
$siteStamp  = sitemap_settings_stamp(['site_', 'meta_', 'seo_', 'footer_']);

$lastmod = [
    'home'      => sitemap_settings_stamp(['hero_', 'home_', 'meta_home_', 'newsletter_']) ?: $siteStamp,
    'menu'      => $menuStamp ?: $siteStamp,
    'locations' => $storeStamp ?: $siteStamp,
    'order'     => sitemap_settings_stamp(['order_', 'meta_order_']) ?: $siteStamp,
    'contact'   => sitemap_settings_stamp(['contact_', 'meta_contact_']) ?: $siteStamp,
    'join'      => sitemap_settings_stamp(['newsletter_', 'meta_join_']) ?: $siteStamp,
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
