<?php
/**
 * Admin · Email templates
 * The reusable emails: the automatic welcome, plus whatever the kitchen
 * wants to send the list. The editor only holds the *content* — the
 * branded header, the store strip and the unsubscribe footer are added by
 * email_wrap() at send time, which is what keeps every email compliant.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** The stand-in reader used for previews and the placeholder examples. */
const PREVIEW_PERSON = ['name' => 'Alex Demir', 'email' => 'preview@example.com'];

/** Read the editor fields off a POST, trimmed to the column widths. */
function template_input(array $post): array
{
    return [
        'name'      => mb_substr(trim((string)($post['name'] ?? '')), 0, 120),
        'subject'   => mb_substr(trim((string)($post['subject'] ?? '')), 0, 200),
        'preheader' => mb_substr(trim((string)($post['preheader'] ?? '')), 0, 200),
        'body_html' => trim((string)($post['body_html'] ?? '')),
    ];
}

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $row    = email_template($id);
    $id     = $row ? (int)$row['id'] : 0;

    // ---------------------------------------------------------------
    //  Live preview — answers the editor's fetch() with rendered HTML
    // ---------------------------------------------------------------
    if ($action === 'preview_html') {
        $in = template_input($_POST);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        // The welcome email opens on the photo banner, so its preview has to
        // as well — otherwise the editor is judging a shell nobody receives.
        $isWelcome = !empty($_POST['is_welcome']) || ($row && (string)$row['kind'] === 'welcome');

        $composed = email_compose(PREVIEW_PERSON, $in['subject'], $in['body_html'], $in['preheader'], ['hero' => $isWelcome]);
        exit($composed['html']);
    }

    // ---------------------------------------------------------------
    //  Save (insert or update)
    // ---------------------------------------------------------------
    if ($action === 'save') {
        $in      = template_input($_POST);
        $welcome = !empty($_POST['is_welcome']);
        $backTo  = 'admin/templates.php?' . ($id ? 'edit=' . $id : 'new=1');

        $errors = [];
        if ($in['name'] === '')      { $errors[] = 'Give the template a name so you can find it again.'; }
        if ($in['subject'] === '')   { $errors[] = 'A subject line is what people see in their inbox — it cannot be empty.'; }
        if ($in['body_html'] === '') { $errors[] = 'The email body is empty.'; }

        if ($errors) {
            old_set($_POST);
            flash(e(implode(' ', $errors)), 'error');
            redirect($backTo);
        }

        // Only ever one welcome template: taking the flag hands it over.
        $kind = $welcome ? 'welcome' : (($row && $row['kind'] === 'welcome') ? 'welcome' : 'campaign');

        if ($row) {
            db_run(
                'UPDATE email_templates SET name = ?, subject = ?, preheader = ?, body_html = ?, kind = ? WHERE id = ?',
                [$in['name'], $in['subject'], ($in['preheader'] === '' ? null : $in['preheader']), $in['body_html'], $kind, $id]
            );
        } else {
            db_run(
                'INSERT INTO email_templates (name, subject, preheader, body_html, kind) VALUES (?, ?, ?, ?, ?)',
                [$in['name'], $in['subject'], ($in['preheader'] === '' ? null : $in['preheader']), $in['body_html'], $kind]
            );
            $id = (int)db()->lastInsertId();
        }

        if ($kind === 'welcome') {
            db_run("UPDATE email_templates SET kind = 'campaign' WHERE kind = 'welcome' AND id <> ?", [$id]);
        }

        flash('“' . e($in['name']) . '” was saved.' . ($kind === 'welcome'
            ? ' It is now the automatic welcome email.'
            : ''), 'success');
        redirect('admin/templates.php?edit=' . $id);
    }

    // ---------------------------------------------------------------
    //  Duplicate — the quickest way to start this month's newsletter
    // ---------------------------------------------------------------
    if ($action === 'duplicate') {
        if (!$row) {
            flash('That template no longer exists.', 'error');
            redirect('admin/templates.php');
        }

        db_run(
            "INSERT INTO email_templates (name, subject, preheader, body_html, kind)
             VALUES (?, ?, ?, ?, 'campaign')",
            [
                mb_substr($row['name'] . ' (copy)', 0, 120),
                $row['subject'],
                $row['preheader'],
                $row['body_html'],
            ]
        );

        flash('Copied “' . e($row['name']) . '”. Edit the copy below.', 'success');
        redirect('admin/templates.php?edit=' . (int)db()->lastInsertId());
    }

    // ---------------------------------------------------------------
    //  Make this the automatic welcome
    // ---------------------------------------------------------------
    if ($action === 'set_welcome') {
        if (!$row) {
            flash('That template no longer exists.', 'error');
            redirect('admin/templates.php');
        }

        db_run("UPDATE email_templates SET kind = 'campaign' WHERE kind = 'welcome'");
        db_run("UPDATE email_templates SET kind = 'welcome' WHERE id = ?", [$id]);

        flash('“' . e($row['name']) . '” now goes out automatically to every new subscriber.', 'success');
        redirect('admin/templates.php');
    }

    // ---------------------------------------------------------------
    //  Delete
    // ---------------------------------------------------------------
    if ($action === 'delete') {
        if (!$row) {
            flash('That template no longer exists.', 'error');
            redirect('admin/templates.php');
        }
        if ($row['kind'] === 'welcome') {
            flash('That is the welcome email. Point the welcome at another template first, then delete this one.', 'error');
            redirect('admin/templates.php');
        }

        db_run('DELETE FROM email_templates WHERE id = ?', [$id]);
        flash('“' . e($row['name']) . '” was deleted. Campaigns already sent are not affected.', 'success');
        redirect('admin/templates.php');
    }

    // ---------------------------------------------------------------
    //  Send one to a real inbox
    // ---------------------------------------------------------------
    if ($action === 'test') {
        $to     = mb_substr(trim((string)($_POST['test_email'] ?? '')), 0, 150);
        $backTo = 'admin/templates.php' . ($id ? '?edit=' . $id : '') . '#test';

        if (!$row) {
            flash('Save the template first, then send yourself a test.', 'error');
            redirect($backTo);
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('“' . e($to) . '” is not a valid email address.', 'error');
            redirect($backTo);
        }

        $result = mail_send_template(
            ['name' => (string)(current_admin()['full_name'] ?? 'there'), 'email' => $to],
            (string)$row['subject'],
            (string)$row['body_html'],
            (string)($row['preheader'] ?? ''),
            ['kind' => 'test']
        );

        flash(
            $result['ok']
                ? 'Test email sent to ' . e($to) . '. Give it a minute, then check the inbox and the spam folder.'
                : 'The test email could not be sent — ' . e($result['detail']),
            $result['ok'] ? 'success' : 'error'
        );
        redirect($backTo);
    }

    redirect('admin/templates.php');
}

