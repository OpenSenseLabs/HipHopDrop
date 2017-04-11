
<?php foreach ($book_menus as $book_id => $menu): ?>
  <div id="book-block-menu-<?php print $book_id; ?>" class="book-block-menu">
    <?php print render($menu); ?>
  </div>
<?php endforeach; ?>
