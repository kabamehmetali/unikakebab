<?php
/**
 * Shared helpers for the public site and the admin panel.
 */

// =====================================================================
//  Output / URLs
// =====================================================================

/** Escape for HTML output. */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Drop the ".php" from an internal path so every link on the site is clean.
 * A matching rewrite in .htaccess maps the clean address back to the file.
 *
 *   "menu.php"                  → "menu"
 *   "index.php"                 → ""            (the folder itself)
 *   "admin/index.php"           → "admin/"
 *   "admin/items.php?action=new"→ "admin/items?action=new"
 *   "contact.php#contact-form"  → "contact#contact-form"
 *   "assets/css/style.css"      → unchanged
 */
function clean_path(string $path): string
{
    // Only the path is rewritten — any ?query and #fragment ride along untouched.
    $cut    = strcspn($path, '?#');
    $suffix = substr($path, $cut);
    $path   = substr($path, 0, $cut);

    if (substr_compare($path, '.php', -4, 4, true) === 0) {
        $path = substr($path, 0, -4);
        // "index" is what a folder serves by default, so it is never spelled out.
        $path = preg_replace('#(^|/)index$#i', '$1', $path);
    }

    return $path . $suffix;
}

/** Build a site URL. Absolute URLs, mailto: and tel: links pass straight through. */
function url(string $path = ''): string
{
    if ($path === '') {
        return BASE_URL . '/';
    }
    if (preg_match('#^(https?:|mailto:|tel:|//|\#)#i', $path)) {
        return $path;
    }
    return BASE_URL . '/' . ltrim(clean_path($path), '/');
}

/** URL for an admin page. */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

/**
 * The public address of the site, without a trailing slash.
 *
 * Needed anywhere a site-relative link will not do: links inside an email
 * have to work from somebody else's inbox, and canonical / og: / JSON-LD
 * tags are all rejected by search engines unless they are fully qualified.
 */
