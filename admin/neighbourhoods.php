<?php
/**
 * Admin · Neighbourhoods
 *
 * The local landing pages — /kebab-in-king-west-toronto and its siblings.
 * One row is one page: slug, copy, the four logistics facts, the dish picks,
 * the questions, and which sibling pages it links out to.
 *
 * "Published" is the release switch and it is deliberately blunt. An
 * unpublished row is a 404, is absent from sitemap.xml, and is linked from
 * nowhere — which is how these go live a batch at a time instead of arriving
 * as a wall of near-identical pages, the pattern Google's spam policy names.
 *
 * A page is only worth publishing if it says something that stops being true
 * when you swap the neighbourhood name out of it. The hints below say so at
 * each field that matters.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

require_methods(['GET', 'POST']);

/** Icons offered for the four logistics facts. */
const NB_ICON_CHOICES = [
    'fa-solid fa-train-subway'      => 'Subway',
    'fa-solid fa-train-tram'        => 'Streetcar',
    'fa-solid fa-bus-simple'        => 'Bus',
    'fa-solid fa-train'             => 'GO train',
    'fa-solid fa-person-walking'    => 'Walking',
    'fa-solid fa-bicycle'           => 'Cycling',
    'fa-solid fa-car-side'          => 'Driving',
    'fa-solid fa-square-parking'    => 'Parking',
    'fa-solid fa-route'             => 'Route',
    'fa-solid fa-traffic-light'     => 'Traffic',
    'fa-solid fa-landmark'          => 'Landmark',
    'fa-solid fa-triangle-exclamation' => 'Heads-up',
    'fa-solid fa-map-location-dot'  => 'Map',
    'fa-solid fa-clock'             => 'Time',
    'fa-solid fa-location-dot'      => 'Landmark',
];

