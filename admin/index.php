<?php
/**
 * Admin dashboard — the first screen after signing in.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

/** "3 hours ago" style timestamps for the dashboard tables. */
if (!function_exists('dash_ago')) {
    function dash_ago(?string $timestamp): string
    {
        $then = strtotime((string)$timestamp);
        if ($then === false) {
            return '—';
        }

        $seconds = time() - $then;
        if ($seconds < 0)    { return 'just now'; }
        if ($seconds < 60)   { return 'just now'; }
        if ($seconds < 3600) {
            $m = (int)floor($seconds / 60);
            return $m . ' min ago';
        }
        if ($seconds < 86400) {
            $h = (int)floor($seconds / 3600);
            return $h . ($h === 1 ? ' hour ago' : ' hours ago');
        }
        if ($seconds < 604800) {
            $d = (int)floor($seconds / 86400);
            return $d === 1 ? 'yesterday' : $d . ' days ago';
        }
        return date('j M Y', $then);
    }
}

// ---------------------------------------------------------------------
//  Numbers
// ---------------------------------------------------------------------
$countItems       = (int)(db_one('SELECT COUNT(*) AS n FROM menu_items WHERE is_active = 1')['n'] ?? 0);
$countCategories  = (int)(db_one('SELECT COUNT(*) AS n FROM menu_categories WHERE is_active = 1')['n'] ?? 0);
$countUnread      = (int)(db_one('SELECT COUNT(*) AS n FROM contact_messages WHERE is_read = 0')['n'] ?? 0);
$countSubscribers = (int)(db_one('SELECT COUNT(*) AS n FROM subscribers WHERE is_active = 1')['n'] ?? 0);

$stats = [
    [
        'num'   => $countItems,
        'label' => $countItems === 1 ? 'Active menu item' : 'Active menu items',
        'icon'  => 'fa-solid fa-burger',
        'tone'  => '',
        'href'  => 'items.php',
    ],
    [
        'num'   => $countCategories,
        'label' => $countCategories === 1 ? 'Menu category' : 'Menu categories',
        'icon'  => 'fa-solid fa-layer-group',
        'tone'  => ' a-stat__icon--blue',
        'href'  => 'categories.php',
    ],
    [
        'num'   => $countUnread,
        'label' => $countUnread === 1 ? 'Unread message' : 'Unread messages',
        'icon'  => 'fa-solid fa-envelope-open-text',
        'tone'  => $countUnread > 0 ? ' a-stat__icon--red' : ' a-stat__icon--blue',
        'href'  => 'messages.php',
    ],
    [
        'num'   => $countSubscribers,
        'label' => $countSubscribers === 1 ? 'Newsletter subscriber' : 'Newsletter subscribers',
        'icon'  => 'fa-solid fa-users',
        'tone'  => ' a-stat__icon--green',
        'href'  => 'subscribers.php',
    ],
];

// ---------------------------------------------------------------------
//  Store snapshot
// ---------------------------------------------------------------------
$shop                 = store();
[$isOpen, $openLabel] = store_open_status();
$hours                = store_hours();
$todayIndex           = (int)date('N') - 1;
$todayRow             = $hours[$todayIndex] ?? ['is_closed' => 1, 'open_time' => null, 'close_time' => null];
$todayName            = day_names()[$todayIndex] ?? 'Today';
$address              = store_address();
$phone                = trim((string)($shop['phone'] ?? ''));

// ---------------------------------------------------------------------
//  Setup checklist
// ---------------------------------------------------------------------
$todo = [];

$smsOn    = setting('sms_enabled', '0') === '1';
$twSid    = trim(setting('twilio_account_sid'));
$twToken  = trim(setting('twilio_auth_token'));
$twFrom   = trim(setting('twilio_from_number'));
$twTo     = trim(setting('twilio_to_number'));

if (!$smsOn) {
    $todo[] = [
        'text' => 'SMS forwarding is switched off, so a new contact message will not text your phone.',
        'href' => 'settings.php',
        'cta'  => 'Turn on SMS',
    ];
} elseif ($twSid === '' || $twToken === '' || $twFrom === '' || $twTo === '') {
    $todo[] = [
        'text' => 'Twilio is switched on but the account SID, token, from-number or to-number is still blank.',
        'href' => 'settings.php',
        'cta'  => 'Finish Twilio',
    ];
}

if (trim(setting('google_maps_api_key')) === '') {
    $todo[] = [
        'text' => 'There is no Google Maps API key, so the map on the locations page cannot load.',
        'href' => 'settings.php',
        'cta'  => 'Add the key',
    ];
}

// --- outgoing email ---------------------------------------------------
if ($mailProblems = mail_problems()) {
    $todo[] = [
        'text' => 'Outgoing email is not ready — ' . lcfirst(rtrim($mailProblems[0], '.'))
                . ', so new subscribers get no welcome and campaigns cannot go out.',
        'href' => 'settings.php#smtp',
        'cta'  => 'Set up email',
    ];
} elseif (trim(setting('site_url')) === '') {
    $todo[] = [
        'text' => 'Your public website address is blank, so links and the logo inside emails point at whatever '
                . 'address the admin panel was opened on. Set it before the first campaign.',
        'href' => 'settings.php#smtp',
        'cta'  => 'Set the address',
    ];
}