// =====================================================================
//  GET
// =====================================================================

// ---- full-page preview, opened in its own tab -----------------------
$previewId = (int)($_GET['preview'] ?? 0);
if ($previewId > 0) {
    $row = email_template($previewId);
    if (!$row) {
        http_response_code(404);
        exit('That template no longer exists.');
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $composed = email_compose(
        PREVIEW_PERSON,
        (string)$row['subject'],
        (string)$row['body_html'],
        (string)($row['preheader'] ?? ''),
        ['hero' => (string)$row['kind'] === 'welcome']
    );
    exit($composed['html']);
}

$templates = email_templates();

$editId  = (int)($_GET['edit'] ?? 0);
$editing = $editId ? email_template($editId) : null;
$isNew   = isset($_GET['new']);
$formId  = (int)($editing['id'] ?? 0);

if ($editId > 0 && !$editing) {
    flash('That template no longer exists.', 'error');
}
$useOld  = (int)old('id', -1) === $formId;

/** Field value: what was submitted wins after a failed save, then the row. */
$val = function (string $key, $fallback = '') use ($useOld, $editing) {
    return $useOld ? old($key, $fallback) : ($editing[$key] ?? $fallback);
};

$showEditor  = $editing !== null || $isNew;
$isWelcome   = $editing && $editing['kind'] === 'welcome';
$mailReady   = mail_is_enabled();
$testDefault = trim((string)(current_admin()['email'] ?? ''));

$title     = 'Email Templates';
$subtitle  = count($templates) . ' template' . (count($templates) === 1 ? '' : 's') . ' ready to send';
$activeNav = 'templates';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$mailReady): ?>
  <div class="a-alert a-alert--warn">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <span>
      Outgoing email is not ready yet, so templates can be written but not sent.
      <a href="<?= e(admin_url('settings.php#smtp')) ?>">Finish the mailbox setup</a>.
    </span>
  </div>
