<?php
/**
 * Admin · Order Online links
 *
 * The rows behind the "Order Online" dropdown in the navbar and the platform
 * cards on the order page. Both read order_links() directly, so anything shown
 * or hidden here changes the public site immediately.
 *
 * The icon is only asked for as a fallback: includes/brands.php recognises the
 * delivery platforms from their URL and draws the real mark, so an admin adding
 * Uber Eats never has to know a Font Awesome class.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

require_methods(['GET', 'POST']);

/** A handful of sensible icons for the links no platform mark covers. */
const ORDER_ICON_CHOICES = [
    'fa-solid fa-utensils'      => 'Cutlery',
    'fa-solid fa-phone-flip'    => 'Phone',
    'fa-solid fa-bag-shopping'  => 'Takeaway bag',
    'fa-solid fa-motorcycle'    => 'Delivery',
    'fa-solid fa-bicycle'       => 'Bicycle',
    'fa-solid fa-car-side'      => 'Car',
    'fa-solid fa-box-open'      => 'Box',
    'fa-solid fa-shop'          => 'Shop',
    'fa-solid fa-globe'         => 'Website',
    'fa-brands fa-whatsapp'     => 'WhatsApp',
];

/**
 * Accept only the link types a customer can actually follow from a phone.
 * Anything else — javascript:, data: — has no business in a link we print.
 */
