<?php
require __DIR__ . '/../includes/functions.php';
require_admin();

$date = (string)($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$doctorId = (int)($_GET['doctor'] ?? 0);
$scheduleId = (int)($_GET['schedule'] ?? 0);

$sessions = array_filter(sessions_on($date), function ($r) use ($doctorId, $scheduleId) {
    $s = $r['schedule'];
    return (!$doctorId || (int)$s['doctor_id'] === $doctorId) && (!$scheduleId || (int)$s['id'] === $scheduleId);
});
$st = db()->prepare("SELECT * FROM appointments WHERE schedule_id = ? AND appt_date = ? AND status <> 'cancelled' ORDER BY number");
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Patient lists <?= e($date) ?></title>
<meta name="robots" content="noindex">
<style>
  body { font-family: "Source Sans 3", Arial, sans-serif; color: #14213D; margin: 24px; font-size: 14px; }
  .sheet { page-break-after: always; margin-bottom: 40px; }
  .sheet:last-child { page-break-after: auto; }
  header { display: flex; align-items: center; gap: 14px; border-bottom: 3px solid #E30613; padding-bottom: 10px; margin-bottom: 12px; }
  header img { width: 40px; }
  h1 { font-size: 18px; margin: 0; color: #0B2265; }
  h2 { font-size: 15px; margin: 0; font-weight: 600; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #B9C4DA; padding: 6px 8px; text-align: left; vertical-align: top; }
  th { background: #EEF2F9; }
  td.n { font-weight: 700; font-size: 16px; width: 40px; text-align: center; }
  td.tick { width: 60px; }
  .muted { color: #56637F; }
  .noprint { margin-bottom: 20px; }
  @media print { .noprint { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<p class="noprint"><button onclick="window.print()">Print</button> <a href="appointments.php?date=<?= e($date) ?>">Back to appointments</a></p>
<?php if (!$sessions): ?><p>No sessions on <?= e(fmt_date($date)) ?>.</p><?php endif; ?>
<?php foreach ($sessions as $r): $s = $r['schedule']; $st->execute([$s['id'], $date]); $list = $st->fetchAll(); ?>
<section class="sheet">
  <header>
    <img src="../assets/img/logo.png" alt="">
    <div>
      <h1><?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?></h1>
      <h2><?= e($s['doctor_name']) ?> · <?= e($s['specialty'] ?? '') ?></h2>
      <span class="muted"><?= e(fmt_date($date)) ?>, <?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?><?= $s['room'] ? ', ' . e($s['room']) : '' ?> · <?= count($list) ?> patients</span>
    </div>
  </header>
  <?php if (!$list): ?><p class="muted">No patients booked.</p><?php else: ?>
  <table>
    <thead><tr><th>No.</th><th>Est. time</th><th>Patient</th><th>Age</th><th>Phone</th><th>NIC</th><th>Ref</th><th>Notes</th><th>Arrived</th></tr></thead>
    <tbody>
      <?php foreach ($list as $a): ?>
      <tr>
        <td class="n"><?= (int)$a['number'] ?></td>
        <td><?= e(fmt_time(estimated_time($s, (int)$a['number']))) ?></td>
        <td><?= e($a['patient_name']) ?></td>
        <td><?= $a['age'] !== null ? (int)$a['age'] : '' ?></td>
        <td><?= e($a['phone']) ?></td>
        <td><?= e($a['nic']) ?></td>
        <td><?= e($a['ref']) ?></td>
        <td><?= e($a['notes']) ?></td>
        <td class="tick"><?= $a['status'] === 'arrived' || $a['status'] === 'completed' ? '✓' : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>
<?php endforeach; ?>
</body>
</html>
