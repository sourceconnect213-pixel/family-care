<?php
require __DIR__ . '/includes/functions.php';

$specId = (int)($_GET['specialty'] ?? 0) ?: null;
$rows = db()->query('SELECT sc.*, d.name AS doctor_name, d.specialty_id, sp.name AS specialty, sp.sort_order
    FROM schedules sc JOIN doctors d ON d.id = sc.doctor_id LEFT JOIN specialties sp ON sp.id = d.specialty_id
    WHERE sc.active = 1 AND d.active = 1 ORDER BY sp.sort_order, d.name, sc.start_time')->fetchAll();

// Group by doctor → weekday
$grid = [];
foreach ($rows as $r) {
    if ($specId && (int)$r['specialty_id'] !== $specId) continue;
    $grid[$r['doctor_id']]['doctor'] = $r;
    $grid[$r['doctor_id']]['days'][(int)$r['weekday']][] = $r;
}
$todayW = (int)date('w');
// Week order starting Monday
$order = [1, 2, 3, 4, 5, 6, 0];

$pageTitle = 'Doctor timetable';
$active = 'timetable';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap">
    <h1>Weekly doctor timetable</h1>
    <p class="lead">Regular channelling sessions. Check the doctor's page for leave and live availability before you come.</p>
    <form class="filter" method="get">
      <label class="sr-only" for="specialty">Specialty</label>
      <select id="specialty" name="specialty" onchange="this.form.submit()">
        <option value="">All specialties</option>
        <?php foreach (specialties() as $s): ?>
          <option value="<?= (int)$s['id'] ?>"<?= $specId === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn--navy" type="submit">Filter</button></noscript>
    </form>
  </div>
</section>
<section class="section">
  <div class="wrap">
    <?php if (!$grid): ?>
      <div class="empty"><p>No sessions for this specialty yet.</p><a class="btn btn--ghost" href="timetable.php">Show all specialties</a></div>
    <?php else: ?>
    <div class="tt-scroll" tabindex="0" role="region" aria-label="Weekly timetable, scrolls sideways">
      <table class="tt">
        <thead>
          <tr>
            <th scope="col">Doctor</th>
            <?php foreach ($order as $w): ?>
              <th scope="col"<?= $w === $todayW ? ' class="is-today"' : '' ?>><?= e(WEEKDAYS_SHORT[$w]) ?><?= $w === $todayW ? '<small>Today</small>' : '' ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grid as $docId => $g): $d = $g['doctor']; ?>
            <tr>
              <th scope="row">
                <a href="doctor.php?id=<?= (int)$docId ?>"><?= e($d['doctor_name']) ?></a>
                <small><?= e($d['specialty'] ?? '') ?></small>
              </th>
              <?php foreach ($order as $w): ?>
                <td<?= $w === $todayW ? ' class="is-today"' : '' ?>>
                  <?php foreach ($g['days'][$w] ?? [] as $s): ?>
                    <span class="tt__slot"><?= e(fmt_time($s['start_time'])) ?><br><small>to <?= e(fmt_time($s['end_time'])) ?></small></span>
                  <?php endforeach; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
