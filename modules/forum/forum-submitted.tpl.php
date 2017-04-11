
<?php if ($time): ?>
  <span class="submitted">
  <?php print t('By !author @time ago', array(
    '@time' => $time,
    '!author' => $author,
    )); ?>
  </span>
<?php else: ?>
  <?php print t('n/a'); ?>
<?php endif; ?>