function order_link_clean_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    // A bare domain is what most people paste; assume https rather than
    // rejecting them over a missing prefix.
    if (preg_match('#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $url)) {
        $url = 'https://' . $url;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    if (in_array($scheme, ['http', 'https'], true)) {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    if ($scheme === 'tel' || $scheme === 'mailto') {
        return $url;
    }

    return null;
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id ? db_one('SELECT * FROM order_links WHERE id = ?', [$id]) : null;
    $id     = $row ? (int)$row['id'] : 0;

    // ---------------------------------------------------------------
    //  Save (insert or update)
    // ---------------------------------------------------------------
    if ($action === 'save') {
        $backTo = 'admin/order-links.php' . ($id ? '?edit=' . $id : '') . '#link-form';

        $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 80);
        $note  = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 160);
        $icon  = trim((string)($_POST['icon'] ?? ''));
        $url   = order_link_clean_url((string)($_POST['url'] ?? ''));

        $errors = [];
        if ($label === '') {
            $errors[] = 'Give the link a label — that is the wording customers tap.';
        }
        if ($url === null) {
            $errors[] = 'That link does not look right. Use a full web address, or a tel: / mailto: link.';
        }
        if (!isset(ORDER_ICON_CHOICES[$icon])) {
            $icon = 'fa-solid fa-utensils';
        }

        if ($errors) {
            old_set($_POST);
            foreach ($errors as $message) {
                flash($message, 'error');
            }
            redirect($backTo);
        }

        $sortRaw = trim((string)($_POST['sort_order'] ?? ''));
        $sort    = $sortRaw === ''
            ? ($row ? (int)$row['sort_order'] : (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM order_links')['n'] ?? 1))
            : (int)$sortRaw;

        $active = empty($_POST['is_active']) ? 0 : 1;

        if ($row) {
            db_run(
                'UPDATE order_links SET label = ?, url = ?, icon = ?, note = ?, sort_order = ?, is_active = ? WHERE id = ?',
                [$label, $url, $icon, ($note === '' ? null : $note), $sort, $active, $id]
            );
            flash('“' . e($label) . '” was updated.', 'success');
        } else {
            db_run(
                'INSERT INTO order_links (label, url, icon, note, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                [$label, $url, $icon, ($note === '' ? null : $note), $sort, $active]
            );
            flash('“' . e($label) . '” was added to the Order Online menu.', 'success');
        }

        redirect('admin/order-links.php');
    }

    // ---------------------------------------------------------------
    //  Show / hide — the reason this page exists
    // ---------------------------------------------------------------
    if ($action === 'toggle') {
        if (!$row) {
            flash('That link no longer exists.', 'error');
            redirect('admin/order-links.php');
        }

        $new = empty($row['is_active']) ? 1 : 0;
        db_run('UPDATE order_links SET is_active = ? WHERE id = ?', [$new, $id]);
        flash(
            '“' . e($row['label']) . '” is now '
            . ($new ? 'showing in the Order Online menu.' : 'hidden from the Order Online menu.'),
            'success'
        );
        redirect('admin/order-links.php');
    }

    // ---------------------------------------------------------------
    //  Move up / down — swap sort_order with the neighbour
    // ---------------------------------------------------------------
    if ($action === 'move') {
        if (!$row) {
            flash('That link no longer exists.', 'error');
            redirect('admin/order-links.php');
        }

        $direction = (string)($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
        $neighbour = $direction === 'up'
            ? db_one('SELECT * FROM order_links
                       WHERE (sort_order < ?) OR (sort_order = ? AND id < ?)
                    ORDER BY sort_order DESC, id DESC LIMIT 1',
                     [(int)$row['sort_order'], (int)$row['sort_order'], $id])
            : db_one('SELECT * FROM order_links
                       WHERE (sort_order > ?) OR (sort_order = ? AND id > ?)
                    ORDER BY sort_order, id LIMIT 1',
                     [(int)$row['sort_order'], (int)$row['sort_order'], $id]);

        if ($neighbour) {
            // Equal sort values would swap to no visible effect, so the pair is
            // renumbered rather than exchanged when they already match.
            $mine  = (int)$row['sort_order'];
            $their = (int)$neighbour['sort_order'];
            if ($mine === $their) {
                [$mine, $their] = $direction === 'up' ? [$their - 1, $their] : [$their + 1, $their];
            }

            db_run('UPDATE order_links SET sort_order = ? WHERE id = ?', [$their, $id]);
            db_run('UPDATE order_links SET sort_order = ? WHERE id = ?', [$mine, (int)$neighbour['id']]);
        }

        redirect('admin/order-links.php');
    }

    // ---------------------------------------------------------------
    //  Delete
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$row) {
            flash('That link no longer exists.', 'error');
            redirect('admin/order-links.php');
        }

        db_run('DELETE FROM order_links WHERE id = ?', [$id]);
        flash('“' . e($row['label']) . '” was removed from the Order Online menu.', 'success');
        redirect('admin/order-links.php');
    }

    redirect('admin/order-links.php');
}

// =====================================================================
//  GET
// =====================================================================
$links   = order_links(false);          // hidden rows included, this is the editor
$liveN   = 0;
foreach ($links as $link) {
    $liveN += empty($link['is_active']) ? 0 : 1;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId ? db_one('SELECT * FROM order_links WHERE id = ?', [$editId]) : null;
$formId  = (int)($editing['id'] ?? 0);
$useOld  = (int)old('id', -1) === $formId;

/** Field value: submitted values win after a failed save, then the edited row. */
$val = function (string $key, $fallback = '') use ($useOld, $editing) {
    if ($useOld) {
        return old($key, $fallback);
    }
    return $editing[$key] ?? $fallback;
};

$checked = function (string $key, int $fallback = 1) use ($useOld, $editing) {
    if ($useOld) {
        $v = (string)old($key, '');
        return $v !== '' && $v !== '0';
    }
    return (int)($editing[$key] ?? $fallback) === 1;
};

$nextSort = (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM order_links')['n'] ?? 1);

$title     = 'Order Online Links';
$subtitle  = 'What sits under the Order Online button';
$activeNav = 'order-links';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-4">

  <!-- ============================= list ============================= -->
  <div class="col-12 col-lg-7">
    <div class="a-card">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title"><i class="fa-solid fa-bag-shopping"></i>Ordering links</h2>
          <p class="a-card__sub">
            <?= count($links) ?> link<?= count($links) === 1 ? '' : 's' ?>
            · <?= (int)$liveN ?> showing on the site
          </p>
        </div>
        <div class="a-card__actions">
          <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('order.php')) ?>" target="_blank" rel="noopener">
            <i class="fa-solid fa-eye"></i> <span class="d-none d-sm-inline">Order page</span>
          </a>
        </div>
      </div>

      <?php if (!$links): ?>
        <div class="a-empty">
          <i class="fa-solid fa-bag-shopping"></i>
          <p>No ordering links yet. Until you add one, the Order Online menu says
             &ldquo;Ordering links coming soon&rdquo;. Add Uber Eats, DoorDash, SkipTheDishes
             or simply a phone number for pickup.</p>
          <a class="a-btn" href="#link-form"><i class="fa-solid fa-plus"></i> Add the first link</a>
        </div>
      <?php else: ?>
        <div class="a-table-wrap">
          <table class="a-table">
            <thead>
              <tr>
                <th aria-label="Order"></th>
                <th>Link</th>
                <th>Order</th>
                <th>Status</th>
                <th class="is-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($links as $i => $link): ?>
              <?php
                $label = (string)$link['label'];
                $brand = order_link_brand((string)$link['url']);
              ?>
              <tr<?= empty($link['is_active']) ? ' class="is-off"' : '' ?>>
                <td class="a-order-move">
                  <form method="post" data-guard>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="direction" value="up">
                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                    <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                            <?= $i === 0 ? 'disabled' : '' ?>
                            title="Move up" aria-label="Move <?= e($label) ?> up">
                      <i class="fa-solid fa-chevron-up"></i>
                    </button>
                  </form>
                  <form method="post" data-guard>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="direction" value="down">
                    <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                    <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                            <?= $i === count($links) - 1 ? 'disabled' : '' ?>
                            title="Move down" aria-label="Move <?= e($label) ?> down">
                      <i class="fa-solid fa-chevron-down"></i>
                    </button>
                  </form>
                </td>

                <td>
                  <div class="a-flex">
                    <span class="a-order-mark"><?= order_link_icon($link) ?></span>
                    <div>
                      <div class="a-table__name"><?= e($label) ?></div>
                      <div class="a-table__meta">
                        <?= e(order_link_note($link) ?: '—') ?>
                        <?php if ($brand !== null): ?>
                          <span class="a-badge a-badge--blue" title="Recognised platform — its own logo is drawn on the site">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> logo
                          </span>
                        <?php endif; ?>
                      </div>
                      <div class="a-table__meta a-order-url"><?= e($link['url']) ?></div>
                    </div>
                  </div>
                </td>

                <td class="a-muted"><?= (int)$link['sort_order'] ?></td>

                <td>
                  <?php if (!empty($link['is_active'])): ?>
                    <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Live</span>
                  <?php else: ?>
                    <span class="a-badge a-badge--off"><i class="fa-solid fa-eye-slash"></i> Hidden</span>
                  <?php endif; ?>
                </td>

                <td class="is-actions">
                  <div class="a-flex a-flex--end">
                    <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                       href="<?= e(admin_url('order-links.php?edit=' . (int)$link['id'])) ?>#link-form"
                       title="Edit <?= e($label) ?>" aria-label="Edit <?= e($label) ?>">
                      <i class="fa-solid fa-pen"></i>
                    </a>

                    <form method="post" data-guard>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                      <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                              title="<?= empty($link['is_active']) ? 'Show in the Order menu' : 'Hide from the Order menu' ?>"
                              aria-label="<?= empty($link['is_active']) ? 'Show' : 'Hide' ?> <?= e($label) ?>">
                        <i class="fa-solid <?= empty($link['is_active']) ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                      </button>
                    </form>

                    <form method="post" data-guard
                          data-confirm="Delete “<?= e($label) ?>”? This cannot be undone — to take it off the site temporarily, hide it instead.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$link['id'] ?>">
                      <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                              title="Delete <?= e($label) ?>" aria-label="Delete <?= e($label) ?>">
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
            Hiding a link takes it off the site but keeps it here, ready to switch back on.
          </span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ============================= form ============================= -->
  <div class="col-12 col-lg-5">
    <div class="a-card" id="link-form">
      <div class="a-card__head">
        <div>
          <h2 class="a-card__title">
            <i class="fa-solid <?= $editing ? 'fa-pen' : 'fa-plus' ?>"></i><?= $editing ? 'Edit link' : 'Add a link' ?>
          </h2>
          <p class="a-card__sub">
            <?= $editing ? 'Editing “' . e($editing['label']) . '”' : 'A new entry in the Order Online menu' ?>
          </p>
        </div>
        <?php if ($editing): ?>
          <div class="a-card__actions">
            <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('order-links.php')) ?>">
              <i class="fa-solid fa-xmark"></i> Cancel
            </a>
          </div>
        <?php endif; ?>
      </div>

      <form method="post" data-guard>
        <div class="a-card__body">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)$formId ?>">

          <div class="a-field">
            <label class="a-field__label" for="linkLabel">Label <span class="req">*</span></label>
            <input class="a-input" type="text" id="linkLabel" name="label" required maxlength="80"
                   placeholder="Uber Eats" value="<?= e($val('label')) ?>">
            <p class="a-field__hint">The wording customers tap, e.g. &ldquo;Uber Eats&rdquo; or &ldquo;Call for pickup&rdquo;.</p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="linkUrl">Link <span class="req">*</span></label>
            <input class="a-input" type="text" id="linkUrl" name="url" required maxlength="255"
                   placeholder="https://www.ubereats.com/store/…" value="<?= e($val('url')) ?>">
            <p class="a-field__hint">
              Paste the address of your shop on the platform. A phone number works too &mdash;
              write it as <code>tel:+14165550184</code> and it dials straight from a mobile.
            </p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="linkNote">Sublabel</label>
            <input class="a-input" type="text" id="linkNote" name="note" maxlength="160"
                   placeholder="Delivery &amp; pickup" value="<?= e($val('note')) ?>">
            <p class="a-field__hint">
              The small grey line under the label. Uber Eats, DoorDash, SkipTheDishes, Grubhub,
              Deliveroo and Postmates are recognised from their address and always read
              &ldquo;Delivery &amp; pickup&rdquo;, so this is only used by the others.
            </p>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="linkIcon">Icon</label>
            <select class="a-input" id="linkIcon" name="icon">
              <?php $currentIcon = (string)$val('icon', 'fa-solid fa-utensils'); ?>
              <?php foreach (ORDER_ICON_CHOICES as $class => $name): ?>
                <option value="<?= e($class) ?>" <?= $currentIcon === $class ? 'selected' : '' ?>>
                  <?= e($name) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="a-field__hint">
              Only used when we do not recognise the platform &mdash; the big delivery apps
              get their own logo drawn automatically.
            </p>
          </div>

          <div class="a-grid-2">
            <div class="a-field">
              <label class="a-field__label" for="linkSort">Sort order</label>
              <input class="a-input" type="number" id="linkSort" name="sort_order" step="1"
                     placeholder="<?= (int)$nextSort ?>" value="<?= e($val('sort_order', '')) ?>">
              <p class="a-field__hint">Lowest number first.</p>
            </div>

            <div class="a-field">
              <span class="a-field__label">Visible</span>
              <label class="a-switch">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active') ? 'checked' : '' ?>>
                <span class="a-switch__track"></span>
                <span>Show in the Order menu</span>
              </label>
            </div>
          </div>
        </div>

        <div class="a-card__foot">
          <button class="a-btn" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> <?= $editing ? 'Save changes' : 'Add link' ?>
          </button>
          <?php if ($editing): ?>
            <a class="a-btn a-btn--ghost" href="<?= e(admin_url('order-links.php')) ?>">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
