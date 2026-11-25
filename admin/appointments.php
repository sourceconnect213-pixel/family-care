<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

/* ---- actions ---- */
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $back = $_POST['back'] ?? 'appointments.php';
    if (!preg_match('/^appointments\.php(\?[\w=&%.+\-]*)?$/', $back)) $back = 'appointments.php';

    if ($action === 'status') {
        $status = (string)($_POST['status'] ?? '');
        if (isset(STATUSES[$status])) {
            $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')->execute([$status, (int)$_POST['id']]);
            flash('success', 'Status changed to ' . STATUSES[$status] . '.');
        }
    } elseif ($action === 'walkin') {
        [$p, $errors] = validate_patient($_POST);
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            $res = book_appointment((int)$_POST['schedule'], (string)$_POST['date'], $p, 'admin');
            if ($res['ok']) flash('success', 'Booked No. ' . $res['appointment']['number'] . ' for ' . $p['name'] . ' (ref ' . $res['appointment']['ref'] . ').');
            else flash('error', $res['error']);
        }
    }
    redirect($back);
}

/* ---- filters ---- */
$date = (string)($_GET['date'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
if ($date === '' && $from === '' && $to === '' && !isset($_GET['q'])) $date = date('Y-m-d');
$doctorId = (int)($_GET['doctor'] ?? 0);
$scheduleId = (int)($_GET['schedule'] ?? 0);
$status = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));

$where = ['1=1'];
$args = [];
$isDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
if ($isDate($date)) { $where[] = 'a.appt_date = ?'; $args[] = $date; }
if ($isDate($from)) { $where[] = 'a.appt_date >= ?'; $args[] = $from; }
if ($isDate($to)) { $where[] = 'a.appt_date <= ?'; $args[] = $to; }
if ($doctorId) { $where[] = 'a.doctor_id = ?'; $args[] = $doctorId; }
if ($scheduleId) { $where[] = 'a.schedule_id = ?'; $args[] = $scheduleId; }
if (isset(STATUSES[$status])) { $where[] = 'a.status = ?'; $args[] = $status; }
if ($search !== '') {
    $where[] = '(a.ref LIKE ? OR a.patient_name LIKE ? OR a.phone LIKE ? OR a.nic LIKE ?)';
    array_push($args, "%$search%", "%$search%", '%' . normalize_phone($search) . '%', "%$search%");
}
$sql = 'SELECT a.*, d.name AS doctor_name, sc.start_time, sc.end_time, sc.minutes_per_patient
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN schedules sc ON sc.id = a.schedule_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY a.appt_date, sc.start_time, d.name, a.number LIMIT 1000';
$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

/* ---- CSV export ---- */
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Date', 'Session', 'Number', 'Doctor', 'Patient', 'Phone', 'NIC', 'Age', 'Gender', 'Status', 'Source', 'Notes', 'Booked at']);
    foreach ($rows as $r) {
        $safe = fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v; // avoid spreadsheet formula injection
        fputcsv($out, array_map($safe, [$r['ref'], $r['appt_date'], $r['start_time'] . '-' . $r['end_time'], $r['number'], $r['doctor_name'],
            $r['patient_name'], $r['phone'], $r['nic'], $r['age'], $r['gender'], STATUSES[$r['status']] ?? $r['status'], $r['source'], $r['notes'], $r['created_at']]));
    }
    exit;
}

$allDoctors = doctors(null, '', false);
$backUrl = 'appointments.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');

// Walk-in: sessions for chosen date (default today)
$walkDate = $isDate($date) ? $date : date('Y-m-d');
$walkSessions = array_filter(sessions_on($walkDate), fn($r) => $r['state'] !== 'leave');

