<?php
require __DIR__ . '/includes/functions.php';

$specId = (int)($_GET['specialty'] ?? 0) ?: null;
$q = trim((string)($_GET['q'] ?? ''));
$list = doctors($specId, $q);
$specs = specialties();

$pageTitle = 'Doctors';
$active = 'doctors';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap">
    <h1>Find a doctor</h1>
    <p class="lead">Choose a doctor to see their sessions and book an appointment number.</p>
    <form class="filter" method="get">
      <label class="sr-only" for="q">Doctor name</label>
      <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by doctor name">
      <label class="sr-only" for="specialty">Specialty</label>
      <select id="specialty" name="specialty">
        <option value="">All specialties</option>
        <?php foreach ($specs as $s): ?>
          <option value="<?= (int)$s['id'] ?>"<?= $specId === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--navy" type="submit">Search</button>
    </form>
  </div>
</section>
<section class="section">
  <div class="wrap">
    <?php if (!$list): ?>
      <div class="empty">
        <p>No doctors match that search.</p>
        <a class="btn btn--ghost" href="doctors.php">Show all doctors</a>
      </div>
    <?php else: ?>
      <p class="result-count"><?= count($list) ?> <?= count($list) === 1 ? 'doctor' : 'doctors' ?></p>
      <div class="doc-grid">
        <?php foreach ($list as $d): include __DIR__ . '/includes/doctor_card.php'; endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
