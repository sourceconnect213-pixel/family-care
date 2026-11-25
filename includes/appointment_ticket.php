<?php
/** @var array $a appointment row from get_appointment_by_ref() */
$est = estimated_time(['start_time' => $a['start_time'], 'minutes_per_patient' => $a['minutes_per_patient']], (int)$a['number']);
?>
<div class="pass pass--<?= e($a['status']) ?>">
  <div class="pass__number">
    <span>Appointment number</span>
    <strong><?= (int)$a['number'] ?></strong>
    <em>Status: <?= e(STATUSES[$a['status']] ?? $a['status']) ?></em>
  </div>
  <dl class="pass__facts">
    <div><dt>Reference</dt><dd class="pass__ref"><?= e($a['ref']) ?></dd></div>
    <div><dt>Doctor</dt><dd><?= e($a['doctor_name']) ?><small><?= e($a['specialty'] ?? '') ?></small></dd></div>
    <div><dt>Date</dt><dd><?= e(fmt_date($a['appt_date'])) ?></dd></div>
    <div><dt>Estimated time</dt><dd><?= e(fmt_time($est)) ?><small>Session <?= e(fmt_time($a['start_time'])) ?> – <?= e(fmt_time($a['end_time'])) ?><?= $a['room'] ? ', ' . e($a['room']) : '' ?></small></dd></div>
    <div><dt>Patient</dt><dd><?= e($a['patient_name']) ?><small><?= e($a['phone']) ?></small></dd></div>
    <div><dt>Pay at the counter</dt><dd><?= e(money((float)$a['fee'] + (float)setting('hospital_fee'))) ?></dd></div>
  </dl>
</div>
