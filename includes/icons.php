<?php
/**
 * The site icon set — favicons, the iOS home-screen icon and the web manifest.
 *
 * A browser tab, a Google result, an iOS home screen and an Android launcher
 * all want a different picture, and none of them will improvise one for you:
 *
 *   · Google only shows a favicon in search results when it is square and its
 *     side is a multiple of 48. A 180×166 image is silently dropped.
 *   · iOS composites any transparency in an apple-touch-icon onto BLACK, so a
 *     logo drawn with a black outline disappears into the background. That one
 *     gets an opaque plate.
 *   · Android's maskable icons are cropped to whatever shape the launcher
 *     fancies, so the mark has to sit inside the middle 80%.
 *   · Google's favicon crawler reads BMP, GIF, ICO, JPEG, PNG and SVG — not
 *     WebP. Everything generated here is therefore PNG or ICO, even though the
 *     rest of the site's uploads are converted to WebP.
 *
 * generate_icon_set() is used twice: once to build the bundled set under
 * assets/img/favicon/, and again whenever an admin uploads a new favicon, so
 * an upload can never leave the site with a worse icon than it shipped with.
 */

/** Where the bundled icons live, relative to the project root. */
const ICON_BUNDLED_DIR = 'assets/img/favicon';

/** Plate colour behind the icons that cannot be transparent (--cream). */
const ICON_PLATE = [0xff, 0xf8, 0xec];

/** The sizes every set contains, and how each one is drawn. */
function icon_recipe(): array
{
    return [
        // name          size  opaque  inset
        'favicon-16'  => [16,  false, 0.00],
        'favicon-32'  => [32,  false, 0.00],
        'favicon-48'  => [48,  false, 0.00],
        'favicon-96'  => [96,  false, 0.00],
        'favicon-192' => [192, false, 0.00],
        'favicon-512' => [512, false, 0.00],
        'apple-touch' => [180, true,  0.07],   // iOS flattens alpha onto black
        'maskable'    => [512, true,  0.18],   // Android crops to the safe zone
    ];
}

// =====================================================================
//  Drawing
// =====================================================================

/**
 * Crop fully transparent margins off an image so the mark fills the icon
 * rather than floating in whatever padding the source file happened to have.
 */
function icon_trim_alpha($im)
{
    $w = imagesx($im);
    $h = imagesy($im);
    $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            if ((imagecolorat($im, $x, $y) >> 24) < 120) {     // not near-invisible
                if ($x < $minX) { $minX = $x; }
                if ($x > $maxX) { $maxX = $x; }
                if ($y < $minY) { $minY = $y; }
                if ($y > $maxY) { $maxY = $y; }
            }
        }
    }

    if ($maxX < 0) {
        return $im;                                            // nothing visible
    }

    $cw  = $maxX - $minX + 1;
    $ch  = $maxY - $minY + 1;
    $dst = imagecreatetruecolor($cw, $ch);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopy($dst, $im, 0, 0, $minX, $minY, $cw, $ch);

    return $dst;
}

/**
 * One square icon.
 *
 * $bg    null for transparency, [r,g,b] for an opaque plate
 * $inset how much of the canvas to leave empty around the mark, 0–0.4
 */
function icon_square($logo, int $size, ?array $bg, float $inset = 0.0)
{
    $lw = imagesx($logo);
    $lh = imagesy($logo);

    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    if ($bg === null) {
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    } else {
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]));
    }
    imagealphablending($canvas, true);

    $box   = (int)round($size * (1 - 2 * $inset));
    $scale = min($box / $lw, $box / $lh);
    $dw    = max(1, (int)round($lw * $scale));
    $dh    = max(1, (int)round($lh * $scale));

    imagecopyresampled(
        $canvas, $logo,
        (int)round(($size - $dw) / 2), (int)round(($size - $dh) / 2),
        0, 0, $dw, $dh, $lw, $lh
    );

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    return $canvas;
}

// =====================================================================
//  .ico
// =====================================================================

