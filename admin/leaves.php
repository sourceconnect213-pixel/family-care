<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if (is_post()) {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'add') {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $from = (string)($_POST['from'] ?? '');
        $to = (string)($_POST['to'] ?? '') ?: $from;
        $scheduleId = (int)($_POST['schedule_id'] ?? 0) ?: null;
        $reason = trim((string)($_POST['reason'] ?? '')) ?: 'Doctor not available';
        $ok = get_doctor($doctorId, false) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $to >= $from
            && (strtotime($to) - strtotime($from)) <= 366 * 86400;
        if (!$ok) {
            flash('error', 'Pick a doctor and a valid date range (up to one year).');
        } else {
            $ins = $pdo->prepare('INSERT INTO leaves (doctor_id, leave_date, schedule_id, reason, created_at) VALUES (?,?,?,?,?)');
            $affected = 0;
            for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day"))) {
                $ins->execute([$doctorId, $d, $scheduleId, $reason, date('Y-m-d H:i:s')]);
                $sql = "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appt_date = ? AND status = 'booked'" . ($scheduleId ? ' AND schedule_id = ?' : '');
                $st = $pdo->prepare($sql);
                $st->execute($scheduleId ? [$doctorId, $d, $scheduleId] : [$doctorId, $d]);
                $affected += (int)$st->fetchColumn();
            }
            flash('success', 'Leave saved. Online booking is closed for those dates.');
            if ($affected) flash('error', "$affected patients already booked in this period. Open Appointments, call them, then mark them Cancelled.");
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM leaves WHERE id = ?')->execute([(int)$_POST['leave_id']]);
        flash('success', 'Leave removed. Those sessions are open for booking again.');
    }
    redirect('leaves.php');
}

$list = $pdo->prepare('SELECT l.*, d.name AS doctor_name, s.weekday, s.start_time FROM leaves l JOIN doctors d ON d.id = l.doctor_id
    LEFT JOIN schedules s ON s.id = l.schedule_id WHERE l.leave_date >= ? ORDER BY l.leave_date, d.name');
$list->execute([date('Y-m-d')]);
$list = $list->fetchAll();
$allDoctors = doctors(null, '', false);
$allSchedules = $pdo->query('SELECT s.*, d.name AS doctor_name FROM schedules s JOIN doctors d ON d.id = s.doctor_id ORDER BY d.name, s.weekday, s.start_time')->fetchAll();

admin_header('Doctor leave', 'leaves');
?>
<section class="panel">
  <h2>Mark a doctor as not available</h2>
  <p class="muted">Use this for holidays, conferences or a cancelled session. Patients won't be able to book those dates online.</p>
  <form method="post" class="grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <label class="span-2">Doctor
      <select name="doctor_id" id="leave-doctor" required>
        <option value="">Choose a doctor</option>
        <?php foreach ($allDoctors as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label class="span-2">Which sessions
      <select name="schedule_id" id="leave-session">
        <option value="">All sessions on these dates</option>
        <?php foreach ($allSchedules as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-doctor="<?= (int)$s['doctor_id'] ?>"><?= e($s['doctor_name']) ?>: <?= e(WEEKDAYS[(int)$s['weekday']]) ?> <?= e(fmt_time($s['start_time'])) ?> only</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>From <input type="date" name="from" required min="<?= e(date('Y-m-d')) ?>"></label>
    <label>To (same day if empty) <input type="date" name="to" min="<?= e(date('Y-m-d')) ?>"></label>
    <label class="span-2">Reason shown to patients <input name="reason" placeholder="Doctor on leave"></label>
    <div class="span-4"><button class="btn btn--red" type="submit">Save leave</button></div>
  </form>
</section>

<section class="panel">
  <h2>Upcoming leave</h2>
  <?php if (!$list): ?>
    <p class="muted">No upcoming leave.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Date</th><th>Doctor</th><th>Session</th><th>Reason</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $l): ?>
      <tr>
        <td class="nowrap"><?= e(fmt_date($l['leave_date'], 'D j M Y')) ?></td>
        <td><?= e($l['doctor_name']) ?></td>
        <td><?= $l['schedule_id'] ? e(fmt_time($l['start_time'])) . ' session' : 'All day' ?></td>
        <td><?= e($l['reason']) ?></td>
        <td>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="leave_id" value="<?= (int)$l['id'] ?>">
            <button class="btn btn--ghost btn--small" type="submit">Remove</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<script>
// Only show the chosen doctor's sessions
(function () {
  var doc = document.getElementById('leave-doctor'), ses = document.getElementById('leave-session');
  function sync() {
    Array.prototype.forEach.call(ses.options, function (o) {
      if (!o.dataset.doctor) return;
      o.hidden = o.dataset.doctor !== doc.value;
      if (o.hidden && o.selected) ses.value = '';
    });
  }
  doc.addEventListener('change', sync); sync();
})();
</script>
<?php admin_footer(); ?>
