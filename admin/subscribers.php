<?php
/**
 * Admin · Newsletter subscribers
 * The list built by the sign-up band on the home page.
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
    $row    = $id ? db_one('SELECT * FROM subscribers WHERE id = ?', [$id]) : null;

    $query = [];
    foreach (['filter', 'q'] as $key) {
        $value = trim((string)($_POST['keep_' . $key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    $listUrl = 'admin/subscribers.php' . ($query ? '?' . http_build_query($query) : '');

    if ($action === 'toggle') {
        if ($row) {
            $now = (int)$row['is_active'] === 1 ? 0 : 1;
            db_run('UPDATE subscribers SET is_active = ? WHERE id = ?', [$now, (int)$row['id']]);
            flash(
                e($row['email']) . ' was ' . ($now ? 'resubscribed' : 'unsubscribed') . '.',
                'success'
            );
        } else {
            flash('That subscriber no longer exists.', 'error');
        }
        redirect($listUrl);
    }

    if ($action === 'delete') {
        if ($row) {
            db_run('DELETE FROM subscribers WHERE id = ?', [(int)$row['id']]);
            flash(e($row['email']) . ' was removed from the list.', 'success');
        } else {
            flash('That subscriber no longer exists.', 'error');
        }
        redirect($listUrl);
    }

    if ($action === 'add') {
        $name  = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 150);

        if ($name === '' || $email === '') {
            old_set($_POST);
            flash('Please fill in both a name and an email address.', 'error');
            redirect($listUrl . '#add-form');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            old_set($_POST);
            flash('“' . e($email) . '” does not look like a valid email address.', 'error');
            redirect($listUrl . '#add-form');
        }

        $welcome = !empty($_POST['send_welcome']);
        $added   = false;

        $existing = db_one('SELECT id, is_active FROM subscribers WHERE email = ?', [$email]);
        if ($existing) {
            if ((int)$existing['is_active'] === 1) {
                flash(e($email) . ' is already on the list.', 'info');
            } else {
                db_run('UPDATE subscribers SET is_active = 1, name = ? WHERE id = ?', [$name, (int)$existing['id']]);
                flash(e($email) . ' was already on the list and has been reactivated.', 'success');
                $added = true;
            }
        } else {
            db_run(
                'INSERT INTO subscribers (name, email, ip_address) VALUES (?, ?, ?)',
                [$name, $email, client_ip()]
            );
            flash(e($name) . ' was added to the list.', 'success');
            $added = true;
        }

        // Adding somebody by hand is not the same as them signing up, so the
        // welcome is opt-in here — and a failure is reported rather than
        // swallowed, because you are standing right there watching.
        if ($added && $welcome) {
            $result = send_welcome_email(['name' => $name, 'email' => $email]);
            flash(
                $result['ok']
                    ? 'The welcome email is on its way to ' . e($email) . '.'
                    : 'They are on the list, but the welcome email failed — ' . e($result['detail']),
                $result['ok'] ? 'success' : 'error'
            );
        }

        redirect($listUrl);
    }

    redirect($listUrl);
}

// =====================================================================
//  Filter
// =====================================================================
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'active', 'inactive'], true)) {
    $filter = 'all';
}
$search = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

if ($filter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($filter === 'inactive') {
    $where[] = 'is_active = 0';
}
if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like]);
}

$sql = 'SELECT * FROM subscribers '
     . ($where ? 'WHERE ' . implode(' AND ', $where) . ' ' : '')
     . 'ORDER BY created_at DESC, id DESC';

// ---------------------------------------------------------------------
//  CSV export — must run before any HTML is sent
// ---------------------------------------------------------------------
if ((string)($_GET['export'] ?? '') === 'csv') {
    $rows = db_all($sql, $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="unika-kebab-subscribers-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                    // BOM, so Excel reads accents correctly
    fputcsv($out, ['Name', 'Email', 'Status', 'Subscribed']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['name'],
            $row['email'],
            (int)$row['is_active'] === 1 ? 'Active' : 'Unsubscribed',
            date('Y-m-d H:i', strtotime((string)$row['created_at'])),
        ]);
    }

    fclose($out);
    exit;
}

$subscribers = db_all($sql, $params);

$counts = db_one(
    'SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), "%Y-%m-01") THEN 1 ELSE 0 END) AS this_month
       FROM subscribers'
) ?? [];
$total     = (int)($counts['total'] ?? 0);
$active    = (int)($counts['active'] ?? 0);
$thisMonth = (int)($counts['this_month'] ?? 0);

$keepFields = '<input type="hidden" name="keep_filter" value="' . e($filter === 'all' ? '' : $filter) . '">'
            . '<input type="hidden" name="keep_q" value="' . e($search) . '">';

$listLink = function (array $extra = []) use ($filter, $search): string {
    $q = array_filter([
        'filter' => $filter === 'all' ? '' : $filter,
        'q'      => $search,
    ] + $extra, fn($v) => (string)$v !== '');
    return admin_url('subscribers.php' . ($q ? '?' . http_build_query($q) : ''));
};

$welcomeReady = mail_is_enabled() && setting('welcome_email_enabled', '1') === '1' && welcome_template() !== null;

$title     = 'Subscribers';
$subtitle  = $active . ' active subscriber' . ($active === 1 ? '' : 's') . ' on the newsletter list';
$activeNav = 'subscribers';
require __DIR__ . '/includes/header.php';
?>

<div class="row g-4 a-mb0">
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon"><i class="fa-solid fa-users"></i></div>
      <div>
        <div class="a-stat__num"><?= $total ?></div>
        <div class="a-stat__label">On the list</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon a-stat__icon--green"><i class="fa-solid fa-envelope-circle-check"></i></div>
      <div>
        <div class="a-stat__num"><?= $active ?></div>
        <div class="a-stat__label">Active</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon a-stat__icon--blue"><i class="fa-solid fa-calendar-plus"></i></div>
      <div>
        <div class="a-stat__num"><?= $thisMonth ?></div>
        <div class="a-stat__label">Joined this month</div>
      </div>
    </div>
  </div>
</div>

<div class="a-mt"></div>

<!-- ============================= list ============================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-users"></i>Newsletter list</h2>
      <p class="a-card__sub"><?= count($subscribers) ?> shown</p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn <?= $filter === 'all' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => ''])) ?>">All</a>
      <a class="a-btn <?= $filter === 'active' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => 'active'])) ?>">Active</a>
      <a class="a-btn <?= $filter === 'inactive' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => 'inactive'])) ?>">Unsubscribed</a>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-flex">
      <form class="a-flex" method="get" action="<?= e(admin_url('subscribers.php')) ?>">
        <?php if ($filter !== 'all'): ?>
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <?php endif; ?>
        <label class="visually-hidden" for="subSearch">Search subscribers</label>
        <input class="a-input a-w-md" type="search" id="subSearch" name="q"
               value="<?= e($search) ?>" placeholder="Search name or email…">
        <button class="a-btn a-btn--dark" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($search !== ''): ?>
          <a class="a-btn a-btn--ghost" href="<?= e($listLink(['q' => ''])) ?>"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($subscribers): ?>
        <a class="a-btn a-btn--sm ms-auto" href="<?= e(admin_url('campaigns.php?new=1')) ?>#camp-form">
          <i class="fa-solid fa-paper-plane"></i> Email this list
        </a>
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e($listLink(['export' => 'csv'])) ?>">
          <i class="fa-solid fa-file-csv"></i> Export CSV
        </a>
      <?php endif; ?>
    </div>

    <p class="a-field__hint a-mb0">
      <i class="fa-solid fa-circle-info"></i>
      Anyone who unsubscribes from an email is deleted from this table outright — the row disappears
      rather than turning grey. “Unsubscribed” below means you switched somebody off by hand.
    </p>
  </div>

  <?php if (!$subscribers): ?>
    <div class="a-empty">
      <i class="fa-solid fa-users"></i>
      <?php if ($search !== '' || $filter !== 'all'): ?>
        <p>Nobody matches that search.</p>
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('subscribers.php')) ?>">Show everyone</a>
      <?php else: ?>
        <p>No subscribers yet. The sign-up band on the home page feeds this list.</p>
        <a class="a-btn a-btn--ghost" href="<?= e(url('index.php')) ?>#newsletter" target="_blank" rel="noopener">
          <i class="fa-solid fa-eye"></i> View the sign-up band
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Joined</th>
            <th class="is-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($subscribers as $sub): ?>
          <?php $isActive = (int)$sub['is_active'] === 1; $sName = (string)$sub['name']; ?>
          <tr<?= $isActive ? '' : ' class="is-off"' ?>>
            <td class="a-table__name"><?= e($sName) ?></td>
            <td><a href="mailto:<?= e($sub['email']) ?>"><?= e($sub['email']) ?></a></td>
            <td>
              <?php if ($isActive): ?>
                <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Active</span>
              <?php else: ?>
                <span class="a-badge a-badge--off"><i class="fa-solid fa-ban"></i> Unsubscribed</span>
              <?php endif; ?>
            </td>
            <td class="a-nowrap a-small a-muted"><?= e(date('j M Y', strtotime((string)$sub['created_at']))) ?></td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <form method="post" data-guard>
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="<?= $isActive ? 'Unsubscribe' : 'Resubscribe' ?>"
                          aria-label="<?= $isActive ? 'Unsubscribe' : 'Resubscribe' ?> <?= e($sName) ?>">
                    <i class="fa-solid <?= $isActive ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                  </button>
                </form>

                <form method="post" data-guard
                      data-confirm="Remove <?= e($sub['email']) ?> from the list completely?">
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$sub['id'] ?>">
                  <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                          title="Delete" aria-label="Delete <?= e($sName) ?>">
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

<!-- ============================ add one ============================ -->
<div class="a-card" id="add-form">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-user-plus"></i>Add someone manually</h2>
      <p class="a-card__sub">For people who signed up in store or over the phone</p>
    </div>
  </div>

  <form method="post" data-guard>
    <?= csrf_field() ?><?= $keepFields ?>
    <input type="hidden" name="action" value="add">

    <div class="a-card__body">
      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="subName">Name <span class="req">*</span></label>
          <input class="a-input" type="text" id="subName" name="name" maxlength="120" required
                 value="<?= e(old('name')) ?>" placeholder="Ayşe Y.">
        </div>
        <div class="a-field">
          <label class="a-field__label" for="subEmail">Email <span class="req">*</span></label>
          <input class="a-input" type="email" id="subEmail" name="email" maxlength="150" required
                 value="<?= e(old('email')) ?>" placeholder="name@email.com">
        </div>
      </div>
      <p class="a-field__hint">
        Only add people who have actually asked to hear from you — it keeps your list clean and your emails out of spam folders.
      </p>

      <label class="a-switch">
        <input type="checkbox" name="send_welcome" value="1" <?= $welcomeReady ? 'checked' : 'disabled' ?>>
        <span class="a-switch__track"></span>
        <span>Send them the welcome email now</span>
      </label>
      <p class="a-field__hint">
        <?php if ($welcomeReady): ?>
          The same email somebody gets when they join through the website.
          <a href="<?= e(admin_url('templates.php')) ?>">Edit the wording</a>.
        <?php else: ?>
          Unavailable until outgoing email is set up under
          <a href="<?= e(admin_url('settings.php#smtp')) ?>">Settings</a>.
        <?php endif; ?>
      </p>
    </div>

    <div class="a-card__foot">
      <button class="a-btn" type="submit"><i class="fa-solid fa-plus"></i> Add subscriber</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
