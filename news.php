<?php
require __DIR__ . '/includes/functions.php';

$list = posts();
$pageTitle = 'Health tips & news';
$active = 'news';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <h1>Health tips &amp; news</h1>
    <p class="lead">Advice from our doctors, and news from <?= e(setting('hospital_name')) ?>.</p>
  </div>
</section>
<section class="section">
  <div class="wrap">
    <?php if (!$list): ?>
      <div class="empty"><p>No articles yet.</p></div>
    <?php else: ?>
      <div class="post-grid">
        <?php foreach ($list as $p): include __DIR__ . '/includes/post_card.php'; endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
