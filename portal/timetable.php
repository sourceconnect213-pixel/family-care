<?php
require __DIR__ . '/_layout.php';
$doc = require_doctor();
$schedules = doctor_schedules((int)$doc['id']);
$leave = db()->prepare('SELECT l.*, s.start_time FROM leaves l LEFT JOIN schedules s ON s.id = l.schedule_id WHERE l.doctor_id = ? AND l.leave_date >= ? ORDER BY l.leave_date');
$leave->execute([$doc['id'], date('Y-m-d')]);
$leave = $leave->fetchAll();

portal_header('My timetable', 'timetable');
?>
<section class="panel">
  <h2>Weekly sessions</h2>
  <?php if (!$schedules): ?><p class="muted">No sessions yet. The hospital admin sets your sessions.</p><?php else: ?>
  <table class="table">
    <thead><tr><th>Day</th><th>Time</th><th>Max patients</th><th>Minutes per patient</th><th>Room</th></tr></thead>
    <tbody>
    <?php foreach ($schedules as $s): ?>
      <tr><td><strong><?= e(WEEKDAYS[(int)$s['weekday']]) ?></strong></td><td><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?></td><td><?= (int)$s['max_patients'] ?></td><td><?= (int)$s['minutes_per_patient'] ?></td><td><?= e($s['room']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<section class="panel">
  <h2>Upcoming leave</h2>
  <?php if (!$leave): ?><p class="muted">No leave booked. To take leave, ask the hospital admin so online booking is closed for those dates.</p><?php else: ?>
  <table class="table">
    <thead><tr><th>Date</th><th>Session</th><th>Reason</th></tr></thead>
    <tbody>
    <?php foreach ($leave as $l): ?>
      <tr><td><?= e(fmt_date($l['leave_date'], 'D j M Y')) ?></td><td><?= $l['schedule_id'] ? e(fmt_time($l['start_time'])) : 'All day' ?></td><td><?= e($l['reason']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php portal_footer(); ?>