<?php endif; ?>

<!-- ============================= list ============================= -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-file-lines"></i>Your templates</h2>
      <p class="a-card__sub">Write once, send whenever. Every one of them carries the unsubscribe footer automatically.</p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn a-btn--sm" href="<?= e(admin_url('templates.php?new=1')) ?>#tpl-form">
        <i class="fa-solid fa-plus"></i> New template
      </a>
    </div>
  </div>

  <?php if (!$templates): ?>
    <div class="a-empty">
      <i class="fa-solid fa-file-lines"></i>
      <p>No templates yet. Write the first one and it is ready to send to the whole list.</p>
      <a class="a-btn" href="<?= e(admin_url('templates.php?new=1')) ?>#tpl-form">
        <i class="fa-solid fa-plus"></i> New template
      </a>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th>Template</th>
            <th>Subject line</th>
            <th>Updated</th>
            <th class="is-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($templates as $tpl): ?>
          <?php $welcome = $tpl['kind'] === 'welcome'; ?>
          <tr<?= $formId === (int)$tpl['id'] ? ' class="is-current"' : '' ?>>
            <td class="a-table__name">
              <?= e($tpl['name']) ?>
              <?php if ($welcome): ?>
                <span class="a-badge a-badge--gold"><i class="fa-solid fa-wand-magic-sparkles"></i> Welcome</span>
              <?php endif; ?>
              <?php if (!empty($tpl['preheader'])): ?>
                <span class="a-table__meta"><?= e(excerpt($tpl['preheader'], 70)) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e(excerpt($tpl['subject'], 60)) ?></td>
            <td class="a-nowrap a-small a-muted"><?= e(date('j M Y', strtotime((string)$tpl['updated_at']))) ?></td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                   href="<?= e(admin_url('templates.php?preview=' . (int)$tpl['id'])) ?>"
                   target="_blank" rel="noopener" title="Preview" aria-label="Preview <?= e($tpl['name']) ?>">
                  <i class="fa-solid fa-eye"></i>
                </a>

                <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                   href="<?= e(admin_url('templates.php?edit=' . (int)$tpl['id'])) ?>#tpl-form"
                   title="Edit" aria-label="Edit <?= e($tpl['name']) ?>">
                  <i class="fa-solid fa-pen"></i>
                </a>

                <a class="a-btn a-btn--ghost a-btn--sm a-btn--icon"
                   href="<?= e(admin_url('campaigns.php?from=' . (int)$tpl['id'])) ?>#camp-form"
                   title="Send to the list" aria-label="Send <?= e($tpl['name']) ?> to the list">
                  <i class="fa-solid fa-paper-plane"></i>
                </a>

                <form method="post" data-guard>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="duplicate">
                  <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="Duplicate" aria-label="Duplicate <?= e($tpl['name']) ?>">
                    <i class="fa-solid fa-copy"></i>
                  </button>
                </form>

                <?php if (!$welcome): ?>
                  <form method="post" data-guard
                        data-confirm="Make “<?= e($tpl['name']) ?>” the email every new subscriber receives?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="set_welcome">
                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                    <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                            title="Use as the welcome email" aria-label="Use <?= e($tpl['name']) ?> as the welcome email">
                      <i class="fa-solid fa-wand-magic-sparkles"></i>
                    </button>
                  </form>

                  <form method="post" data-guard
                        data-confirm="Delete the template “<?= e($tpl['name']) ?>”?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                    <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit"
                            title="Delete" aria-label="Delete <?= e($tpl['name']) ?>">
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

