<?php
/**
 * Admin · Email campaigns
 * Writing a newsletter and getting it to everybody on the list.
 *
 * A send is deliberately not one long request. Starting a campaign copies
 * the recipients into email_queue, and the browser then works through that
 * queue a batch at a time. A shared-hosting time limit, a closed laptop or
 * a dropped connection costs you one batch, never the whole send — pick it
 * up again and it carries on from the first address still pending.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** How many people would receive a campaign right now. */
function campaign_audience_count(): int
{
    return (int)(db_one('SELECT COUNT(*) AS n FROM subscribers WHERE is_active = 1')['n'] ?? 0);
}

function campaign_row(int $id): ?array
{
    return $id > 0 ? db_one('SELECT * FROM email_campaigns WHERE id = ?', [$id]) : null;
}

/** Where a campaign has got to, counted from the queue itself. */
function campaign_progress(int $id): array
{
    $row = db_one(
        "SELECT COUNT(*) AS total,
                SUM(status = 'sent')   AS sent,
                SUM(status = 'failed') AS failed
           FROM email_queue WHERE campaign_id = ?",
        [$id]
    ) ?? [];

    $total  = (int)($row['total'] ?? 0);
    $sent   = (int)($row['sent'] ?? 0);
    $failed = (int)($row['failed'] ?? 0);

    return [
        'total'     => $total,
        'sent'      => $sent,
        'failed'    => $failed,
        'remaining' => max(0, $total - $sent - $failed),
        'percent'   => $total > 0 ? (int)round((($sent + $failed) / $total) * 100) : 0,
    ];
}

/** Copy the counters back onto the campaign, and close it when the queue is empty. */
function campaign_sync(int $id): array
{
    $p = campaign_progress($id);

    if ($p['remaining'] === 0 && $p['total'] > 0) {
        db_run(
            "UPDATE email_campaigns
                SET sent_count = ?, failed_count = ?, status = 'sent', finished_at = NOW()
              WHERE id = ? AND status = 'sending'",
            [$p['sent'], $p['failed'], $id]
        );
    } else {
        db_run(
            'UPDATE email_campaigns SET sent_count = ?, failed_count = ? WHERE id = ?',
            [$p['sent'], $p['failed'], $id]
        );
    }

    return $p;
}

function campaign_input(array $post): array
{
    return [
        'name'      => mb_substr(trim((string)($post['name'] ?? '')), 0, 150),
        'subject'   => mb_substr(trim((string)($post['subject'] ?? '')), 0, 200),
        'preheader' => mb_substr(trim((string)($post['preheader'] ?? '')), 0, 200),
        'body_html' => trim((string)($post['body_html'] ?? '')),
    ];
}

const CAMPAIGN_PREVIEW_PERSON = ['name' => 'Alex Demir', 'email' => 'preview@example.com'];

$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