/** Every text column the form writes, so save/insert stay in one list. */
const NB_TEXT_FIELDS = [
    'page_title', 'meta_desc', 'meta_keywords', 'og_image', 'og_image_alt',
    'hero_eyebrow', 'hero_title', 'hero_text', 'hero_image', 'hero_image_alt',
    'intro_title', 'intro_text', 'travel_title', 'travel_text',
    'fact1_icon', 'fact1_label', 'fact1_text',
    'fact2_icon', 'fact2_label', 'fact2_text',
    'fact3_icon', 'fact3_label', 'fact3_text',
    'fact4_icon', 'fact4_label', 'fact4_text',
    'picks_title', 'picks_text',
    'pick1_dish', 'pick1_note', 'pick2_dish', 'pick2_note', 'pick3_dish', 'pick3_note',
    'faq1_q', 'faq1_a', 'faq2_q', 'faq2_a', 'faq3_q', 'faq3_a',
    'closing_title', 'closing_text', 'neighbours',
];

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id ? db_one('SELECT * FROM neighbourhoods WHERE id = ?', [$id]) : null;
    $id     = $row ? (int)$row['id'] : 0;      // never trust the posted id

    // ---------------------------------------------------------------
    //  Save
    // ---------------------------------------------------------------
    if ($action === 'save') {
        $backTo = 'admin/neighbourhoods.php' . ($id ? '?edit=' . $id : '') . '#nb-form';

        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $slug = $slug !== '' ? slugify($slug) : slugify('kebab-in-' . $name . '-toronto');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Give the neighbourhood a name.';
        }

        // The rewrite in .htaccess only routes the kebab-in- shape. A slug
        // without it saves happily and then 404s on the public site, which is
        // a confusing way to find out, so it is enforced here instead.
        if (!preg_match('/^kebab-in-[a-z0-9-]+$/', $slug)) {
            $errors[] = 'The address must look like “kebab-in-king-west-toronto” — lowercase, '
                      . 'starting with “kebab-in-”. Anything else will not resolve on the site.';
        }

        $clash = db_one('SELECT id FROM neighbourhoods WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($clash) {
            $errors[] = 'Another neighbourhood already uses the address “' . $slug . '”.';
        }

        if ($errors) {
            old_set($_POST);
            foreach ($errors as $message) {
                flash($message, 'error');
            }
            redirect($backTo);
        }

        $orNull = static fn(string $v): ?string => $v === '' ? null : $v;

        $values = [];
        foreach (NB_TEXT_FIELDS as $field) {
            // text columns take the lot; everything else is clipped to width
            $limit  = in_array($field, ['intro_text', 'travel_text', 'faq1_a', 'faq2_a', 'faq3_a'], true)
                ? 8000
                : 500;
            $values[$field] = $orNull(mb_substr(trim((string)($_POST[$field] ?? '')), 0, $limit));
        }

        $batch   = max(1, min(9, (int)($_POST['batch'] ?? 1)));
        $active  = empty($_POST['is_active']) ? 0 : 1;
        $sortRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sort    = $sortRaw === ''
            ? ($row ? (int)$row['sort_order']
                    : (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM neighbourhoods')['n'] ?? 1))
            : (int)$sortRaw;

        $columns = array_merge(['slug', 'name', 'batch', 'sort_order', 'is_active'], NB_TEXT_FIELDS);
        $params  = array_merge([$slug, $name, $batch, $sort, $active], array_values($values));

        if ($row) {
            $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", $columns));
            db_run("UPDATE neighbourhoods SET $set WHERE id = ?", array_merge($params, [$id]));
            flash('“' . e($name) . '” was updated.', 'success');
        } else {
            $cols  = implode(', ', array_map(static fn(string $c): string => "`$c`", $columns));
            $holds = implode(', ', array_fill(0, count($columns), '?'));
            db_run("INSERT INTO neighbourhoods ($cols) VALUES ($holds)", $params);
            flash('“' . e($name) . '” was added.', 'success');
        }

        redirect('admin/neighbourhoods.php');
    }

    // ---------------------------------------------------------------
    //  Publish / unpublish — the staged-rollout switch
    // ---------------------------------------------------------------
    if ($action === 'toggle') {
        if (!$row) {
            flash('That neighbourhood no longer exists.', 'error');
            redirect('admin/neighbourhoods.php');
        }

        $new = empty($row['is_active']) ? 1 : 0;
        db_run('UPDATE neighbourhoods SET is_active = ? WHERE id = ?', [$new, $id]);
        flash(
            '“' . e($row['name']) . '” is now '
            . ($new
                ? 'live — it is in the sitemap and linked from the site.'
                : 'unpublished — the page now answers 404 and has left the sitemap.'),
            'success'
        );
        redirect('admin/neighbourhoods.php');
    }

    // ---------------------------------------------------------------
    //  Move up / down
    // ---------------------------------------------------------------
    if ($action === 'move') {
        if (!$row) {
            flash('That neighbourhood no longer exists.', 'error');
            redirect('admin/neighbourhoods.php');
        }

        $direction = (string)($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        $neighbour = $direction === 'up'
            ? db_one('SELECT * FROM neighbourhoods
                       WHERE (sort_order < ?) OR (sort_order = ? AND id < ?)
                    ORDER BY sort_order DESC, id DESC LIMIT 1',
                     [(int)$row['sort_order'], (int)$row['sort_order'], $id])
            : db_one('SELECT * FROM neighbourhoods
                       WHERE (sort_order > ?) OR (sort_order = ? AND id > ?)
                    ORDER BY sort_order, id LIMIT 1',
                     [(int)$row['sort_order'], (int)$row['sort_order'], $id]);

        if ($neighbour) {
            $mine  = (int)$row['sort_order'];
            $their = (int)$neighbour['sort_order'];
            if ($mine === $their) {
                [$mine, $their] = $direction === 'up' ? [$their - 1, $their] : [$their + 1, $their];
            }
            db_run('UPDATE neighbourhoods SET sort_order = ? WHERE id = ?', [$their, $id]);
            db_run('UPDATE neighbourhoods SET sort_order = ? WHERE id = ?', [$mine, (int)$neighbour['id']]);
        }

        redirect('admin/neighbourhoods.php');
    }

    // ---------------------------------------------------------------
    //  Delete
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$row) {
            flash('That neighbourhood no longer exists.', 'error');
            redirect('admin/neighbourhoods.php');
        }

        db_run('DELETE FROM neighbourhoods WHERE id = ?', [$id]);
        flash('“' . e($row['name']) . '” was deleted.', 'success');
        redirect('admin/neighbourhoods.php');
    }

    redirect('admin/neighbourhoods.php');
}

