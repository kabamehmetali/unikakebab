<?php
/**
 * Admin · Home page
 *
 * Every headline, paragraph, button, photo and "why us" card on the public
 * home page is edited from this one screen — no code, no FTP.
 *
 * POST actions (hidden "action" input):
 *   content         all home page copy + the two photos
 *   feature_save    insert or update one "Why Unika" card
 *   feature_delete  remove one card
 *   feature_toggle  show / hide one card
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';
require_admin();

// =====================================================================
//  POST
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    // ---- a) all of the home page copy -------------------------------
    if ($action === 'content') {
        csrf_require();

        $textKeys = [
            // hero
            'hero_eyebrow', 'hero_title', 'hero_title_accent', 'hero_subtitle',
            'hero_btn1_text', 'hero_btn1_link', 'hero_btn2_text', 'hero_btn2_link',
            // story
            'home_about_eyebrow', 'home_about_title', 'home_about_text',
            'home_about_btn_text', 'home_about_btn_link',
            // menu preview
            'home_menu_eyebrow', 'home_menu_title', 'home_menu_text',
            'home_menu_btn_text', 'home_menu_btn_link',
            // why us
            'home_features_eyebrow', 'home_features_title',
            // order band
            'home_cta_eyebrow', 'home_cta_title', 'home_cta_text',
            'home_cta_btn_text', 'home_cta_btn_link',
            // newsletter band
            'newsletter_eyebrow', 'newsletter_title', 'newsletter_text', 'newsletter_btn_text',
            // the shareable /join landing page
            'newsletter_share_title', 'newsletter_share_text', 'newsletter_image_alt',
            // alt text for the two photos below — saved whether or not a new
            // file was picked, so the wording can be fixed on its own
            'hero_image_alt', 'home_about_image_alt',
        ];

        $pairs = [];
        foreach ($textKeys as $key) {
            $pairs[$key] = trim((string)($_POST[$key] ?? ''));
        }
        $pairs['hero_show_logo'] = isset($_POST['hero_show_logo']) ? '1' : '0';

        setting_set_many($pairs);

        // Photos are optional — the text above is saved either way.
        $problems = [];
        $photos   = [
            ['field' => 'hero_image',       'prefix' => 'hero',  'label' => 'Hero photo'],
            ['field' => 'home_about_image', 'prefix' => 'about', 'label' => 'Story photo'],
            ['field' => 'newsletter_image',  'prefix' => 'join',  'label' => 'Sign-up page photo'],
        ];

        foreach ($photos as $photo) {
            $err  = null;
            $file = (isset($_FILES[$photo['field']]) && is_array($_FILES[$photo['field']]))
                ? $_FILES[$photo['field']]
                : [];

            $path = save_upload($file, 'site', $photo['prefix'], $err);

            if ($path !== null) {
                delete_upload(setting($photo['field']));   // only removes files under uploads/
                setting_set($photo['field'], $path);
            } elseif ($err) {
                $problems[] = $photo['label'] . ': ' . $err;
            }
        }

        flash('Home page saved.');
        foreach ($problems as $problem) {
            flash(e($problem), 'error');
        }
        redirect('admin/home.php');
    }

    // ---- b) add / edit one "Why Unika" card -------------------------
    if ($action === 'feature_save') {
        csrf_require();

        $id        = (int)($_POST['id'] ?? 0);
        $icon      = mb_substr(trim((string)($_POST['icon'] ?? '')), 0, 60);
        $cardTitle = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
        $cardText  = trim((string)($_POST['text'] ?? ''));
        $sort      = (int)($_POST['sort_order'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($icon === '') {
            $icon = 'fa-solid fa-fire';
        }

        if ($cardTitle === '') {
            old_set($_POST);
            flash('Give the card a title before saving it.', 'error');
            redirect('admin/home.php' . ($id > 0 ? '?feature=' . $id : ''));
        }

        if ($id > 0) {
            db_run(
                'UPDATE home_features SET icon = ?, title = ?, text = ?, sort_order = ?, is_active = ? WHERE id = ?',
                [$icon, $cardTitle, $cardText, $sort, $isActive, $id]
            );
            flash('Card &ldquo;' . e($cardTitle) . '&rdquo; updated.');
        } else {
            db_run(
                'INSERT INTO home_features (icon, title, text, sort_order, is_active) VALUES (?, ?, ?, ?, ?)',
                [$icon, $cardTitle, $cardText, $sort, $isActive]
            );
            flash('Card &ldquo;' . e($cardTitle) . '&rdquo; added.');
        }
        redirect('admin/home.php');
    }

    // ---- c) delete one card -----------------------------------------
    if ($action === 'feature_delete') {
        csrf_require();

        $id  = (int)($_POST['id'] ?? 0);
        $row = $id > 0 ? db_one('SELECT id, title FROM home_features WHERE id = ?', [$id]) : null;

        if ($row) {
            db_run('DELETE FROM home_features WHERE id = ?', [$id]);
            flash('Card &ldquo;' . e($row['title']) . '&rdquo; deleted.');
        } else {
            flash('That card no longer exists.', 'error');
        }
        redirect('admin/home.php');
    }

    // ---- d) show / hide one card ------------------------------------
    if ($action === 'feature_toggle') {
        csrf_require();

        $id  = (int)($_POST['id'] ?? 0);
        $row = $id > 0 ? db_one('SELECT id, title, is_active FROM home_features WHERE id = ?', [$id]) : null;

        if ($row) {
            $next = ((int)$row['is_active'] === 1) ? 0 : 1;
            db_run('UPDATE home_features SET is_active = ? WHERE id = ?', [$next, $id]);
            flash('Card &ldquo;' . e($row['title']) . '&rdquo; is now ' . ($next ? 'live on the home page.' : 'hidden.'));
        } else {
            flash('That card no longer exists.', 'error');
        }
        redirect('admin/home.php');
    }

    flash('That action was not recognised.', 'error');
    redirect('admin/home.php');
}

// =====================================================================
//  GET — data for the page
// =====================================================================
$features = home_features(false);

$editId = (int)($_GET['feature'] ?? 0);
$edit   = $editId > 0 ? db_one('SELECT * FROM home_features WHERE id = ?', [$editId]) : null;

$nextSort = (int)(db_one('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM home_features')['n'] ?? 1);

// Redraw the card form with whatever was typed if the last save bounced.
$fromOld = (string)old('form') === 'feature';

$fId     = (int)($edit['id'] ?? 0);
$fIcon   = $fromOld ? (string)old('icon')       : (string)($edit['icon'] ?? '');
$fTitle  = $fromOld ? (string)old('title')      : (string)($edit['title'] ?? '');
$fText   = $fromOld ? (string)old('text')       : (string)($edit['text'] ?? '');
$fSort   = $fromOld ? (string)old('sort_order') : (string)($edit['sort_order'] ?? $nextSort);
$fActive = $fromOld ? ((string)old('is_active') === '1') : ((int)($edit['is_active'] ?? 1) === 1);

// =====================================================================
//  Tiny field renderers — keeps 29 settings readable and consistent.
//  $external = the input lives outside <form id="homeContentForm"> and is
//  tied back to it with the HTML form attribute.
// =====================================================================

/** One single-line setting field. */
function home_field_text(string $key, string $label, string $placeholder = '', string $hint = '', bool $external = false): void
{
    $form = $external ? ' form="homeContentForm"' : '';
    echo '<div class="a-field">'
       . '<label class="a-field__label" for="f_' . e($key) . '">' . e($label) . '</label>'
       . '<input class="a-input" type="text" id="f_' . e($key) . '" name="' . e($key) . '"'
       . ' value="' . e(setting($key)) . '" placeholder="' . e($placeholder) . '" maxlength="255"' . $form . '>';
    if ($hint !== '') {
        echo '<p class="a-field__hint">' . e($hint) . '</p>';
    }
    echo '</div>';
}

