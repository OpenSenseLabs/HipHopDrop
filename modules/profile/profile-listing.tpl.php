
<div class="profile clearfix">
  <?php print $user_picture; ?>

  <div class="name">
    <?php print $name; ?>
  </div>

  <?php foreach ($profile as $field): ?>
    <div class="field">
      <?php print $field->value; ?>
    </div>
  <?php endforeach; ?>

</div>