<?php if ($showEditor): ?>
<!-- ============================ editor ============================ -->
<div class="a-card" id="tpl-form">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title">
        <i class="fa-solid fa-<?= $editing ? 'pen' : 'plus' ?>"></i>
        <?= $editing ? 'Edit “' . e($editing['name']) . '”' : 'New template' ?>
      </h2>
      <p class="a-card__sub">
        Write the middle of the email. The logo, the store details and the unsubscribe button
        are wrapped around it automatically.
      </p>
    </div>
    <?php if ($editing): ?>
      <div class="a-card__actions">
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('templates.php?new=1')) ?>#tpl-form">
          <i class="fa-solid fa-plus"></i> New instead
        </a>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" data-guard id="tplForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $formId ?>">

    <div class="a-card__body">
      <div class="a-grid-2">
        <div class="a-field">
          <label class="a-field__label" for="tplName">Template name <span class="req">*</span></label>
          <input class="a-input" type="text" id="tplName" name="name" maxlength="120" required
                 value="<?= e($val('name')) ?>" placeholder="Monthly specials">
          <p class="a-field__hint">Only you see this — it is how you pick the template later.</p>
        </div>

        <div class="a-field">
          <label class="a-field__label" for="tplSubject">Subject line <span class="req">*</span></label>
          <input class="a-input" type="text" id="tplSubject" name="subject" maxlength="200" required
                 value="<?= e($val('subject')) ?>" placeholder="This month off the coals at {{site_name}}"
                 data-counter="#tplSubjectCount" data-counter-ideal="60" data-tpl-field>
          <p class="a-field__hint"><span id="tplSubjectCount"></span> · Inboxes cut it off around 60.</p>
        </div>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="tplPreheader">Preview line</label>
        <input class="a-input" type="text" id="tplPreheader" name="preheader" maxlength="200"
               value="<?= e($val('preheader')) ?>"
               placeholder="New skewers, a seasonal plate and something sweet."
               data-tpl-field>
        <p class="a-field__hint">
          The grey line the inbox shows next to the subject. Leave it empty and the inbox
          grabs the first words of the email instead.
        </p>
      </div>

      <div class="a-field">
        <label class="a-field__label" for="tplBody">Email body <span class="req">*</span></label>
        <textarea class="a-textarea a-textarea--tall a-code" id="tplBody" name="body_html" rows="18" required
                  spellcheck="false" data-tpl-field><?= e($val('body_html')) ?></textarea>
        <p class="a-field__hint">
          Plain HTML. Use <code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code>, <code>&lt;p&gt;</code>,
          <code>&lt;ul&gt;&lt;li&gt;</code>, <code>&lt;blockquote&gt;</code>, <code>&lt;hr&gt;</code>,
          <code>&lt;table&gt;</code> and <code>&lt;img src="…"&gt;</code> — each one is styled for you.
          For a gold button write <code>&lt;a class="button" href="…"&gt;Order now&lt;/a&gt;</code>.
        </p>
      </div>

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend"><i class="fa-solid fa-code"></i> Placeholders</legend>
        <p class="a-field__hint">
          Click one to drop it where the cursor is. They are replaced for each reader as the email goes out.
        </p>
        <div class="a-tokens">
          <?php foreach (email_placeholder_help() as $token => $help): ?>
            <button class="a-token" type="button" data-token="{{<?= e($token) ?>}}" title="<?= e($help) ?>">
              {{<?= e($token) ?>}}
            </button>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <label class="a-switch">
        <input type="checkbox" name="is_welcome" value="1"
               <?= $isWelcome ? 'checked disabled' : ((string)old('is_welcome', '') !== '' ? 'checked' : '') ?>>
        <span class="a-switch__track"></span>
        <span>
          Send this automatically to every new subscriber
          <?php if ($isWelcome): ?>
            <em class="a-muted a-small">— already the welcome email</em>
          <?php endif; ?>
        </span>
      </label>
      <?php if ($isWelcome): ?>
        <input type="hidden" name="is_welcome" value="1">
      <?php endif; ?>
    </div>

    <div class="a-card__foot">
      <button class="a-btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save template</button>
      <?php if ($editing): ?>
        <a class="a-btn a-btn--ghost" href="<?= e(admin_url('templates.php?preview=' . $formId)) ?>"
           target="_blank" rel="noopener">
          <i class="fa-solid fa-up-right-from-square"></i> Open the saved version
        </a>
        <a class="a-btn a-btn--dark" href="<?= e(admin_url('campaigns.php?from=' . $formId)) ?>#camp-form">
          <i class="fa-solid fa-paper-plane"></i> Send this to the list
        </a>
      <?php endif; ?>
      <a class="a-btn a-btn--ghost" href="<?= e(admin_url('templates.php')) ?>">Cancel</a>
    </div>
  </form>