function site_base_url(): string
{
    $configured = trim(setting('site_url'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    // Nothing configured — work it out from the request we are serving.
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';                       // command line, and no site_url set
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
          || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
          || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

    return ($https ? 'https://' : 'http://') . $host . BASE_URL;
}

/** url(), but absolute. */
function abs_url(string $path = ''): string
{
    if (preg_match('#^(https?:|mailto:|tel:|//)#i', $path)) {
        return $path;
    }

    $base = site_base_url();
    if ($base === '') {
        return url($path);               // best effort: a site-relative link
    }
    if ($path === '') {
        return $base . '/';
    }

    return $base . '/' . ltrim(clean_path($path), '/');
}

/**
 * URL for an uploaded or bundled image, with a fallback when the value is empty
 * or the file has gone missing.
 */
function img(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    $path = trim((string)$path);
    if ($path === '') {
        return url($fallback);
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if (!file_exists(APP_ROOT . '/' . ltrim($path, '/'))) {
        return url($fallback);
    }
    return url($path);
}

/** Configured fallback image for menu items that do not have a photo. */
function menu_item_placeholder(): string
{
    $path = trim(setting('menu_item_placeholder', 'assets/img/placeholder.svg'));
    if ($path !== '' && (preg_match('#^(https?:)?//#i', $path) || file_exists(APP_ROOT . '/' . ltrim($path, '/')))) {
        return $path;
    }

    return 'assets/img/placeholder.svg';
}

/** URL for a menu-item image, falling back to the admin-configured placeholder. */
function menu_item_img(?string $path): string
{
    return img($path, menu_item_placeholder());
}

/** Format a price. Returns an empty string when there is no price set. */
function money(?string $amount, ?string $note = null): string
{
    if ($amount === null || $amount === '') {
        return '';
    }
    $prefix = ($note === '+') ? '+' : '';
    return $prefix . '$' . number_format((float)$amount, 2);
}

/** Turn text into a URL-safe slug. */
function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    return trim($text, '-') ?: 'item';
}

/** Shorten text for previews. */
function excerpt(?string $text, int $length = 120): string
{
    $text = trim(strip_tags((string)$text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '…';
}

/** Build a readable, labelled SMS notification for a contact-form message. */
function contact_sms_body(string $sender, string $email, string $phone, string $subject, string $message): string
{
    $singleLine = static function (string $value, string $fallback): string {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        return $value !== '' ? $value : $fallback;
    };

    $body = excerpt($message, 300);

    return "Unika Kebab — New contact message\n"
         . 'Sender: ' . $singleLine($sender, 'Not provided') . "\n"
         . 'Email: ' . $singleLine($email, 'Not provided') . "\n"
         . 'Phone: ' . $singleLine($phone, 'Not provided') . "\n"
         . 'Subject: ' . $singleLine($subject, 'Not provided') . "\n"
         . "Message:\n"
         . ($body !== '' ? $body : 'Not provided');
}

/** A tel: href built from a human-readable phone number. */
function phone_href(?string $phone): string
{
    $digits = preg_replace('/[^0-9+]/', '', (string)$phone);
    return 'tel:' . $digits;
}

// =====================================================================
//  Settings
// =====================================================================

/** Read a setting (all settings are loaded once per request). */
function setting(string $key, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string)$row['setting_value'];
        }
    }

    $value = $cache[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
}

/** Insert or update one setting. */
function setting_set(string $key, ?string $value): void
{
    db_run(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}

/** Insert or update many settings at once. */
function setting_set_many(array $pairs): void
{
    foreach ($pairs as $key => $value) {
        setting_set((string)$key, $value === null ? null : (string)$value);
    }
}

// =====================================================================
//  SEO
// =====================================================================

/**
 * Every public page, in the order they should appear in the sitemap and in
 * the admin SEO editor. Keyed by the $pageKey a page hands to the header.
 *
 *   file      the script the page lives in
 *   label     what the admin panel calls it
 *   priority  sitemap <priority>
 *   changefreq sitemap <changefreq>
 *   public    include in sitemap.xml / robots.txt at all
 */
function seo_pages(): array
{
    return [
        'home' => [
            'file' => 'index.php', 'label' => 'Home',
            'priority' => '1.0', 'changefreq' => 'weekly', 'public' => true,
        ],
        'menu' => [
            'file' => 'menu.php', 'label' => 'Menu',
            'priority' => '0.9', 'changefreq' => 'weekly', 'public' => true,
        ],
        'locations' => [
            'file' => 'locations.php', 'label' => 'Locations',
            'priority' => '0.8', 'changefreq' => 'monthly', 'public' => true,
        ],
        'order' => [
            'file' => 'order.php', 'label' => 'Order Online',
            'priority' => '0.8', 'changefreq' => 'monthly', 'public' => true,
        ],
        'contact' => [
            'file' => 'contact.php', 'label' => 'Contact Us',
            'priority' => '0.7', 'changefreq' => 'monthly', 'public' => true,
        ],
        'subscribe' => [
            'file' => 'subscribe.php', 'label' => 'Newsletter (subscribe)',
            'priority' => '0.1', 'changefreq' => 'yearly', 'public' => false,
        ],
        'unsubscribe' => [
            'file' => 'unsubscribe.php', 'label' => 'Newsletter (unsubscribe)',
            'priority' => '0.1', 'changefreq' => 'yearly', 'public' => false,
        ],
    ];
}

/**
 * Scheme and host with no path — "https://unikakebab.ca".
 *
 * site_base_url() (includes/mailer.php) already resolves the public address
 * from the site_url setting, falling back to the host serving the request;
 * this trims the sub-folder back off for the places that want a bare origin.
 */
function site_origin(): string
{
    $base = site_base_url();
    if ($base === '') {
        return '';
    }
    return preg_replace('#^(https?://[^/]+).*$#i', '$1', $base) ?: $base;
}

/**
 * Absolute URL for an image, for og:image and JSON-LD — both of which reject
 * relative paths. img() has already resolved the fallback and prefixed
 * BASE_URL, so only the scheme and host are still missing.
 */
function abs_media(?string $path, string $fallback = 'assets/img/logo.png'): string
{
    $src = img($path, $fallback);

    if (preg_match('#^https?://#i', $src)) {
        return $src;
    }
    if (strpos($src, '//') === 0) {
        return (parse_url(site_origin(), PHP_URL_SCHEME) ?: 'https') . ':' . $src;
    }

    $origin = site_origin();
    return $origin === '' ? $src : $origin . $src;
}

/**
 * The complete meta set for a page key such as "home" or "menu".
 *
 * Open Graph and canonical values are optional in the admin panel — a blank
 * og_title falls back to the page title, a blank canonical to the page's own
 * URL, and blank robots to the site-wide default. That way an admin only has
 * to fill in the fields they actually want to override.
 */
function page_meta(string $page): array
{
    $pages    = seo_pages();
    $file     = $pages[$page]['file'] ?? 'index.php';
    $siteName = setting('site_name', 'Unika Kebab');
    $suffix   = setting('seo_title_suffix', '');

    $title = setting("meta_{$page}_title", '');
    if ($title === '') {
        $label = $pages[$page]['label'] ?? $siteName;
        $title = ($page === 'home' || $suffix === '') ? $siteName : $label . ' | ' . $suffix;
    }

    $desc      = setting("meta_{$page}_desc", '');
    $canonical = trim(setting("meta_{$page}_canonical", ''));
    $ogImage   = trim(setting("meta_{$page}_og_image", ''));

    return [
        'key'          => $page,
        'title'        => $title,
        'desc'         => $desc,
        'keywords'     => setting("meta_{$page}_keywords", ''),
        'robots'       => setting("meta_{$page}_robots", '') ?: setting('seo_default_robots', 'index, follow'),
        'canonical'    => $canonical !== '' ? abs_url($canonical) : abs_url($file),
        'og_title'     => setting("meta_{$page}_og_title", '') ?: $title,
        'og_desc'      => setting("meta_{$page}_og_desc", '') ?: $desc,
        'og_image'     => abs_media($ogImage !== '' ? $ogImage : setting('og_image', 'assets/img/logo.png')),
        'og_image_alt' => setting("meta_{$page}_og_image_alt", '') ?: setting('og_image_alt', $siteName),
    ];
}

/**
 * Pick the alt text for an image: the admin's own wording when they supplied
 * it, otherwise a generated description. Decorative images should pass an
 * empty $fallback and get alt="" plus aria-hidden, not a made-up sentence.
 */
function alt_text(?string $alt, string $fallback = ''): string
{
    $alt = trim((string)$alt);
    return $alt !== '' ? $alt : $fallback;
}

/** Alt text for a menu item photo, falling back to "<name> — <category> at <site>". */
function menu_item_alt(array $item, string $categoryName = ''): string
{
    $siteName = setting('site_name', 'Unika Kebab');
    $parts    = array_filter([$item['name'] ?? '', $categoryName]);
    $fallback = implode(' — ', $parts);

    return alt_text($item['image_alt'] ?? null, trim($fallback . ' at ' . $siteName, ' —'));
}

/** Two-letter country code for the store, for schema.org and geo meta tags. */
function store_country_code(): string
{
    $country = strtolower(trim((string)(store()['country'] ?? '')));
    $known   = [
        'canada' => 'CA', 'united states' => 'US', 'usa' => 'US',
        'united kingdom' => 'GB', 'uk' => 'GB', 'turkey' => 'TR', 'türkiye' => 'TR',
    ];

    if (isset($known[$country])) {
        return $known[$country];
    }
    // Already a code, or nothing usable — default to Canada.
    return (strlen($country) === 2) ? strtoupper($country) : 'CA';
}

/** Opening hours in the openingHoursSpecification shape schema.org expects. */
function seo_opening_hours(): array
{
    $days = day_names();
    $out  = [];

    foreach (store_hours() as $index => $row) {
        if (!empty($row['is_closed']) || empty($row['open_time']) || empty($row['close_time'])) {
            continue;
        }
        $out[] = [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => 'https://schema.org/' . ($days[$index] ?? 'Monday'),
            'opens'     => substr((string)$row['open_time'], 0, 5),
            'closes'    => substr((string)$row['close_time'], 0, 5),
        ];
    }

    return $out;
}

/**
 * The JSON-LD graph for a page: the restaurant itself, the website, the page
 * being viewed and — when the page supplies them — a breadcrumb trail.
 *
 * Every node is @id-linked so search engines read them as one entity rather
 * than four unrelated blobs, which is what makes rich results fire.
 *
 * $breadcrumbs is a list of ['name' => …, 'url' => …] in trail order.
 */
function seo_structured_data(string $pageKey, array $meta, array $breadcrumbs = []): array
{
    $s        = store();
    $siteName = setting('site_name', 'Unika Kebab');
    $home     = abs_url();
    $orgId    = $home . '#restaurant';
    $siteId   = $home . '#website';

    $restaurant = array_filter([
        '@type'         => 'Restaurant',
        '@id'           => $orgId,
        'name'          => $s['name'] ?? $siteName,
        'alternateName' => setting('site_tagline', ''),
        'url'           => $home,
        'logo'          => abs_media(setting('logo', 'assets/img/logo.png')),
        'image'         => abs_media(setting('og_image', setting('logo', 'assets/img/logo.png'))),
        'description'   => setting('footer_about', $meta['desc']),
        'servesCuisine' => ['Turkish', 'Middle Eastern', 'Kebab', 'Halal'],
        'priceRange'    => setting('seo_price_range', '$$'),
        'telephone'     => $s['phone'] ?? '',
        'email'         => $s['email'] ?? '',
        'hasMenu'       => abs_url('menu.php'),
        'acceptsReservations' => 'False',
        'address'       => array_filter([
            '@type'           => 'PostalAddress',
            'streetAddress'   => trim(($s['address_line1'] ?? '') . ' ' . ($s['address_line2'] ?? '')),
            'addressLocality' => $s['city'] ?? '',
            'addressRegion'   => $s['province'] ?? '',
            'postalCode'      => $s['postal_code'] ?? '',
            'addressCountry'  => store_country_code(),
        ]),
        'geo' => (!empty($s['latitude']) && !empty($s['longitude'])) ? [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float)$s['latitude'],
            'longitude' => (float)$s['longitude'],
        ] : null,
        'openingHoursSpecification' => seo_opening_hours(),
        'sameAs' => array_values(array_map(
            static fn(array $social): string => $social['url'],
            store_socials()
        )),
    ], static fn($value): bool => $value !== null && $value !== '' && $value !== []);

    $graph = [
        $restaurant,
        [
            '@type'     => 'WebSite',
            '@id'       => $siteId,
            'url'       => $home,
            'name'      => $siteName,
            'publisher' => ['@id' => $orgId],
            'inLanguage' => str_replace('_', '-', setting('seo_locale', 'en_CA')),
        ],
        array_filter([
            '@type'      => 'WebPage',
            '@id'        => $meta['canonical'] . '#webpage',
            'url'        => $meta['canonical'],
            'name'       => $meta['title'],
            'description'=> $meta['desc'],
            'isPartOf'   => ['@id' => $siteId],
            'about'      => ['@id' => $orgId],
            'primaryImageOfPage' => ['@type' => 'ImageObject', 'url' => $meta['og_image']],
            'author'     => [
                '@type' => 'Person',
                'name'  => setting('seo_author', 'Mehmet Ali KABA'),
            ],
            'inLanguage' => str_replace('_', '-', setting('seo_locale', 'en_CA')),
        ]),
    ];

    if ($breadcrumbs) {
        $graph[] = [
            '@type'           => 'BreadcrumbList',
            '@id'             => $meta['canonical'] . '#breadcrumb',
            'itemListElement' => array_map(
                static fn(int $i, array $crumb): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $crumb['name'],
                    'item'     => abs_url($crumb['url']),
                ],
                array_keys($breadcrumbs),
                $breadcrumbs
            ),
        ];
    }

    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

// =====================================================================
//  Store details & opening hours
// =====================================================================

/** The single store row. */
function store(): array
{
    static $store = null;
    if ($store === null) {
        $store = db_one('SELECT * FROM store WHERE id = 1') ?? [];
    }
    return $store;
}

/** The full formatted address on one line. */
function store_address(string $separator = ', '): string
{
    $s     = store();
    $parts = array_filter([
        $s['address_line1'] ?? '',
        $s['address_line2'] ?? '',
        $s['city'] ?? '',
        trim(($s['province'] ?? '') . ' ' . ($s['postal_code'] ?? '')),
        $s['country'] ?? '',
    ], fn($p) => trim((string)$p) !== '');

    return implode($separator, $parts);
}

/** A verified Google Maps share URL, or the supplied generated-map fallback. */
function store_map_share_url(string $fallback = ''): string
{
    $url    = trim(setting('store_map_share_url'));
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true)) {
        return $url;
    }

    return $fallback;
}

