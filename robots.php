<?php
/**
 * robots.txt, served through a rewrite in .htaccess.
 *
 * Generated rather than static for two reasons. The Sitemap: line has to name
 * the real host, so the same file works on localhost, on staging and in
 * production without anybody remembering to edit it — and every path in a
 * robots.txt is resolved from the ORIGIN, not from the folder the site lives
 * in. A hand-written "Disallow: /admin/" protects nothing on a site installed
 * at /unikakebab/, so the prefix is written in from BASE_URL.
 */
// No forms, no flash messages, nobody to remember: skip the session so
// robots.txt is not served with a Set-Cookie and a no-store header.
define('APP_STATELESS', true);

require __DIR__ . '/includes/app.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');          // the file itself is not a page

$isLive = trim(setting('site_url')) !== '';
$base   = rtrim(BASE_URL, '/');           // "" at a domain root, "/unikakebab" in a sub-folder

/** One directive, with the install folder written in. */
function robots_line(string $directive, string $path): string
{
    $base = rtrim(BASE_URL, '/');
    return $directive . ': ' . $base . $path;
}

echo '# robots.txt — ' . setting('site_name', 'Unika Kebab') . "\n";
echo "\n";
echo "User-agent: *\n";

if (!$isLive) {
    echo "# No public address is configured yet (Admin -> Settings -> SEO), so this\n";
    echo "# is treated as a staging copy and kept out of the index entirely.\n";
    echo 'Disallow: ' . ($base === '' ? '/' : $base . '/') . "\n";
    return;
}

// Nothing here is a page. The admin panel also carries a noindex tag, but a
// crawler that is told not to fetch it never gets to read that, so the two
// belong together.
foreach (['/admin/', '/includes/', '/content/', '/vendor/'] as $path) {
    echo robots_line('Disallow', $path) . "\n";
}

// Form endpoints and the error page: real addresses, but never destinations.
// Written without the ".php" because that is the only spelling the site now
// serves — the extension is redirected away by .htaccess.
foreach (['/subscribe', '/unsubscribe', '/error'] as $path) {
    echo robots_line('Disallow', $path) . "\n";
}

// A token in a URL is a session artefact, never content worth indexing.
echo robots_line('Disallow', '/*?*_csrf=') . "\n";

echo "\n";
echo "# Google needs the stylesheet, the scripts and the photos to render a page\n";
echo "# the way a visitor sees it; blocking them costs rankings outright.\n";
foreach (['/assets/', '/uploads/'] as $path) {
    echo robots_line('Allow', $path) . "\n";
}

if (setting('seo_sitemap_enabled', '1') === '1') {
    echo "\n";
    echo 'Sitemap: ' . abs_url('sitemap.xml') . "\n";
}
