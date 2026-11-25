<?php
require __DIR__ . '/includes/functions.php';

$doc = get_doctor((int)($_GET['id'] ?? 0));
if (!$doc) {
    http_response_code(404);
    $pageTitle = 'Doctor not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section"><div class="wrap empty"><h1>Doctor not found</h1><p>This doctor may no longer be channelling here.</p><a class="btn btn--navy" href="doctors.php">See all doctors</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$schedules = doctor_schedules((int)$doc['id']);
$upcoming = upcoming_sessions((int)$doc['id']);

$pageTitle = $doc['name'];
$active = 'doctors';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head page-head--doctor">
  <div class="wrap doctor-head">
    <?= doctor_avatar($doc, 'shield--lg') ?>
    <div>
      <p class="crumbs"><a href="doctors.php">Doctors</a> / <a href="doctors.php?specialty=<?= (int)$doc['specialty_id'] ?>"><?= e($doc['specialty'] ?? 'Doctor') ?></a></p>
      <h1><?= e($doc['name']) ?></h1>
      <p class="doctor-head__spec"><?= e($doc['specialty'] ?? '') ?><?= $doc['qualifications'] ? ', ' . e($doc['qualifications']) : '' ?></p>
      <p class="doctor-head__fee">Doctor's fee <?= e(money($doc['fee'])) ?> <span>+ hospital charge <?= e(money(setting('hospital_fee'))) ?></span></p>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap doctor-layout">
    <div>
      <h2 class="section__title">Book a session</h2>
      <?php if (!$upcoming): ?>
        <div class="empty"><p>No open sessions in the next <?= (int)setting('booking_days_ahead') ?> days. Call <?= e(setting('phone')) ?> to ask about appointments.</p></div>
      <?php else: ?>
        <div class="dates">
          <?php foreach ($upcoming as $date => $sessions): ?>
            <div class="date-row">
              <div class="date-row__date">
                <strong><?= e(fmt_date($date, 'j')) ?></strong>
                <span><?= e(fmt_date($date, 'M')) ?></span>
                <em><?= $date === date('Y-m-d') ? 'Today' : e(fmt_date($date, 'l')) ?></em>
              </div>
              <div class="date-row__sessions">
                <?php foreach ($sessions as $r): $s = $r['schedule']; ?>
                  <?php if ($r['state'] === 'open'): ?>
                    <a class="slot slot--open" href="book.php?schedule=<?= (int)$s['id'] ?>&amp;date=<?= e($date) ?>">
                      <span class="slot__time"><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?></span>
                      <span class="slot__meta"><?= (int)$s['max_patients'] - (int)$r['booked'] ?> of <?= (int)$s['max_patients'] ?> numbers left</span>
                      <span class="slot__cta">Book No. <?= (int)$r['next'] ?></span>
                    </a>
                  <?php else: ?>
                    <div class="slot slot--<?= e($r['state']) ?>">
                      <span class="slot__time"><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?></span>
                      <span class="slot__meta"><?= e(['full' => 'Fully booked', 'leave' => $r['leave'] ?? 'Doctor not available', 'closed' => 'Booking not open'][$r['state']] ?? '') ?></span>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="doctor-side">
      <h2 class="section__title">Weekly timetable</h2>
      <table class="mini-table">
        <tbody>
        <?php if (!$schedules): ?><tr><td>No regular sessions.</td></tr><?php endif; ?>
        <?php foreach ($schedules as $s): ?>
          <tr>
            <th scope="row"><?= e(WEEKDAYS[(int)$s['weekday']]) ?></th>
            <td><?= e(fmt_time($s['start_time'])) ?> – <?= e(fmt_time($s['end_time'])) ?><?= $s['room'] ? '<br><small>' . e($s['room']) . '</small>' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($doc['bio']): ?>
        <h2 class="section__title">About</h2>
        <p><?= nl2br(e($doc['bio'])) ?></p>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
