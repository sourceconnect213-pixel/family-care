<?php
require __DIR__ . '/_layout.php';
$doc = require_doctor();
$pdo = db();
$docId = (int)$doc['id'];
const DOCTOR_STATUSES = ['booked', 'arrived', 'completed', 'no_show'];

if (is_post()) {
    verify_csrf();
    $status = (string)($_POST['status'] ?? '');
    $date = (string)($_POST['date'] ?? '');
    if (in_array($status, DOCTOR_STATUSES, true)) {
        // only this doctor's own, non-cancelled appointments
        $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ? AND status <> 'cancelled'")
            ->execute([$status, (int)$_POST['id'], $docId]);
        flash('success', 'Marked ' . STATUSES[$status] . '.');
    }
    redirect('index.php' . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? '?date=' . $date : ''));
}

$today = date('Y-m-d');
$date = (string)($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    // Open on today if the doctor sits today, otherwise on their next session
    $date = $today;
    $hasToday = array_filter(doctor_schedules($docId), fn($s) => (int)$s['weekday'] === weekday_of($today));
    if (!$hasToday) {
        foreach (upcoming_sessions($docId) as $d => $sessions) { $date = $d; break; }
    }
}

// New bookings the doctor hasn't opened yet
$st = $pdo->prepare("SELECT appt_date, COUNT(*) AS n FROM appointments WHERE doctor_id = ? AND seen_by_doctor = 0 AND status <> 'cancelled' AND appt_date >= ? GROUP BY appt_date ORDER BY appt_date");
$st->execute([$docId, $today]);
$unseen = $st->fetchAll();
$unseenTotal = array_sum(array_column($unseen, 'n'));

// Next 14 days at a glance
$upcoming = [];
foreach (upcoming_sessions($docId) as $d => $sessions) {
    if (strtotime($d) > strtotime('+14 days')) break;
    foreach ($sessions as $r) $upcoming[] = $r;
}

// Patients for the chosen date
$st = $pdo->prepare("SELECT a.*, sc.start_time, sc.end_time, sc.minutes_per_patient, sc.room, sc.max_patients
    FROM appointments a JOIN schedules sc ON sc.id = a.schedule_id
    WHERE a.doctor_id = ? AND a.appt_date = ? ORDER BY sc.start_time, a.number");
$st->execute([$docId, $date]);
$bySession = [];
foreach ($st->fetchAll() as $a) $bySession[$a['schedule_id']][] = $a;

portal_header('My patients', 'patients');
?>
<?php if ($unseenTotal): ?>
  <div class="alert alert--new" role="status">
    <strong><?= $unseenTotal ?> new <?= $unseenTotal === 1 ? 'booking' : 'bookings' ?></strong> since you last looked:
    <?php foreach ($unseen as $i => $u): ?><?= $i ? ', ' : ' ' ?><a href="?date=<?= e($u['appt_date']) ?>"><?= e(fmt_date($u['appt_date'], 'D j M')) ?> (<?= (int)$u['n'] ?>)</a><?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="panel">
  <div class="panel__head">
    <h2>Next 14 days</h2>
  </div>
  <?php if (!$upcoming): ?>
    <p class="muted">No sessions in the next 14 days.</p>
  <?php else: ?>
  <div class="day-strip">
    <?php foreach ($upcoming as $r): $s = $r['schedule']; ?>
      <a class="day-pill<?= $r['date'] === $date ? ' is-active' : '' ?><?= $r['state'] === 'leave' ? ' is-leave' : '' ?>" href="?date=<?= e($r['date']) ?>">
        <span><?= $r['date'] === $today ? 'Today' : e(fmt_date($r['date'], 'D j M')) ?></span>
        <strong><?= $r['state'] === 'leave' ? 'Leave' : (int)$r['booked'] . ' / ' . (int)$s['max_patients'] ?></strong>
        <small><?= e(fmt_time($s['start_time'])) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panel__head">
    <h2>Patients on <?= e(fmt_date($date)) ?></h2>
    <form method="get" class="row-gap">
      <label class="sr-only" for="pick">Date</label>
      <input id="pick" type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
      <noscript><button class="btn btn--ghost btn--small">Show</button></noscript>
    </form>
  </div>
  <?php if (!$bySession): ?>
    <p class="muted">No patients booked for this date.</p>
  <?php endif; ?>
  <?php foreach ($bySession as $rows): $first = $rows[0]; $active = array_filter($rows, fn($r) => $r['status'] !== 'cancelled'); ?>
    <h3><?= e(fmt_time($first['start_time'])) ?> – <?= e(fmt_time($first['end_time'])) ?> session<?= $first['room'] ? ', ' . e($first['room']) : '' ?> <span class="muted">· <?= count($active) ?> of <?= (int)$first['max_patients'] ?> booked</span></h3>
    <div class="table-scroll">
    <table class="table">
      <thead><tr><th>No.</th><th>Est. time</th><th>Patient</th><th>Phone</th><th>Note</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $a): ?>
        <tr class="<?= $a['status'] === 'cancelled' ? 'is-muted' : '' ?>">
          <td class="num"><?= (int)$a['number'] ?></td>
          <td class="nowrap"><?= e(fmt_time(estimated_time($a, (int)$a['number']))) ?></td>
          <td>
            <strong><?= e($a['patient_name']) ?></strong>
            <?php if (!$a['seen_by_doctor'] && $a['status'] !== 'cancelled'): ?><span class="badge badge--new">New</span><?php endif; ?>
            <br><small class="muted"><?= $a['age'] !== null ? (int)$a['age'] . ' years' : '' ?><?= $a['gender'] ? ($a['age'] !== null ? ', ' : '') . e(ucfirst($a['gender'])) : '' ?></small>
          </td>
          <td><a href="tel:<?= e($a['phone']) ?>"><?= e($a['phone']) ?></a></td>
          <td><?= $a['notes'] ? '<small class="note">' . e($a['notes']) . '</small>' : '' ?></td>
          <td><?= '<span class="badge badge--' . e($a['status']) . '">' . e(STATUSES[$a['status']] ?? $a['status']) . '</span>' ?></td>
          <td>
            <?php if ($a['status'] !== 'cancelled'): ?>
            <form method="post" class="inline-status">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="date" value="<?= e($date) ?>">
              <label class="sr-only" for="s<?= (int)$a['id'] ?>">Status</label>
              <select id="s<?= (int)$a['id'] ?>" name="status">
                <?php foreach (DOCTOR_STATUSES as $k): ?><option value="<?= e($k) ?>"<?= $a['status'] === $k ? ' selected' : '' ?>><?= e(STATUSES[$k]) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn--navy btn--small" type="submit">Save</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endforeach; ?>
</section>
<?php
// The doctor has now seen this day's bookings
$pdo->prepare('UPDATE appointments SET seen_by_doctor = 1 WHERE doctor_id = ? AND appt_date = ?')->execute([$docId, $date]);
portal_footer();
