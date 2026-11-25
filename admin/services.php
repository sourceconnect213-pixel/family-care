<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $name = trim((string)($_POST['name'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $sort = (int)($_POST['sort_order'] ?? 0);
    if ($action === 'add') {
        if ($name === '') flash('error', 'Enter a service name.');
        else { $pdo->prepare('INSERT INTO services (name, description, sort_order) VALUES (?,?,?)')->execute([$name, $desc, $sort]); flash('success', 'Service added.'); }
    } elseif ($action === 'update') {
        if ($name === '') flash('error', 'Service name can\'t be empty.');
        else { $pdo->prepare('UPDATE services SET name=?, description=?, sort_order=? WHERE id=?')->execute([$name, $desc, $sort, (int)$_POST['sid']]); flash('success', 'Service saved.'); }
    } elseif ($action === 'delete') {
        $pdo->prepare('DELETE FROM services WHERE id = ?')->execute([(int)$_POST['sid']]);
        flash('success', 'Service deleted.');
    }
    redirect('services.php');
}

$list = services();
admin_header('Website: services', 'services');
?>
<section class="panel">
  <p class="muted">These are the "General services" on the home page and the Services page. Medical services come from <a href="specialties.php">Specialties</a>.</p>
  <div class="table-scroll">
  <table class="table table--form">
    <thead><tr><th>Order</th><th>Name</th><th>Short description</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $s): $fid = 'sv' . (int)$s['id']; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>" class="w-num" aria-label="Order"></td>
        <td><input form="<?= $fid ?>" name="name" value="<?= e($s['name']) ?>" required aria-label="Name"></td>
        <td><input form="<?= $fid ?>" name="description" value="<?= e($s['description']) ?>" aria-label="Description"></td>
        <td class="nowrap">
          <form id="<?= $fid ?>" method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn btn--navy btn--small">Save</button></form>
          <form method="post" class="inline" onsubmit="return confirm('Delete this service?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn btn--danger btn--small">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<section class="panel">
  <h2>Add a service</h2>
  <form method="post" class="grid-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Order <input type="number" name="sort_order" value="<?= count($list) ?>"></label>
    <label>Name <input name="name" required placeholder="Physiotherapy"></label>
    <label class="span-2">Short description <input name="description" placeholder="Exercises and treatment after injury or surgery."></label>
    <div class="span-4"><button class="btn btn--red" type="submit">Add service</button></div>
  </form>
</section>
<?php admin_footer(); ?>
