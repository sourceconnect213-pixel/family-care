<?php
require __DIR__ . '/includes/functions.php';

$scheduleId = (int)($_REQUEST['schedule'] ?? 0);
$date = (string)($_REQUEST['date'] ?? '');
$sch = $scheduleId ? get_schedule($scheduleId) : null;
$doc = $sch ? get_doctor((int)$sch['doctor_id']) : null;

if (!$sch || !$doc || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    flash('error', 'Choose a doctor and session to book.');
    redirect('doctors.php');
}

$state = session_state($sch, $date);
$errors = [];
$p = ['name' => '', 'phone' => '', 'nic' => '', 'age' => '', 'gender' => '', 'notes' => ''];
$formError = '';

if (is_post()) {
    verify_csrf();
    if (!empty($_POST['website'])) redirect('index.php'); // spam trap
    [$p, $errors] = validate_patient($_POST);
    if (empty($_POST['agree'])) $errors['agree'] = 'Confirm the details are correct to continue.';
    if (!$errors) {
        $res = book_appointment($scheduleId, $date, $p, 'online');
        if ($res['ok']) {
            $_SESSION['my_refs'][] = $res['appointment']['ref'];
            redirect('booking.php?ref=' . urlencode($res['appointment']['ref']) . '&new=1');
        }
        $formError = $res['error'];
        $state = session_state($sch, $date);
    }
}

$hospitalFee = (float)setting('hospital_fee');
$pageTitle = 'Book ' . $doc['name'];
$active = 'doctors';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap">
    <p class="crumbs"><a href="doctors.php">Doctors</a> / <a href="doctor.php?id=<?= (int)$doc['id'] ?>"><?= e($doc['name']) ?></a></p>
    <h1>Book your appointment</h1>
  </div>
</section>
<section class="section">
  <div class="wrap book-layout">
    <aside class="ticket" aria-label="Session details">
      <div class="ticket__doc">
        <?= doctor_avatar($doc) ?>
        <div>
          <strong><?= e($doc['name']) ?></strong>
          <span><?= e($doc['specialty'] ?? '') ?></span>
        </div>
      </div>
      <dl class="ticket__facts">
        <div><dt>Date</dt><dd><?= e(fmt_date($date)) ?></dd></div>
        <div><dt>Session</dt><dd><?= e(fmt_time($sch['start_time'])) ?> – <?= e(fmt_time($sch['end_time'])) ?><?= $sch['room'] ? ', ' . e($sch['room']) : '' ?></dd></div>
        <?php if ($state['state'] === 'open'): ?>
        <div class="ticket__number"><dt>Your number will be</dt><dd>No. <?= (int)$state['next'] ?><small>Estimated time <?= e(fmt_time(estimated_time($sch, (int)$state['next']))) ?></small></dd></div>
        <?php endif; ?>
      </dl>
      <dl class="ticket__fees">
        <div><dt>Doctor's fee</dt><dd><?= e(money($doc['fee'])) ?></dd></div>
        <div><dt>Hospital charge</dt><dd><?= e(money($hospitalFee)) ?></dd></div>
        <div class="ticket__total"><dt>Pay at the counter</dt><dd><?= e(money($doc['fee'] + $hospitalFee)) ?></dd></div>
      </dl>
    </aside>

    <div>
      <?= render_flash() ?>
      <?php if ($formError): ?><div class="alert alert--error" role="alert"><?= e($formError) ?></div><?php endif; ?>

      <?php if ($state['state'] !== 'open'): ?>
        <div class="alert alert--error" role="alert">
          <?= e(['full' => 'This session is fully booked.', 'leave' => 'The doctor is not available for this session.', 'closed' => 'Online booking for this session has closed.', 'invalid' => 'The doctor does not hold this session on that date.'][$state['state']] ?? 'This session can\'t be booked.') ?>
        </div>
        <a class="btn btn--navy" href="doctor.php?id=<?= (int)$doc['id'] ?>">Pick another session</a>
      <?php else: ?>
      <form class="form" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="schedule" value="<?= (int)$scheduleId ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <h2 class="form__title">Patient details</h2>
        <?php $field = function ($name, $label, $attrs = '', $hint = '') use ($p, $errors) {
            $key = $name === 'patient_name' ? 'name' : $name; ?>
          <div class="field<?= isset($errors[$name]) ? ' field--error' : '' ?>">
            <label for="f-<?= e($name) ?>"><?= e($label) ?></label>
            <?php if ($hint): ?><p class="field__hint" id="h-<?= e($name) ?>"><?= e($hint) ?></p><?php endif; ?>
            <input id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($p[$key] ?? '') ?>" <?= $attrs ?><?= $hint ? ' aria-describedby="h-' . e($name) . '"' : '' ?>>
            <?php if (isset($errors[$name])): ?><p class="field__error"><?= e($errors[$name]) ?></p><?php endif; ?>
          </div>
        <?php }; ?>
        <?php $field('patient_name', 'Patient\'s full name', 'autocomplete="name" required maxlength="120"'); ?>
        <?php $field('phone', 'Mobile number', 'type="tel" inputmode="tel" autocomplete="tel" required placeholder="07X XXX XXXX"', 'You\'ll need this number to check or cancel the booking.'); ?>
        <div class="field-row">
          <?php $field('nic', 'NIC number (optional)', 'maxlength="12" autocapitalize="characters"'); ?>
          <?php $field('age', 'Age (optional)', 'type="number" min="0" max="120" inputmode="numeric"'); ?>
        </div>
        <fieldset class="field">
          <legend>Gender (optional)</legend>
          <div class="radios">
            <?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $v => $l): ?>
              <label><input type="radio" name="gender" value="<?= e($v) ?>"<?= $p['gender'] === $v ? ' checked' : '' ?>> <?= e($l) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="field">
          <label for="f-notes">Note for the doctor (optional)</label>
          <textarea id="f-notes" name="notes" rows="3" maxlength="500"><?= e($p['notes']) ?></textarea>
        </div>
        <div class="field<?= isset($errors['agree']) ? ' field--error' : '' ?>">
          <label class="check"><input type="checkbox" name="agree" value="1"<?= !empty($_POST['agree']) ? ' checked' : '' ?>> These details are correct. I'll arrive before my estimated time and pay at the counter.</label>
          <?php if (isset($errors['agree'])): ?><p class="field__error"><?= e($errors['agree']) ?></p><?php endif; ?>
        </div>
        <button class="btn btn--red btn--large" type="submit">Confirm booking</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
