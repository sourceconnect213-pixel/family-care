<?php
require __DIR__ . '/includes/functions.php';

$ref = strtoupper(trim((string)($_REQUEST['ref'] ?? '')));
$phone = normalize_phone((string)($_REQUEST['phone'] ?? ''));
$a = null;
$error = '';

// simple brute-force guard: 20 lookups per 10 minutes per browser session
$_SESSION['lookups'] = array_filter($_SESSION['lookups'] ?? [], fn($t) => $t > time() - 600);

if (is_post()) {
    verify_csrf();
    if (count($_SESSION['lookups']) >= 20) {
        $error = 'Too many attempts. Wait 10 minutes, or call ' . setting('phone') . '.';
    } else {
        $_SESSION['lookups'][] = time();
        $found = $ref ? get_appointment_by_ref($ref) : null;
        if ($found && hash_equals($found['phone'], $phone)) {
            $a = $found;
            if (($_POST['action'] ?? '') === 'cancel') {
                $starts = strtotime($a['appt_date'] . ' ' . $a['start_time']);
                if ($a['status'] !== 'booked') {
                    $error = 'Only bookings with status "Booked" can be cancelled.';
                } elseif ($starts <= time()) {
                    $error = 'This session has already started. Call ' . setting('phone') . ' for help.';
                } else {
                    cancel_appointment($a, 'patient');
                    flash('success', 'Booking ' . $a['ref'] . ' is cancelled.');
                    $a = get_appointment_by_ref($a['ref']);
                }
            }
        } else {
            $error = 'No booking matches that reference and mobile number. Check both and try again.';
        }
    }
}

$pageTitle = 'My booking';
$active = 'check';
require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
  <div class="wrap narrow">
    <h1>Check or cancel a booking</h1>
    <p class="lead">Enter the reference you got when you booked (it starts with FC) and the mobile number you used.</p>
  </div>
</section>
<section class="section">
  <div class="wrap narrow">
    <?= render_flash() ?>
    <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <?php if ($a): ?>
      <?php include __DIR__ . '/includes/appointment_ticket.php'; ?>
      <?php if ($a['status'] === 'booked' && strtotime($a['appt_date'] . ' ' . $a['start_time']) > time()): ?>
        <form method="post" class="actions" onsubmit="return confirm('Cancel this booking? Your number will be released.')">
          <?= csrf_field() ?>
          <input type="hidden" name="ref" value="<?= e($a['ref']) ?>">
          <input type="hidden" name="phone" value="<?= e($a['phone']) ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn btn--outline-red" type="submit">Cancel booking</button>
          <button class="btn btn--ghost" type="button" onclick="window.print()">Print</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <form class="form form--inline" method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label for="ref">Reference number</label>
          <input id="ref" name="ref" value="<?= e($ref) ?>" placeholder="FC7K2M9Q" required maxlength="12" autocapitalize="characters" autocomplete="off">
        </div>
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" name="phone" type="tel" inputmode="tel" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="07X XXX XXXX" required>
        </div>
        <button class="btn btn--navy" type="submit">Find booking</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