/** Answer an AJAX batch request. */
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    exit(json_encode($data));
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        if ($isAjax) {
            json_out(['ok' => false, 'message' => 'Your session expired. Reload the page and start the send again.'], 403);
        }
        csrf_require();
    }

    $action   = (string)($_POST['action'] ?? '');
    $id       = (int)($_POST['id'] ?? 0);
    $campaign = campaign_row($id);
    $id       = $campaign ? (int)$campaign['id'] : 0;

    // ---------------------------------------------------------------
    //  Live preview for the composer
    // ---------------------------------------------------------------
    if ($action === 'preview_html') {
        $in = campaign_input($_POST);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        exit(email_compose(CAMPAIGN_PREVIEW_PERSON, $in['subject'], $in['body_html'], $in['preheader'])['html']);
    }

    // ---------------------------------------------------------------
    //  Work through the queue — the heart of the send
    // ---------------------------------------------------------------
    if ($action === 'send_batch') {
        if (!$campaign) {
            if ($isAjax) {
                json_out(['ok' => false, 'message' => 'That campaign no longer exists.'], 404);
            }
            flash('That campaign no longer exists.', 'error');
            redirect('admin/campaigns.php');
        }

        if ($campaign['status'] !== 'sending') {
            $p = campaign_progress($id);
            $payload = [
                'ok'      => true,
                'done'    => true,
                'status'  => $campaign['status'],
                'message' => 'This campaign is not sending right now.',
            ] + $p;

            if ($isAjax) {
                json_out($payload);
            }
            flash($payload['message'], 'info');
            redirect('admin/campaigns.php?id=' . $id);
        }

        $batch = mail_batch_size();

        // Claim this batch before touching the mail server, so a second
        // request landing at the same moment picks up different rows.
        db_run(
            "UPDATE email_queue SET status = 'sending'
              WHERE campaign_id = ? AND status = 'pending'
              ORDER BY id LIMIT " . $batch,
            [$id]
        );
        $rows = db_all("SELECT * FROM email_queue WHERE campaign_id = ? AND status = 'sending' ORDER BY id", [$id]);

        if (!$rows) {
            $p = campaign_sync($id);
            $payload = ['ok' => true, 'done' => true, 'status' => 'sent',
                        'message' => 'All done — ' . $p['sent'] . ' delivered.'] + $p;

            if ($isAjax) {
                json_out($payload);
            }
            flash($payload['message'], 'success');
            redirect('admin/campaigns.php?id=' . $id);
        }

        // One connection for the whole batch: the handshake and the login
        // are the slow part, and doing them once per recipient would be
        // both slower and a good way to get rate-limited.
        $mailer = new SmtpMailer();
        try {
            $mailer->open();
        } catch (Throwable $e) {
            // Hand the batch back so nothing is lost, and say why.
            db_run("UPDATE email_queue SET status = 'pending' WHERE campaign_id = ? AND status = 'sending'", [$id]);

            $payload = ['ok' => false, 'message' => 'The mail server could not be reached — ' . $e->getMessage()]
                     + campaign_progress($id);

            if ($isAjax) {
                json_out($payload, 200);
            }
            flash(e($payload['message']), 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }

        foreach ($rows as $row) {
            $result = mail_send_template(
                ['name' => (string)$row['name'], 'email' => (string)$row['email']],
                (string)$campaign['subject'],
                (string)$campaign['body_html'],
                (string)($campaign['preheader'] ?? ''),
                ['mailer' => $mailer, 'kind' => 'campaign', 'campaign_id' => $id]
            );

            db_run(
                'UPDATE email_queue SET status = ?, error = ?, sent_at = NOW() WHERE id = ?',
                [
                    $result['ok'] ? 'sent' : 'failed',
                    $result['ok'] ? null : mb_substr($result['detail'], 0, 255),
                    (int)$row['id'],
                ]
            );
        }

        $mailer->close();

        $p    = campaign_sync($id);
        $done = $p['remaining'] === 0;

        $payload = [
            'ok'      => true,
            'done'    => $done,
            'status'  => $done ? 'sent' : 'sending',
            'message' => $done
                ? 'Finished — ' . $p['sent'] . ' delivered' . ($p['failed'] ? ', ' . $p['failed'] . ' failed' : '') . '.'
                : 'Sent ' . ($p['sent'] + $p['failed']) . ' of ' . $p['total'] . '…',
        ] + $p;

        if ($isAjax) {
            json_out($payload);
        }
        flash($payload['message'], $done ? 'success' : 'info');
        redirect('admin/campaigns.php?id=' . $id);
    }

    // ---------------------------------------------------------------
    //  Save a draft
    // ---------------------------------------------------------------
    if ($action === 'save') {
        $in         = campaign_input($_POST);
        $templateId = (int)($_POST['template_id'] ?? 0);
        $backTo     = 'admin/campaigns.php?' . ($id ? 'id=' . $id : 'new=1') . '#camp-form';

        if ($campaign && $campaign['status'] !== 'draft') {
            flash('That campaign has already been sent, so it cannot be edited. Duplicate it instead.', 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }

        $errors = [];
        if ($in['subject'] === '')   { $errors[] = 'A subject line is what people see in their inbox — it cannot be empty.'; }
        if ($in['body_html'] === '') { $errors[] = 'The email body is empty.'; }

        if ($errors) {
            old_set($_POST);
            flash(e(implode(' ', $errors)), 'error');
            redirect($backTo);
        }

        if ($in['name'] === '') {
            $in['name'] = mb_substr($in['subject'], 0, 150);
        }
        if (!email_template($templateId)) {
            $templateId = 0;
        }

        if ($campaign) {
            db_run(
                'UPDATE email_campaigns SET name = ?, subject = ?, preheader = ?, body_html = ?, template_id = ? WHERE id = ?',
                [$in['name'], $in['subject'], ($in['preheader'] === '' ? null : $in['preheader']),
                 $in['body_html'], ($templateId ?: null), $id]
            );
        } else {
            db_run(
                "INSERT INTO email_campaigns (template_id, name, subject, preheader, body_html, status)
                 VALUES (?, ?, ?, ?, ?, 'draft')",
                [($templateId ?: null), $in['name'], $in['subject'],
                 ($in['preheader'] === '' ? null : $in['preheader']), $in['body_html']]
            );
            $id = (int)db()->lastInsertId();
        }

        flash('Draft “' . e($in['name']) . '” saved. Nothing has been sent yet.', 'success');
        redirect('admin/campaigns.php?id=' . $id);
    }

    // ---------------------------------------------------------------
    //  Start sending — freeze the recipient list, then hand over to the queue
    // ---------------------------------------------------------------
    if ($action === 'start') {
        if (!$campaign) {
            flash('That campaign no longer exists.', 'error');
            redirect('admin/campaigns.php');
        }
        if ($campaign['status'] === 'sending') {
            redirect('admin/campaigns.php?id=' . $id);
        }
        if ($campaign['status'] === 'sent') {
            flash('That campaign has already gone out. Duplicate it to send it again.', 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }
        if (!mail_is_enabled()) {
            $problems = mail_problems();
            flash('Email is not ready to send — ' . e($problems ? $problems[0] : 'check the mailbox settings.'), 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }

        // A paused campaign keeps whatever it already delivered.
        if ($campaign['status'] !== 'paused') {
            db_run('DELETE FROM email_queue WHERE campaign_id = ?', [$id]);
            db_run(
                'INSERT INTO email_queue (campaign_id, email, name)
                 SELECT ?, email, name FROM subscribers WHERE is_active = 1 ORDER BY id',
                [$id]
            );
        } else {
            // Anything left mid-flight when it was paused goes back in the pile.
            db_run("UPDATE email_queue SET status = 'pending' WHERE campaign_id = ? AND status = 'sending'", [$id]);
        }

        $total = (int)(db_one('SELECT COUNT(*) AS n FROM email_queue WHERE campaign_id = ?', [$id])['n'] ?? 0);

        if ($total === 0) {
            flash('There is nobody active on the list to send to yet.', 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }

        db_run(
            "UPDATE email_campaigns
                SET status = 'sending', total_recipients = ?, started_at = COALESCE(started_at, NOW()), finished_at = NULL
              WHERE id = ?",
            [$total, $id]
        );

        flash('Sending to ' . $total . ' subscriber' . ($total === 1 ? '' : 's') . '. Leave this page open until it finishes.', 'info');
        redirect('admin/campaigns.php?id=' . $id . '#progress');
    }

    // ---------------------------------------------------------------
    //  Pause a send in progress
    // ---------------------------------------------------------------
    if ($action === 'pause') {
        if (!$campaign) {
            flash('That campaign no longer exists.', 'error');
            redirect('admin/campaigns.php');
        }

        db_run("UPDATE email_campaigns SET status = 'paused' WHERE id = ? AND status = 'sending'", [$id]);
        db_run("UPDATE email_queue SET status = 'pending' WHERE campaign_id = ? AND status = 'sending'", [$id]);

        $p = campaign_progress($id);
        flash('Paused after ' . $p['sent'] . ' of ' . $p['total'] . '. Nobody gets the same email twice when you resume.', 'info');
        redirect('admin/campaigns.php?id=' . $id);
    }

    // ---------------------------------------------------------------
    //  Duplicate
    // ---------------------------------------------------------------
    if ($action === 'duplicate') {
        if (!$campaign) {
            flash('That campaign no longer exists.', 'error');
            redirect('admin/campaigns.php');
        }

        db_run(
            "INSERT INTO email_campaigns (template_id, name, subject, preheader, body_html, status)
             VALUES (?, ?, ?, ?, ?, 'draft')",
            [$campaign['template_id'], mb_substr($campaign['name'] . ' (copy)', 0, 150),
             $campaign['subject'], $campaign['preheader'], $campaign['body_html']]
        );

        flash('Copied to a new draft.', 'success');
        redirect('admin/campaigns.php?id=' . (int)db()->lastInsertId() . '#camp-form');
    }

    // ---------------------------------------------------------------
    //  Delete
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$campaign) {
            flash('That campaign no longer exists.', 'error');
            redirect('admin/campaigns.php');
        }
        if ($campaign['status'] === 'sending') {
            flash('That campaign is sending. Pause it first, then delete it.', 'error');
            redirect('admin/campaigns.php?id=' . $id);
        }

        db_run('DELETE FROM email_campaigns WHERE id = ?', [$id]);
        flash('“' . e($campaign['name']) . '” was deleted. The delivery log keeps its record.', 'success');
        redirect('admin/campaigns.php');
    }

    // ---------------------------------------------------------------
    //  Send one test copy
    // ---------------------------------------------------------------
    if ($action === 'test') {
        $to     = mb_substr(trim((string)($_POST['test_email'] ?? '')), 0, 150);
        $in     = campaign_input($_POST);
        $backTo = 'admin/campaigns.php' . ($id ? '?id=' . $id : '') . '#camp-form';

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('“' . e($to) . '” is not a valid email address.', 'error');
            redirect($backTo);
        }
        if ($in['subject'] === '' || $in['body_html'] === '') {
            flash('Write a subject and a body first, then send yourself a test.', 'error');
            redirect($backTo);
        }

        $result = mail_send_template(
            ['name' => (string)(current_admin()['full_name'] ?? 'there'), 'email' => $to],
            $in['subject'],
            $in['body_html'],
            $in['preheader'],
            ['kind' => 'test', 'campaign_id' => ($id ?: null)]
        );

        flash(
            $result['ok']
                ? 'Test copy sent to ' . e($to) . '. Your subscriber list was not touched.'
                : 'The test could not be sent — ' . e($result['detail']),
            $result['ok'] ? 'success' : 'error'
        );
        redirect($backTo);
    }

    redirect('admin/campaigns.php');
}

// =====================================================================
//  GET
// =====================================================================
$openId   = (int)($_GET['id'] ?? 0);
$campaign = campaign_row($openId);

if ($openId > 0 && !$campaign) {
    flash('That campaign no longer exists.', 'error');
}

// Starting from a template: /admin/campaigns.php?from=3
$fromTemplate = email_template((int)($_GET['from'] ?? 0));
$isNew        = isset($_GET['new']) || ($fromTemplate !== null && !$campaign);

$campaigns = db_all(
    'SELECT c.*, t.name AS template_name
       FROM email_campaigns c
       LEFT JOIN email_templates t ON t.id = c.template_id
   ORDER BY c.created_at DESC, c.id DESC'
);

$templates = email_templates();
$audience  = campaign_audience_count();
$mailReady = mail_is_enabled();

$progress = $campaign ? campaign_progress((int)$campaign['id']) : null;
$queue    = $campaign
    ? db_all('SELECT * FROM email_queue WHERE campaign_id = ? ORDER BY (status = ?) DESC, id', [(int)$campaign['id'], 'failed'])
    : [];

$recentLog = db_all('SELECT * FROM email_log ORDER BY id DESC LIMIT 25');

$logCounts = db_one(
    "SELECT SUM(status = 'sent') AS sent, SUM(status = 'failed') AS failed
       FROM email_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
) ?? [];

// ---- what the composer starts with ----------------------------------
$editable = $campaign === null || $campaign['status'] === 'draft';
$useOld   = (int)old('id', -1) === (int)($campaign['id'] ?? 0);

$source = $useOld ? null : ($campaign ?? $fromTemplate);

$val = function (string $key, $fallback = '') use ($useOld, $source) {
    if ($useOld) {
        return old($key, $fallback);
    }
    return $source[$key] ?? $fallback;
};

$formTemplateId = $useOld
    ? (int)old('template_id', 0)
    : (int)($campaign['template_id'] ?? $fromTemplate['id'] ?? 0);

$formName = (string)$val('name', $fromTemplate ? (string)$fromTemplate['name'] . ' — ' . date('j M Y') : '');

$showComposer = $isNew || ($campaign !== null && $editable);
$testDefault  = trim((string)(current_admin()['email'] ?? ''));

$statusBadge = [
    'draft'   => ['a-badge--off',  'fa-pen',            'Draft'],
    'sending' => ['a-badge--gold', 'fa-paper-plane',    'Sending'],
    'sent'    => ['a-badge--on',   'fa-circle-check',   'Sent'],
    'paused'  => ['a-badge--blue', 'fa-circle-pause',   'Paused'],
];

$title     = 'Email Campaigns';
$subtitle  = $audience . ' active subscriber' . ($audience === 1 ? '' : 's') . ' on the list';
$activeNav = 'campaigns';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$mailReady): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      Outgoing email is not switched on, so campaigns can be written but not sent.
      <a href="<?= e(admin_url('settings.php#smtp')) ?>">Finish the mailbox setup</a>.
    </span>
  </div>
