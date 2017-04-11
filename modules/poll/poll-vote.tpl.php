
<div class="poll">
  <div class="vote-form">
    <div class="choices">
      <?php if ($block): ?>
        <div class="title"><?php print $title; ?></div>
      <?php endif; ?>
      <?php print $choice; ?>
    </div>
    <?php print $vote; ?>
  </div>
  <?php // This is the 'rest' of the form, in case items have been added. ?>
  <?php print $rest ?>
</div>