/** One 32-bit BMP record for an .ico container. */
function icon_ico_record($im): string
{
    $w = imagesx($im);
    $h = imagesy($im);

    // BITMAPINFOHEADER. The height is doubled because the record carries the
    // colour rows and the legacy 1-bit AND mask back to back.
    $header = pack('VVVvvVVVVVV', 40, $w, $h * 2, 1, 32, 0, 0, 0, 0, 0, 0);

    $pixels = '';
    for ($y = $h - 1; $y >= 0; $y--) {                 // DIB rows run bottom-up
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            $a = 255 - (int)round((($c >> 24) & 0x7f) * 255 / 127);
            $pixels .= pack('CCCC', $c & 0xff, ($c >> 8) & 0xff, ($c >> 16) & 0xff, $a);
        }
    }

    // 32-bit records carry their own alpha, so the AND mask is left opaque.
    $mask = str_repeat(str_repeat("\0", (int)(ceil($w / 32) * 4)), $h);

    return $header . $pixels . $mask;
}

/** Write a multi-resolution .ico built from a list of GD images. */
function icon_write_ico(array $images, string $path): bool
{
    $records = array_map('icon_ico_record', $images);
    $offset  = 6 + 16 * count($records);
    $dir     = pack('vvv', 0, 1, count($records));

    foreach ($images as $i => $im) {
        $w = imagesx($im);
        $h = imagesy($im);
        $dir .= pack(
            'CCCCvvVV',
            $w >= 256 ? 0 : $w,
            $h >= 256 ? 0 : $h,
            0, 0, 1, 32,
            strlen($records[$i]),
            $offset
        );
        $offset += strlen($records[$i]);
    }

    return file_put_contents($path, $dir . implode('', $records)) !== false;
}

// =====================================================================
//  Generating a set
// =====================================================================

/**
 * Build a complete icon set from one source image.
 *
 *   $sourceFile  absolute path to a PNG/JPEG/GIF/WebP
 *   $destDir     absolute path to write into (created when missing)
 *   $base        filename prefix, so several sets can share one folder
 *
 * Returns the written filenames keyed by recipe name plus 'ico', or null with
 * $error set. Nothing is written unless every size succeeds.
 */
function generate_icon_set(string $sourceFile, string $destDir, string $base = 'icon', ?string &$error = null): ?array
{
    if (!function_exists('imagecreatefromstring')) {
        $error = 'PHP is built without the GD image library, so icons cannot be generated.';
        return null;
    }
    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
        $error = 'The icon folder could not be created.';
        return null;
    }
    if (!is_writable($destDir)) {
        $error = 'The icon folder is not writable: ' . $destDir;
        return null;
    }

    $raw = @file_get_contents($sourceFile);
    if ($raw === false) {
        $error = 'The uploaded image could not be read.';
        return null;
    }

    $src = @imagecreatefromstring($raw);
    unset($raw);
    if ($src === false) {
        $error = 'That file is not an image PHP can read.';
        return null;
    }

    imagepalettetotruecolor($src);
    imagealphablending($src, false);
    imagesavealpha($src, true);

    $logo    = icon_trim_alpha($src);
    $written = [];
    $paths   = [];

    $prefix = $base === '' ? '' : $base . '-';

    foreach (icon_recipe() as $name => [$size, $opaque, $inset]) {
        $file = $prefix . $name . '.png';
        $im   = icon_square($logo, $size, $opaque ? ICON_PLATE : null, $inset);
        $ok   = imagepng($im, $destDir . '/' . $file, 9);

        if (!$ok) {
            foreach ($paths as $done) {
                @unlink($done);
            }
            $error = 'The icon set could not be written to disk.';
            return null;
        }

        @chmod($destDir . '/' . $file, 0664);
        $written[$name] = $file;
        $paths[]        = $destDir . '/' . $file;
    }

    // The .ico still matters: it is what a browser asks for by name when a
    // page forgets its <link>, and what several crawlers try first.
    $icoImages = [];
    foreach ([16, 32, 48] as $size) {
        $icoImages[] = icon_square($logo, $size, null, 0.0);
    }
    $icoFile = $prefix . 'favicon.ico';
    if (icon_write_ico($icoImages, $destDir . '/' . $icoFile)) {
        @chmod($destDir . '/' . $icoFile, 0664);
        $written['ico'] = $icoFile;
    }

    return $written;
}