</div>

<!-- ============================ preview ============================ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-desktop"></i>Preview</h2>
      <p class="a-card__sub">Exactly what lands in the inbox, with sample details filled in.</p>
    </div>
    <div class="a-card__actions">
      <button class="a-btn a-btn--ghost a-btn--sm" type="button" data-preview-width="390">
        <i class="fa-solid fa-mobile-screen"></i> Phone
      </button>
      <button class="a-btn a-btn--ghost a-btn--sm" type="button" data-preview-width="0">
        <i class="fa-solid fa-display"></i> Desktop
      </button>
      <button class="a-btn a-btn--dark a-btn--sm" type="button" id="tplRefresh">
        <i class="fa-solid fa-rotate"></i> Refresh
      </button>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-preview" id="tplPreviewWrap"
         data-preview-url="<?= e(admin_url('templates.php')) ?>"
         data-preview-token="<?= e(csrf_token()) ?>">
      <div class="a-preview__bar">
        <span class="a-preview__from"><i class="fa-solid fa-circle-user"></i> <?= e(setting('mail_from_name', 'Unika Kebab')) ?></span>
        <span class="a-preview__subject" id="tplPreviewSubject"><?= e($val('subject', 'Subject line')) ?></span>
      </div>
      <iframe class="a-preview__frame" id="tplPreviewFrame" title="Email preview" sandbox=""></iframe>
    </div>
  </div>
</div>

<?php if ($editing): ?>
<!-- ============================ test send ============================ -->
<div class="a-card" id="test">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-vial"></i>Send yourself a test</h2>
      <p class="a-card__sub">One copy of the saved template, straight to any address you like.</p>
    </div>
  </div>

  <div class="a-card__body">
    <?php if (!$mailReady): ?>
      <div class="a-alert a-alert--warn">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>Fill in the mailbox details under <a href="<?= e(admin_url('settings.php#smtp')) ?>">Settings</a> first.</span>
      </div>
    <?php endif; ?>

    <form method="post" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <input type="hidden" name="id" value="<?= $formId ?>">

      <div class="a-flex">
        <label class="visually-hidden" for="testEmail">Send the test to</label>
        <input class="a-input a-w-md" type="email" id="testEmail" name="test_email" maxlength="150" required
               value="<?= e($testDefault) ?>" placeholder="you@email.com">
        <button class="a-btn a-btn--dark" type="submit" <?= $mailReady ? '' : 'disabled' ?>>
          <i class="fa-solid fa-paper-plane"></i> Send test
        </button>
      </div>
      <p class="a-field__hint">
        A test is a real email — it goes through the same mailbox, carries the same unsubscribe
        link and is written to the send log. It does not touch your subscriber list.
      </p>
    </form>
  </div>
</div>
<?php endif; ?>

<?php endif; /* $showEditor */ ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
