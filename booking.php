<?php
require __DIR__ . '/includes/functions.php';

$ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
if (!in_array($ref, $_SESSION['my_refs'] ?? [], true)) {
    redirect('check.php?ref=' . urlencode($ref));
}
$a = get_appointment_by_ref($ref);
if (!$a) redirect('check.php');

$pageTitle = 'Booking confirmed';
require __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="wrap narrow">
    <?php if (!empty($_GET['new'])): ?>
      <h1 class="confirm-title">You're booked</h1>
      <p class="lead">Save your reference number. Show it, or tell it, at the channelling counter.</p>
    <?php else: ?>
      <h1 class="confirm-title">Your booking</h1>
    <?php endif; ?>
    <?php include __DIR__ . '/includes/appointment_ticket.php'; ?>
    <ul class="notes">
      <li>Come 15 minutes before your estimated time. Numbers are called in order, so the time can move.</li>
      <li>Bring previous prescriptions and reports.</li>
      <li>To cancel, use <a href="check.php?ref=<?= e($a['ref']) ?>">My booking</a> with your reference and mobile number, or call <?= e(setting('phone')) ?>.</li>
    </ul>
    <p class="actions">
      <button class="btn btn--navy" type="button" onclick="window.print()">Print or save as PDF</button>
      <a class="btn btn--ghost" href="index.php">Back to home</a>
    </p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
