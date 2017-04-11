
<div class="feed-item">
  <h3 class="feed-item-title">
    <a href="<?php print $feed_url; ?>"><?php print $feed_title; ?></a>
  </h3>

  <div class="feed-item-meta">
  <?php if ($source_url): ?>
    <a href="<?php print $source_url; ?>" class="feed-item-source"><?php print $source_title; ?></a> -
  <?php endif; ?>
    <span class="feed-item-date"><?php print $source_date; ?></span>
  </div>

<?php if ($content): ?>
  <div class="feed-item-body">
    <?php print $content; ?>
  </div>
<?php endif; ?>

<?php if ($categories): ?>
  <div class="feed-item-categories">
    <?php print t('Categories'); ?>: <?php print implode(', ', $categories); ?>
  </div>
<?php endif ;?>

</div>
