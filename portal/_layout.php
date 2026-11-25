<?php
require_once __DIR__ . '/../includes/functions.php';

function portal_header(string $title, string $active = ''): void
{
    $doc = current_doctor();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> | Doctor portal | <?= e(setting('hospital_name')) ?></title>
<link rel="icon" href="../assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css?v=2">
</head>
<body class="admin portal">
<?php if ($doc): ?>
<aside class="side">
  <a class="side__brand" href="index.php">
    <img src="../assets/img/logo.png" alt="" width="34" height="43">
    <span><strong><?= e(setting('hospital_name')) ?></strong><small>Doctor portal</small></span>
  </a>
  <nav aria-label="Doctor portal">
    <a href="index.php"<?= $active === 'patients' ? ' aria-current="page"' : '' ?>>My patients</a>
    <a href="timetable.php"<?= $active === 'timetable' ? ' aria-current="page"' : '' ?>>My timetable</a>
    <a href="password.php"<?= $active === 'password' ? ' aria-current="page"' : '' ?>>Change password</a>
  </nav>
  <div class="side__foot">
    <span>Signed in as <strong><?= e($doc['name']) ?></strong></span>
    <a href="../doctor.php?id=<?= (int)$doc['id'] ?>" target="_blank" rel="noopener">My public profile</a>
    <form method="post" action="logout.php"><?= csrf_field() ?><button type="submit" class="linkish">Sign out</button></form>
  </div>
</aside>
<?php endif; ?>
<main class="main<?= $doc ? '' : ' main--solo' ?>">
  <?php if ($doc): ?><h1 class="main__title"><?= e($title) ?></h1><?php endif; ?>
  <?= render_flash() ?>
<?php
}

function portal_footer(): void
{
    echo "</main>\n</body>\n</html>\n";
}
