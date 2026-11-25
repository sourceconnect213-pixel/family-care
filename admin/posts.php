<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = isset($_GET['new']) || $id;
$post = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $st->execute([$id]);
    $post = $st->fetch() ?: null;
    if (!$post) { flash('error', 'Article not found.'); redirect('posts.php'); }
}

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete' && $post) {
        delete_site_image($post['image']);
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        flash('success', 'Article deleted.');
        redirect('posts.php');
    }
    $title = trim((string)($_POST['title'] ?? ''));
    $date = (string)($_POST['published_on'] ?? '');
    $yt = trim((string)($_POST['youtube_url'] ?? ''));
    if ($title === '') flash('error', 'Enter a title.');
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) flash('error', 'Pick a date.');
    elseif ($yt !== '' && !youtube_id($yt)) flash('error', 'That doesn\'t look like a YouTube link. Copy it from the Share button on YouTube.');
    else {
        [$image, $err] = save_site_image($_FILES['image'] ?? [], $post['image'] ?? null);
        if (!empty($_POST['remove_image']) && $image) { delete_site_image($image); $image = null; }
        $kind = ($_POST['kind'] ?? '') === 'news' ? 'news' : 'tip';
        $vals = [$title, $kind, trim((string)$_POST['summary']), trim((string)$_POST['body']), $image, $yt ?: null, $date, empty($_POST['published']) ? 0 : 1];
        if ($post) {
            $pdo->prepare('UPDATE posts SET title=?, kind=?, summary=?, body=?, image=?, youtube_url=?, published_on=?, published=? WHERE id=?')->execute([...$vals, $id]);
        } else {
            $pdo->prepare('INSERT INTO posts (title, kind, summary, body, image, youtube_url, published_on, published) VALUES (?,?,?,?,?,?,?,?)')->execute($vals);
            $id = (int)$pdo->lastInsertId();
        }
        $err ? flash('error', 'Article saved, but the photo wasn\'t: ' . $err) : flash('success', 'Article saved.');
        redirect('posts.php?id=' . $id);
    }
    redirect($id ? 'posts.php?id=' . $id : 'posts.php?new=1');
}

if (!$editing) {
    $list = $pdo->query('SELECT * FROM posts ORDER BY published_on DESC, id DESC')->fetchAll();
    admin_header('Website: health tips & news', 'posts');
    ?>
    <p><a class="btn btn--red" href="posts.php?new=1">Write an article</a></p>
    <section class="panel">
      <?php if (!$list): ?><p class="muted">No articles yet.</p><?php else: ?>
      <table class="table">
        <thead><tr><th>Date</th><th>Title</th><th>Type</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $p): $future = $p['published_on'] > date('Y-m-d'); ?>
          <tr class="<?= $p['published'] ? '' : 'is-muted' ?>">
            <td class="nowrap"><?= e(fmt_date($p['published_on'], 'j M Y')) ?></td>
            <td><strong><a href="posts.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></strong><?= youtube_id($p['youtube_url']) ? ' <span class="chip">Video</span>' : '' ?></td>
            <td><?= $p['kind'] === 'news' ? 'News' : 'Health tip' ?></td>
            <td><?= !$p['published'] ? 'Hidden' : ($future ? 'Scheduled' : 'Published') ?></td>
            <td class="nowrap"><a class="btn btn--ghost btn--small" href="posts.php?id=<?= (int)$p['id'] ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </section>
    <?php
    admin_footer();
    exit;
}

$img = site_image_url($post['image'] ?? null);
admin_header($post ? 'Edit article' : 'Write an article', 'posts');
?>
<p><a href="posts.php">All articles</a></p>
<form method="post" enctype="multipart/form-data" class="panel grid-form">
  <?= csrf_field() ?>
  <?php if ($post): ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php endif; ?>
  <label class="span-2">Title <input name="title" value="<?= e($post['title'] ?? '') ?>" required></label>
  <label>Type
    <select name="kind">
      <option value="tip"<?= ($post['kind'] ?? 'tip') === 'tip' ? ' selected' : '' ?>>Health tip</option>
      <option value="news"<?= ($post['kind'] ?? '') === 'news' ? ' selected' : '' ?>>News</option>
    </select>
  </label>
  <label>Date <span class="help">(future dates publish on that day)</span><input type="date" name="published_on" value="<?= e($post['published_on'] ?? date('Y-m-d')) ?>" required></label>
  <label class="span-4">Short summary <input name="summary" value="<?= e($post['summary'] ?? '') ?>" maxlength="300"></label>
  <label class="span-4">Article text <span class="help">(leave an empty line between paragraphs)</span><textarea name="body" class="tall"><?= e($post['body'] ?? '') ?></textarea></label>
  <label class="span-2">YouTube link <span class="help">(optional, shows the video)</span><input name="youtube_url" value="<?= e($post['youtube_url'] ?? '') ?>" placeholder="https://youtu.be/..."></label>
  <div class="span-2 photo-field">
    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="thumb"><?php endif; ?>
    <label>Photo <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <?php if ($img): ?><label class="check"><input type="checkbox" name="remove_image" value="1"> Remove photo</label><?php endif; ?>
  </div>
  <label class="check span-4"><input type="checkbox" name="published" value="1"<?= ($post['published'] ?? 1) ? ' checked' : '' ?>> Published (visible on the website)</label>
  <div class="span-4"><button class="btn btn--red" type="submit">Save article</button></div>
</form>
<?php if ($post): ?>
<form method="post" onsubmit="return confirm('Delete this article?')">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="action" value="delete">
  <button class="btn btn--danger" type="submit">Delete article</button>
</form>
<?php endif; ?>
<?php admin_footer(); ?>