<?php endif; ?>

<!-- ============================= numbers ============================= -->
<div class="row g-4 a-mb0">
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon a-stat__icon--green"><i class="fa-solid fa-users"></i></div>
      <div>
        <div class="a-stat__num"><?= $audience ?></div>
        <div class="a-stat__label">People a campaign reaches</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon"><i class="fa-solid fa-paper-plane"></i></div>
      <div>
        <div class="a-stat__num"><?= (int)($logCounts['sent'] ?? 0) ?></div>
        <div class="a-stat__label">Emails sent in 30 days</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="a-stat">
      <div class="a-stat__icon <?= (int)($logCounts['failed'] ?? 0) > 0 ? 'a-stat__icon--red' : 'a-stat__icon--blue' ?>">
        <i class="fa-solid fa-circle-exclamation"></i>
      </div>
      <div>
        <div class="a-stat__num"><?= (int)($logCounts['failed'] ?? 0) ?></div>
        <div class="a-stat__label">Failed in 30 days</div>
      </div>
    </div>
  </div>
</div>

<div class="a-mt"></div>

<?php if ($campaign && $campaign['status'] !== 'draft'): ?>
<!-- ============================= progress ============================= -->
<div class="a-card" id="progress">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-gauge-high"></i><?= e($campaign['name']) ?></h2>
      <p class="a-card__sub"><?= e($campaign['subject']) ?></p>
    </div>
    <div class="a-card__actions">
      <?php [$cls, $icon, $label] = $statusBadge[$campaign['status']] ?? $statusBadge['draft']; ?>
      <span class="a-badge <?= e($cls) ?>"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?></span>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-progress" id="campProgress"
         data-send-url="<?= e(admin_url('campaigns.php')) ?>"
         data-campaign-id="<?= (int)$campaign['id'] ?>"
         data-token="<?= e(csrf_token()) ?>"
         data-autostart="<?= $campaign['status'] === 'sending' ? '1' : '0' ?>">

      <div class="a-progress__bar">
        <span class="a-progress__fill" style="width:<?= (int)$progress['percent'] ?>%"></span>
      </div>

      <div class="a-progress__row">
        <span class="a-progress__count">
          <strong data-progress-done><?= (int)($progress['sent'] + $progress['failed']) ?></strong>
          of <strong data-progress-total><?= (int)$progress['total'] ?></strong> processed
        </span>
        <span class="a-progress__split">
          <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i>
            <span data-progress-sent><?= (int)$progress['sent'] ?></span> delivered</span>
          <span class="a-badge <?= $progress['failed'] > 0 ? 'a-badge--red' : 'a-badge--off' ?>">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span data-progress-failed><?= (int)$progress['failed'] ?></span> failed</span>
        </span>
      </div>

      <p class="a-progress__status" data-progress-message>
        <?php if ($campaign['status'] === 'sending'): ?>
          Working through the list — keep this tab open.
        <?php elseif ($campaign['status'] === 'paused'): ?>
          Paused. Resume and it continues from where it stopped.
        <?php else: ?>
          Finished <?= e($campaign['finished_at'] ? date('j M Y, g:i a', strtotime((string)$campaign['finished_at'])) : '') ?>.
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="a-card__foot">
    <?php if ($campaign['status'] === 'sending'): ?>
      <form method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="send_batch">
        <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
        <button class="a-btn" type="submit" data-batch-fallback>
          <i class="fa-solid fa-forward"></i> Send the next <?= mail_batch_size() ?>
        </button>
      </form>
      <form method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="pause">
        <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
        <button class="a-btn a-btn--ghost" type="submit"><i class="fa-solid fa-pause"></i> Pause</button>
      </form>
    <?php elseif ($campaign['status'] === 'paused'): ?>
      <form method="post" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start">
        <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
        <button class="a-btn" type="submit"><i class="fa-solid fa-play"></i> Resume sending</button>
      </form>
    <?php endif; ?>

    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="duplicate">
      <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
      <button class="a-btn a-btn--ghost" type="submit"><i class="fa-solid fa-copy"></i> Duplicate as a new draft</button>
    </form>

    <a class="a-btn a-btn--ghost" href="<?= e(admin_url('campaigns.php')) ?>">Back to campaigns</a>
  </div>