/** One multi-line setting field. */
function home_field_area(string $key, string $label, string $placeholder = '', string $hint = '', bool $tall = false, bool $external = false): void
{
    $form = $external ? ' form="homeContentForm"' : '';
    echo '<div class="a-field">'
       . '<label class="a-field__label" for="f_' . e($key) . '">' . e($label) . '</label>'
       . '<textarea class="a-textarea' . ($tall ? ' a-textarea--tall' : '') . '" id="f_' . e($key) . '"'
       . ' name="' . e($key) . '" placeholder="' . e($placeholder) . '"' . $form . '>' . e(setting($key)) . '</textarea>';
    if ($hint !== '') {
        echo '<p class="a-field__hint">' . e($hint) . '</p>';
    }
    echo '</div>';
}

/** A button: its label and where it points, side by side. */
function home_field_pair(string $textKey, string $linkKey, string $label, string $textPlaceholder, string $linkPlaceholder, string $linkHint = '', bool $external = false): void
{
    echo '<div class="a-grid-2">';
    home_field_text($textKey, $label . ' text', $textPlaceholder, '', $external);
    home_field_text($linkKey, $label . ' link', $linkPlaceholder, $linkHint, $external);
    echo '</div>';
}

$title     = 'Home Page';
$subtitle  = 'Every word, photo and button on the public home page';
$activeNav = 'home';
require __DIR__ . '/includes/header.php';
?>

