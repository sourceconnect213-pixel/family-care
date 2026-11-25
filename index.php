<?php
require __DIR__ . '/includes/functions.php';

$today = date('Y-m-d');
$boardDate = $today;
$board = sessions_on($today);
// Show tomorrow when today's sessions have all finished
$stillOn = array_filter($board, fn($r) => strtotime("$today {$r['schedule']['end_time']}") > time());
if (!$stillOn) {
    $boardDate = date('Y-m-d', strtotime('tomorrow'));
    $board = sessions_on($boardDate);
} else {
    $board = array_values($stillOn);
}
$specs = specialties();
$allDoctors = doctors();
$featured = array_slice($allDoctors, 0, 6);
$counts = [];
foreach ($allDoctors as $d) $counts[$d['specialty_id']] = ($counts[$d['specialty_id']] ?? 0) + 1;

$active = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="wrap hero__grid">
    <div class="hero__intro">
      <h1>Channel a doctor at <?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?></h1>
      <p class="lead"><?= e(setting('tagline')) ?> Pick a doctor, choose a session and get your appointment number before you leave home.</p>

      <form class="finder" action="doctors.php" method="get">
        <label for="finder-spec">What do you need?</label>
        <div class="finder__row">
          <select id="finder-spec" name="specialty">
            <option value="">All specialties</option>
            <?php foreach ($specs as $s): if (empty($counts[$s['id']])) continue; ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn--red" type="submit">Find doctors</button>
        </div>
        <p class="finder__alt">Already booked? <a href="check.php">Check your appointment</a></p>
      </form>
    </div>

    <aside class="board" aria-labelledby="board-title">
      <div class="board__head">
        <h2 id="board-title"><?= $boardDate === $today ? 'Today\'s channelling' : 'Tomorrow\'s channelling' ?></h2>
        <time datetime="<?= e($boardDate) ?>"><?= e(fmt_date($boardDate, 'D j M')) ?></time>
      </div>
      <?php if (!$board): ?>
        <p class="board__empty">No doctor sessions scheduled. See the <a href="timetable.php">weekly timetable</a>.</p>
      <?php else: ?>
        <ul class="board__list">
          <?php foreach (array_slice($board, 0, 7) as $r): $s = $r['schedule']; ?>
            <li class="board__row">
              <span class="board__time"><?= e(fmt_time($s['start_time'])) ?></span>
              <span class="board__doc">
                <a href="doctor.php?id=<?= (int)$s['doctor_id'] ?>"><?= e($s['doctor_name']) ?></a>
                <small><?= e($s['specialty'] ?? '') ?></small>
              </span>
              <?php if ($r['state'] === 'open'): ?>
                <a class="board__status board__status--open" href="book.php?schedule=<?= (int)$s['id'] ?>&amp;date=<?= e($boardDate) ?>">No. <?= (int)$r['next'] ?> free</a>
              <?php elseif ($r['state'] === 'full'): ?>
                <span class="board__status board__status--full">Full</span>
              <?php elseif ($r['state'] === 'leave'): ?>
                <span class="board__status board__status--off">Not in</span>
              <?php else: ?>
                <span class="board__status board__status--closed">In session</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($board) > 7): ?>
          <a class="board__more" href="timetable.php">See all <?= count($board) ?> sessions</a>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
</section>

<section class="section steps-section">
  <div class="wrap">
    <h2 class="section__title">How online channelling works</h2>
    <ol class="steps">
      <li><strong>Choose your doctor</strong><span>Browse by specialty and see each doctor's weekly sessions.</span></li>
      <li><strong>Pick a session</strong><span>See open dates for the next <?= (int)setting('booking_days_ahead') ?> days and how many numbers are left.</span></li>
      <li><strong>Get your number</strong><span>You'll get an appointment number and an estimated time. Pay at the counter when you arrive.</span></li>
    </ol>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section__head">
      <h2 class="section__title">Our doctors</h2>
      <a href="doctors.php">See all doctors</a>
    </div>
    <div class="doc-grid">
      <?php foreach ($featured as $d): include __DIR__ . '/includes/doctor_card.php'; endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--mist">
  <div class="wrap">
    <h2 class="section__title">Specialties</h2>
    <ul class="spec-list">
      <?php foreach ($specs as $s): ?>
        <li>
          <a href="doctors.php?specialty=<?= (int)$s['id'] ?>">
            <strong><?= e($s['name']) ?></strong>
            <span><?= e($s['description']) ?></span>
            <em><?= (int)($counts[$s['id']] ?? 0) ?> <?= ($counts[$s['id']] ?? 0) === 1 ? 'doctor' : 'doctors' ?></em>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="section contact" id="contact">
  <div class="wrap contact__grid">
    <div>
      <h2 class="section__title">Find us</h2>
      <p class="contact__address"><?= e(setting('address')) ?></p>
      <p><?= nl2br(e(setting('opening_hours'))) ?></p>
      <p><a class="btn btn--ghost" href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener">Open in Google Maps</a></p>
    </div>
    <div class="contact__calls">
      <a class="call call--red" href="tel:<?= e(preg_replace('/\s+/', '', setting('emergency_phone'))) ?>">
        <span>Emergency, 24 hours</span><strong><?= e(setting('emergency_phone')) ?></strong>
      </a>
      <a class="call" href="tel:<?= e(preg_replace('/\s+/', '', setting('phone'))) ?>">
        <span>Channelling and enquiries</span><strong><?= e(setting('phone')) ?></strong>
      </a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
