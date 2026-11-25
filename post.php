<?php
require __DIR__ . '/includes/functions.php';

$st = db()->prepare('SELECT * FROM posts WHERE id = ? AND published = 1 AND published_on <= ?');
$st->execute([(int)($_GET['id'] ?? 0), date('Y-m-d')]);
$post = $st->fetch();
if (!$post) {
    [$nfTitle, $nfText, $nfLink, $nfLinkText] = ['Article not found', 'This article may have been removed.', 'news.php', 'See all health tips'];
    require __DIR__ . '/includes/not_found.php';
}
$img = site_image_url($post['image']);
$yt = youtube_id($post['youtube_url']);
$more = array_values(array_filter(posts(4), fn($p) => (int)$p['id'] !== (int)$post['id']));
$pageTitle = $post['title'];
$active = 'news';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap narrow">
    <p class="crumbs"><a href="news.php">Health tips &amp; news</a></p>
    <h1><?= e($post['title']) ?></h1>
    <p class="post-date"><time datetime="<?= e($post['published_on']) ?>"><?= e(fmt_date($post['published_on'], 'j F Y')) ?></time></p>
  </div>
</section>
<section class="section">
  <div class="wrap narrow">
    <?php if ($yt): ?>
      <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="<?= e($post['title']) ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
    <?php elseif ($img): ?>
      <img class="post-image" src="<?= e($img) ?>" alt="">
    <?php endif; ?>
    <?php if ($post['summary']): ?><p class="lead"><?= e($post['summary']) ?></p><?php endif; ?>
    <div class="prose"><?= paragraphs($post['body']) ?></div>
  </div>
</section>
<?php if ($more): ?>
<section class="section section--mist">
  <div class="wrap">
    <h2 class="section__title">More health tips</h2>
    <div class="post-grid">
      <?php foreach (array_slice($more, 0, 3) as $p): include __DIR__ . '/includes/post_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