/** Social links that have actually been filled in. */
function store_socials(): array
{
    $s   = store();
    $map = [
        'facebook'  => ['Facebook',  'fa-brands fa-facebook-f'],
        'instagram' => ['Instagram', 'fa-brands fa-instagram'],
        'twitter'   => ['X',         'fa-brands fa-x-twitter'],
        'tiktok'    => ['TikTok',    'fa-brands fa-tiktok'],
        'youtube'   => ['YouTube',   'fa-brands fa-youtube'],
    ];

    $out = [];
    foreach ($map as $key => [$label, $icon]) {
        if (!empty($s[$key])) {
            $out[] = ['key' => $key, 'label' => $label, 'icon' => $icon, 'url' => $s[$key]];
        }
    }
    return $out;
}

/** Day names, Monday first, matching store_hours.day_index. */
function day_names(bool $short = false): array
{
    return $short
        ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
        : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
}

/** All seven days of opening hours, indexed 0–6, always complete. */
function store_hours(): array
{
    static $hours = null;

    if ($hours === null) {
        $hours = [];
        foreach (db_all('SELECT * FROM store_hours WHERE store_id = 1 ORDER BY day_index') as $row) {
            $hours[(int)$row['day_index']] = $row;
        }
        for ($i = 0; $i < 7; $i++) {
            if (!isset($hours[$i])) {
                $hours[$i] = ['day_index' => $i, 'is_closed' => 1, 'open_time' => null, 'close_time' => null, 'note' => null];
            }
        }
        ksort($hours);
    }

    return $hours;
}

