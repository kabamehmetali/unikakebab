<?php
/**
 * Admin · Menu categories
 * The groups that build the public menu page — Sandwiches, Plates, Sides…
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** Make a slug unique inside menu_categories, ignoring one row id. */
function category_unique_slug(string $base, int $ignoreId): string
{
    $base = mb_substr(slugify($base), 0, 120);
    $slug = $base;
    $n    = 2;

    while (db_one('SELECT id FROM menu_categories WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $ignoreId])) {
        $slug = $base . '-' . $n;
        if (++$n > 200) {
            $slug = $base . '-' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $slug;
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id ? db_one('SELECT * FROM menu_categories WHERE id = ?', [$id]) : null;
    $id     = $row ? (int)$row['id'] : 0;

    // ---------------------------------------------------------------
    //  Save (insert or update)
    // ---------------------------------------------------------------
    if ($action === 'save') {
        $backTo = 'admin/categories.php' . ($id ? '?edit=' . $id : '') . '#cat-form';

        $name = trim((string)($_POST['name'] ?? ''));
        $desc = trim((string)($_POST['description'] ?? ''));
        $slug = trim((string)($_POST['slug'] ?? ''));

        if ($name === '') {
            old_set($_POST);
            flash('Please give the category a name — that is the heading customers see on the menu.', 'error');
            redirect($backTo);
        }

        $name = mb_substr($name, 0, 120);
        $slug = category_unique_slug($slug !== '' ? $slug : $name, $id);

        $sortRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sort    = $sortRaw === ''
            ? ($row ? (int)$row['sort_order'] : (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM menu_categories')['n'] ?? 1))
            : (int)$sortRaw;

        $active = empty($_POST['is_active']) ? 0 : 1;
        $image  = $row['image'] ?? null;

        $uploadError = null;
        $uploaded    = save_upload($_FILES['image'] ?? [], 'menu', 'cat', $uploadError);

        if ($uploadError !== null) {
            old_set($_POST);
            flash(e($uploadError), 'error');
            redirect($backTo);
        }

        if ($uploaded !== null) {
            delete_upload($image);          // replacing the photo removes the old file
            $image = $uploaded;
        } elseif (!empty($_POST['remove_image'])) {
            delete_upload($image);
            $image = null;
        }

        if ($row) {
            db_run(
                'UPDATE menu_categories
                    SET name = ?, slug = ?, description = ?, image = ?, sort_order = ?, is_active = ?
                  WHERE id = ?',
                [$name, $slug, ($desc === '' ? null : $desc), $image, $sort, $active, $id]
            );
            flash('“' . e($name) . '” was updated.', 'success');
        } else {
            db_run(
                'INSERT INTO menu_categories (name, slug, description, image, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $slug, ($desc === '' ? null : $desc), $image, $sort, $active]
            );
            flash('“' . e($name) . '” was added to the menu.', 'success');
        }

        redirect('admin/categories.php');
    }

    // ---------------------------------------------------------------
    //  Delete (menu_items cascade with it)
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$row) {
            flash('That category no longer exists.', 'error');
            redirect('admin/categories.php');
        }

        $items = db_all('SELECT image FROM menu_items WHERE category_id = ?', [$id]);
        foreach ($items as $item) {
            delete_upload($item['image']);   // the rows cascade, the files do not
        }
        delete_upload($row['image']);
        db_run('DELETE FROM menu_categories WHERE id = ?', [$id]);

        $n = count($items);
        flash(
            '“' . e($row['name']) . '” was deleted'
            . ($n > 0 ? ', along with ' . $n . ' menu item' . ($n === 1 ? '' : 's') : '') . '.',
            'success'
        );
        redirect('admin/categories.php');
    }

    // ---------------------------------------------------------------
    //  Show / hide
    // ---------------------------------------------------------------
    if ($action === 'toggle') {
        if (!$row) {
            flash('That category no longer exists.', 'error');
            redirect('admin/categories.php');
        }

        $new = empty($row['is_active']) ? 1 : 0;
        db_run('UPDATE menu_categories SET is_active = ? WHERE id = ?', [$new, $id]);
        flash('“' . e($row['name']) . '” is now ' . ($new ? 'visible on the menu.' : 'hidden from the menu.'), 'success');
        redirect('admin/categories.php');
    }

    redirect('admin/categories.php');
}

// =====================================================================
//  GET
// =====================================================================
$categories = db_all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM menu_items i WHERE i.category_id = c.id) AS item_count
       FROM menu_categories c
   ORDER BY c.sort_order, c.id'
);

$totalItems = 0;
foreach ($categories as $c) {
    $totalItems += (int)$c['item_count'];
}

$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId ? db_one('SELECT * FROM menu_categories WHERE id = ?', [$editId]) : null;
$formId  = (int)($editing['id'] ?? 0);
$useOld  = (int)old('id', -1) === $formId;

/** Field value: submitted values win after a failed save, then the edited row. */
$val = function (string $key, $fallback = '') use ($useOld, $editing) {
    if ($useOld) {
        return old($key, $fallback);
    }
    return $editing[$key] ?? $fallback;
};

/** Checkbox state: an unchecked box is simply absent from the POST. */
$checked = function (string $key, int $fallback = 1) use ($useOld, $editing) {
    if ($useOld) {
        $v = (string)old($key, '');
        return $v !== '' && $v !== '0';
    }
    return (int)($editing[$key] ?? $fallback) === 1;
};

$nextSort  = (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM menu_categories')['n'] ?? 1);
$formImage = (string)$val('image', '');

$title     = 'Menu Categories';
$subtitle  = 'The sections your menu page is built from';
$activeNav = 'categories';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-4">

  <!-- ============================= list ============================= -->
  <div class="col-12 col-lg-7">
    <div class="a-card">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-layer-group"></i>Categories</h2>
          <p class="a-card__sub">
            <?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?>
            · <?= (int)$totalItems ?> item<?= $totalItems === 1 ? '' : 's' ?> in total
          </p>
        </div>
        <div class="a-card__actions">
          <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('menu.php')) ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-eye"></i> <span class="d-none d-sm-inline">Menu page</span>
          </a>
        </div>
      </div>

      <?php if (!$categories): ?>
        <div class="a-empty">
          <i class="fa-solid fa-layer-group"></i>
          <p>No categories yet. Start with <strong>Sandwiches</strong>, <strong>Plates</strong> and <strong>Sides</strong> —
             menu items are filed under one of these.</p>
          <a class="a-btn" href="#cat-form"><i class="fa-solid fa-plus"></i> Add the first category</a>
        </div>
      <?php else: ?>
        <div class="a-table-wrap">
          <table class="a-table">
            <thead>
              <tr>
                <th aria-label="Order"></th>
                <th>Category</th>
                <th>Items</th>
                <th>Order</th>
                <th>Status</th>
                <th class="is-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
              <?php $catName = (string)$cat['name']; ?>
              <tr<?= empty($cat['is_active']) ? ' class="is-off"' : '' ?>>
                <td>
                  <i class="fa-solid fa-grip-vertical a-drag" aria-hidden="true"
                     title="Set the position with the “Sort order” field"></i>
                </td>
                <td>
                  <div class="a-flex">
                    <img class="a-table__thumb" src="<?= e(img($cat['image'])) ?>" alt="" width="52" height="52">
                    <div>
                      <div class="a-table__name"><?= e($catName) ?></div>
                      <div class="a-table__meta">/<?= e($cat['slug']) ?></div>
                    </div>
                  </div>
                </td>
                <td>
                  <a class="a-badge a-badge--blue" href="<?= e(admin_url('items.php?cat=' . (int)$cat['id'])) ?>"
                     title="Show these items">
                    <i class="fa-solid fa-burger"></i> <?= (int)$cat['item_count'] ?>
                  </a>
                </td>
                <td class="a-muted"><?= (int)$cat['sort_order'] ?></td>
                <td>
                  <?php if (!empty($cat['is_active'])): ?>
                    <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Live</span>
                  <?php else: ?>
                    <span class="a-badge a-badge--off"><i class="fa-solid fa-eye-slash"></i> Hidden</span>
                  <?php endif; ?>
                </td>
                <td class="is-actions">
                  <div class="a-flex a-flex--end">
                    <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                       href="<?= e(admin_url('categories.php?edit=' . (int)$cat['id'])) ?>#cat-form"
                       title="Edit <?= e($catName) ?>" aria-label="Edit <?= e($catName) ?>">
                      <i class="fa-solid fa-pen"></i>
                    </a>

                    <form method="post" data-guard>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                      <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                              title="<?= empty($cat['is_active']) ? 'Show on the menu' : 'Hide from the menu' ?>"
                              aria-label="<?= empty($cat['is_active']) ? 'Show' : 'Hide' ?> <?= e($catName) ?>">
                        <i class="fa-solid <?= empty($cat['is_active']) ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                      </button>
                    </form>

                    <form method="post" data-guard
                          data-confirm="Delete “<?= e($catName) ?>”? Its <?= (int)$cat['item_count'] ?> menu item<?= (int)$cat['item_count'] === 1 ? '' : 's' ?> will be deleted with it. This cannot be undone.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                      <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                              title="Delete <?= e($catName) ?>" aria-label="Delete <?= e($catName) ?>">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="a-card__foot">
          <span class="a-small a-muted">
            <i class="fa-solid fa-circle-info"></i>
            Lower sort numbers come first on the menu page. Hiding a category hides its items too.
          </span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============================= form ============================= -->
  <div class="col-12 col-lg-5">
    <div class="a-card" id="cat-form">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title">
            <i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?>"></i><?= $editing ? 'Edit category' : 'Add a category' ?>
          </h2>
          <p class="a-card__sub">
            <?= $editing ? 'Editing “' . e($editing['name']) . '”' : 'A new section on the menu page' ?>
          </p>
        </div>
        <?php if ($editing): ?>
          <div class="a-card__actions">
            <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('categories.php')) ?>">
              <i class="fa-solid fa-xmark"></i> Cancel
            </a>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" enctype="multipart/form-data" data-guard>
        <div class="a-card__body">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$formId ?>">

          <div class="a-field">
            <label class="a-field__label" for="catName">Name <span class="req">*</span></label>
            <input class="a-input" type="text" id="catName" name="name" required maxlength="120"
                   data-slug-source placeholder="Sandwiches"
                   value="<?= e($val('name')) ?>">
          </div>

          <div class="a-field">
            <label class="a-field__label" for="catSlug">Slug</label>
            <input class="a-input" type="text" id="catSlug" name="slug" maxlength="140"
                   data-slug-target placeholder="sandwiches"
                   value="<?= e($val('slug')) ?>">
            <p class="a-field__hint">Used in the menu page link, e.g. <code>/menu#sandwiches</code>. Leave it blank and we build one from the name.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="catDesc">Description</label>
            <textarea class="a-textarea" id="catDesc" name="description" rows="3"
                      placeholder="Wrapped in pita warmed on the grill, with pickles, parsley and house sauce."><?= e($val('description')) ?></textarea>
            <p class="a-field__hint">One warm line under the section heading. Optional.</p>
          </div>

          <div class="a-field">
            <span class="a-field__label">Photo</span>
            <div class="a-media">
              <img class="a-media__preview" id="catPreview" src="<?= e(img($formImage)) ?>" alt="" width="104" height="104">
              <div class="a-media__body">
                <input type="file" name="image" id="catImage" accept="image/jpeg,image/png,image/gif,image/webp"
                       data-preview="#catPreview">
                <p class="a-field__hint">JPG, PNG, GIF or WebP up to 8 MB — saved as WebP automatically.</p>
                <?php if ($formImage !== ''): ?>
                  <label class="a-check">
                    <input type="checkbox" name="remove_image" value="1">
                    <span>Remove the current photo</span>
                  </label>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="a-grid-2">
            <div class="a-field">
              <label class="a-field__label" for="catSort">Sort order</label>
              <input class="a-input" type="number" id="catSort" name="sort_order" step="1"
                     placeholder="<?= (int)$nextSort ?>"
                     value="<?= e($val('sort_order', '')) ?>">
              <p class="a-field__hint">Lowest number first.</p>
            </div>

            <div class="a-field">
              <span class="a-field__label">Visible</span>
              <label class="a-switch">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Show on the menu page</span>
              </label>
            </div>
          </div>
        </div>

        <div class="a-card__foot">
          <button class="a-btn" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add category' ?>
          </button>
          <?php if ($editing): ?>
            <a class="a-btn a-btn--ghost" href="<?= e(admin_url('categories.php')) ?>">Cancel</a>
            <a class="a-btn a-btn--ghost a-nowrap" href="<?= e(admin_url('items.php?cat=' . (int)$editing['id'])) ?>">
              <i class="fa-solid fa-burger"></i> Its items
            </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