</div>

<?php if ($queue): ?>
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-list-check"></i>Who it went to</h2>
      <p class="a-card__sub"><?= count($queue) ?> recipient<?= count($queue) === 1 ? '' : 's' ?>, failures first</p>
    </div>
  </div>
  <div class="a-table-wrap">
    <table class="a-table">
      <thead>
        <tr><th>Name</th><th>Email</th><th>Result</th><th>When</th></tr>
      </thead>
      <tbody>
      <?php foreach ($queue as $q): ?>
        <tr<?= $q['status'] === 'failed' ? ' class="is-off"' : '' ?>>
          <td class="a-table__name"><?= e($q['name'] !== '' ? $q['name'] : '—') ?></td>
          <td><?= e($q['email']) ?></td>
          <td>
            <?php if ($q['status'] === 'sent'): ?>
              <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Delivered</span>
            <?php elseif ($q['status'] === 'failed'): ?>
              <span class="a-badge a-badge--red"><i class="fa-solid fa-circle-exclamation"></i> Failed</span>
              <span class="a-table__meta"><?= e($q['error']) ?></span>
            <?php else: ?>
              <span class="a-badge a-badge--off"><i class="fa-solid fa-clock"></i> Waiting</span>
            <?php endif; ?>
          </td>
          <td class="a-nowrap a-small a-muted">
            <?= e($q['sent_at'] ? date('j M, g:i a', strtotime((string)$q['sent_at'])) : '—') ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; /* progress block */ ?>