/** "11:00:00" → "11:00 am" */
function fmt_time(?string $time): string
{
    if (!$time) {
        return '';
    }
    $ts = strtotime($time);
    if ($ts === false) {
        return (string)$time;
    }
    // 23:59 reads better as midnight on an opening-hours list.
    if (date('H:i', $ts) === '23:59') {
        return '12:00 am';
    }
    return strtolower(date('g:i a', $ts));
}

/** One day's hours as readable text. */
function fmt_day_hours(array $row): string
{
    if (!empty($row['is_closed']) || empty($row['open_time'])) {
        return 'Closed';
    }
    return fmt_time($row['open_time']) . ' – ' . fmt_time($row['close_time']);
}

/**
 * All seven days listed separately, ready to print:
 * [ ['index' => 2, 'day' => 'Wednesday', 'short' => 'Wed',
 *    'hours' => '11:00 am – 10:00 pm', 'open' => '11:00 am', 'close' => '10:00 pm',
 *    'closed' => false, 'note' => '', 'is_today' => true], … ]
 */
function store_hours_days(): array
{
    $long     = day_names();
    $short    = day_names(true);
    $todayIdx = (int)date('N') - 1;          // Monday = 0
    $out      = [];

    foreach (store_hours() as $i => $row) {
        $closed = !empty($row['is_closed']) || empty($row['open_time']);

        $out[] = [
            'index'    => $i,
            'day'      => $long[$i],
            'short'    => $short[$i],
            'hours'    => fmt_day_hours($row),
            'open'     => $closed ? '' : fmt_time($row['open_time']),
            'close'    => $closed ? '' : fmt_time($row['close_time']),
            'closed'   => $closed,
            'note'     => trim((string)($row['note'] ?? '')),
            'is_today' => $i === $todayIdx,
        ];
    }

    return $out;
}

