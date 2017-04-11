
<div class="text"><?php print $title; ?></div>
<div class="bar">
  <div style="width: <?php print $percentage; ?>%;" class="foreground"></div>
</div>
<div class="percent">
  <?php print $percentage; ?>% (<?php print format_plural($votes, '1 vote', '@count votes'); ?>)
</div>
