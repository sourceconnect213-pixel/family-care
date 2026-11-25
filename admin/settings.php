<?php
require __DIR__ . '/_layout.php';
require_admin();

$fields = [
    'hospital_name'      => ['Hospital name', 'text'],
    'hospital_town'      => ['Town', 'text'],
    'tagline'            => ['Short welcome line on the home page', 'text'],
    'address'            => ['Address', 'text'],
    'phone'              => ['Channelling phone', 'text'],
    'emergency_phone'    => ['Emergency phone', 'text'],
    'email'              => ['Email', 'email'],
    'map_url'            => ['Google Maps link', 'url'],
    'opening_hours'      => ['Opening hours (one per line)', 'textarea'],
    'hospital_fee'       => ['Hospital charge per appointment (Rs.)', 'number'],
    'booking_days_ahead' => ['How many days ahead patients can book', 'number'],
    'close_before_min'   => ['Close online booking this many minutes before a session starts', 'number'],
];

if (is_post()) {
    verify_csrf();
    $st = db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    foreach ($fields as $key => [$label, $type]) {
        $val = trim((string)($_POST[$key] ?? ''));
        if ($type === 'number') $val = (string)max(0, (int)$val);
        if ($key === 'booking_days_ahead') $val = (string)min(180, max(1, (int)$val));
        if ($type === 'url' && $val !== '' && !preg_match('#^https?://#i', $val)) $val = '';
        $st->execute([$val, $key]);
    }
    flash('success', 'Settings saved.');
    redirect('settings.php');
}

admin_header('Hospital settings', 'settings');
?>
<section class="panel">
  <form method="post" class="grid-form">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $type]): ?>
      <label class="<?= $type === 'textarea' || $key === 'tagline' || $key === 'address' ? 'span-4' : 'span-2' ?>"><?= e($label) ?>
        <?php if ($type === 'textarea'): ?>
          <textarea name="<?= e($key) ?>" rows="3"><?= e(setting($key)) ?></textarea>
        <?php else: ?>
          <input type="<?= e($type) ?>" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>"<?= $type === 'number' ? ' min="0"' : '' ?>>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <div class="span-4"><button class="btn btn--red" type="submit">Save settings</button></div>
  </form>
</section>
<?php admin_footer(); ?>