<!-- =================================================================
     Sections 1, 2 and 3 sit inside the content form. Sections 5 and 6
     come after the cards table, so their inputs join this same form
     with form="homeContentForm".
     ================================================================= -->
<form id="homeContentForm" method="post" enctype="multipart/form-data" action="<?= e(admin_url('home.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="content">

  <!-- ============ 1 · Hero ============ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-fire-flame-curved"></i>Hero section</h2>
        <p class="a-card__sub">The big photo, headline and buttons at the very top of the page.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-field">
        <label class="a-field__label" for="f_hero_image">Background photo</label>
        <div class="a-media">
          <img class="a-media__preview" id="heroPreview" src="<?= e(img(setting('hero_image'), 'assets/img/hero-home.jpg')) ?>" alt="Current hero photo">
          <div class="a-media__body">
            <input type="file" id="f_hero_image" name="hero_image" accept="image/*" data-preview="#heroPreview">
            <p class="a-field__hint">Wide and dark works best — at least 1600&nbsp;px across. JPG, PNG or WebP, up to 8&nbsp;MB. Leave it empty to keep the photo you already have.</p>
            <p class="a-small a-muted a-mb0">In use: <?= e(setting('hero_image', 'assets/img/hero-home.jpg')) ?></p>
            <label class="a-field__label a-field__label--sub" for="f_hero_image_alt">Alt text</label>
            <input class="a-input a-input--sm" type="text" id="f_hero_image_alt" name="hero_image_alt"
                   maxlength="255" value="<?= e(setting('hero_image_alt')) ?>"
                   placeholder="Kebab skewers grilling over glowing charcoal at Unika Kebab">
            <p class="a-field__hint">
              The hero is a CSS background, so it has no alt attribute of its own — fill this in and
              the banner is announced to screen readers, leave it blank and it is treated as
              decoration. Blank is the right answer unless the photo carries real meaning.
            </p>
          </div>
        </div>
      </div>

      <?php home_field_text('hero_eyebrow', 'Small line above the headline', 'Grilled over real charcoal'); ?>

      <div class="a-grid-2">
        <?php
        home_field_text('hero_title', 'Headline', 'Skewers with a story');
        home_field_text(
            'hero_title_accent',
            'Highlighted word',
            'story',
            'Must be a word that appears in the title — it is drawn in gold.'
        );
        ?>
      </div>

      <?php
      home_field_area(
          'hero_subtitle',
          'Subtitle',
          'Hand-shaped kofte, marinated shish tavuk and shawarma carved to order — pressed into warm pita straight off the grill.',
          'One or two sentences. Around 160 characters reads best on a phone.'
      );

      home_field_pair(
          'hero_btn1_text',
          'hero_btn1_link',
          'Main button',
          'View the menu',
          'menu',
          'A page such as menu or order, a phone link like tel:+14165550184, or a full https:// address.'
      );

      home_field_pair(
          'hero_btn2_text',
          'hero_btn2_link',
          'Second button',
          'Order online',
          'order',
          'Clear both boxes to show a single button.'
      );
      ?>

      <div class="a-field a-mb0">
        <span class="a-field__label">Logo</span>
        <label class="a-switch">
          <input type="checkbox" name="hero_show_logo" value="1" <?= setting('hero_show_logo', '1') === '1' ? 'checked' : '' ?>>
          <span class="a-switch__track"></span>
          <span>Show the Unika logo above the headline</span>
        </label>
        <p class="a-field__hint">Turn this off when the headline alone should carry the top of the page.</p>
      </div>

    </div>
  </div>

  <!-- ============ 2 · Story ============ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-book-open"></i>Story section</h2>
        <p class="a-card__sub">The photo-and-paragraph block that tells people how you cook.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-field">
        <label class="a-field__label" for="f_home_about_image">Photo</label>
        <div class="a-media">
          <img class="a-media__preview" id="aboutPreview" src="<?= e(img(setting('home_about_image'), 'assets/img/bg-parchment.jpg')) ?>" alt="Current story photo">
          <div class="a-media__body">
            <input type="file" id="f_home_about_image" name="home_about_image" accept="image/*" data-preview="#aboutPreview">
            <p class="a-field__hint">An upright shot of the grill, the counter or the team. Anything from 1000&nbsp;px wide is plenty.</p>
            <p class="a-small a-muted a-mb0">In use: <?= e(setting('home_about_image', 'assets/img/bg-parchment.jpg')) ?></p>
            <label class="a-field__label a-field__label--sub" for="f_home_about_image_alt">Alt text</label>
            <input class="a-input a-input--sm" type="text" id="f_home_about_image_alt" name="home_about_image_alt"
                   maxlength="255" value="<?= e(setting('home_about_image_alt')) ?>"
                   placeholder="Chef hand-shaping kofte in the Unika Kebab kitchen">
            <p class="a-field__hint">Describes the story photo for screen readers and image search.</p>
          </div>
        </div>
      </div>

      <div class="a-grid-2">
        <?php
        home_field_text('home_about_eyebrow', 'Small line above the title', 'Our kitchen');
        home_field_text('home_about_title', 'Title', 'Fire, smoke and a little patience');
        ?>
      </div>

      <?php
      home_field_area(
          'home_about_text',
          'Story',
          'Everything at Unika starts on the charcoal. We shape the kofte by hand each morning, marinate the chicken overnight and grill every skewer to order over glowing coals.',
          'Two or three short paragraphs is the sweet spot. Write it the way you would say it.',
          true
      );

      home_field_pair(
          'home_about_btn_text',
          'home_about_btn_link',
          'Button',
          'See what we grill',
          'menu'
      );
      ?>

    </div>
  </div>

  <!-- ============ 3 · Menu preview ============ -->
  <div class="a-card">
    <div class="a-card__head">
      <div>
        <h2 class="a-card__title"><i class="fa-solid fa-burger"></i>Menu preview section</h2>
        <p class="a-card__sub">The short taste of the menu shown halfway down the page.</p>
      </div>
    </div>
    <div class="a-card__body">

      <div class="a-alert a-alert--info">
        <i class="fa-solid fa-circle-info"></i>
        <span>The dishes in this section are the menu items ticked as <strong>Featured</strong>.
          Change which ones appear over on <a href="<?= e(admin_url('items.php')) ?>">Menu&nbsp;Items</a>.</span>
      </div>

      <div class="a-grid-2">
        <?php
        home_field_text('home_menu_eyebrow', 'Small line above the title', 'From the grill');
        home_field_text('home_menu_title', 'Title', 'Favourites off the coals');
        ?>
      </div>

      <?php
      home_field_area(
          'home_menu_text',
          'Intro text',
          'A short look at what regulars order most. The full list — sandwiches, platters, sides and sweets — lives on the menu page.'
      );

      home_field_pair(
          'home_menu_btn_text',
          'home_menu_btn_link',
          'Button',
          'Full menu',
          'menu'
      );
      ?>

    </div>
  </div>
