<?php
require __DIR__ . '/_layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        db()->prepare('UPDATE doctors SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('success', 'Doctor visibility updated.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $st = db()->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('error', 'This doctor has appointment records, so they can\'t be deleted. Hide them from the website instead.');
        } else {
            $doc = get_doctor($id, false);
            if ($doc && $doc['photo']) @unlink(UPLOAD_DIR . '/' . $doc['photo']);
            db()->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
            flash('success', 'Doctor deleted.');
        }
    }
    redirect('doctors.php');
}

$list = doctors(null, '', false);
admin_header('Doctors & sessions', 'doctors');
?>
<p><a class="btn btn--red" href="doctor_edit.php">Add a doctor</a></p>
<section class="panel">
  <?php if (!$list): ?>
    <p class="muted">No doctors yet. Add your first doctor to start taking bookings.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th></th><th>Doctor</th><th>Weekly sessions</th><th>Fee</th><th>On website</th><th>Doctor login</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $d): $sch = doctor_schedules((int)$d['id'], false); ?>
      <tr class="<?= $d['active'] ? '' : 'is-muted' ?>">
        <td class="avatar-cell"><?= doctor_avatar($d) ?></td>
        <td><strong><a href="doctor_edit.php?id=<?= (int)$d['id'] ?>"><?= e($d['name']) ?></a></strong><br><small class="muted"><?= e($d['specialty'] ?? 'No specialty') ?></small></td>
        <td>
          <?php if (!$sch): ?><span class="warn">No sessions: patients can't book</span><?php endif; ?>
          <?php foreach ($sch as $s): ?>
            <span class="chip<?= $s['active'] ? '' : ' chip--off' ?>"><?= e(WEEKDAYS_SHORT[(int)$s['weekday']]) ?> <?= e(fmt_time($s['start_time'])) ?></span>
          <?php endforeach; ?>
        </td>
        <td class="nowrap"><?= e(money($d['fee'])) ?></td>
        <td><?= $d['active'] ? 'Shown' : 'Hidden' ?></td>
        <td><?= $d['login_enabled'] ? e($d['username']) : '<span class="muted">Not set</span>' ?></td>
        <td class="nowrap">
          <a class="btn btn--ghost btn--small" href="doctor_edit.php?id=<?= (int)$d['id'] ?>">Edit</a>
          <form method="post" class="inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="toggle">
            <button class="btn btn--ghost btn--small" type="submit"><?= $d['active'] ? 'Hide' : 'Show' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Delete <?= e(addslashes($d['name'])) ?>? This can\'t be undone.')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn--danger btn--small" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>