// =====================================================================
//  GET
// =====================================================================
$areas = neighbourhoods(false);         // unpublished rows included; this is the editor
$liveN = 0;
foreach ($areas as $area) {
    $liveN += empty($area['is_active']) ? 0 : 1;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId ? db_one('SELECT * FROM neighbourhoods WHERE id = ?', [$editId]) : null;
$formId  = (int)($editing['id'] ?? 0);
$useOld  = (int)old('id', -1) === $formId;

/** Field value: submitted values win after a failed save, then the edited row. */
$val = function (string $key, $fallback = '') use ($useOld, $editing) {
    if ($useOld) {
        return old($key, $fallback);
    }
    return $editing[$key] ?? $fallback;
};

/** The live menu, so the dish picks are chosen rather than typed. */
$menuChoices = db_all(
    "SELECT i.name, c.name AS category_name
       FROM menu_items i
       JOIN menu_categories c ON c.id = i.category_id
      WHERE i.is_active = 1 AND c.is_active = 1
   ORDER BY c.sort_order, c.id, i.sort_order, i.id"
);

$title     = 'Neighbourhoods';
$subtitle  = 'Local landing pages';
$activeNav = 'neighbourhoods';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-4">

  <!-- ================= the list ================= -->
  <div class="col-12">
    <div class="a-card">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-map-location-dot"></i> Neighbourhood pages</h2>
          <p class="a-card__sub">
            <?= (int)$liveN ?> live of <?= count($areas) ?>.
            Publish them a batch at a time and give each one three or four weeks before the next —
            a wall of near-identical local pages is the one shape Google actively penalises.
          </p>
        </div>
        <div class="a-card__actions">
          <a class="a-btn a-btn--sm" href="<?= e(admin_url('neighbourhoods.php')) ?>#nb-form">
            <i class="fa-solid fa-plus"></i> Add a neighbourhood
          </a>
        </div>
      </div>

      <?php if (!$areas): ?>
        <div class="a-empty">
          <i class="fa-solid fa-map-location-dot"></i>
          <p>No neighbourhood pages yet. Add the first one below.</p>
          <a class="a-btn" href="#nb-form"><i class="fa-solid fa-plus"></i> Add a neighbourhood</a>
        </div>
      <?php else: ?>
        <div class="a-table-wrap">
          <table class="a-table">
            <thead>
              <tr>
                <th aria-label="Order"></th>
                <th>Neighbourhood</th>
                <th>Batch</th>
                <th>Status</th>
                <th class="is-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($areas as $i => $area): ?>
              <?php $areaName = (string)$area['name']; ?>
              <tr<?= empty($area['is_active']) ? ' class="is-off"' : '' ?>>
                <td class="a-order-move">
                  <form method="post" data-guard>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="direction" value="up">
                    <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                    <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                            <?= $i === 0 ? 'disabled' : '' ?>
                            title="Move up" aria-label="Move <?= e($areaName) ?> up">
                      <i class="fa-solid fa-chevron-up"></i>
                    </button>
                  </form>
                  <form method="post" data-guard>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="direction" value="down">
                    <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                    <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                            <?= $i === count($areas) - 1 ? 'disabled' : '' ?>
                            title="Move down" aria-label="Move <?= e($areaName) ?> down">
                      <i class="fa-solid fa-chevron-down"></i>
                    </button>
                  </form>
                </td>

                <td>
                  <div class="a-table__name"><?= e($areaName) ?></div>
                  <?php if (trim((string)$area['hero_title']) !== ''): ?>
                    <div class="a-table__meta"><?= e(excerpt($area['hero_title'], 70)) ?></div>
                  <?php endif; ?>
                  <div class="a-table__meta a-order-url">
                    <?php if (!empty($area['is_active'])): ?>
                      <a href="<?= e(url($area['slug'])) ?>" target="_blank" rel="noopener">/<?= e($area['slug']) ?></a>
                    <?php else: ?>
                      /<?= e($area['slug']) ?>
                    <?php endif; ?>
                  </div>
                </td>

                <td class="a-muted"><?= (int)$area['batch'] ?></td>

                <td>
                  <?php if (!empty($area['is_active'])): ?>
                    <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Live</span>
                  <?php else: ?>
                    <span class="a-badge a-badge--off"><i class="fa-solid fa-eye-slash"></i> Draft</span>
                  <?php endif; ?>
                </td>

                <td class="is-actions">
                  <div class="a-flex a-flex--end">
                    <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                       href="<?= e(admin_url('neighbourhoods.php?edit=' . (int)$area['id'])) ?>#nb-form"
                       title="Edit <?= e($areaName) ?>" aria-label="Edit <?= e($areaName) ?>">
                      <i class="fa-solid fa-pen"></i>
                    </a>

                    <form method="post" data-guard>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                      <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                              title="<?= empty($area['is_active']) ? 'Publish' : 'Unpublish' ?> <?= e($areaName) ?>"
                              aria-label="<?= empty($area['is_active']) ? 'Publish' : 'Unpublish' ?> <?= e($areaName) ?>">
                        <i class="fa-solid fa-<?= empty($area['is_active']) ? 'eye' : 'eye-slash' ?>"></i>
                      </button>
                    </form>

                    <form method="post" data-guard
                          onsubmit="return confirm('Delete the <?= e($areaName) ?> page for good?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$area['id'] ?>">
                      <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon a-btn--danger" type="submit"
                              title="Delete <?= e($areaName) ?>" aria-label="Delete <?= e($areaName) ?>">
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ================= the form ================= -->
  <div class="col-12">
    <div class="a-card" id="nb-form">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title">
            <i class="fa-solid fa-<?= $editing ? 'pen' : 'plus' ?>"></i>
            <?= $editing ? 'Edit ' . e($editing['name']) : 'Add a neighbourhood' ?>
          </h2>
          <p class="a-card__sub">
            The test for every field below: if someone swapped the neighbourhood name for a
            different one, would the sentence become false? If it would still read fine, it is
            not worth publishing.
          </p>
        </div>
      </div>

      <form method="post" data-guard>
        <div class="a-card__body">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$formId ?>">

          <!-- ---------- identity ---------- -->
          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-tag"></i> The page</legend>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="a-field">
                <label class="a-field__label" for="nbName">Neighbourhood <span class="req">*</span></label>
                <input class="a-input" type="text" id="nbName" name="name" required maxlength="120"
                       value="<?= e($val('name')) ?>">
                <p class="a-field__hint">As locals write it — “King West”, not “King Street West Village”.</p>
              </div>
            </div>
            <div class="col-md-5">
              <div class="a-field">
                <label class="a-field__label" for="nbSlug">Web address</label>
                <input class="a-input" type="text" id="nbSlug" name="slug" maxlength="140"
                       placeholder="kebab-in-king-west-toronto" value="<?= e($val('slug')) ?>">
                <p class="a-field__hint">
                  Must start with <code>kebab-in-</code>. Leave blank and it is built from the name.
                  Changing it on a live page throws away everything that address has earned.
                </p>
              </div>
            </div>
            <div class="col-md-3">
              <div class="row g-3">
                <div class="col-6">
                  <div class="a-field">
                    <label class="a-field__label" for="nbBatch">Batch</label>
                    <input class="a-input" type="number" id="nbBatch" name="batch" min="1" max="9"
                           value="<?= e((string)$val('batch', '1')) ?>">
                    <p class="a-field__hint">Release group.</p>
                  </div>
                </div>
                <div class="col-6">
                  <div class="a-field">
                    <label class="a-field__label" for="nbSort">Order</label>
                    <input class="a-input" type="number" id="nbSort" name="sort_order"
                           value="<?= e((string)$val('sort_order', '')) ?>">
                    <p class="a-field__hint">Blank = last.</p>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12">
              <label class="a-check">
                <input type="checkbox" name="is_active" value="1" <?= $val('is_active', '0') ? 'checked' : '' ?>>
                <span>
                  Published — live on the site, in the sitemap, and linked from the footer.
                  Unpublished pages answer 404.
                </span>
              </label>
            </div>
          </div>

          <!-- ---------- search ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-magnifying-glass"></i> Search listing</legend>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="a-field">
                <label class="a-field__label" for="nbTitle">Page title</label>
                <input class="a-input" type="text" id="nbTitle" name="page_title" maxlength="180"
                       value="<?= e($val('page_title')) ?>">
                <p class="a-field__hint">Roughly 60 characters. The site name is added automatically.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="a-field">
                <label class="a-field__label" for="nbKeywords">Keywords</label>
                <input class="a-input" type="text" id="nbKeywords" name="meta_keywords" maxlength="255"
                       value="<?= e($val('meta_keywords')) ?>">
                <p class="a-field__hint">Optional; Google ignores these, but the field is here for parity.</p>
              </div>
            </div>
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbDesc">Search description</label>
                <textarea class="a-textarea" id="nbDesc" name="meta_desc" rows="2" maxlength="300"><?= e($val('meta_desc')) ?></textarea>
                <p class="a-field__hint">
                  Up to about 160 characters. Leave this blank and the page inherits the site-wide
                  blurb — which is how a set of pages ends up looking like duplicates of each other.
                </p>
              </div>
            </div>
            <div class="col-md-8">
              <div class="a-field">
                <label class="a-field__label" for="nbOg">Share image</label>
                <input class="a-input" type="text" id="nbOg" name="og_image" maxlength="255"
                       placeholder="uploads/site/king-west.jpg" value="<?= e($val('og_image')) ?>">
                <p class="a-field__hint">Path to an image, ideally about 1200×630. Its own photo per page.</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="a-field">
                <label class="a-field__label" for="nbOgAlt">Share image description</label>
                <input class="a-input" type="text" id="nbOgAlt" name="og_image_alt" maxlength="255"
                       value="<?= e($val('og_image_alt')) ?>">
              </div>
            </div>
          </div>

          <!-- ---------- hero ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-image"></i> Banner</legend>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="a-field">
                <label class="a-field__label" for="nbEyebrow">Eyebrow</label>
                <input class="a-input" type="text" id="nbEyebrow" name="hero_eyebrow" maxlength="120"
                       value="<?= e($val('hero_eyebrow')) ?>">
              </div>
            </div>
            <div class="col-md-8">
              <div class="a-field">
                <label class="a-field__label" for="nbHeroTitle">Headline</label>
                <input class="a-input" type="text" id="nbHeroTitle" name="hero_title" maxlength="180"
                       value="<?= e($val('hero_title')) ?>">
                <p class="a-field__hint">The page's main heading.</p>
              </div>
            </div>
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbHeroText">Standfirst</label>
                <textarea class="a-textarea" id="nbHeroText" name="hero_text" rows="2" maxlength="400"><?= e($val('hero_text')) ?></textarea>
              </div>
            </div>
            <div class="col-md-8">
              <div class="a-field">
                <label class="a-field__label" for="nbHeroImg">Banner image</label>
                <input class="a-input" type="text" id="nbHeroImg" name="hero_image" maxlength="255"
                       placeholder="assets/img/bg-stone.jpg" value="<?= e($val('hero_image')) ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="a-field">
                <label class="a-field__label" for="nbHeroImgAlt">Banner description</label>
                <input class="a-input" type="text" id="nbHeroImgAlt" name="hero_image_alt" maxlength="255"
                       value="<?= e($val('hero_image_alt')) ?>">
              </div>
            </div>
          </div>

          <!-- ---------- prose ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-align-left"></i> Opening section</legend>
          <div class="row g-3">
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbIntroTitle">Heading</label>
                <input class="a-input" type="text" id="nbIntroTitle" name="intro_title" maxlength="180"
                       value="<?= e($val('intro_title')) ?>">
              </div>
            </div>
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbIntroText">Copy</label>
                <textarea class="a-textarea" id="nbIntroText" name="intro_text" rows="8"><?= e($val('intro_text')) ?></textarea>
                <p class="a-field__hint">
                  Leave a blank line between paragraphs. Name real streets, real places, real
                  landmarks — this is the section that has to be about the neighbourhood rather
                  than about the restaurant.
                </p>
              </div>
            </div>
          </div>

          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-route"></i> Getting here</legend>
          <div class="row g-3">
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbTravelTitle">Heading</label>
                <input class="a-input" type="text" id="nbTravelTitle" name="travel_title" maxlength="180"
                       value="<?= e($val('travel_title')) ?>">
              </div>
            </div>
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbTravelText">Copy</label>
                <textarea class="a-textarea" id="nbTravelText" name="travel_text" rows="5"><?= e($val('travel_text')) ?></textarea>
                <p class="a-field__hint">Blank line between paragraphs.</p>
              </div>
            </div>
          </div>

          <!-- ---------- the four facts ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-list-check"></i> Four checkable facts</legend>
          <p class="a-field__hint mb-3">
            Route numbers, stop names, walking minutes, where to park. Things a reader can verify —
            they are what makes the page worth keeping, and they are the part no one can spin.
          </p>
          <div class="row g-3">
            <?php foreach ([1, 2, 3, 4] as $n): ?>
              <div class="col-lg-6">
                <div class="row g-2">
                  <div class="col-5">
                    <div class="a-field">
                      <label class="a-field__label" for="nbFact<?= $n ?>Icon">Icon <?= $n ?></label>
                      <select class="a-select" id="nbFact<?= $n ?>Icon" name="fact<?= $n ?>_icon">
                        <option value="">—</option>
                        <?php foreach (NB_ICON_CHOICES as $cls => $label): ?>
                          <option value="<?= e($cls) ?>" <?= $val("fact{$n}_icon") === $cls ? 'selected' : '' ?>>
                            <?= e($label) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-7">
                    <div class="a-field">
                      <label class="a-field__label" for="nbFact<?= $n ?>Label">Label</label>
                      <input class="a-input" type="text" id="nbFact<?= $n ?>Label" name="fact<?= $n ?>_label"
                             maxlength="60" value="<?= e($val("fact{$n}_label")) ?>">
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="a-field">
                      <label class="a-field__label" for="nbFact<?= $n ?>Text">Detail</label>
                      <textarea class="a-textarea" id="nbFact<?= $n ?>Text" name="fact<?= $n ?>_text"
                                rows="2" maxlength="255"><?= e($val("fact{$n}_text")) ?></textarea>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ---------- picks ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-utensils"></i> What this area orders</legend>
          <div class="row g-3">
            <div class="col-md-5">
              <div class="a-field">
                <label class="a-field__label" for="nbPicksTitle">Heading</label>
                <input class="a-input" type="text" id="nbPicksTitle" name="picks_title" maxlength="180"
                       value="<?= e($val('picks_title')) ?>">
              </div>
            </div>
            <div class="col-md-7">
              <div class="a-field">
                <label class="a-field__label" for="nbPicksText">Intro line</label>
                <input class="a-input" type="text" id="nbPicksText" name="picks_text" maxlength="400"
                       value="<?= e($val('picks_text')) ?>">
              </div>
            </div>

            <?php foreach ([1, 2, 3] as $n): ?>
              <div class="col-lg-4">
                <div class="a-field">
                  <label class="a-field__label" for="nbPick<?= $n ?>">Dish <?= $n ?></label>
                  <select class="a-select" id="nbPick<?= $n ?>" name="pick<?= $n ?>_dish">
                    <option value="">—</option>
                    <?php foreach ($menuChoices as $choice): ?>
                      <?php
                        // Category-qualified, because Adana Kebab, Beef Kofte Kebap and
                        // Chicken Shawarma each exist as both a sandwich and a plate at
                        // different prices — the name on its own is ambiguous.
                        $dishName  = (string)$choice['name'];
                        $dishValue = $choice['category_name'] . '::' . $dishName;
                      ?>
                      <option value="<?= e($dishValue) ?>" <?= $val("pick{$n}_dish") === $dishValue ? 'selected' : '' ?>>
                        <?= e($dishName) ?> — <?= e($choice['category_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="a-field">
                  <label class="a-field__label" for="nbPick<?= $n ?>Note">Why this one, here</label>
                  <textarea class="a-textarea" id="nbPick<?= $n ?>Note" name="pick<?= $n ?>_note"
                            rows="3" maxlength="255"><?= e($val("pick{$n}_note")) ?></textarea>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="a-field__hint">
            Dishes are matched to the live menu by name, so an item taken off the menu quietly
            drops off these pages instead of advertising something the kitchen no longer makes.
          </p>

          <!-- ---------- faq ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-circle-question"></i> Questions</legend>
          <div class="row g-3">
            <?php foreach ([1, 2, 3] as $n): ?>
              <div class="col-12">
                <div class="a-field">
                  <label class="a-field__label" for="nbFaq<?= $n ?>Q">Question <?= $n ?></label>
                  <input class="a-input" type="text" id="nbFaq<?= $n ?>Q" name="faq<?= $n ?>_q"
                         maxlength="255" value="<?= e($val("faq{$n}_q")) ?>">
                </div>
                <div class="a-field">
                  <label class="a-field__label" for="nbFaq<?= $n ?>A">Answer <?= $n ?></label>
                  <textarea class="a-textarea" id="nbFaq<?= $n ?>A" name="faq<?= $n ?>_a"
                            rows="3"><?= e($val("faq{$n}_a")) ?></textarea>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ---------- close & links ---------- -->
          </fieldset>

          <fieldset class="a-fieldset">
          <legend class="a-fieldset__legend"><i class="fa-solid fa-flag-checkered"></i> Closing &amp; links</legend>
          <div class="row g-3">
            <div class="col-md-5">
              <div class="a-field">
                <label class="a-field__label" for="nbCloseTitle">Closing heading</label>
                <input class="a-input" type="text" id="nbCloseTitle" name="closing_title" maxlength="180"
                       value="<?= e($val('closing_title')) ?>">
              </div>
            </div>
            <div class="col-md-7">
              <div class="a-field">
                <label class="a-field__label" for="nbCloseText">Closing line</label>
                <textarea class="a-textarea" id="nbCloseText" name="closing_text" rows="2"
                          maxlength="500"><?= e($val('closing_text')) ?></textarea>
              </div>
            </div>
            <div class="col-12">
              <div class="a-field">
                <label class="a-field__label" for="nbNeighbours">Links to nearby pages</label>
                <input class="a-input" type="text" id="nbNeighbours" name="neighbours" maxlength="500"
                       placeholder="kebab-in-fort-york-toronto, kebab-in-exhibition-place-toronto"
                       value="<?= e($val('neighbours')) ?>">
                <p class="a-field__hint">
                  Two or three addresses, comma separated, and only areas genuinely next door.
                  Linking every page to every other page reads as a link network rather than a
                  set of pages that happen to be related. Unpublished pages are skipped
                  automatically, so no link here can go dead.
                </p>
              </div>
            </div>
          </div>
          </fieldset>
        </div>

        <div class="a-card__foot">
          <button class="a-btn" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add neighbourhood' ?>
          </button>
          <?php if ($editing): ?>
            <a class="a-btn a-btn--ghost" href="<?= e(admin_url('neighbourhoods.php')) ?>">Cancel</a>
            <?php if (!empty($editing['is_active'])): ?>
              <a class="a-btn a-btn--ghost" href="<?= e(url($editing['slug'])) ?>" target="_blank" rel="noopener">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> View the page
              </a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