if (!welcome_template()) {
    $todo[] = [
        'text' => 'No template is marked as the welcome email, so nobody joining the list hears back.',
        'href' => 'templates.php',
        'cta'  => 'Pick a welcome',
    ];
} elseif (setting('welcome_email_enabled', '1') !== '1') {
    $todo[] = [
        'text' => 'The automatic welcome email is switched off.',
        'href' => 'settings.php#smtp',
        'cta'  => 'Turn it on',
    ];
}

$stalled = db_one("SELECT id, name FROM email_campaigns WHERE status = 'sending' ORDER BY id LIMIT 1");
if ($stalled) {
    $todo[] = [
        'text' => '“' . $stalled['name'] . '” is part-way through sending. Open it and it carries on from '
                . 'the first address still waiting.',
        'href' => 'campaigns.php?id=' . (int)$stalled['id'],
        'cta'  => 'Finish the send',
    ];
}

if (count(order_links(true)) === 0) {
    $todo[] = [
        'text' => 'No ordering links are active — the Order Online page has nothing to send customers to.',
        'href' => 'home.php',
        'cta'  => 'Add a link',
    ];
}

if ($address === '') {
    $todo[] = [
        'text' => 'Your street address is empty, so the footer, contact page and map have nothing to show.',
        'href' => 'store.php',
        'cta'  => 'Add the address',
    ];
}

if (count(featured_items(6)) < 1) {
    $todo[] = [
        'text' => 'Nothing on the menu is marked as featured, so the home page preview is empty.',
        'href' => 'items.php',
        'cta'  => 'Feature a dish',
    ];
}

$usingDefaultPassword = false;
foreach (db_all('SELECT password_hash FROM admins') as $account) {
    if (password_verify('admin123', (string)$account['password_hash'])) {
        $usingDefaultPassword = true;
        break;
    }
}
if ($usingDefaultPassword) {
    $todo[] = [
        'text' => 'The admin password is still the starter one (admin123). Anyone who knows it can sign in.',
        'href' => 'account.php',
        'cta'  => 'Change password',
    ];
}

// ---------------------------------------------------------------------
//  Recent activity
// ---------------------------------------------------------------------
$recentMessages = db_all(
    'SELECT id, name, subject, message, is_read, created_at
       FROM contact_messages
      ORDER BY created_at DESC, id DESC
      LIMIT 5'
);

$recentSubscribers = db_all(
    'SELECT id, name, email, is_active, created_at
       FROM subscribers
      ORDER BY created_at DESC, id DESC
      LIMIT 5'
);

$admin     = current_admin();
$firstName = trim((string)($admin['full_name'] ?? ''));
$firstName = $firstName !== '' ? preg_split('/\s+/', $firstName)[0] : 'there';

$title     = 'Dashboard';
$subtitle  = 'Hello ' . $firstName . ' — here is how the grill is looking today';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<!-- ===================== stats ===================== -->
<div class="row g-3">
  <?php foreach ($stats as $stat): ?>
    <div class="col-6 col-lg-3">
      <a class="a-stat" href="<?= e(admin_url($stat['href'])) ?>">
        <span class="a-stat__icon<?= e($stat['tone']) ?>"><i class="<?= e($stat['icon']) ?>" aria-hidden="true"></i></span>
        <div>
          <div class="a-stat__num"><?= e(number_format($stat['num'])) ?></div>
          <div class="a-stat__label"><?= e($stat['label']) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===================== site status ===================== -->
<div class="a-card a-mt">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa-solid fa-store"></i> Site status</h2>
    <div class="a-card__actions">
      <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('store.php')) ?>">
        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit store info
      </a>
    </div>
  </div>
  <div class="a-card__body">
    <div class="row g-4">

      <div class="col-12 col-md-6 col-lg-3">
        <p class="a-field__label">Right now</p>
        <div>
          <span class="a-badge <?= $isOpen ? 'a-badge--on' : 'a-badge--off' ?>">
            <i class="fa-solid <?= $isOpen ? 'fa-fire-flame-curved' : 'fa-moon' ?>" aria-hidden="true"></i>
            <?= e($isOpen ? 'Open' : 'Closed') ?>
          </span>
          <div class="a-small a-muted"><?= e($openLabel) ?></div>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <p class="a-field__label"><?= e($todayName) ?>'s hours</p>
        <div><?= e(fmt_day_hours($todayRow)) ?></div>
        <?php if (!empty($todayRow['note'])): ?>
          <div class="a-small a-muted"><?= e($todayRow['note']) ?></div>
        <?php endif; ?>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <p class="a-field__label">Phone</p>
        <div>
          <?php if ($phone !== ''): ?>
            <a href="<?= e(phone_href($phone)) ?>"><?= e($phone) ?></a>
          <?php else: ?>
            <span class="a-muted">Not set yet</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-12 col-md-6 col-lg-3">
        <p class="a-field__label">Address</p>
        <div>
          <?php if ($address !== ''): ?>
            <?= e($address) ?>
          <?php else: ?>
            <span class="a-muted">Not set yet</span>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- ===================== setup checklist ===================== -->