/** Is the shop open right now? Returns [open(bool), message(string)]. */
function store_open_status(): array
{
    $rows     = store_hours();
    $todayIdx = (int)date('N') - 1;              // Monday = 0
    $today    = $rows[$todayIdx] ?? null;
    $now      = time();

    if ($today && empty($today['is_closed']) && !empty($today['open_time'])) {
        $open  = strtotime(date('Y-m-d ') . $today['open_time']);
        $close = strtotime(date('Y-m-d ') . $today['close_time']);
        if ($close <= $open) {
            $close = strtotime('+1 day', $close);   // closes after midnight
        }
        if ($now >= $open && $now <= $close) {
            return [true, 'Open now until ' . fmt_time($today['close_time'])];
        }
        if ($now < $open) {
            return [false, 'Opens today at ' . fmt_time($today['open_time'])];
        }
    }

    // Find the next day that is open.
    for ($i = 1; $i <= 7; $i++) {
        $idx = ($todayIdx + $i) % 7;
        $row = $rows[$idx] ?? null;
        if ($row && empty($row['is_closed']) && !empty($row['open_time'])) {
            $names = day_names();
            $label = ($i === 1) ? 'tomorrow' : $names[$idx];
            return [false, 'Opens ' . $label . ' at ' . fmt_time($row['open_time'])];
        }
    }

    return [false, 'Closed'];
}

// =====================================================================
//  Menu
// =====================================================================

/** Active categories, ordered. */
function menu_categories(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db_all("SELECT * FROM menu_categories $where ORDER BY sort_order, id");
}

/** Active items for one category. */
function menu_items(int $categoryId, bool $activeOnly = true): array
{
    $where = $activeOnly ? 'AND is_active = 1' : '';
    return db_all(
        "SELECT * FROM menu_items WHERE category_id = ? $where ORDER BY sort_order, id",
        [$categoryId]
    );
}

/** Featured items for the home page preview. */
function featured_items(int $limit = 6): array
{
    $limit = max(1, min(24, $limit));
    return db_all(
        "SELECT i.*, c.name AS category_name, c.slug AS category_slug
           FROM menu_items i
           JOIN menu_categories c ON c.id = i.category_id
          WHERE i.is_featured = 1 AND i.is_active = 1 AND c.is_active = 1
          ORDER BY i.sort_order, i.id
          LIMIT $limit"
    );
}