</form>

<!-- ============ 4 · Why Unika cards ============ -->
<div class="a-card" id="cards">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-star"></i>Why Unika cards</h2>
      <p class="a-card__sub">The small icon cards that explain what makes the grill different.</p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn a-btn--sm" href="#cardform"><i class="fa-solid fa-plus"></i> Add a card</a>
    </div>
  </div>

  <div class="a-card__body">
    <div class="a-grid-2">
      <?php
      home_field_text('home_features_eyebrow', 'Small line above the title', 'Why Unika', '', true);
      home_field_text('home_features_title', 'Title', 'Small kitchen, serious standards', '', true);
      ?>
    </div>
  </div>

  <?php if (!$features): ?>
    <div class="a-empty">
      <i class="fa-regular fa-star"></i>
      <p>No cards yet — the "Why Unika" strip stays hidden until you add one.<br>
         Three or four short cards work best.</p>
      <a class="a-btn" href="#cardform"><i class="fa-solid fa-plus"></i> Add the first card</a>
    </div>
  <?php else: ?>
    <div class="a-table-wrap">
      <table class="a-table">
        <thead>
          <tr>
            <th>Icon</th>
            <th>Card</th>
            <th>Status</th>
            <th>Order</th>
            <th><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($features as $feature): ?>
          <?php
            $rowActive = (int)$feature['is_active'] === 1;
            $rowId     = (int)$feature['id'];
            $rowText   = excerpt($feature['text'] ?? '', 72);
          ?>
          <tr<?= $rowActive ? '' : ' class="is-off"' ?>>
            <td><i class="<?= e($feature['icon'] ?: 'fa-solid fa-fire') ?>" aria-hidden="true"></i></td>
            <td>
              <div class="a-table__name"><?= e($feature['title']) ?></div>
              <div class="a-table__meta"><?= $rowText !== '' ? e($rowText) : '<span class="a-muted">No description yet</span>' ?></div>
            </td>
            <td>
              <span class="a-badge <?= $rowActive ? 'a-badge--on' : 'a-badge--off' ?>">
                <?= $rowActive ? 'Live' : 'Hidden' ?>
              </span>
            </td>
            <td><?= (int)$feature['sort_order'] ?></td>
            <td class="is-actions">
              <div class="a-flex a-flex--end">
                <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('home.php')) ?>?feature=<?= $rowId ?>#cardform">
                  <i class="fa-solid fa-pen"></i> Edit
                </a>

                <form method="post" action="<?= e(admin_url('home.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="feature_toggle">
                  <input type="hidden" name="id" value="<?= $rowId ?>">
                  <button class="a-btn a-btn--ghost a-btn--sm a-btn--icon" type="submit"
                          title="<?= $rowActive ? 'Hide this card' : 'Show this card' ?>">
                    <i class="fa-solid <?= $rowActive ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                  </button>
                </form>

                <form method="post" action="<?= e(admin_url('home.php')) ?>"
                      data-confirm="Delete the card &quot;<?= e($feature['title']) ?>&quot;? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="feature_delete">
                  <input type="hidden" name="id" value="<?= $rowId ?>">
                  <button class="a-btn a-btn--danger a-btn--sm a-btn--icon" type="submit" title="Delete this card">
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

  <div class="a-card__body" id="cardform">
    <form method="post" action="<?= e(admin_url('home.php')) ?>" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="feature_save">
      <input type="hidden" name="form" value="feature">
      <input type="hidden" name="id" value="<?= $fId ?>">

      <fieldset class="a-fieldset">
        <legend class="a-fieldset__legend">
          <i class="fa-solid <?= $edit ? 'fa-pen' : 'fa-plus' ?>"></i>
          <?= $edit ? 'Edit card' : 'Add a card' ?>
        </legend>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label req" for="f_card_title">Title <span class="req">*</span></label>
            <input class="a-input" type="text" id="f_card_title" name="title" maxlength="120"
                   value="<?= e($fTitle) ?>" placeholder="Real charcoal, no shortcuts" required>
          </div>

          <div class="a-field">
            <label class="a-field__label" for="f_card_icon">Icon</label>
            <input class="a-input" type="text" id="f_card_icon" name="icon" maxlength="60"
                   value="<?= e($fIcon) ?>" placeholder="fa-solid fa-fire">
            <p class="a-field__hint">
              Font Awesome 6 class names. Good ones for a kebab shop:
              fa-solid fa-fire-flame-curved, fa-solid fa-wheat-awn, fa-solid fa-certificate,
              fa-solid fa-stopwatch, fa-solid fa-pepper-hot, fa-solid fa-leaf, fa-solid fa-truck-fast.
              Left blank it becomes fa-solid fa-fire.
            </p>
          </div>
        </div>

        <div class="a-field">
          <label class="a-field__label" for="f_card_text">Text</label>
          <textarea class="a-textarea" id="f_card_text" name="text"
                    placeholder="Every skewer is grilled to order over glowing coals — that char is the whole point."><?= e($fText) ?></textarea>
          <p class="a-field__hint">One sentence. Be specific about charcoal grilling, fresh preparation, or convenient pickup.</p>
        </div>

        <div class="a-grid-2">
          <div class="a-field">
            <label class="a-field__label" for="f_card_sort">Sort order</label>
            <input class="a-input" type="number" id="f_card_sort" name="sort_order" min="0" step="1"
                   value="<?= e($fSort) ?>">
            <p class="a-field__hint">Low numbers come first.</p>
          </div>

          <div class="a-field">
            <span class="a-field__label">Visibility</span>
            <label class="a-switch">
              <input type="checkbox" name="is_active" value="1" <?= $fActive ? 'checked' : '' ?>>
              <span class="a-switch__track"></span>
              <span>Show this card on the home page</span>
            </label>
          </div>
        </div>

        <div class="a-flex">
          <button class="a-btn a-btn--dark" type="submit">
            <i class="fa-solid fa-floppy-disk"></i> <?= $edit ? 'Save card' : 'Add card' ?>
          </button>
          <?php if ($edit): ?>
            <a class="a-btn a-btn--ghost" href="<?= e(admin_url('home.php')) ?>#cards">Cancel</a>
          <?php endif; ?>
        </div>
      </fieldset>
    </form>
  </div>