admin_header('Appointments', 'appointments');
?>
<form class="filters" method="get">
  <label>Date <input type="date" name="date" value="<?= e($date) ?>"></label>
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <label>Doctor
    <select name="doctor">
      <option value="">All doctors</option>
      <?php foreach ($allDoctors as $d): ?><option value="<?= (int)$d['id'] ?>"<?= $doctorId === (int)$d['id'] ? ' selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Status
    <select name="status">
      <option value="">Any status</option>
      <?php foreach (STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Search <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, phone, NIC or ref"></label>
  <div class="filters__btns">
    <button class="btn btn--navy" type="submit">Filter</button>
    <a class="btn btn--ghost" href="appointments.php?date=<?= e(date('Y-m-d')) ?>">Today</a>
  </div>
</form>

<details class="panel walkin"<?= $walkSessions ? '' : '' ?>>
  <summary>Add a walk-in or phone booking for <?= e(fmt_date($walkDate, 'D j M')) ?></summary>
  <?php if (!$walkSessions): ?>
    <p class="muted">No sessions on this date. Pick a single date in the filter above to add a booking for another day.</p>
  <?php else: ?>
  <form method="post" class="grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="walkin">
    <input type="hidden" name="date" value="<?= e($walkDate) ?>">
    <input type="hidden" name="back" value="<?= e($backUrl) ?>">
    <label class="span-2">Session
      <select name="schedule" required>
        <?php foreach ($walkSessions as $r): $s = $r['schedule']; ?>
          <option value="<?= (int)$s['id'] ?>"<?= $r['state'] === 'full' ? ' disabled' : '' ?>><?= e(fmt_time($s['start_time'])) ?> · <?= e($s['doctor_name']) ?> (<?= (int)$r['booked'] ?>/<?= (int)$s['max_patients'] ?><?= $r['state'] === 'full' ? ', full' : '' ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Patient name <input name="patient_name" required></label>
    <label>Mobile <input name="phone" type="tel" required placeholder="07XXXXXXXX"></label>
    <label>NIC <input name="nic"></label>
    <label>Age <input name="age" type="number" min="0" max="120"></label>
    <label>Gender
      <select name="gender"><option value="">–</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select>
    </label>
    <label class="span-2">Notes <input name="notes"></label>
    <div><button class="btn btn--red" type="submit">Add booking</button></div>
  </form>
  <?php endif; ?>
</details>

<section class="panel">
  <div class="panel__head">
    <h2><?= count($rows) ?> <?= count($rows) === 1 ? 'appointment' : 'appointments' ?></h2>
    <div class="row-gap">
      <?php if ($isDate($date)): ?><a class="btn btn--ghost btn--small" target="_blank" href="print.php?date=<?= e($date) ?><?= $doctorId ? '&amp;doctor=' . $doctorId : '' ?><?= $scheduleId ? '&amp;schedule=' . $scheduleId : '' ?>">Print patient lists</a><?php endif; ?>
      <a class="btn btn--ghost btn--small" href="<?= e($backUrl . (str_contains($backUrl, '?') ? '&' : '?') . 'export=csv') ?>">Download CSV</a>
    </div>
  </div>
  <?php if (!$rows): ?>
    <p class="muted">No appointments match these filters.</p>
  <?php else: ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>No.</th><th>Patient</th><th>Doctor & session</th><th>Ref</th><th>Status</th><th>Change status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['status'] === 'cancelled' ? 'is-muted' : '' ?>">
        <td class="num"><?= (int)$r['number'] ?></td>
        <td>
          <strong><?= e($r['patient_name']) ?></strong><?= $r['age'] !== null ? ', ' . (int)$r['age'] . 'y' : '' ?>
          <br><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?= $r['nic'] ? ' · NIC ' . e($r['nic']) : '' ?>
          <?php if ($r['notes']): ?><br><small class="note"><?= e($r['notes']) ?></small><?php endif; ?>
        </td>
        <td><?= e($r['doctor_name']) ?><br><small class="muted"><?= e(fmt_date($r['appt_date'], 'D j M')) ?>, <?= e(fmt_time($r['start_time'])) ?> · est. <?= e(fmt_time(estimated_time($r, (int)$r['number']))) ?></small></td>
        <td><code><?= e($r['ref']) ?></code><br><small class="muted"><?= $r['source'] === 'admin' ? 'Front desk' : 'Online' ?></small></td>
        <td><?= status_badge($r['status']) ?></td>
        <td>
          <form method="post" class="inline-status">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
            <label class="sr-only" for="st-<?= (int)$r['id'] ?>">Status for <?= e($r['patient_name']) ?></label>
            <select id="st-<?= (int)$r['id'] ?>" name="status">
              <?php foreach (STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn--small btn--navy" type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>