/** Order Online links for the navbar dropdown and the order page. */
function order_links(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db_all("SELECT * FROM order_links $where ORDER BY sort_order, id");
}

/** Home page "why us" cards. */
function home_features(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return db_all("SELECT * FROM home_features $where ORDER BY sort_order, id");
}

// =====================================================================
//  Newsletter sign-up memory
//
//  A successful sign-up is remembered in a cookie so somebody coming back
//  to the site sees "you are on the list" instead of an empty form they
//  have already filled in once, with a button to swap in another address.
//
//  The cookie is signed. Not because the state is sensitive — it decides
//  which of two panels to draw — but because the value is echoed back on
//  the page and checked against the subscribers table. Signing it means an
//  address has to be one we put there ourselves, so nobody can hand-craft
//  a cookie to either plant text on the page or probe whether some other
//  address is on the list.
//
//  It is only ever a hint. The list itself lives in the database, which is
//  what newsletter_state() actually trusts.
// =====================================================================

/** Cookie holding the remembered sign-up. */
const NEWSLETTER_COOKIE = 'unika_newsletter';

/** How long a remembered sign-up survives, in seconds. */
const NEWSLETTER_COOKIE_TTL = 31536000;      // one year

/** Shared cookie flags, so setting and clearing always agree. */
function newsletter_cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'httponly' => true,                  // only ever read by PHP
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
    ];
}

/** Remember that this browser signed up with $email. */
function newsletter_remember(string $email, string $name = ''): void
{
    $payload = json_encode(
        ['e' => $email, 'n' => $name, 't' => time()],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $value = base64_encode($payload) . '.' . hash_hmac('sha256', $payload, app_secret());

    setcookie(NEWSLETTER_COOKIE, $value, newsletter_cookie_options(time() + NEWSLETTER_COOKIE_TTL));
    $_COOKIE[NEWSLETTER_COOKIE] = $value;    // visible to the rest of this request too
}

/** Forget the remembered sign-up, so the form comes back. */
function newsletter_forget(): void
{
    if (!headers_sent()) {
        setcookie(NEWSLETTER_COOKIE, '', newsletter_cookie_options(time() - 3600));
    }
    unset($_COOKIE[NEWSLETTER_COOKIE]);
}

/** The address in the cookie, or null when there is none we signed. */
function newsletter_remembered(): ?array
{
    $raw = (string)($_COOKIE[NEWSLETTER_COOKIE] ?? '');
    if ($raw === '' || substr_count($raw, '.') !== 1) {
        return null;
    }

    [$encoded, $signature] = explode('.', $raw, 2);
    $payload = base64_decode($encoded, true);
    if ($payload === false
        || !hash_equals(hash_hmac('sha256', $payload, app_secret()), $signature)) {
        return null;
    }

    $data  = json_decode($payload, true);
    $email = is_array($data) ? trim((string)($data['e'] ?? '')) : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    return ['email' => $email, 'name' => trim((string)($data['n'] ?? ''))];
}

/**
 * Feedback from a sign-up attempt, carried across the redirect.
 *
 * Kept apart from flash(): the sign-up band sits halfway down the home page,
 * so anything it has to say belongs inside the band the visitor is looking at
 * rather than in the hero at the top, which they have already scrolled past.
 */
function nl_notice(string $message, string $type = 'success'): void
{
    $_SESSION['nl_notices'][] = ['message' => $message, 'type' => $type];
}

/** Read and clear the queued sign-up feedback. */
function nl_notices_take(): array
{
    $notices = $_SESSION['nl_notices'] ?? [];
    unset($_SESSION['nl_notices']);

    return is_array($notices) ? $notices : [];
}

/**
 * The confirmed sign-up for this browser, or null when there is not one.
 *
 * The cookie only says what this browser did; the table says what is true
 * now. Somebody who has since unsubscribed — from another device, or with
 * the one-click link in an email — gets the form back rather than a panel
 * telling them they are subscribed when they are not.
 */
function newsletter_state(): ?array
{
    static $state = false;                   // false = not looked up yet

    if ($state !== false) {
        return $state;
    }

    $state      = null;
    $remembered = newsletter_remembered();

    if ($remembered !== null) {
        $row = db_one(
            'SELECT name, is_active FROM subscribers WHERE email = ?',
            [$remembered['email']]
        );

        if ($row && (int)$row['is_active'] === 1) {
            $state = [
                'email' => $remembered['email'],
                'name'  => trim((string)($row['name'] ?? '')) ?: $remembered['name'],
            ];
        } else {
            newsletter_forget();             // gone from the list, so drop the cookie
        }
    }

    return $state;
}

// =====================================================================
//  CSRF
// =====================================================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    $token = $token ?? ($_POST['_csrf'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/** Stop the request when a POST arrives without a valid token. */
function csrf_require(): void
{
    if (!csrf_verify()) {
        // 403 rather than 419 — Apache re-maps unknown status codes to a 500.
        http_response_code(403);
        exit('Your session expired. Please go back, reload the page and try again.');
    }
}

// =====================================================================
//  Google reCAPTCHA (v3 score-based verification)
// =====================================================================

/** True when both reCAPTCHA keys have been configured in Settings. */
function recaptcha_is_enabled(): bool
{
    return trim(setting('google_recaptcha_site_key')) !== ''
        && trim(setting('google_recaptcha_secret_key')) !== '';
}

/** Verify a browser's reCAPTCHA v3 response, score, and intended action with Google. */
function recaptcha_verify(string $token, string $expectedAction): array
{
    if (!recaptcha_is_enabled()) {
        return [true, ''];
    }

    $token = trim($token);
    if ($token === '') {
        return [false, 'Security verification could not be completed. Please try again.'];
    }

    $payload = [
        'secret'   => setting('google_recaptcha_secret_key'),
        'response' => $token,
    ];
    $ip = client_ip();
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $payload['remoteip'] = $ip;
    }

    $body = false;
    $data = http_build_query($payload, '', '&');

    if (function_exists('curl_init')) {
        $curl = curl_init('https://www.google.com/recaptcha/api/siteverify');
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($curl);
            curl_close($curl);
        }
    } else {
        $context = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data,
            'timeout' => 10,
        ]]);
        $body = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    }

    $result = is_string($body) ? json_decode($body, true) : null;
    if (!is_array($result) || empty($result['success'])) {
        return [false, 'We could not verify the security check. Please try again.'];
    }

    $action = (string)($result['action'] ?? '');
    $score  = $result['score'] ?? null;
    if (!hash_equals($expectedAction, $action) || !is_numeric($score) || (float)$score < 0.5) {
        return [false, 'We could not verify the security check. Please try again.'];
    }

    return [true, ''];
}