</div>

<!-- ============ 5 · Order call-to-action band ============ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-bag-shopping"></i>Order call-to-action band</h2>
      <p class="a-card__sub">The dark strip near the bottom that nudges people to order.</p>
    </div>
  </div>
  <div class="a-card__body">
    <div class="a-grid-2">
      <?php
      home_field_text('home_cta_eyebrow', 'Small line above the title', 'Hungry now?', '', true);
      home_field_text('home_cta_title', 'Title', 'Order with confidence', '', true);
      ?>
    </div>

    <?php
    home_field_area(
        'home_cta_text',
        'Text',
        'Order ahead for pickup or have it delivered — the grill fires as soon as your order lands.',
        '',
        false,
        true
    );

    home_field_pair(
        'home_cta_btn_text',
        'home_cta_btn_link',
        'Button',
        'Start an order',
        'order',
        'Point this at order, or straight at a delivery app if you prefer.',
        true
    );
    ?>
  </div>
</div>

<!-- ============ 6 · Newsletter band ============ -->
<div class="a-card">
  <div class="a-card__head">
    <div>
      <h2 class="a-card__title"><i class="fa-solid fa-envelope-open-text"></i>Newsletter band</h2>
      <p class="a-card__sub">The sign-up strip. Everyone who joins lands in Subscribers.</p>
    </div>
    <div class="a-card__actions">
      <a class="a-btn a-btn--ghost a-btn--sm" href="<?= e(admin_url('subscribers.php')) ?>">
        <i class="fa-solid fa-users"></i> Subscribers
      </a>
    </div>
  </div>
  <div class="a-card__body">
    <div class="a-grid-2">
      <?php
      home_field_text('newsletter_eyebrow', 'Small line above the title', 'The Unika list', '', true);
      home_field_text('newsletter_title', 'Title', 'Specials before anyone else', '', true);
      ?>
    </div>

    <?php
    home_field_area(
        'newsletter_text',
        'Text',
        'One short email a month: new skewers, seasonal plates and the occasional free baklava. No spam, unsubscribe anytime.',
        'Say how often you email and what is in it — it lifts sign-ups.',
        false,
        true
    );

    home_field_text('newsletter_btn_text', 'Button label', 'Join the list', '', true);
    ?>

    <hr class="a-rule">

    <div class="a-field">
      <span class="a-field__label">The shareable sign-up page</span>
      <p class="a-field__hint">
        Everything below is the standalone page at
        <a href="<?= e(url('join.php')) ?>" target="_blank" rel="noopener"><?= e(abs_url('join.php')) ?></a> —
        the one you paste into an Instagram bio or print on a table card.
        The link and a copy button live on
        <a href="<?= e(admin_url('subscribers.php')) ?>">Subscribers</a>.
      </p>
    </div>

    <div class="a-field">
      <span class="a-field__label">Photo</span>
      <div class="a-media">
        <img class="a-media__preview" id="joinPreview"
             src="<?= e(img(setting('newsletter_image'), 'assets/img/hero-home.jpg')) ?>"
             alt="Current sign-up page photo" width="104" height="104">
        <div class="a-media__body">
          <input type="file" name="newsletter_image" form="homeContentForm"
                 accept="image/jpeg,image/png,image/gif,image/webp" data-preview="#joinPreview">
          <p class="a-field__hint">
            The full-width photo behind the sign-up card. A wide, appetising shot works best —
            the card sits over the middle of it.
          </p>

          <label class="a-field__label a-field__label--sub" for="newsletter_image_alt">Alt text</label>
          <input class="a-input a-input--sm" type="text" id="newsletter_image_alt" name="newsletter_image_alt"
                 form="homeContentForm" maxlength="255" value="<?= e(setting('newsletter_image_alt')) ?>"
                 placeholder="Charcoal-grilled skewers at <?= e(setting('site_name', 'Unika Kebab')) ?>">
        </div>
      </div>
    </div>

    <?php
    home_field_text(
        'newsletter_share_title',
        'Sign-up page headline',
        'Specials before anyone else',
        'Leave blank to reuse the band title above.',
        true
    );

    home_field_area(
        'newsletter_share_text',
        'Sign-up page text',
        'One short email a month: new skewers, seasonal plates and the occasional free baklava.',
        'Leave blank to reuse the band text above.',
        false,
        true
    );
    ?>

    <div class="a-sticky-save">
      <button class="a-btn a-btn--dark" type="submit" form="homeContentForm">
        <i class="fa-solid fa-floppy-disk"></i> Save the home page
      </button>
      <a class="a-btn a-btn--ghost" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> View home page
      </a>
      <span class="a-small a-muted a-nowrap">Saves every section on this screen except the cards, which save on their own.</span>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
