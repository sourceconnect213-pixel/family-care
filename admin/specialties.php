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
        if ($name === '') flash('error', 'Enter a specialty name.');
        else { $pdo->prepare('INSERT INTO specialties (name, description, sort_order) VALUES (?,?,?)')->execute([$name, $desc, $sort]); flash('success', 'Specialty added.'); }
    } elseif ($action === 'update') {
        if ($name === '') flash('error', 'Specialty name can\'t be empty.');
        else { $pdo->prepare('UPDATE specialties SET name=?, description=?, sort_order=? WHERE id=?')->execute([$name, $desc, $sort, (int)$_POST['sid']]); flash('success', 'Specialty saved.'); }
    } elseif ($action === 'delete') {
        $pdo->prepare('UPDATE doctors SET specialty_id = NULL WHERE specialty_id = ?')->execute([(int)$_POST['sid']]);
        $pdo->prepare('DELETE FROM specialties WHERE id = ?')->execute([(int)$_POST['sid']]);
        flash('success', 'Specialty deleted.');
    }
    redirect('specialties.php');
}

$specs = specialties();
admin_header('Specialties', 'specialties');
?>
<section class="panel">
  <p class="muted">Specialties group doctors on the website. Lower order numbers show first.</p>
  <div class="table-scroll">
  <table class="table table--form">
    <thead><tr><th>Order</th><th>Name</th><th>Short description</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($specs as $s): $fid = 'sp' . (int)$s['id']; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>" class="w-num" aria-label="Order"></td>
        <td><input form="<?= $fid ?>" name="name" value="<?= e($s['name']) ?>" required aria-label="Name"></td>
        <td><input form="<?= $fid ?>" name="description" value="<?= e($s['description']) ?>" aria-label="Description"></td>
        <td class="nowrap">
          <form id="<?= $fid ?>" method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn btn--navy btn--small">Save</button></form>
          <form method="post" class="inline" onsubmit="return confirm('Delete this specialty? Doctors in it will have no specialty.')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="sid" value="<?= (int)$s['id'] ?>"><button class="btn btn--danger btn--small">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<section class="panel">
  <h2>Add a specialty</h2>
  <form method="post" class="grid-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Order <input type="number" name="sort_order" value="<?= count($specs) ?>"></label>
    <label>Name <input name="name" required placeholder="Neurologist"></label>
    <label class="span-2">Short description <input name="description" placeholder="Brain, nerves and headaches."></label>
    <div class="span-4"><button class="btn btn--red" type="submit">Add specialty</button></div>
  </form>
</section>
<?php admin_footer(); ?>