// =====================================================================
//  Flash messages
// =====================================================================

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function flash_take(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Redirect and stop. */
function redirect(string $to): void
{
    header('Location: ' . url($to));
    exit;
}

// =====================================================================
//  Secrets
// =====================================================================

/**
 * Mask an API key for display — first and last few characters only.
 * "AC8015b070fe0d5e1b9e2c5d97c630f5e0" → "AC80••••••••••••••••••••••••••f5e0"
 */
function mask_secret(?string $value, int $head = 4, int $tail = 4): string
{
    $value = trim((string)$value);
    $len   = strlen($value);

    if ($len === 0) {
        return '';
    }
    if ($len <= $head + $tail) {
        return str_repeat('•', $len);
    }

    return substr($value, 0, $head)
         . str_repeat('•', min(24, $len - $head - $tail))
         . substr($value, -$tail);
}

// =====================================================================
//  File uploads
// =====================================================================

/**
 * Validate and store an uploaded image, converting to WebP when GD allows it.
 * Returns the path to save in the database, or null with $error filled in.
 */
function save_upload(array $file, string $folder = 'menu', string $prefix = 'img', ?string &$error = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;                                   // nothing was chosen
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The file could not be uploaded (error code ' . $file['error'] . ').';
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        $error = 'That image is larger than 8 MB.';
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a valid image.';
        return null;
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$info[2]])) {
        $error = 'Please upload a JPG, PNG, GIF or WebP image.';
        return null;
    }

    $folder = preg_replace('/[^a-z0-9_-]/i', '', $folder) ?: 'menu';
    $dir    = UPLOAD_DIR . '/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $error = 'The upload folder could not be created.';
        return null;
    }
    if (!is_writable($dir)) {
        $error = 'The upload folder is not writable: ' . $dir;
        return null;
    }

    $base = preg_replace('/[^a-z0-9_-]/i', '', $prefix) ?: 'img';
    $base .= '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

    // Re-encode through GD so every upload is resized, whatever the browser sent.
    // WebP is preferred; not every PHP build has it (XAMPP's GD often does not),
    // so we fall back to PNG for images with transparency and JPEG for the rest.
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));

        if ($src !== false) {
            $src = shrink_image($src, 1600);
            imagepalettetotruecolor($src);
            imagealphablending($src, true);
            imagesavealpha($src, true);

            $hasAlpha = in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true);

            $attempts = [];
            if (function_exists('imagewebp')) {
                $attempts[] = ['webp', fn($im, $path) => imagewebp($im, $path, 82)];
            }
            if ($hasAlpha && function_exists('imagepng')) {
                $attempts[] = ['png', fn($im, $path) => imagepng($im, $path, 6)];
            }
            if (function_exists('imagejpeg')) {
                $attempts[] = ['jpg', function ($im, $path) {
                    // JPEG has no transparency — flatten onto white first.
                    $flat = imagecreatetruecolor(imagesx($im), imagesy($im));
                    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                    imagecopy($flat, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                    $ok = imagejpeg($flat, $path, 85);
                    imagedestroy($flat);
                    return $ok;
                }];
            }

            foreach ($attempts as [$ext, $encode]) {
                $target = $dir . '/' . $base . '.' . $ext;
                if ($encode($src, $target)) {
                    imagedestroy($src);
                    @chmod($target, 0664);
                    return UPLOAD_URL . '/' . $folder . '/' . $base . '.' . $ext;
                }
                @unlink($target);
            }

            imagedestroy($src);
        }
    }

    // Fallback: keep the original file as-is.
    $target = $dir . '/' . $base . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        $error = 'The image could not be saved to disk.';
        return null;
    }
    @chmod($target, 0664);
    return UPLOAD_URL . '/' . $folder . '/' . $base . '.' . $allowed[$info[2]];
}

