<?php
/** @var array $p post row */
$img = site_image_url($p['image']);
$yt = youtube_id($p['youtube_url']);
$thumb = $img ?: ($yt ? "https://i.ytimg.com/vi/$yt/hqdefault.jpg" : null);
?>
<article class="post-card">
  <a class="post-card__media<?= $thumb ? '' : ' post-card__media--plain' ?>" href="<?= e(base_url('post.php?id=' . (int)$p['id'])) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy"><?php endif; ?>
    <?php if ($yt): ?><span class="post-card__play">Video</span><?php endif; ?>
  </a>
  <div class="post-card__body">
    <time datetime="<?= e($p['published_on']) ?>"><?= e(fmt_date($p['published_on'], 'j F Y')) ?></time>
    <h3><a href="<?= e(base_url('post.php?id=' . (int)$p['id'])) ?>"><?= e($p['title']) ?></a></h3>
    <?php if ($p['summary']): ?><p><?= e($p['summary']) ?></p><?php endif; ?>
  </div>
</article>
