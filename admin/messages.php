<?php
/**
 * Admin · Contact messages
 * Everything sent through the contact form, with its SMS forwarding status.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** "2 hours ago" style timestamp. */
function time_ago(?string $datetime): string
{
    $ts = $datetime ? strtotime($datetime) : false;
    if ($ts === false) {
        return 'unknown';
    }

    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return ($h = floor($diff / 3600)) . ' hour' . ($h === 1.0 ? '' : 's') . ' ago';
    if ($diff < 604800) return ($d = floor($diff / 86400)) . ' day' . ($d === 1.0 ? '' : 's') . ' ago';

    return date('j M Y', $ts);
}

/** The badge shown for a row's SMS forwarding result. */
function sms_badge(string $status): array
{
    switch ($status) {
        case 'sent':   return ['a-badge--on',  'fa-comment-sms',        'Sent'];
        case 'failed': return ['a-badge--red', 'fa-triangle-exclamation', 'Failed'];
        default:       return ['a-badge--off', 'fa-minus',              'Not sent'];
    }
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = $id ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$id]) : null;

    // Preserve the current filter / search across the redirect.
    $query = [];
    foreach (['filter', 'q'] as $key) {
        $value = trim((string)($_POST['keep_' . $key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }
    $listUrl = 'admin/messages.php' . ($query ? '?' . http_build_query($query) : '');

    if ($action === 'mark_read' || $action === 'mark_unread') {
        if ($row) {
            $read = $action === 'mark_read' ? 1 : 0;
            db_run('UPDATE contact_messages SET is_read = ? WHERE id = ?', [$read, (int)$row['id']]);
            flash('Message from ' . e($row['name']) . ' marked as ' . ($read ? 'read' : 'unread') . '.', 'success');
        } else {
            flash('That message no longer exists.', 'error');
        }
        redirect($listUrl);
    }

    if ($action === 'delete') {
        if ($row) {
            db_run('DELETE FROM contact_messages WHERE id = ?', [(int)$row['id']]);
            flash('The message from ' . e($row['name']) . ' was deleted.', 'success');
        } else {
            flash('That message no longer exists.', 'error');
        }
        redirect($listUrl);
    }

    if ($action === 'delete_all_read') {
        $n = db_run('DELETE FROM contact_messages WHERE is_read = 1')->rowCount();
        flash(
            $n > 0
                ? $n . ' read message' . ($n === 1 ? '' : 's') . ' deleted.'
                : 'There were no read messages to delete.',
            $n > 0 ? 'success' : 'info'
        );
        redirect($listUrl);
    }

    if ($action === 'resend_sms') {
        if (!$row) {
            flash('That message no longer exists.', 'error');
            redirect($listUrl);
        }

        $to = trim(setting('twilio_to_number'));
        if (setting('sms_enabled', '0') !== '1' || $to === '') {
            flash(
                'SMS forwarding is not switched on yet. '
                . '<a href="' . e(admin_url('settings.php')) . '">Set up Twilio in Settings</a>.',
                'error'
            );
            redirect($listUrl . (strpos($listUrl, '?') === false ? '?' : '&') . 'view=' . (int)$row['id']);
        }

        $body = contact_sms_body(
            (string)$row['name'],
            (string)$row['email'],
            (string)($row['phone'] ?? ''),
            (string)($row['subject'] ?? ''),
            (string)$row['message']
        );

        $result = send_sms($to, $body);
        db_run(
            'UPDATE contact_messages SET sms_status = ?, sms_detail = ? WHERE id = ?',
            [$result['status'], mb_substr($result['detail'], 0, 255), (int)$row['id']]
        );

        flash(
            'SMS ' . e($result['status']) . ' — ' . e($result['detail']),
            $result['status'] === 'sent' ? 'success' : 'error'
        );
        redirect($listUrl . (strpos($listUrl, '?') === false ? '?' : '&') . 'view=' . (int)$row['id']);
    }

    redirect($listUrl);
}

// =====================================================================
//  Open one message
// =====================================================================
$viewId  = (int)($_GET['view'] ?? 0);
$viewing = $viewId ? db_one('SELECT * FROM contact_messages WHERE id = ?', [$viewId]) : null;

// Opening an unread message marks it read.
if ($viewing && (int)$viewing['is_read'] === 0) {
    db_run('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [(int)$viewing['id']]);
    $viewing['is_read'] = 1;
}

// =====================================================================
//  Filter + list
// =====================================================================
$filter = (string)($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
$search = trim((string)($_GET['q'] ?? ''));

$where  = [];
$params = [];

if ($filter === 'unread') {
    $where[] = 'is_read = 0';
} elseif ($filter === 'read') {
    $where[] = 'is_read = 1';
}
if ($search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    $like     = '%' . $search . '%';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$messages = db_all(
    'SELECT * FROM contact_messages '
    . ($where ? 'WHERE ' . implode(' AND ', $where) . ' ' : '')
    . 'ORDER BY created_at DESC, id DESC LIMIT 300',
    $params
);

$counts = db_one(
    'SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) AS `read`
       FROM contact_messages'
) ?? [];
$total  = (int)($counts['total'] ?? 0);
$unread = (int)($counts['unread'] ?? 0);
$read   = (int)($counts['read'] ?? 0);

$keepFields = '<input type="hidden" name="keep_filter" value="' . e($filter === 'all' ? '' : $filter) . '">'
            . '<input type="hidden" name="keep_q" value="' . e($search) . '">';

/** Build a list URL that keeps the current filter. */
$listLink = function (array $extra = []) use ($filter, $search): string {
    $q = array_filter([
        'filter' => $filter === 'all' ? '' : $filter,
        'q'      => $search,
    ] + $extra, fn($v) => (string)$v !== '');
    return admin_url('messages.php' . ($q ? '?' . http_build_query($q) : ''));
};

$title     = 'Messages';
$subtitle  = $unread > 0
    ? $unread . ' unread message' . ($unread === 1 ? '' : 's') . ' waiting'
    : 'Everything sent through the contact form';
$activeNav = 'messages';
require __DIR__ . '/includes/header.php';
?>

<?php if ($viewing): ?>
  <?php
    $vName    = (string)$viewing['name'];
    $vSubject = trim((string)($viewing['subject'] ?? ''));
    [$smsClass, $smsIcon, $smsLabel] = sms_badge((string)$viewing['sms_status']);
    $replySubject = 'Re: ' . ($vSubject !== '' ? $vSubject : 'your message to Unika Kebab');
  ?>
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-envelope-open-text"></i><?= e($vName) ?></h2>
        <p class="a-card__sub">
          <?= e(date('j F Y \a\t g:i a', strtotime((string)$viewing['created_at']))) ?>
          · <?= e(time_ago($viewing['created_at'])) ?>
        </p>
      </div>
      <div class="a-card__actions">
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e($listLink()) ?>">
          <i class="fa-solid fa-xmark"></i> Close
        </a>
      </div>
    </div>

    <div class="a-card__body">
      <div class="a-grid-2">
        <div class="a-field">
          <span class="a-field__label">Email</span>
          <p class="a-mb0"><a href="mailto:<?= e($viewing['email']) ?>"><?= e($viewing['email']) ?></a></p>
        </div>
        <div class="a-field">
          <span class="a-field__label">Phone</span>
          <p class="a-mb0">
            <?php if (!empty($viewing['phone'])): ?>
              <a href="<?= e(phone_href($viewing['phone'])) ?>"><?= e($viewing['phone']) ?></a>
            <?php else: ?>
              <span class="a-muted">Not given</span>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <div class="a-field">
        <span class="a-field__label">Subject</span>
        <p class="a-mb0"><?= $vSubject !== '' ? e($vSubject) : '<span class="a-muted">No subject</span>' ?></p>
      </div>

      <div class="a-field">
        <span class="a-field__label">Message</span>
        <p class="a-mb0"><?= nl2br(e($viewing['message'])) ?></p>
      </div>

      <div class="a-grid-2">
        <div class="a-field">
          <span class="a-field__label">SMS forwarding</span>
          <p class="a-mb0">
            <span class="a-badge <?= e($smsClass) ?>"><i class="fa-solid <?= e($smsIcon) ?>"></i> <?= e($smsLabel) ?></span>
            <?php if (!empty($viewing['sms_detail'])): ?>
              <span class="a-muted a-small"><?= e($viewing['sms_detail']) ?></span>
            <?php endif; ?>
          </p>
        </div>
        <div class="a-field">
          <span class="a-field__label">Sent from</span>
          <p class="a-mb0 a-muted a-small"><?= e($viewing['ip_address'] ?: 'unknown address') ?></p>
        </div>
      </div>
    </div>

    <div class="a-card__foot">
      <a class="a-btn" href="mailto:<?= e($viewing['email']) ?>?subject=<?= e(rawurlencode($replySubject)) ?>">
        <i class="fa-solid fa-reply"></i> Reply by email
      </a>

      <?php if (!empty($viewing['phone'])): ?>
        <a class="a-btn a-btn--ghost" href="<?= e(phone_href($viewing['phone'])) ?>">
          <i class="fa-solid fa-phone"></i> Call
        </a>
      <?php endif; ?>

      <form method="post" data-guard>
        <?= csrf_field() ?><?= $keepFields ?>
        <input type="hidden" name="action" value="mark_unread">
        <input type="hidden" name="id" value="<?= (int)$viewing['id'] ?>">
        <button class="a-btn a-btn--ghost" type="submit"><i class="fa-solid fa-envelope"></i> Mark unread</button>
      </form>

      <form method="post" data-guard>
        <?= csrf_field() ?><?= $keepFields ?>
        <input type="hidden" name="action" value="resend_sms">
        <input type="hidden" name="id" value="<?= (int)$viewing['id'] ?>">
        <button class="a-btn a-btn--ghost" type="submit"><i class="fa-solid fa-comment-sms"></i> Send as SMS</button>
      </form>

      <form method="post" data-guard
            data-confirm="Delete this message from <?= e($vName) ?>? This cannot be undone.">
        <?= csrf_field() ?><?= $keepFields ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$viewing['id'] ?>">
        <button class="a-btn a-btn--danger" type="submit"><i class="fa-solid fa-trash"></i> Delete</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- ============================= list ============================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-inbox"></i>Inbox</h2>
      <p class="a-card__sub">
        <?= $total ?> message<?= $total === 1 ? '' : 's' ?> · <?= $unread ?> unread
      </p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn <?= $filter === 'all' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => ''])) ?>">All</a>
      <a class="a-btn <?= $filter === 'unread' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => 'unread'])) ?>">
        Unread<?= $unread > 0 ? ' (' . $unread . ')' : '' ?>
      </a>
      <a class="a-btn <?= $filter === 'read' ? '' : 'a-btn--ghost' ?> a-btn--sm" href="<?= e($listLink(['filter' => 'read'])) ?>">Read</a>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-flex">
      <form class="a-flex" method="get" action="<?= e(admin_url('messages.php')) ?>">
        <?php if ($filter !== 'all'): ?>
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <?php endif; ?>
        <label class="visually-hidden" for="msgSearch">Search messages</label>
        <input class="a-input a-w-md" type="search" id="msgSearch" name="q"
               value="<?= e($search) ?>" placeholder="Search name, email or text…">
        <button class="a-btn a-btn--dark" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($search !== ''): ?>
          <a class="a-btn a-btn--ghost" href="<?= e($listLink(['q' => ''])) ?>"><i class="fa-solid fa-xmark"></i> Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($read > 0): ?>
        <form class="ms-auto" method="post" data-guard
              data-confirm="Delete all <?= $read ?> read message<?= $read === 1 ? '' : 's' ?>? This cannot be undone.">
          <?= csrf_field() ?><?= $keepFields ?>
          <input type="hidden" name="action" value="delete_all_read">
          <button class="a-btn a-btn--danger a-btn--sm" type="submit">
            <i class="fa-solid fa-broom"></i> Delete read (<?= $read ?>)
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$messages): ?>
    <div class="a-empty">
      <i class="fa-solid fa-inbox"></i>
      <?php if ($search !== '' || $filter !== 'all'): ?>
        <p>Nothing matches that search.</p>
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('messages.php')) ?>">Show every message</a>
      <?php else: ?>
        <p>No messages yet. Anything sent through the contact form lands here.</p>
        <a class="a-btn a-btn--ghost" href="<?= e(url('contact.php')) ?>" target="_blank" rel="noopener">
          <i class="fa-solid fa-eye"></i> View the contact page
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th>From</th>
            <th>Message</th>
            <th>SMS</th>
            <th>Received</th>
            <th class="is-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($messages as $msg): ?>
          <?php
            $isUnread = (int)$msg['is_read'] === 0;
            $mName    = (string)$msg['name'];
            $mSubject = trim((string)($msg['subject'] ?? ''));
            [$smsClass, $smsIcon, $smsLabel] = sms_badge((string)$msg['sms_status']);
          ?>
          <tr<?= $isUnread ? ' class="is-unread"' : '' ?>>
            <td>
              <div class="a-table__name">
                <?= e($mName) ?>
                <?php if ($isUnread): ?>
                  <span class="a-badge a-badge--gold"><i class="fa-solid fa-circle"></i> New</span>
                <?php endif; ?>
              </div>
              <div class="a-table__meta"><?= e($msg['email']) ?></div>
            </td>
            <td>
              <div class="a-table__name"><?= $mSubject !== '' ? e($mSubject) : '<span class="a-muted">No subject</span>' ?></div>
              <div class="a-table__meta"><?= e(excerpt($msg['message'], 70)) ?></div>
            </td>
            <td>
              <span class="a-badge <?= e($smsClass) ?>" title="<?= e($msg['sms_detail'] ?? '') ?>">
                <i class="fa-solid <?= e($smsIcon) ?>"></i> <?= e($smsLabel) ?>
              </span>
            </td>
            <td class="a-nowrap a-small a-muted"
                title="<?= e(date('j F Y \a\t g:i a', strtotime((string)$msg['created_at']))) ?>">
              <?= e(time_ago($msg['created_at'])) ?>
            </td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e($listLink(['view' => (int)$msg['id']])) ?>">
                  <i class="fa-solid fa-eye"></i> View
                </a>

                <form method="post" data-guard>
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="<?= $isUnread ? 'mark_read' : 'mark_unread' ?>">
                  <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="Mark as <?= $isUnread ? 'read' : 'unread' ?>"
                          aria-label="Mark message from <?= e($mName) ?> as <?= $isUnread ? 'read' : 'unread' ?>">
                    <i class="fa-solid <?= $isUnread ? 'fa-envelope-open' : 'fa-envelope' ?>"></i>
                  </button>
                </form>

                <form method="post" data-guard
                      data-confirm="Delete this message from <?= e($mName) ?>? This cannot be undone.">
                  <?= csrf_field() ?><?= $keepFields ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
                  <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                          title="Delete" aria-label="Delete message from <?= e($mName) ?>">
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

<?php require __DIR__ . '/includes/footer.php'; ?>
