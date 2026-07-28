<?php
/**
 * Standard inner-page hero.
 *
 * Set $hero before including:
 *   ['eyebrow' => '', 'title' => '', 'text' => '', 'image' => 'assets/img/hero-menu.jpg',
 *    'crumb' => 'Menu', 'ornament' => false, 'compact' => true]
 *
 * 'ornament' defaults to true; set it to false when the next section opens with
 * something of its own (the menu page starts with its category bar).
 * 'compact' draws a shallower banner so the content starts sooner.
 */
$hero = $hero ?? [];
$heroImage = img($hero['image'] ?? 'assets/img/hero-home.jpg', 'assets/img/hero-home.jpg');
$heroOrnament = $hero['ornament'] ?? true;
$heroClass = 'hero hero--page' . (!empty($hero['compact']) ? ' hero--compact' : '');
?>
<section class="<?= e($heroClass) ?>">
  <div class="hero__bg" style="background-image:url('<?= e($heroImage) ?>')"></div>
  <div class="hero__veil"></div>

  <div class="hero__inner">
    <div class="container">
      <?php if (!empty($hero['crumb'])): ?>
        <nav class="hero__crumbs" aria-label="Breadcrumb">
          <a href="<?= e(url('index.php')) ?>">Home</a>
          <em aria-hidden="true">/</em>
          <span><?= e($hero['crumb']) ?></span>
        </nav>
      <?php endif; ?>

      <?php if (!empty($hero['eyebrow'])): ?>
        <span class="eyebrow"><?= e($hero['eyebrow']) ?></span>
      <?php endif; ?>

      <h1 class="hero__title"><?= e($hero['title'] ?? '') ?></h1>

      <?php if (!empty($hero['text'])): ?>
        <p class="hero__text"><?= e($hero['text']) ?></p>
      <?php endif; ?>

      <?php if ($heroOrnament): ?>
        <img class="ornament" src="<?= e(url('assets/img/ornament.png')) ?>"
             alt="" aria-hidden="true" width="720" height="124">
      <?php endif; ?>
    </div>
  </div>
</section>
