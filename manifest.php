<?php
/**
 * Web app manifest, served at /site.webmanifest through a rewrite.
 *
 * Generated rather than static for the same reason robots.txt is: the icon
 * paths depend on whether an admin has uploaded their own set, and every URL
 * in here has to be absolute-from-root or Android resolves it against the
 * manifest's own folder and quietly ends up with no icons.
 */
// No forms, no flash messages, nobody to remember: skip the session so
// the manifest is not served with a Set-Cookie and a no-store header.
define('APP_STATELESS', true);

require __DIR__ . '/includes/app.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$siteName = setting('site_name', 'Unika Kebab');
$icons    = site_icons();

$manifest = [
    'name'             => $siteName,
    'short_name'       => mb_substr(setting('site_short_name', $siteName), 0, 12),
    'description'      => setting('meta_home_desc', setting('footer_about', '')),
    'start_url'        => url('index.php'),
    'scope'            => url(),
    'display'          => 'standalone',
    'theme_color'      => '#12100e',
    'background_color' => '#fff8ec',
    'lang'             => str_replace('_', '-', setting('seo_locale', 'en_CA')),
    'icons'            => [
        ['src' => $icons['favicon-192'], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icons['favicon-512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $icons['maskable'],    'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode(
    array_filter($manifest, static fn($value): bool => $value !== '' && $value !== []),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
