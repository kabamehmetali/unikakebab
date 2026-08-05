<?php
/**
 * Admin · Add or edit one menu item
 *
 * A page of its own rather than a form hanging off the bottom of the list.
 * Editing the twentieth item used to mean scrolling past the whole menu, and
 * coming back landed at the top again.
 *
 * Where "back" goes is worked out here rather than taken from the request: the
 * filter the list was under travels as cat/q, and the row to land on is the id
 * of the item being saved. Nothing that decides a redirect comes from a value
 * a visitor could point somewhere else.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

require_methods(['GET', 'POST']);

/**
 * The list URL to return to: same filter, and an anchor on the row just
 * touched so the browser scrolls straight back to it.
 */
function items_return_url(array $filter, int $focusId = 0): string
{
    $query = array_filter([
        'cat' => $filter['cat'] ?? '',
        'q'   => $filter['q']   ?? '',
    ], static fn($v): bool => (string)$v !== '');

    return 'admin/items.php'
         . ($query ? '?' . http_build_query($query) : '')
         . ($focusId ? '#item-' . $focusId : '');
}

/** The filter the list was under, from either a GET link or a POST field. */
$filter = [
    'cat' => (string)($_REQUEST['cat'] ?? ''),
    'q'   => trim((string)($_REQUEST['q'] ?? '')),
];
$filter['cat'] = ctype_digit($filter['cat']) ? $filter['cat'] : '';
$filter['q']   = mb_substr($filter['q'], 0, 120);

$categories = db_all('SELECT * FROM menu_categories ORDER BY sort_order, id');

// =====================================================================
//  POST — save
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $id  = (int)($_POST['id'] ?? 0);
    $row = $id ? db_one('SELECT * FROM menu_items WHERE id = ?', [$id]) : null;
    $id  = $row ? (int)$row['id'] : 0;

    // An edit link for a row somebody else has since deleted must not silently
    // turn into a brand new item.
    if ((int)($_POST['id'] ?? 0) > 0 && !$row) {
        flash('That item no longer exists — it may have been deleted in another tab.', 'error');
        redirect(items_return_url($filter));
    }

    $backTo = 'admin/item-edit.php?' . http_build_query(
        array_filter(['id' => $id ?: null, 'cat' => $filter['cat'], 'q' => $filter['q']])
    );

    $name       = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 150);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $desc       = trim((string)($_POST['description'] ?? ''));
    $priceRaw   = trim((string)($_POST['price'] ?? ''));
    $priceNote  = (string)($_POST['price_note'] ?? '') === '+' ? '+' : null;
    $badge      = mb_substr(trim((string)($_POST['badge'] ?? '')), 0, 40);

    $errors = [];

    if ($name === '') {
        $errors[] = 'Please give the item a name.';
    }
    if (!$categoryId || !db_one('SELECT id FROM menu_categories WHERE id = ?', [$categoryId])) {
        $errors[] = 'Please choose a category for this item.';
    }

    // An empty price box is allowed — it means "no price shown".
    $price = null;
    if ($priceRaw !== '') {
        $clean = str_replace(['$', ',', ' '], '', $priceRaw);
        if (!is_numeric($clean) || (float)$clean < 0) {
            $errors[] = 'The price must be a number, for example 12.99. Leave it empty to hide the price.';
        } elseif ((float)$clean > 99999.99) {
            $errors[] = 'That price is higher than this field allows.';
        } else {
            $price = number_format((float)$clean, 2, '.', '');
        }
    }

    if ($errors) {
        old_set($_POST);
        flash(e(implode(' ', $errors)), 'error');
        redirect($backTo);
    }

    $sortRaw = trim((string)($_POST['sort_order'] ?? ''));
    $sort    = $sortRaw === ''
        ? ($row
            ? (int)$row['sort_order']
            : (int)(db_one(
                'SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM menu_items WHERE category_id = ?',
                [$categoryId]
            )['n'] ?? 1))
        : (int)$sortRaw;

    $image       = $row['image'] ?? null;
    $uploadError = null;
    $uploaded    = save_upload($_FILES['image'] ?? [], 'menu', 'item', $uploadError);

    if ($uploadError !== null) {
        old_set($_POST);
        flash(e($uploadError), 'error');
        redirect($backTo);
    }

    if ($uploaded !== null) {
        delete_upload($image);              // replacing the photo removes the old file
        $image = $uploaded;
    } elseif (!empty($_POST['remove_image'])) {
        delete_upload($image);
        $image = null;
    }

    // Alt text describes the photo, so it goes when the photo does.
    $imageAlt = mb_substr(trim((string)($_POST['image_alt'] ?? '')), 0, 255);
    if ($image === null) {
        $imageAlt = null;
    }

    $params = [
        $categoryId,
        $name,
        ($desc === '' ? null : $desc),
        $price,
        $priceNote,
        $image,
        ($imageAlt === '' ? null : $imageAlt),
        ($badge === '' ? null : $badge),
        empty($_POST['is_vegetarian']) ? 0 : 1,
        empty($_POST['is_spicy'])      ? 0 : 1,
        empty($_POST['is_featured'])   ? 0 : 1,
        empty($_POST['is_active'])     ? 0 : 1,
        $sort,
    ];

    if ($row) {
        $params[] = $id;
        db_run(
            'UPDATE menu_items
                SET category_id = ?, name = ?, description = ?, price = ?, price_note = ?, image = ?,
                    image_alt = ?, badge = ?, is_vegetarian = ?, is_spicy = ?, is_featured = ?,
                    is_active = ?, sort_order = ?
              WHERE id = ?',
            $params
        );
        $savedId = $id;
        flash('“' . e($name) . '” was updated.', 'success');
    } else {
        db_run(
            'INSERT INTO menu_items
                (category_id, name, description, price, price_note, image, image_alt,
                 badge, is_vegetarian, is_spicy, is_featured, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $params
        );
        $savedId = (int)db()->lastInsertId();
        flash('“' . e($name) . '” was added to the menu.', 'success');
    }

    // "Save and add another" keeps the editor open on an empty form, still
    // carrying the category, so a batch of items goes in without a round trip
    // through the list between each one.
    if (!empty($_POST['save_and_new'])) {
        redirect('admin/item-edit.php?' . http_build_query(
            array_filter(['cat' => $filter['cat'], 'q' => $filter['q'], 'in' => $categoryId])
        ));
    }

    redirect(items_return_url($filter, $savedId));
}

