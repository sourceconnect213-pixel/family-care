<?php
require __DIR__ . '/_layout.php';
require_admin();

$today = date('Y-m-d');
$pdo = db();
$q = fn($sql, $args = []) => (function () use ($pdo, $sql, $args) { $st = $pdo->prepare($sql); $st->execute($args); return $st; })();

$todayCount = (int)$q("SELECT COUNT(*) FROM appointments WHERE appt_date = ? AND status <> 'cancelled'", [$today])->fetchColumn();
$weekCount = (int)$q("SELECT COUNT(*) FROM appointments WHERE appt_date > ? AND appt_date <= ? AND status <> 'cancelled'", [$today, date('Y-m-d', strtotime('+7 days'))])->fetchColumn();
$newToday = (int)$q("SELECT COUNT(*) FROM appointments WHERE created_at >= ?", [$today . ' 00:00:00'])->fetchColumn();
$doctorCount = (int)$pdo->query('SELECT COUNT(*) FROM doctors WHERE active = 1')->fetchColumn();
$sessions = sessions_on($today);
$recent = $pdo->query('SELECT a.*, d.name AS doctor_name FROM appointments a JOIN doctors d ON d.id = a.doctor_id ORDER BY a.created_at DESC LIMIT 8')->fetchAll();

admin_header('Dashboard', 'dashboard');
?>
<p class="muted"><?= e(fmt_date($today)) ?></p>
<div class="tiles">
  <a class="tile" href="appointments.php?date=<?= e($today) ?>"><strong><?= $todayCount ?></strong><span>Patients booked today</span></a>
  <a class="tile" href="appointments.php?from=<?= e(date('Y-m-d', strtotime('+1 day'))) ?>&amp;to=<?= e(date('Y-m-d', strtotime('+7 days'))) ?>"><strong><?= $weekCount ?></strong><span>Booked for the next 7 days</span></a>
  <div class="tile"><strong><?= $newToday ?></strong><span>New bookings made today</span></div>
  <a class="tile" href="doctors.php"><strong><?= $doctorCount ?></strong><span>Active doctors</span></a>
</div>

<section class="panel">
  <div class="panel__head">
    <h2>Today's sessions</h2>
    <a class="btn btn--ghost btn--small" href="appointments.php?date=<?= e($today) ?>">All of today's patients</a>
  </div>
  <?php if (!$sessions): ?>
    <p class="muted">No sessions scheduled today.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Time</th><th>Doctor</th><th>Booked</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sessions as $r): $s = $r['schedule']; ?>
      <tr>
        <td class="nowrap"><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?></td>
        <td><strong><?= e($s['doctor_name']) ?></strong><br><small class="muted"><?= e($s['specialty'] ?? '') ?><?= $s['room'] ? ', ' . e($s['room']) : '' ?></small></td>
        <td>
          <div class="meter" role="img" aria-label="<?= (int)$r['booked'] ?> of <?= (int)$s['max_patients'] ?> booked"><span style="width: <?= min(100, round(100 * $r['booked'] / max(1, $s['max_patients']))) ?>%"></span></div>
          <small><?= (int)$r['booked'] ?> / <?= (int)$s['max_patients'] ?></small>
        </td>
        <td><?= e(['open' => 'Taking bookings', 'full' => 'Full', 'closed' => 'Online booking closed', 'leave' => 'On leave'][$r['state']] ?? '') ?></td>
        <td class="nowrap">
          <a href="appointments.php?date=<?= e($today) ?>&amp;schedule=<?= (int)$s['id'] ?>">Patients</a> ·
          <a href="print.php?date=<?= e($today) ?>&amp;schedule=<?= (int)$s['id'] ?>" target="_blank">Print list</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel__head"><h2>Latest bookings</h2></div>
  <?php if (!$recent): ?>
    <p class="muted">No bookings yet. They'll appear here as patients book online or you add walk-ins.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Ref</th><th>Patient</th><th>Doctor</th><th>Date</th><th>No.</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $a): ?>
      <tr>
        <td><code><?= e($a['ref']) ?></code></td>
        <td><?= e($a['patient_name']) ?><br><small class="muted"><?= e($a['phone']) ?></small></td>
        <td><?= e($a['doctor_name']) ?></td>
        <td class="nowrap"><?= e(fmt_date($a['appt_date'], 'D j M')) ?></td>
        <td><?= (int)$a['number'] ?></td>
        <td><?= status_badge($a['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>
