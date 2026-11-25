<?php
require __DIR__ . '/includes/functions.php';

$today = date('Y-m-d');
$boardDate = $today;
$board = sessions_on($today);
// Show tomorrow when today's sessions have all finished
$stillOn = array_values(array_filter($board, fn($r) => strtotime("$today {$r['schedule']['end_time']}") > time()));
if (!$stillOn) {
    $boardDate = date('Y-m-d', strtotime('tomorrow'));
    $board = sessions_on($boardDate);
} else {
    $board = $stillOn;
}
$specs = specialties();
$allDoctors = doctors();
$counts = [];
foreach ($allDoctors as $d) $counts[$d['specialty_id']] = ($counts[$d['specialty_id']] ?? 0) + 1;
$medical = group_by_letter($specs);
$general = group_by_letter(services());
$about = get_page('about');
$features = array_slice(array_values(array_filter(pages(), fn($pg) => $pg['slug'] !== 'about')), 0, 2);
$latest = posts(3);
$heroImage = site_image_url(setting('hero_image'));
$aboutImage = site_image_url(setting('about_image'));

$active = 'home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero<?= $heroImage ? ' hero--photo' : '' ?>"<?= $heroImage ? ' style="--hero-img: url(\'' . e($heroImage) . '\')"' : '' ?>>
  <div class="wrap hero__grid">
    <div class="hero__intro">
      <h1><?= e(setting('hero_title')) ?></h1>
      <p class="lead"><?= e(setting('hero_text')) ?></p>

      <form class="finder" action="doctors.php" method="get">
        <label for="finder-spec">Find a doctor</label>
        <div class="finder__row">
          <select id="finder-spec" name="specialty">
            <option value="">All specialties</option>
            <?php foreach ($specs as $s): if (empty($counts[$s['id']])) continue; ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn--red" type="submit">Search</button>
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
          <?php foreach (array_slice($board, 0, 6) as $r): $s = $r['schedule']; ?>
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
        <?php if (count($board) > 6): ?>
          <a class="board__more" href="timetable.php">See all <?= count($board) ?> sessions</a>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
</section>

<section class="svc" aria-labelledby="svc-title">
  <div class="wrap">
    <h2 id="svc-title" class="sr-only">Our services</h2>
    <div class="svc__tabs" role="tablist">
      <button role="tab" id="tab-med" aria-controls="panel-med" aria-selected="true">Medical &amp; surgical services</button>
      <button role="tab" id="tab-gen" aria-controls="panel-gen" aria-selected="false" tabindex="-1">General services</button>
    </div>
    <div class="svc__panel" id="panel-med" role="tabpanel" aria-labelledby="tab-med">
      <p class="svc__intro">Specialist doctors channel at <?= e(setting('hospital_name')) ?> every week. Choose a specialty to see who is available.</p>
      <div class="az">
        <?php foreach ($medical as $letter => $items): ?>
          <div class="az__group">
            <span class="az__letter" aria-hidden="true"><?= e($letter) ?></span>
            <ul>
              <?php foreach ($items as $s): ?><li><a href="doctors.php?specialty=<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="svc__panel" id="panel-gen" role="tabpanel" aria-labelledby="tab-gen">
      <p class="svc__intro">Tests, treatment and care you can get here, many without an appointment.</p>
      <div class="az">
        <?php foreach ($general as $letter => $items): ?>
          <div class="az__group">
            <span class="az__letter" aria-hidden="true"><?= e($letter) ?></span>
            <ul>
              <?php foreach ($items as $s): ?><li><a href="services.php#service-<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="stats" aria-label="Family Care in numbers">
  <div class="wrap stats__grid">
    <?php foreach ([1, 2, 3, 4] as $n): if (setting("stat{$n}_label") === '') continue; ?>
      <div class="stat"><strong><?= e(stat_value((string)$n)) ?></strong><span><?= e(setting("stat{$n}_label")) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($about): ?>
<section class="section about">
  <div class="wrap about__grid">
    <div class="about__media<?= $aboutImage ? '' : ' about__media--brand' ?>">
      <?php if ($aboutImage): ?>
        <img src="<?= e($aboutImage) ?>" alt="" loading="lazy">
      <?php else: ?>
        <img src="assets/img/logo.png" alt="" class="about__logo">
      <?php endif; ?>
    </div>
    <div class="about__text">
      <h2 class="section__title"><?= e($about['title']) ?></h2>
      <p class="lead"><?= e($about['summary']) ?></p>
      <?php $firstPara = trim(explode("\n\n", (string)$about['body'])[0] ?? ''); ?>
      <?php if ($firstPara): ?><p><?= e($firstPara) ?></p><?php endif; ?>
      <a class="btn btn--navy" href="page.php?slug=about">More about us</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($features): ?>
<section class="section section--tight">
  <div class="wrap features">
    <?php foreach ($features as $f): $img = site_image_url($f['image']); ?>
      <a class="feature<?= $img ? ' feature--photo' : '' ?>" href="page.php?slug=<?= e(urlencode($f['slug'])) ?>"<?= $img ? ' style="--feature-img: url(\'' . e($img) . '\')"' : '' ?>>
        <h2><?= e($f['title']) ?></h2>
        <p><?= e($f['summary']) ?></p>
        <span class="feature__more">Find out more</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="section section--mist steps-section">
  <div class="wrap">
    <h2 class="section__title">How online channelling works</h2>
    <ol class="steps">
      <li><strong>Choose your doctor</strong><span>Browse by specialty and see each doctor's weekly sessions.</span></li>
      <li><strong>Pick a session</strong><span>See open dates for the next <?= (int)setting('booking_days_ahead') ?> days and how many numbers are left.</span></li>
      <li><strong>Get your number</strong><span>You'll get an appointment number and an estimated time. Pay at the counter when you arrive.</span></li>
    </ol>
  </div>
</section>

<?php if ($latest): ?>
<section class="section">
  <div class="wrap">
    <div class="section__head">
      <h2 class="section__title">Health tips &amp; news</h2>
      <a href="news.php">See all</a>
    </div>
    <div class="post-grid">
      <?php foreach ($latest as $p): include __DIR__ . '/includes/post_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section--mist contact" id="contact">
  <div class="wrap contact__grid">
    <div>
      <h2 class="section__title">Find us</h2>
      <p class="contact__address"><?= e(setting('address')) ?></p>
      <p><?= nl2br(e(setting('opening_hours'))) ?></p>
      <p><a class="btn btn--ghost" href="<?= e(setting('map_url')) ?>" target="_blank" rel="noopener">Open in Google Maps</a></p>
    </div>
    <div class="contact__calls">
      <a class="call call--red" href="<?= e(tel(setting('emergency_phone'))) ?>">
        <span>Emergency, 24 hours</span><strong><?= e(setting('emergency_phone')) ?></strong>
      </a>
      <a class="call" href="<?= e(tel(setting('phone'))) ?>">
        <span>Channelling and enquiries</span><strong><?= e(setting('phone')) ?></strong>
      </a>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