// =====================================================================
//  GET — draw the form
// =====================================================================
$editId  = (int)($_GET['id'] ?? 0);
$editing = $editId ? db_one('SELECT * FROM menu_items WHERE id = ?', [$editId]) : null;

if ($editId && !$editing) {
    flash('That item no longer exists.', 'error');
    redirect(items_return_url($filter));
}

$formId = (int)($editing['id'] ?? 0);
$useOld = (int)old('id', -1) === $formId;

/** Field value: submitted values win after a failed save, then the edited row. */
$val = function (string $key, $fallback = '') use ($useOld, $editing) {
    if ($useOld) {
        return old($key, $fallback);
    }
    return $editing[$key] ?? $fallback;
};

/** Checkbox state: an unchecked box is simply absent from the POST. */
$checked = function (string $key, int $fallback = 0) use ($useOld, $editing) {
    if ($useOld) {
        $v = (string)old($key, '');
        return $v !== '' && $v !== '0';
    }
    return (int)($editing[$key] ?? $fallback) === 1;
};

$formImage = (string)$val('image', '');
$defaultCat = (int)($_GET['in'] ?? 0)
           ?: (int)($filter['cat'] ?: 0)
           ?: (int)($categories[0]['id'] ?? 0);
$formCat   = (int)$val('category_id', $defaultCat);

// items_return_url() already carries the admin/ prefix, because redirect()
// wants it that way — so this is url(), not admin_url().
$listUrl   = url(items_return_url($filter, $formId));
$listLabel = $filter['cat'] !== '' || $filter['q'] !== ''
    ? 'Back to the filtered list'
    : 'Back to menu items';

$title     = $editing ? 'Edit menu item' : 'Add a menu item';
$subtitle  = $editing
    ? 'Editing “' . $editing['name'] . '”'
    : 'Leave the price empty for things you price in store, like the desserts';
$activeNav = 'items';
require __DIR__ . '/includes/header.php';
?>

<p class="a-backlink">
  <a href="<?= e($listUrl) ?>">
    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <?= e($listLabel) ?>
  </a>
</p>

<?php if (!$categories): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      You need at least one category before you can add items.
      <a href="<?= e(admin_url('categories.php')) ?>">Create a category first</a>.
    </span>
  </div>
<?php endif; ?>