<!-- ============================= list ============================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-paper-plane"></i>Campaigns</h2>
      <p class="a-card__sub">Every newsletter, draft or sent</p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('templates.php')) ?>">
        <i class="fa-solid fa-file-lines"></i> <span class="d-none d-sm-inline">Templates</span>
      </a>
      <a class="a-btn a-btn--sm" href="<?= e(admin_url('campaigns.php?new=1')) ?>#camp-form">
        <i class="fa-solid fa-plus"></i> New campaign
      </a>
    </div>
  </div>

  <?php if (!$campaigns): ?>
    <div class="a-empty">
      <i class="fa-solid fa-paper-plane"></i>
      <p>No campaigns yet. Start from a template and it is two clicks to the whole list.</p>
      <a class="a-btn" href="<?= e(admin_url('campaigns.php?new=1')) ?>#camp-form">
        <i class="fa-solid fa-plus"></i> New campaign
      </a>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th>Campaign</th>
            <th>Status</th>
            <th>Delivered</th>
            <th>Created</th>
            <th class="is-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($campaigns as $c): ?>
          <?php [$cls, $icon, $label] = $statusBadge[$c['status']] ?? $statusBadge['draft']; ?>
          <tr<?= (int)$c['id'] === $openId ? ' class="is-current"' : '' ?>>
            <td class="a-table__name">
              <?= e($c['name']) ?>
              <span class="a-table__meta"><?= e(excerpt($c['subject'], 64)) ?></span>
            </td>
            <td><span class="a-badge <?= e($cls) ?>"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?></span></td>
            <td class="a-nowrap">
              <?php if ($c['status'] === 'draft'): ?>
                <span class="a-muted a-small">—</span>
              <?php else: ?>
                <strong><?= (int)$c['sent_count'] ?></strong>
                <span class="a-muted a-small">of <?= (int)$c['total_recipients'] ?></span>
                <?php if ((int)$c['failed_count'] > 0): ?>
                  <span class="a-badge a-badge--red"><?= (int)$c['failed_count'] ?> failed</span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="a-nowrap a-small a-muted"><?= e(date('j M Y', strtotime((string)$c['created_at']))) ?></td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                   href="<?= e(admin_url('campaigns.php?id=' . (int)$c['id'])) ?>#camp-form"
                   title="<?= $c['status'] === 'draft' ? 'Edit' : 'Open' ?>"
                   aria-label="Open <?= e($c['name']) ?>">
                  <i class="fa-solid fa-<?= $c['status'] === 'draft' ? 'pen' : 'chart-simple' ?>"></i>
                </a>

                <form method="post" data-guard>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="duplicate">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="Duplicate" aria-label="Duplicate <?= e($c['name']) ?>">
                    <i class="fa-solid fa-copy"></i>
                  </button>
                </form>

                <?php if ($c['status'] !== 'sending'): ?>
                  <form method="post" data-guard
                        data-confirm="Delete “<?= e($c['name']) ?>”? The delivery log keeps its record.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                            title="Delete" aria-label="Delete <?= e($c['name']) ?>">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($showComposer): ?>
