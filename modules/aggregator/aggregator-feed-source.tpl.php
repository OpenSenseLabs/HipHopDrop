
<div class="feed-source">
  <?php print $source_icon; ?>
  <?php print $source_image; ?>
  <div class="feed-description">
    <?php print $source_description; ?>
  </div>
  <div class="feed-url">
    <em><?php print t('URL:'); ?></em> <a href="<?php print $source_url; ?>"><?php print $source_url; ?></a>
  </div>
  <div class="feed-updated">
    <em><?php print t('Updated:'); ?></em> <?php print $last_checked; ?>
  </div>
</div>