<div class="a-card" id="item-form">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title">
        <i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?>"></i>
        <?= $editing ? 'Edit item' : 'Add a menu item' ?>
      </h2>
      <p class="a-card__sub">
        <?= $editing
              ? 'Changes go live on the menu page as soon as you save.'
              : 'It appears at the bottom of its category — drag it into place in the list afterwards.' ?>
      </p>
    </div>
    <?php if ($editing): ?>
      <div class="a-card__actions">
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('menu.php')) ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-eye"></i> <span class="d-none d-sm-inline">Menu page</span>
        </a>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" enctype="multipart/form-data" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$formId ?>">
    <input type="hidden" name="cat" value="<?= e($filter['cat']) ?>">
    <input type="hidden" name="q" value="<?= e($filter['q']) ?>">

    <div class="a-card__body">
      <div class="row g-0 g-lg-4">

        <!-- left: the words -->
        <div class="col-12 col-lg-7">
          <div class="a-grid-2">
            <div class="a-field">
              <label class="a-field__label" for="itemName">Name <span class="req">*</span></label>
              <input class="a-input" type="text" id="itemName" name="name" maxlength="150" required autofocus
                     value="<?= e($val('name')) ?>" placeholder="Adana Kebab">
            </div>

            <div class="a-field">
              <label class="a-field__label" for="itemCat">Category <span class="req">*</span></label>
              <select class="a-select" id="itemCat" name="category_id" required <?= $categories ? '' : 'disabled' ?>>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= (int)$cat['id'] ?>" <?= $formCat === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
                <?php if (!$categories): ?><option value="">No categories yet</option><?php endif; ?>
              </select>
            </div>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="itemDesc">Description</label>
            <textarea class="a-textarea" id="itemDesc" name="description" rows="3"
                      placeholder="Seasoned beef with parsley, onion, tomato, pickles, and taratur sauce."><?= e($val('description')) ?></textarea>
            <p class="a-field__hint">One appetising sentence. This shows under the name on the menu page.</p>
          </div>

          <div class="a-grid-3">
            <div class="a-field">
              <label class="a-field__label" for="itemPrice">Price</label>
              <input class="a-input" type="text" id="itemPrice" name="price" inputmode="decimal"
                     value="<?= e($val('price')) ?>" placeholder="12.99">
              <p class="a-field__hint">Empty = no price shown.</p>
            </div>

            <div class="a-field">
              <label class="a-field__label" for="itemPriceNote">Price style</label>
              <select class="a-select" id="itemPriceNote" name="price_note">
                <option value="">Normal — $12.99</option>
                <option value="+" <?= (string)$val('price_note') === '+' ? 'selected' : '' ?>>Add-on — +$6.00</option>
              </select>
            </div>

            <div class="a-field">
              <label class="a-field__label" for="itemSort">Sort order</label>
              <input class="a-input" type="number" id="itemSort" name="sort_order" min="0" step="1"
                     value="<?= e($val('sort_order')) ?>" placeholder="auto">
              <p class="a-field__hint">Low numbers first — or just drag the item in the list.</p>
            </div>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="itemBadge">Label</label>
            <input class="a-input" type="text" id="itemBadge" name="badge" maxlength="40"
                   value="<?= e($val('badge')) ?>" placeholder="Best seller">
            <p class="a-field__hint">A short gold tag on the card — “Best seller”, “New”, “Chef’s pick”. Leave empty for none.</p>
          </div>
        </div>

        <!-- right: the photo and the switches -->
        <div class="col-12 col-lg-5">
          <div class="a-field">
            <span class="a-field__label">Photo</span>
            <div class="a-media">
              <img class="a-media__preview" id="itemPreview" src="<?= e(menu_item_img($formImage)) ?>" alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="image" accept="image/*" data-preview="#itemPreview">
                <p class="a-field__hint">
                  JPG, PNG or WebP up to 8 MB. Saved as WebP automatically.
                  Without a photo the menu shows this item as a clean text row.
                </p>
                <?php if ($formImage !== ''): ?>
                  <label class="a-check">
                    <input type="checkbox" name="remove_image" value="1">
                    <span>Remove the current photo</span>
                  </label>
                <?php endif; ?>

                <label class="a-field__label a-field__label--sub" for="image_alt">Alt text</label>
                <input class="a-input a-input--sm" type="text" id="image_alt" name="image_alt"
                       maxlength="255" value="<?= e($val('image_alt', '')) ?>"
                       placeholder="<?= e(trim(($val('name', '') ?: 'This dish') . ' at ' . setting('site_name', 'Unika Kebab'))) ?>">
                <p class="a-field__hint">
                  Describes the photo for screen readers and image search. Say what is on the plate —
                  &ldquo;Adana kebab in warm pita with pickles and parsley&rdquo; beats &ldquo;food photo&rdquo;.
                  Blank falls back to the item and category name.
                </p>
              </div>
            </div>
          </div>

          <div class="a-field">
            <span class="a-field__label">Options</span>

            <div class="a-mt">
              <label class="a-switch">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active', 1) ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Show on the menu</span>
              </label>
            </div>

            <div class="a-mt">
              <label class="a-switch">
                <input type="checkbox" name="is_featured" value="1" <?= $checked('is_featured') ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Feature on the home page</span>
              </label>
            </div>

            <div class="a-mt">
              <label class="a-switch">
                <input type="checkbox" name="is_spicy" value="1" <?= $checked('is_spicy') ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Spicy</span>
              </label>
            </div>

            <div class="a-mt">
              <label class="a-switch">
                <input type="checkbox" name="is_vegetarian" value="1" <?= $checked('is_vegetarian') ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Vegetarian</span>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="a-card__foot a-card__foot--sticky">
      <button class="a-btn" type="submit" <?= $categories ? '' : 'disabled' ?>>
        <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add item' ?>
      </button>

      <?php if (!$editing): ?>
        <button class="a-btn a-btn--dark" type="submit" name="save_and_new" value="1" <?= $categories ? '' : 'disabled' ?>>
          <i class="fa-solid fa-plus"></i> Save and add another
        </button>
      <?php endif; ?>

      <a class="a-btn a-btn--ghost" href="<?= e($listUrl) ?>">Cancel</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
