<?php
require_once __DIR__ . '/../includes/functions.php';

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    $nav = [
        'dashboard'    => ['index.php', 'Dashboard'],
        'appointments' => ['appointments.php', 'Appointments'],
        'doctors'      => ['doctors.php', 'Doctors & sessions'],
        'leaves'       => ['leaves.php', 'Doctor leave'],
        'specialties'  => ['specialties.php', 'Specialties'],
        'settings'     => ['settings.php', 'Hospital settings'],
        'sms'          => ['sms.php', 'SMS messages'],
        'account'      => ['account.php', 'Staff accounts'],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Admin | <?= e(setting('hospital_name')) ?></title>
<link rel="icon" href="../assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css?v=2">
</head>
<body class="admin">
<?php if ($admin): ?>
<aside class="side">
  <a class="side__brand" href="index.php">
    <img src="../assets/img/logo.png" alt="" width="34" height="43">
    <span><strong><?= e(setting('hospital_name')) ?></strong><small>Admin panel</small></span>
  </a>
  <nav aria-label="Admin">
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= e($href) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <span>Signed in as <strong><?= e($admin['name']) ?></strong></span>
    <a href="../index.php" target="_blank" rel="noopener">View website</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="linkish">Sign out</button></form>
  </div>
</aside>
<?php endif; ?>
<main class="main<?= $admin ? '' : ' main--solo' ?>">
  <?php if ($admin): ?><h1 class="main__title"><?= e($title) ?></h1><?php endif; ?>
  <?= render_flash() ?>
<?php
}

function admin_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}

function status_badge(string $status): string
{
    return '<span class="badge badge--' . e($status) . '">' . e(STATUSES[$status] ?? $status) . '</span>';
}
