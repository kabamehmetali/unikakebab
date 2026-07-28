<?php
/**
 * Renders and clears any queued flash messages.
 * Include it wherever the message should appear on a public page.
 */
foreach (flash_take() as $msg):
    $type = in_array($msg['type'], ['success', 'error', 'info'], true) ? $msg['type'] : 'info';
    $icon = ['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation', 'info' => 'fa-circle-info'][$type];
?>
  <div class="alert-note alert-note--<?= e($type) ?>" role="status">
    <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
    <span><?= e($msg['message']) ?></span>
  </div>
<?php endforeach; ?>
