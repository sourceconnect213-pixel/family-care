<?php
/** @var array $d doctor row */
$cardSchedules = doctor_schedules((int)$d['id']);
$days = [];
foreach ($cardSchedules as $cs) $days[(int)$cs['weekday']] = WEEKDAYS_SHORT[(int)$cs['weekday']];
ksort($days);
?>
<article class="doc-card">
  <?= doctor_avatar($d) ?>
  <div class="doc-card__body">
    <h3><a href="<?= e(base_url('doctor.php?id=' . (int)$d['id'])) ?>"><?= e($d['name']) ?></a></h3>
    <p class="doc-card__spec"><?= e($d['specialty'] ?? 'Doctor') ?></p>
    <?php if ($d['qualifications']): ?><p class="doc-card__qual"><?= e($d['qualifications']) ?></p><?php endif; ?>
    <p class="doc-card__days" aria-label="Session days">
      <?php foreach (WEEKDAYS_SHORT as $i => $wd): ?>
        <span class="<?= isset($days[$i]) ? 'on' : '' ?>" title="<?= e(WEEKDAYS[$i]) ?><?= isset($days[$i]) ? '' : ': no session' ?>"><?= e(mb_substr($wd, 0, 2)) ?></span>
      <?php endforeach; ?>
    </p>
  </div>
  <a class="btn btn--navy btn--small" href="<?= e(base_url('doctor.php?id=' . (int)$d['id'])) ?>">See sessions</a>
</article>