/** Remove a generated set, given the prefix stored in the favicon_set setting. */
function delete_icon_set(?string $base): void
{
    $base = trim((string)$base);
    if ($base === '' || strpos($base, UPLOAD_URL . '/') !== 0) {
        return;                                    // never touch bundled artwork
    }

    foreach (array_keys(icon_recipe()) as $name) {
        $file = APP_ROOT . '/' . $base . '-' . $name . '.png';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    $ico = APP_ROOT . '/' . $base . '-favicon.ico';
    if (is_file($ico)) {
        @unlink($ico);
    }
}

/**
 * Handle an admin favicon upload: validate it, build the whole set from it and
 * return the prefix to store in the favicon_set setting.
 *
 * This deliberately does not go through save_upload(). That helper re-encodes
 * everything to WebP, which is a fine choice for menu photos and the wrong one
 * here — Google's favicon crawler does not read WebP, so an uploaded icon
 * would vanish from search results. It also leaves the image whatever shape it
 * arrived in, and a non-square favicon is ignored outright.
 *
 * Returns null when nothing was uploaded, or null with $error set on failure.
 */
function save_icon_upload(array $file, ?string &$error = null): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;                                       // nothing was chosen
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'The file could not be uploaded (error code ' . $file['error'] . ').';
        return null;
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        $error = 'That image is larger than 8 MB.';
        return null;
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $error = 'That file is not a valid image.';
        return null;
    }
    if (!in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
        $error = 'Please upload a JPG, PNG, GIF or WebP image — it is converted to the right formats here.';
        return null;
    }
    if (min((int)$info[0], (int)$info[1]) < 48) {
        $error = 'That image is too small to make a favicon from. Use one at least 180×180.';
        return null;
    }

    $dir  = UPLOAD_DIR . '/site/icons';
    $base = 'icon_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

    $set = generate_icon_set($file['tmp_name'], $dir, $base, $error);
    if ($set === null) {
        return null;
    }

    return UPLOAD_URL . '/site/icons/' . $base;
}

// =====================================================================
//  Reading the current set
// =====================================================================

/**
 * Every icon URL the <head> needs, already cache-stamped.
 *
 * An admin-uploaded set wins when it is complete; anything missing falls back
 * to the bundled artwork, so a half-written upload can never leave the site
 * with no icon at all.
 */
function site_icons(): array
{
    static $icons = null;
    if ($icons !== null) {
        return $icons;
    }

    $custom = trim(setting('favicon_set'));
    $names  = array_keys(icon_recipe());

    /** First of the candidate paths that exists on disk. */
    $pick = function (string $name) use ($custom): string {
        if ($custom !== '') {
            $candidate = $custom . '-' . $name . '.png';
            if (is_file(APP_ROOT . '/' . $candidate)) {
                return $candidate;
            }
        }
        return ICON_BUNDLED_DIR . '/' . $name . '.png';
    };

    $icons = [];
    foreach ($names as $name) {
        $icons[$name] = asset($pick($name));
    }

    // The .ico sits at the project root as well, because that is the one
    // address a browser will try without being told.
    $ico = ($custom !== '' && is_file(APP_ROOT . '/' . $custom . '-favicon.ico'))
        ? $custom . '-favicon.ico'
        : 'favicon.ico';
    $icons['ico'] = asset($ico);

    return $icons;
}

/**
 * The bundled set uses plain names (favicon-32.png); the generator writes
 * prefixed ones. This keeps both readable through one lookup.
 */
function site_icon(string $name): string
{
    return site_icons()[$name] ?? site_icons()['favicon-192'];
}