/** Scale an image resource down so its longest side is at most $max pixels. */
function shrink_image($src, int $max)
{
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= $max && $h <= $max) {
        return $src;
    }

    $ratio = $w >= $h ? $max / $w : $max / $h;
    $nw    = max(1, (int)round($w * $ratio));
    $nh    = max(1, (int)round($h * $ratio));

    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    return $dst;
}

/** Remove a previously uploaded file (never touches bundled assets). */
function delete_upload(?string $path): void
{
    $path = trim((string)$path);
    if ($path === '' || strpos($path, UPLOAD_URL . '/') !== 0) {
        return;
    }
    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

// =====================================================================
//  Twilio SMS
// =====================================================================

/**
 * Send an SMS through Twilio using the credentials stored in Settings.
 * Returns ['status' => sent|failed|skipped, 'detail' => '…'].
 */
function send_sms(string $to, string $body): array
{
    $sid   = trim(setting('twilio_account_sid'));
    $token = trim(setting('twilio_auth_token'));
    $from  = trim(setting('twilio_from_number'));
    $to    = trim($to);

    if (setting('sms_enabled', '0') !== '1') {
        return ['status' => 'skipped', 'detail' => 'SMS forwarding is switched off in Settings.'];
    }
    if ($sid === '' || $token === '' || $from === '' || $to === '') {
        return ['status' => 'skipped', 'detail' => 'Twilio SID, token, from-number or to-number is missing.'];
    }
    if (!function_exists('curl_init')) {
        return ['status' => 'failed', 'detail' => 'PHP cURL extension is not available.'];
    }

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERPWD        => $sid . ':' . $token,
        CURLOPT_POSTFIELDS     => http_build_query([
            'From' => $from,
            'To'   => $to,
            'Body' => mb_substr($body, 0, 1500),
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => 'failed', 'detail' => 'Connection error: ' . $curlErr];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['status' => 'sent', 'detail' => 'Twilio SID ' . ($data['sid'] ?? 'unknown')];
    }

    return [
        'status' => 'failed',
        'detail' => 'Twilio ' . $httpCode . ': ' . ($data['message'] ?? 'unknown error'),
    ];
}

// =====================================================================
//  Misc
// =====================================================================

/** Mark the active navbar link. Takes "menu" or "menu.php" — either spelling. */
function nav_active(string $page): string
{
    $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    return $current === basename($page, '.php') ? ' active' : '';
}

/** Value of a form field after a failed submission (see includes/app.php). */
function old(string $key, $default = '')
{
    return $GLOBALS['__old'][$key] ?? $default;
}

/** Remember submitted values so the form can be redrawn after a redirect. */
function old_set(array $data): void
{
    unset($data['_csrf'], $data['g-recaptcha-response']);
    $_SESSION['old'] = $data;
}

/** Visitor IP, for logging form submissions. */
function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
