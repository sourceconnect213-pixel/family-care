<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = isset($_GET['new']) || $id;
$page = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM pages WHERE id = ?');
    $st->execute([$id]);
    $page = $st->fetch() ?: null;
    if (!$page) { flash('error', 'Page not found.'); redirect('pages.php'); }
}

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'delete' && $page) {
        delete_site_image($page['image']);
        $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$id]);
        flash('success', 'Page deleted.');
        redirect('pages.php');
    }
    $title = trim((string)($_POST['title'] ?? ''));
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)($_POST['slug'] ?? '') ?: $title), '-'));
    $taken = $pdo->prepare('SELECT 1 FROM pages WHERE slug = ? AND id <> ?');
    $taken->execute([$slug, $id]);
    if ($title === '') flash('error', 'Enter a page title.');
    elseif ($slug === '' || strlen($slug) > 60) flash('error', 'Enter a short web address, like "laboratory".');
    elseif ($taken->fetchColumn()) flash('error', 'Another page already uses that web address.');
    else {
        [$image, $err] = save_site_image($_FILES['image'] ?? [], $page['image'] ?? null);
        if (!empty($_POST['remove_image']) && $image) { delete_site_image($image); $image = null; }
        $vals = [$title, $slug, trim((string)$_POST['summary']), trim((string)$_POST['body']), $image,
            empty($_POST['in_top_menu']) ? 0 : 1, (int)($_POST['sort_order'] ?? 0), empty($_POST['published']) ? 0 : 1, date('Y-m-d H:i:s')];
        if ($page) {
            $pdo->prepare('UPDATE pages SET title=?, slug=?, summary=?, body=?, image=?, in_top_menu=?, sort_order=?, published=?, updated_at=? WHERE id=?')->execute([...$vals, $id]);
        } else {
            $pdo->prepare('INSERT INTO pages (title, slug, summary, body, image, in_top_menu, sort_order, published, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')->execute($vals);
            $id = (int)$pdo->lastInsertId();
        }
        $err ? flash('error', 'Page saved, but the photo wasn\'t: ' . $err) : flash('success', 'Page saved.');
        redirect('pages.php?id=' . $id);
    }
    redirect($id ? 'pages.php?id=' . $id : 'pages.php?new=1');
}

if (!$editing) {
    $list = $pdo->query('SELECT * FROM pages ORDER BY sort_order, title')->fetchAll();
    admin_header('Website: pages', 'pages');
    ?>
    <p><a class="btn btn--red" href="pages.php?new=1">Add a page</a></p>
    <section class="panel">
      <p class="muted">Pages such as About us, Laboratory and Pharmacy. Pages ticked "top menu" appear in the small links at the top of every page. The home page shows "About us" and the first two other pages as feature cards (Laboratory and Pharmacy by default).</p>
      <table class="table">
        <thead><tr><th>Page</th><th>Address</th><th>Top menu</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $pg): ?>
          <tr class="<?= $pg['published'] ? '' : 'is-muted' ?>">
            <td><strong><a href="pages.php?id=<?= (int)$pg['id'] ?>"><?= e($pg['title']) ?></a></strong><br><small class="muted"><?= e($pg['summary']) ?></small></td>
            <td><code>page.php?slug=<?= e($pg['slug']) ?></code></td>
            <td><?= $pg['in_top_menu'] ? 'Yes' : 'No' ?></td>
            <td><?= $pg['published'] ? 'Published' : 'Hidden' ?></td>
            <td class="nowrap"><a class="btn btn--ghost btn--small" href="pages.php?id=<?= (int)$pg['id'] ?>">Edit</a> <a class="btn btn--ghost btn--small" href="../page.php?slug=<?= e(urlencode($pg['slug'])) ?>" target="_blank" rel="noopener">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </section>
    <?php
    admin_footer();
    exit;
}

$img = site_image_url($page['image'] ?? null);
admin_header($page ? 'Edit page: ' . $page['title'] : 'Add a page', 'pages');
?>
<p><a href="pages.php">All pages</a></p>
<form method="post" enctype="multipart/form-data" class="panel grid-form">
  <?= csrf_field() ?>
  <?php if ($page): ?><input type="hidden" name="id" value="<?= (int)$page['id'] ?>"><?php endif; ?>
  <label class="span-2">Title <input name="title" value="<?= e($page['title'] ?? '') ?>" required></label>
  <label>Web address <span class="help">(letters and dashes)</span><input name="slug" value="<?= e($page['slug'] ?? '') ?>" placeholder="made from the title"></label>
  <label>Order <input type="number" name="sort_order" value="<?= (int)($page['sort_order'] ?? 0) ?>"></label>
  <label class="span-4">Short summary <span class="help">(shown under the title and on cards)</span><input name="summary" value="<?= e($page['summary'] ?? '') ?>" maxlength="300"></label>
  <label class="span-4">Page text <span class="help">(leave an empty line between paragraphs)</span><textarea name="body" class="tall"><?= e($page['body'] ?? '') ?></textarea></label>
  <div class="span-4 photo-field">
    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="thumb"><?php endif; ?>
    <label>Photo for the top of the page and its card <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    <?php if ($img): ?><label class="check"><input type="checkbox" name="remove_image" value="1"> Remove photo</label><?php endif; ?>
  </div>
  <label class="check span-2"><input type="checkbox" name="in_top_menu" value="1"<?= ($page['in_top_menu'] ?? 1) ? ' checked' : '' ?>> Show in the top menu</label>
  <label class="check span-2"><input type="checkbox" name="published" value="1"<?= ($page['published'] ?? 1) ? ' checked' : '' ?>> Published (visible on the website)</label>
  <div class="span-4 row-gap"><button class="btn btn--red" type="submit">Save page</button></div>
</form>
<?php if ($page): ?>
<form method="post" onsubmit="return confirm('Delete this page? This can\'t be undone.')">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$page['id'] ?>"><input type="hidden" name="action" value="delete">
  <button class="btn btn--danger" type="submit">Delete page</button>
</form>
<?php endif; ?>
<?php admin_footer(); ?>
