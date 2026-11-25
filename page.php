<?php
require __DIR__ . '/includes/functions.php';

$page = get_page((string)($_GET['slug'] ?? ''));
if (!$page) {
    [$nfTitle, $nfText, $nfLink, $nfLinkText] = ['Page not found', 'This page may have moved.', 'index.php', 'Go to the home page'];
    require __DIR__ . '/includes/not_found.php';
}
$img = site_image_url($page['image']);
$pageTitle = $page['title'];
$active = 'page-' . $page['slug'];
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero<?= $img ? ' page-hero--photo' : '' ?>"<?= $img ? ' style="--hero-img: url(\'' . e($img) . '\')"' : '' ?>>
  <div class="wrap">
    <h1><?= e($page['title']) ?></h1>
    <?php if ($page['summary']): ?><p class="lead"><?= e($page['summary']) ?></p><?php endif; ?>
  </div>
</section>
<section class="section">
  <div class="wrap article-layout">
    <article class="prose"><?= paragraphs($page['body']) ?></article>
    <aside class="article-side">
      <h2>Need to see a doctor?</h2>
      <p>Book your appointment number online and come in at your time.</p>
      <a class="btn btn--red" href="doctors.php">Channel your doctor</a>
      <p class="article-side__call">Or call <a href="<?= e(tel(setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
