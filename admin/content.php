<?php
require __DIR__ . '/_layout.php';
require_admin();

$text = [
    'hero_title' => ['Big headline', 'span-4'],
    'hero_text'  => ['Line under the headline', 'span-4'],
    'lab_phone'  => ['Laboratory phone (shown in the footer; leave empty to show email)', 'span-2'],
    'whatsapp'   => ['WhatsApp number (adds a WhatsApp button; leave empty to hide)', 'span-2'],
];

if (is_post()) {
    verify_csrf();
    $st = db()->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
    foreach ($text as $k => $_) $st->execute([trim(mb_substr((string)($_POST[$k] ?? ''), 0, 300)), $k]);
    foreach ([1, 2, 3, 4] as $n) {
        $st->execute([trim(mb_substr((string)($_POST["stat{$n}_value"] ?? ''), 0, 20)), "stat{$n}_value"]);
        $st->execute([trim(mb_substr((string)($_POST["stat{$n}_label"] ?? ''), 0, 60)), "stat{$n}_label"]);
    }
    $errors = [];
    foreach (['hero_image', 'about_image'] as $k) {
        $current = setting($k) ?: null;
        if (!empty($_POST["remove_$k"])) { delete_site_image($current); $current = null; }
        [$name, $err] = save_site_image($_FILES[$k] ?? [], $current);
        if ($err) $errors[] = $err;
        $st->execute([$name ?? '', $k]);
    }
    $errors ? flash('error', implode(' ', $errors)) : flash('success', 'Home page saved.');
    redirect('content.php');
}

admin_header('Website: home page', 'content');
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <section class="panel">
    <h2>Top of the home page</h2>
    <div class="grid-form">
      <?php foreach ($text as $k => [$label, $span]): ?>
        <label class="<?= e($span) ?>"><?= e($label) ?> <input name="<?= e($k) ?>" value="<?= e(setting($k)) ?>"></label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <h2>Photos</h2>
    <p class="muted">Use your own photos of the hospital, staff or patients (with their permission). Wide photos work best: at least 1600 pixels across, under 5 MB.</p>
    <div class="grid-form">
      <?php foreach (['hero_image' => 'Big photo behind the headline', 'about_image' => 'Photo beside "About us"'] as $k => $label): $url = site_image_url(setting($k)); ?>
        <div class="span-2 photo-field">
          <?php if ($url): ?><img src="<?= e($url) ?>" alt="" class="thumb"><?php endif; ?>
          <label><?= e($label) ?> <input type="file" name="<?= e($k) ?>" accept="image/jpeg,image/png,image/webp"></label>
          <?php if ($url): ?><label class="check"><input type="checkbox" name="remove_<?= e($k) ?>" value="1"> Remove photo</label><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel">
    <h2>Numbers strip</h2>
    <p class="muted">Write <code>{doctors}</code> or <code>{specialties}</code> as the number to count them automatically. Leave a label empty to hide that number.</p>
    <div class="grid-form">
      <?php foreach ([1, 2, 3, 4] as $n): ?>
        <label>Number <?= $n ?> <input name="stat<?= $n ?>_value" value="<?= e(setting("stat{$n}_value")) ?>" maxlength="20"></label>
        <label>Label <input name="stat<?= $n ?>_label" value="<?= e(setting("stat{$n}_label")) ?>" maxlength="60"></label>
      <?php endforeach; ?>
    </div>
  </section>
  <p><button class="btn btn--red" type="submit">Save home page</button> <a class="btn btn--ghost" href="../index.php" target="_blank" rel="noopener">View home page</a></p>
</form>
<?php admin_footer(); ?>
