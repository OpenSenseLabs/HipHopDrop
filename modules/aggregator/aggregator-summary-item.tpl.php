
<a href="<?php print $feed_url; ?>"><?php print $feed_title; ?></a>
<span class="age"><?php print $feed_age; ?></span>

<?php if ($source_url): ?>,
  <span class="source"><a href="<?php print $source_url; ?>"><?php print $source_title; ?></a></span>
<?php endif; ?>
