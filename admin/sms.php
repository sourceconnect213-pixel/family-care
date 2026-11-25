<?php
require __DIR__ . '/_layout.php';
require_admin();
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $provider = array_key_exists($_POST['sms_provider'] ?? '', SMS_PROVIDERS) ? $_POST['sms_provider'] : 'off';
        $official = trim((string)($_POST['official_number'] ?? ''));
        if ($official !== '' && !sms_number($official)) {
            flash('error', 'Official number must be a 10-digit Sri Lankan number, for example 0771234567.');
            redirect('sms.php');
        }
        $st = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
        $st->execute([$provider, 'sms_provider']);
        $st->execute([trim((string)($_POST['sms_user_id'] ?? '')), 'sms_user_id']);
        $st->execute([trim((string)($_POST['sms_sender_id'] ?? '')), 'sms_sender_id']);
        $st->execute([$official !== '' ? normalize_phone($official) : '', 'official_number']);
        $st->execute([empty($_POST['sms_to_patient']) ? '0' : '1', 'sms_to_patient']);
        $st->execute([empty($_POST['sms_to_official']) ? '0' : '1', 'sms_to_official']);
        // The API key is never shown again; an empty box keeps the saved key
        $key = trim((string)($_POST['sms_api_key'] ?? ''));
        if ($key !== '') $st->execute([$key, 'sms_api_key']);
        flash('success', 'SMS settings saved.');
    } elseif ($action === 'test') {
        $to = setting('official_number');
        if ($to === '') flash('error', 'Save the official number first.');
        else {
            $res = send_sms($to, 'Test message from the ' . sms_signature() . ' website. SMS is working.', 'test');
            $res['ok'] ? flash('success', 'Test SMS sent to ' . $to . '.') : flash('error', 'Test SMS failed: ' . $res['response']);
        }
    }
    redirect('sms.php');
}

$log = $pdo->query('SELECT * FROM sms_log ORDER BY id DESC LIMIT 100')->fetchAll();
$kinds = ['patient_booked' => 'Patient: booked', 'patient_cancelled' => 'Patient: cancelled', 'official_booked' => 'Official: new channelling', 'official_cancelled' => 'Official: cancelled', 'test' => 'Test'];
$hasKey = setting('sms_api_key') !== '';

admin_header('SMS messages', 'sms');
?>
<section class="panel">
  <h2>Who gets a text message</h2>
  <ul class="muted">
    <li><strong>The patient</strong> gets a confirmation with their number, estimated time and reference, and a message if the booking is cancelled.</li>
    <li><strong>The hospital's official number</strong> gets a message each time a doctor is channelled or a booking is cancelled. Doctors' personal numbers are never used: doctors see their bookings in the doctor portal.</li>
    <li>Every message is sent under the hospital's registered sender ID, so patients see it come from the hospital.</li>
  </ul>
  <form method="post" class="grid-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <label>SMS provider
      <select name="sms_provider">
        <?php foreach (SMS_PROVIDERS as $k => $l): ?><option value="<?= e($k) ?>"<?= setting('sms_provider') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Sender ID <span class="secret-note">(approved by the provider)</span><input name="sms_sender_id" value="<?= e(setting('sms_sender_id')) ?>" maxlength="11" placeholder="FamilyCare"></label>
    <label>User ID <span class="secret-note">(Notify.lk only)</span><input name="sms_user_id" value="<?= e(setting('sms_user_id')) ?>" autocomplete="off"></label>
    <label>API key / token <span class="secret-note"><?= $hasKey ? '(saved, leave empty to keep)' : '' ?></span><input type="password" name="sms_api_key" autocomplete="off" placeholder="<?= $hasKey ? '••••••••' : '' ?>"></label>
    <label class="span-2">Hospital's official number <span class="secret-note">(gets the channelling alerts)</span><input name="official_number" type="tel" value="<?= e(setting('official_number')) ?>" placeholder="0771234567"></label>
    <label class="check"><input type="checkbox" name="sms_to_patient" value="1"<?= setting('sms_to_patient') === '1' ? ' checked' : '' ?>> Text the patient</label>
    <label class="check"><input type="checkbox" name="sms_to_official" value="1"<?= setting('sms_to_official') === '1' ? ' checked' : '' ?>> Text the official number</label>
    <div class="span-4 row-gap"><button class="btn btn--red" type="submit">Save SMS settings</button></div>
  </form>
  <form method="post" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="test">
    <button class="btn btn--ghost" type="submit"<?= setting('sms_provider') === 'off' ? ' disabled' : '' ?>>Send a test SMS to the official number</button>
  </form>
</section>

<section class="panel">
  <h2>Sent messages</h2>
  <?php if (!$log): ?>
    <p class="muted">No messages yet. Messages appear here once SMS is switched on and patients book.</p>
  <?php else: ?>
  <div class="table-scroll">
  <table class="table">
    <thead><tr><th>Time</th><th>To</th><th>Type</th><th>Message</th><th>Result</th></tr></thead>
    <tbody>
    <?php foreach ($log as $m): ?>
      <tr>
        <td class="nowrap"><?= e(fmt_date($m['created_at'], 'j M, g.i a')) ?></td>
        <td><?= e($m['recipient']) ?></td>
        <td><?= e($kinds[$m['kind']] ?? $m['kind']) ?></td>
        <td><small><?= e($m['message']) ?></small></td>
        <td><?= $m['status'] === 'sent' ? '<span class="badge badge--completed">Sent</span>' : '<span class="badge badge--cancelled" title="' . e($m['response']) . '">Failed</span><br><small class="muted">' . e(mb_substr((string)$m['response'], 0, 80)) . '</small>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
<?php admin_footer(); ?>
