
<?php print $user_picture; ?>

<?php foreach ($profile as $field): ?>
  <p>
    <?php if ($field->type != 'checkbox'): ?>
      <strong><?php print $field->title; ?></strong><br />
    <?php endif; ?>
    <?php print $field->value; ?>
  </p>
<?php endforeach; ?>
