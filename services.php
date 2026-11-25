<?php
require __DIR__ . '/includes/functions.php';

$specs = specialties();
$counts = [];
foreach (doctors() as $d) $counts[$d['specialty_id']] = ($counts[$d['specialty_id']] ?? 0) + 1;
$general = services();

$pageTitle = 'Services';
$active = 'services';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap">
    <h1>Our services</h1>
    <p class="lead">Specialist channelling, tests, emergency care and more, under one roof in <?= e(setting('hospital_town')) ?>.</p>
  </div>
</section>
<section class="section">
  <div class="wrap">
    <h2 class="section__title">Medical &amp; surgical services</h2>
    <ul class="spec-list">
      <?php foreach ($specs as $s): $n = (int)($counts[$s['id']] ?? 0); ?>
        <li>
          <a href="doctors.php?specialty=<?= (int)$s['id'] ?>">
            <strong><?= e($s['name']) ?></strong>
            <span><?= e($s['description']) ?></span>
            <em><?= $n ? $n . ' ' . ($n === 1 ? 'doctor' : 'doctors') . ' channelling' : 'Call to ask' ?></em>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<section class="section section--mist">
  <div class="wrap">
    <h2 class="section__title">General services</h2>
    <ul class="spec-list spec-list--plain">
      <?php foreach ($general as $s): ?>
        <li id="service-<?= (int)$s['id'] ?>"><div><strong><?= e($s['name']) ?></strong><span><?= e($s['description']) ?></span></div></li>
      <?php endforeach; ?>
    </ul>
    <p class="services-call">Questions about a service? Call <a href="<?= e(tel(setting('phone'))) ?>"><?= e(setting('phone')) ?></a>.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