<!-- ============================ composer ============================ -->
<div class="a-card" id="camp-form">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title">
        <i class="fa-solid fa-<?= $campaign ? 'pen' : 'plus' ?>"></i>
        <?= $campaign ? 'Edit draft' : 'New campaign' ?>
      </h2>
      <p class="a-card__sub">
        Pick a template to fill this in, or write it here. Saving only stores a draft —
        nothing leaves the building until you press send.
      </p>
    </div>
  </div>

  <form method="post" data-guard id="campForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($campaign['id'] ?? 0) ?>">

    <div class="a-card__body">
      <?php if ($templates): ?>
        <div class="a-field">
          <label class="a-field__label" for="campTemplate">Start from a template</label>
          <select class="a-select a-w-md" id="campTemplate" name="template_id" data-template-picker>
            <option value="0">Write it from scratch</option>
            <?php foreach ($templates as $tpl): ?>
              <option value="<?= (int)$tpl['id'] ?>"
                      <?= $formTemplateId === (int)$tpl['id'] ? 'selected' : '' ?>
                      data-subject="<?= e($tpl['subject']) ?>"
                      data-preheader="<?= e((string)$tpl['preheader']) ?>"
                      data-body="<?= e($tpl['body_html']) ?>">
                <?= e($tpl['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="a-field__hint">
            Choosing one copies its wording in here. Editing it afterwards changes this campaign only —
            the template itself is left alone.
          </p>
        </div>
      <?php endif; ?>

      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="campName">Campaign name</label>
          <input class="a-input" type="text" id="campName" name="name" maxlength="150"
                 value="<?= e($formName) ?>" placeholder="August specials">
          <p class="a-field__hint">Just for your own list. Left blank, we use the subject line.</p>
        </div>

        <div class="a-field">
          <label class="a-field__label" for="campSubject">Subject line <span class="req">*</span></label>
          <input class="a-input" type="text" id="campSubject" name="subject" maxlength="200" required
                 value="<?= e($val('subject')) ?>" placeholder="This month off the coals at {{site_name}}"
                 data-counter="#campSubjectCount" data-counter-ideal="60" data-tpl-field>
          <p class="a-field__hint"><span id="campSubjectCount"></span> · Inboxes cut it off around 60.</p>
        </div>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="campPreheader">Preview line</label>
        <input class="a-input" type="text" id="campPreheader" name="preheader" maxlength="200"
               value="<?= e($val('preheader')) ?>"
               placeholder="New skewers, a seasonal plate and something sweet." data-tpl-field>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="campBody">Email body <span class="req">*</span></label>
        <textarea class="a-textarea a-textarea--tall a-code" id="campBody" name="body_html" rows="16" required
                  spellcheck="false" data-tpl-field><?= e($val('body_html')) ?></textarea>
        <p class="a-field__hint">
          Same HTML as the template editor. The branded shell and the unsubscribe footer are added
          automatically — you never have to remember them.
        </p>
      </div>

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend"><i class="fa-solid fa-code"></i> Placeholders</legend>
        <div class="a-tokens">
          <?php foreach (email_placeholder_help() as $token => $help): ?>
            <button class="a-token" type="button" data-token="{{<?= e($token) ?>}}" title="<?= e($help) ?>">
              {{<?= e($token) ?>}}
            </button>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </div>

    <div class="a-card__foot">
      <button class="a-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save draft</button>
      <a class="a-btn a-btn--ghost" href="<?= e(admin_url('campaigns.php')) ?>">Cancel</a>
    </div>
  </form>
</div>

<!-- ============================ preview ============================ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-desktop"></i>Preview</h2>
      <p class="a-card__sub">What the campaign looks like in an inbox right now</p>
    </div>
    <div class="a-card__actions">
      <button class="a-btn a-btn--ghost a-btn--sm" type="button" data-preview-width="390">
        <i class="fa-solid fa-mobile-screen"></i> Phone
      </button>
      <button class="a-btn a-btn--ghost a-btn--sm" type="button" data-preview-width="0">
        <i class="fa-solid fa-display"></i> Desktop
      </button>
      <button class="a-btn a-btn--dark a-btn--sm" type="button" id="campRefresh">
        <i class="fa-solid fa-rotate"></i> Refresh
      </button>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-preview" id="campPreviewWrap"
         data-preview-url="<?= e(admin_url('campaigns.php')) ?>"
         data-preview-token="<?= e(csrf_token()) ?>"
         data-preview-form="#campForm">
      <div class="a-preview__bar">
        <span class="a-preview__from"><i class="fa-solid fa-circle-user"></i> <?= e(setting('mail_from_name', 'Unika Kebab')) ?></span>
        <span class="a-preview__subject"><?= e($val('subject', 'Subject line')) ?></span>
      </div>
      <iframe class="a-preview__frame" id="campPreviewFrame" title="Campaign preview" sandbox=""></iframe>
    </div>
  </div>
</div>

<!-- ============================ send ============================ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-rocket"></i>Test it, then send it</h2>
      <p class="a-card__sub">A test costs nothing. A campaign to <?= $audience ?> people cannot be taken back.</p>
    </div>
  </div>

  <div class="a-card__body">
    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <!-- Seeded with the saved draft so the test still works without
           JavaScript; the mirrors keep them in step with unsaved edits. -->
      <input type="hidden" name="id" value="<?= (int)($campaign['id'] ?? 0) ?>">
      <input type="hidden" name="subject"   value="<?= e($val('subject')) ?>"   data-mirror="#campSubject">
      <input type="hidden" name="preheader" value="<?= e($val('preheader')) ?>" data-mirror="#campPreheader">
      <input type="hidden" name="body_html" value="<?= e($val('body_html')) ?>" data-mirror="#campBody">

      <div class="a-flex">
        <label class="visually-hidden" for="campTest">Send the test to</label>
        <input class="a-input a-w-md" type="email" id="campTest" name="test_email" maxlength="150" required
               value="<?= e($testDefault) ?>" placeholder="you@email.com">
        <button class="a-btn a-btn--dark" type="submit" <?= $mailReady ? '' : 'disabled' ?>>
          <i class="fa-solid fa-vial"></i> Send a test copy
        </button>
      </div>
      <p class="a-field__hint">Sends whatever is in the composer above, including unsaved edits.</p>
    </form>
  </div>

  <div class="a-card__foot">
    <?php if ($campaign): ?>
      <form method="post" data-guard
            data-confirm="Send “<?= e($campaign['subject']) ?>” to all <?= $audience ?> active subscribers? This cannot be undone.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start">
        <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
        <button class="a-btn" type="submit" <?= ($mailReady && $audience > 0) ? '' : 'disabled' ?>>
          <i class="fa-solid fa-paper-plane"></i>
          Send to all <?= $audience ?> subscriber<?= $audience === 1 ? '' : 's' ?>
        </button>
      </form>
      <span class="a-muted a-small">Saved changes go out — save the draft first if you have just edited it.</span>
    <?php else: ?>
      <span class="a-muted a-small">
        <i class="fa-solid fa-circle-info"></i>
        Save the draft first — then the send button appears here.
      </span>
    <?php endif; ?>
  </div>
</div>
<?php endif; /* composer */ ?>

<!-- ============================ log ============================ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-clock-rotate-left"></i>Recent sends</h2>
      <p class="a-card__sub">The last 25 emails this site sent — welcomes, tests and campaigns</p>
    </div>
  </div>

  <?php if (!$recentLog): ?>
    <div class="a-empty">
      <i class="fa-solid fa-inbox"></i>
      <p>Nothing has been sent yet. Every email that leaves shows up here with the mail server's answer.</p>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr><th>When</th><th>Type</th><th>To</th><th>Subject</th><th>Result</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentLog as $log): ?>
          <tr<?= $log['status'] === 'failed' ? ' class="is-off"' : '' ?>>
            <td class="a-nowrap a-small a-muted"><?= e(date('j M, g:i a', strtotime((string)$log['created_at']))) ?></td>
            <td>
              <span class="a-badge <?= $log['kind'] === 'welcome' ? 'a-badge--gold' : ($log['kind'] === 'test' ? 'a-badge--blue' : 'a-badge--off') ?>">
                <?= e(ucfirst($log['kind'])) ?>
              </span>
            </td>
            <td><?= e($log['email']) ?></td>
            <td><?= e(excerpt($log['subject'], 50)) ?></td>
            <td>
              <?php if ($log['status'] === 'sent'): ?>
                <span class="a-badge a-badge--on"><i class="fa-solid fa-circle-check"></i> Sent</span>
              <?php else: ?>
                <span class="a-badge a-badge--red"><i class="fa-solid fa-circle-exclamation"></i> Failed</span>
                <span class="a-table__meta"><?= e($log['detail']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