<div class="a-card">
  <div class="a-card__head">
    <h2 class="a-card__title">
      <i class="fa-solid fa-clipboard-check"></i>
      <?= $todo ? 'Needs your attention' : 'Setup check' ?>
    </h2>
    <?php if ($todo): ?>
      <div class="a-card__actions">
        <span class="a-badge a-badge--gold"><?= count($todo) ?> to do</span>
      </div>
    <?php endif; ?>
  </div>
  <div class="a-card__body">
    <?php if ($todo): ?>
      <?php foreach ($todo as $i => $job): ?>
        <div class="a-alert a-alert--warn<?= $i === count($todo) - 1 ? ' a-mb0' : '' ?>">
          <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
          <span>
            <?= e($job['text']) ?>
            <a href="<?= e(admin_url($job['href'])) ?>"><?= e($job['cta']) ?> <i class="fa-solid fa-arrow-right-long" aria-hidden="true"></i></a>
          </span>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="a-alert a-alert--success a-mb0">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span>Everything is set up — store details, menu, ordering links and messaging are all good to go.</span>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===================== recent activity ===================== -->
<div class="a-grid-2">

  <div class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa-solid fa-envelope-open-text"></i> Latest messages</h2>
      <div class="a-card__actions">
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('messages.php')) ?>">View all</a>
      </div>
    </div>

    <?php if ($recentMessages): ?>
      <div class="a-table-wrap">
        <table class="a-table">
          <thead>
            <tr>
              <th>From</th>
              <th class="a-nowrap">When</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentMessages as $row): ?>
              <?php
                $preview = trim((string)($row['subject'] ?? ''));
                if ($preview === '') {
                    $preview = excerpt($row['message'] ?? '', 60);
                }
              ?>
              <tr<?= empty($row['is_read']) ? ' class="is-unread"' : '' ?>>
                <td>
                  <a class="a-table__name" href="<?= e(admin_url('messages.php')) ?>"><?= e($row['name']) ?></a>
                  <?php if (empty($row['is_read'])): ?>
                    <span class="a-badge a-badge--gold">New</span>
                  <?php endif; ?>
                  <div class="a-table__meta"><?= e(excerpt($preview, 60)) ?></div>
                </td>
                <td class="a-table__meta a-nowrap"><?= e(dash_ago($row['created_at'] ?? null)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="a-empty">
        <i class="fa-solid fa-inbox" aria-hidden="true"></i>
        <p>No messages yet. Anything sent through the contact form lands here.</p>
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('contact.php')) ?>" target="_blank" rel="noopener">See the contact page</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="a-card">
    <div class="a-card__head">
      <h2 class="a-card__title"><i class="fa-solid fa-users"></i> Newest subscribers</h2>
      <div class="a-card__actions">
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('subscribers.php')) ?>">View all</a>
      </div>
    </div>

    <?php if ($recentSubscribers): ?>
      <div class="a-table-wrap">
        <table class="a-table">
          <thead>
            <tr>
              <th>Subscriber</th>
              <th class="a-nowrap">Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSubscribers as $row): ?>
              <tr<?= empty($row['is_active']) ? ' class="is-off"' : '' ?>>
                <td>
                  <span class="a-table__name"><?= e($row['name']) ?></span>
                  <?php if (empty($row['is_active'])): ?>
                    <span class="a-badge a-badge--off">Unsubscribed</span>
                  <?php endif; ?>
                  <div class="a-table__meta"><?= e($row['email']) ?></div>
                </td>
                <td class="a-table__meta a-nowrap"><?= e(dash_ago($row['created_at'] ?? null)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="a-empty">
        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
        <p>Nobody has joined the mailing list yet. The sign-up band sits at the bottom of every page.</p>
        <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Open the website</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ===================== quick actions ===================== -->
<div class="a-card">
  <div class="a-card__head">
    <h2 class="a-card__title"><i class="fa-solid fa-bolt"></i> Quick actions</h2>
    <p class="a-card__sub">The four jobs you will do most often</p>
  </div>
  <div class="a-card__body">
    <div class="a-flex">
      <a class="a-btn" href="<?= e(admin_url('items.php?action=new')) ?>">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add a menu item
      </a>
      <a class="a-btn a-btn--ghost" href="<?= e(admin_url('categories.php?action=new')) ?>">
        <i class="fa-solid fa-layer-group" aria-hidden="true"></i> Add a category
      </a>
      <a class="a-btn a-btn--ghost" href="<?= e(admin_url('home.php')) ?>">
        <i class="fa-solid fa-house" aria-hidden="true"></i> Edit the home page
      </a>
      <a class="a-btn a-btn--dark" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Open the website
      </a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
