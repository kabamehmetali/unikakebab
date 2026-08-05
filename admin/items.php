<?php
/**
 * Admin · Menu items
 * Everything that appears under a category on the public menu page.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id ? db_one('SELECT * FROM menu_items WHERE id = ?', [$id]) : null;
    $id     = $row ? (int)$row['id'] : 0;

    // ---------------------------------------------------------------
    //  Menu item placeholder
    // ---------------------------------------------------------------
    if ($action === 'save_placeholder') {
        $uploadError = null;
        $uploaded = save_upload($_FILES['menu_item_placeholder'] ?? [], 'site', 'menu-placeholder', $uploadError);

        // The alt text is saved on its own, so the wording can be corrected
        // without having to re-upload the image it describes.
        setting_set(
            'menu_item_placeholder_alt',
            mb_substr(trim((string)($_POST['menu_item_placeholder_alt'] ?? '')), 0, 255)
        );

        if ($uploadError !== null) {
            flash(e($uploadError), 'error');
        } elseif ($uploaded === null) {
            flash('Placeholder alt text saved.', 'success');
        } else {
            delete_upload(setting('menu_item_placeholder', ''));
            setting_set('menu_item_placeholder', $uploaded);
            flash('Menu item placeholder updated.', 'success');
        }

        redirect('admin/items.php');
    }

    // ---------------------------------------------------------------
    //  Drag-and-drop reorder
    //  Answers with JSON, never a redirect — the list saves in place.
    // ---------------------------------------------------------------
    if ($action === 'reorder') {
        header('Content-Type: application/json; charset=utf-8');

        /** Send one JSON answer and stop. */
        $reply = function (bool $ok, string $message, int $code = 200): void {
            http_response_code($code);
            echo json_encode(['ok' => $ok, 'message' => $message]);
            exit;
        };

        $categoryId = (int)($_POST['category_id'] ?? 0);
        $posted     = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['order'] ?? []))
        )));

        if (!$categoryId || !db_one('SELECT id FROM menu_categories WHERE id = ?', [$categoryId])) {
            $reply(false, 'That category no longer exists.', 404);
        }
        if (!$posted) {
            $reply(false, 'There was nothing to reorder.', 400);
        }

        // The rows we were sent must still be exactly the rows in that category.
        // If they are not, somebody has added or removed an item in another tab
        // and saving this order would quietly drop their change.
        $current = array_map('intval', array_column(
            db_all('SELECT id FROM menu_items WHERE category_id = ?', [$categoryId]),
            'id'
        ));
        $sent = $posted;
        sort($current);
        sort($sent);

        if ($current !== $sent) {
            $reply(false, 'This list has changed somewhere else. Reload the page and try again.', 409);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE menu_items SET sort_order = ? WHERE id = ? AND category_id = ?');
            foreach ($posted as $position => $itemId) {
                $stmt->execute([$position + 1, $itemId, $categoryId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $reply(false, 'The new order could not be saved.', 500);
        }

        $reply(true, 'Order saved');
    }

    // Keep the current filter when bouncing back to the list.
    $query = [];
    foreach (['cat', 'q'] as $key) {
        $value = trim((string)($_POST['keep_' . $key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    $listUrl = 'admin/items.php' . ($query ? '?' . http_build_query($query) : '');

    // ---------------------------------------------------------------
    //  Delete
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$row) {
            flash('That item no longer exists.', 'error');
            redirect($listUrl);
        }
        delete_upload($row['image']);
        db_run('DELETE FROM menu_items WHERE id = ?', [$id]);
        flash('“' . e($row['name']) . '” was deleted.', 'success');
        redirect($listUrl);
    }

    // ---------------------------------------------------------------
    //  Show / hide and featured toggles
    // ---------------------------------------------------------------
    if ($action === 'toggle_active' || $action === 'toggle_featured') {
        if (!$row) {
            flash('That item no longer exists.', 'error');
            redirect($listUrl);
        }

        if ($action === 'toggle_active') {
            $now = (int)$row['is_active'] === 1 ? 0 : 1;
            db_run('UPDATE menu_items SET is_active = ? WHERE id = ?', [$now, $id]);
            flash('“' . e($row['name']) . '” is now ' . ($now ? 'visible on' : 'hidden from') . ' the menu.', 'success');
        } else {
            $now = (int)$row['is_featured'] === 1 ? 0 : 1;
            db_run('UPDATE menu_items SET is_featured = ? WHERE id = ?', [$now, $id]);
            flash(
                '“' . e($row['name']) . '” was ' . ($now ? 'added to' : 'removed from') . ' the home page favourites.',
                'success'
            );
        }

        redirect($listUrl);
    }

    redirect($listUrl);
}

// =====================================================================
//  Filters + list
// =====================================================================
$categories = db_all('SELECT * FROM menu_categories ORDER BY sort_order, id');

$filterCat = (int)($_GET['cat'] ?? 0);
$search    = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

if ($filterCat > 0) {
    $where[]  = 'i.category_id = ?';
    $params[] = $filterCat;
}
if ($search !== '') {
    $where[]  = '(i.name LIKE ? OR i.description LIKE ? OR i.badge LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$items = db_all(
    'SELECT i.*, c.name AS category_name
       FROM menu_items i
       JOIN menu_categories c ON c.id = i.category_id
     ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
      ORDER BY c.sort_order, c.id, i.sort_order, i.id',
    $params
);

$totalItems = (int)(db_one('SELECT COUNT(*) AS n FROM menu_items')['n'] ?? 0);
$isFiltered = $filterCat > 0 || $search !== '';

// Rows are grouped by category, because a drag only ever reorders items inside
// one section — which is exactly how the public menu page lays them out.
// A text search shows a part of several categories at once, so there is no
// meaningful order to save: the handles come off until the search is cleared.
$canSort = ($search === '');
$grouped = [];
foreach ($items as $item) {
    $grouped[(int)$item['category_id']][] = $item;
}

// Carry the filter through every form on the page.
$keepFields = '<input type="hidden" name="keep_cat" value="' . e((string)($filterCat ?: '')) . '">'
            . '<input type="hidden" name="keep_q" value="' . e($search) . '">';

// Adding and editing happen on their own page. The filter travels with the
// link so that coming back lands on the same list, scrolled to the same row —
// admin/item-edit.php rebuilds the return URL from exactly these two values.
$filterQuery = array_filter(['cat' => $filterCat ?: '', 'q' => $search], static fn($v): bool => (string)$v !== '');

$editUrl = static function (int $id) use ($filterQuery): string {
    return admin_url('item-edit.php?' . http_build_query(['id' => $id] + $filterQuery));
};

$newUrl = static function () use ($filterQuery): string {
    $query = $filterQuery;
    return admin_url('item-edit.php' . ($query ? '?' . http_build_query($query) : ''));
};

$title     = 'Menu Items';
$subtitle  = 'Everything you serve, filed under a category';
$activeNav = 'items';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$categories): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      You need at least one category before you can add items.
      <a href="<?= e(admin_url('categories.php')) ?>">Create a category first</a>.
    </span>
  </div>
<?php endif; ?>

<!-- ======================= image placeholder ======================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-image"></i>Menu item placeholder</h2>
      <p class="a-card__sub">The image used when a menu item has no photo of its own</p>
    </div>
  </div>

  <form class="a-card__body" method="post" enctype="multipart/form-data" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_placeholder">

    <div class="a-media">
      <img class="a-media__preview" id="menuPlaceholderPreview" src="<?= e(menu_item_img(null)) ?>"
           alt="Current menu item placeholder" width="104" height="104">
      <div class="a-media__body">
        <input type="file" name="menu_item_placeholder" accept="image/*" data-preview="#menuPlaceholderPreview">
        <p class="a-field__hint">JPG, PNG or WebP up to 8 MB. A square image works best in menu thumbnails and featured cards.</p>

        <label class="a-field__label a-field__label--sub" for="menu_item_placeholder_alt">Alt text</label>
        <input class="a-input a-input--sm" type="text"
               id="menu_item_placeholder_alt" name="menu_item_placeholder_alt"
               maxlength="255" value="<?= e(setting('menu_item_placeholder_alt')) ?>"
               placeholder="Unika Kebab menu item photo coming soon">
        <p class="a-field__hint">Used whenever an item falls back to this image.</p>
      </div>
    </div>

    <div class="a-mt">
      <button class="a-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save placeholder</button>
    </div>
  </form>
</div>

<!-- ============================= list ============================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-burger"></i>Menu items</h2>
      <p class="a-card__sub">
        <?php if ($isFiltered): ?>
          <?= count($items) ?> of <?= $totalItems ?> item<?= $totalItems === 1 ? '' : 's' ?> shown
        <?php else: ?>
          <?= $totalItems ?> item<?= $totalItems === 1 ? '' : 's' ?> on the menu
        <?php endif; ?>
      </p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('menu.php')) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-eye"></i> <span class="d-none d-sm-inline">Menu page</span>
      </a>
      <a class="a-btn a-btn--sm" href="<?= e($newUrl()) ?>"><i class="fa-solid fa-plus"></i> Add item</a>
    </div>
  </div>

  <div class="a-card__body">
    <form class="a-flex" method="get" action="<?= e(admin_url('items.php')) ?>">
      <label class="visually-hidden" for="filterCat">Category</label>
      <select class="a-select a-w-sm" id="filterCat" name="cat">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= $filterCat === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="visually-hidden" for="filterQ">Search</label>
      <input class="a-input a-w-md" type="search" id="filterQ" name="q"
             value="<?= e($search) ?>" placeholder="Search items…">

      <button class="a-btn a-btn--dark" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
      <?php if ($isFiltered): ?>
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('items.php')) ?>">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$items): ?>
    <div class="a-empty">
      <i class="fa-solid fa-burger"></i>
      <?php if ($isFiltered): ?>
        <p>No items match that filter.</p>
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('items.php')) ?>">Show every item</a>
      <?php else: ?>
        <p>The menu is empty. Add your first skewer, wrap or side below.</p>
        <a class="a-btn" href="<?= e($newUrl()) ?>"><i class="fa-solid fa-plus"></i> Add the first item</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $colCount = $canSort ? 7 : 5; ?>

    <div class="a-sortbar">
      <?php if ($canSort): ?>
        <p class="a-sortbar__hint">
          <i class="fa-solid fa-up-down-left-right" aria-hidden="true"></i>
          Drag an item by its handle to reorder it inside its category — the menu page follows this order.
          Keyboard: focus a handle and press <kbd>↑</kbd> or <kbd>↓</kbd>.
        </p>
      <?php else: ?>
        <p class="a-sortbar__hint a-sortbar__hint--off">
          <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
          A search shows part of several categories, so there is no order to save.
          <a href="<?= e(admin_url('items.php' . ($filterCat ? '?cat=' . $filterCat : ''))) ?>">Clear the search</a>
          to drag items again.
        </p>
      <?php endif; ?>
      <span class="a-sortbar__status" data-sort-status role="status" aria-live="polite"></span>
    </div>

    <div class="a-table-wrap" data-remember-scroll>
      <table class="a-table a-table--sortable"
             data-sort-url="<?= e(admin_url('items.php')) ?>"
             data-sort-token="<?= e(csrf_token()) ?>">
        <thead>
          <tr>
            <?php if ($canSort): ?>
              <th class="is-drag"><span class="visually-hidden">Reorder</span></th>
              <th class="is-order" title="Position inside the category">#</th>
            <?php endif; ?>
            <th>Item</th>
            <th>Price</th>
            <th>Labels</th>
            <th>Status</th>
            <th class="is-actions">Actions</th>
          </tr>
        </thead>

        <?php foreach ($grouped as $catId => $catItems): ?>
          <tbody class="a-table__group">
            <tr>
              <td colspan="<?= $colCount ?>">
                <span class="a-table__group-name"><?= e($catItems[0]['category_name']) ?></span>
                <span class="a-badge"><?= count($catItems) ?> item<?= count($catItems) === 1 ? '' : 's' ?></span>
              </td>
            </tr>
          </tbody>

          <tbody<?= $canSort ? ' class="a-sort" data-sortable data-category-id="' . (int)$catId . '"' : '' ?>>
          <?php foreach ($catItems as $position => $item): ?>
            <?php
              $itemName = (string)$item['name'];
              $isActive = !empty($item['is_active']);
              $isFeat   = !empty($item['is_featured']);
            ?>
          <tr id="item-<?= (int)$item['id'] ?>"<?= $isActive ? '' : ' class="is-off"' ?><?= $canSort ? ' data-id="' . (int)$item['id'] . '"' : '' ?>>
            <?php if ($canSort): ?>
              <td class="is-drag">
                <button class="a-drag" type="button"
                        title="Drag to reorder, or use the arrow keys"
                        aria-label="Reorder <?= e($itemName) ?>">
                  <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </button>
              </td>
              <td class="is-order"><span class="a-sort__num"><?= $position + 1 ?></span></td>
            <?php endif; ?>
            <td>
              <div class="a-flex">
                <img class="a-table__thumb" src="<?= e(menu_item_img($item['image'])) ?>" alt="" width="52" height="52">
                <div>
                  <div class="a-table__name"><?= e($itemName) ?></div>
                  <?php if (!empty($item['description'])): ?>
                    <div class="a-table__meta"><?= e(excerpt($item['description'], 62)) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="a-nowrap">
              <?php $priceText = money($item['price'], $item['price_note']); ?>
              <?php if ($priceText !== ''): ?>
                <strong><?= e($priceText) ?></strong>
              <?php else: ?>
                <span class="a-muted a-small">No price</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="a-flex">
                <?php if ($isFeat): ?>
                  <span class="a-badge a-badge--gold" title="Shown on the home page">
                    <i class="fa-solid fa-star"></i> Featured
                  </span>
                <?php endif; ?>
                <?php if (!empty($item['badge'])): ?>
                  <span class="a-badge"><?= e($item['badge']) ?></span>
                <?php endif; ?>
                <?php if (!empty($item['is_spicy'])): ?>
                  <span class="a-badge a-badge--red" title="Spicy"><i class="fa-solid fa-pepper-hot"></i></span>
                <?php endif; ?>
                <?php if (!empty($item['is_vegetarian'])): ?>
                  <span class="a-badge a-badge--on" title="Vegetarian"><i class="fa-solid fa-leaf"></i></span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($isActive): ?>
                <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Live</span>
              <?php else: ?>
                <span class="a-badge a-badge--off"><i class="fa-solid fa-eye-slash"></i> Hidden</span>
              <?php endif; ?>
            </td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon" href="<?= e($editUrl((int)$item['id'])) ?>"
                   title="Edit <?= e($itemName) ?>" aria-label="Edit <?= e($itemName) ?>">
                  <i class="fa-solid fa-pen"></i>
                </a>

                <form method="post" data-guard>
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="toggle_featured">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="<?= $isFeat ? 'Remove from the home page' : 'Show on the home page' ?>"
                          aria-label="<?= $isFeat ? 'Unfeature' : 'Feature' ?> <?= e($itemName) ?>">
                    <i class="<?= $isFeat ? 'fa-solid' : 'fa-regular' ?> fa-star"></i>
                  </button>
                </form>

                <form method="post" data-guard>
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="<?= $isActive ? 'Hide from the menu' : 'Show on the menu' ?>"
                          aria-label="<?= $isActive ? 'Hide' : 'Show' ?> <?= e($itemName) ?>">
                    <i class="fa-solid <?= $isActive ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                  </button>
                </form>

                <form method="post" data-guard
                      data-confirm="Delete “<?= e($itemName) ?>” from the menu? This cannot be undone.">
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                          title="Delete <?= e($itemName) ?>" aria-label="Delete <?= e($itemName) ?>">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
