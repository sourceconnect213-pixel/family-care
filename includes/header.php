<?php
/** @var string $pageTitle @var string $active */
$pageTitle = $pageTitle ?? '';
$active = $active ?? '';
$hospital = setting('hospital_name') . ', ' . setting('hospital_town');
$nav = [
    'home'      => ['index.php', 'Home'],
    'doctors'   => ['doctors.php', 'Find a doctor'],
    'timetable' => ['timetable.php', 'Timetable'],
    'services'  => ['services.php', 'Services'],
    'news'      => ['news.php', 'Health tips'],
    'contact'   => ['index.php#contact', 'Contact us'],
];
$topLinks = [];
foreach (pages(true) as $pg) $topLinks['page-' . $pg['slug']] = ['page.php?slug=' . urlencode($pg['slug']), $pg['title']];
$topLinks['check'] = ['check.php', 'My booking'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ? "$pageTitle | $hospital" : "$hospital | Doctor channelling") ?></title>
<meta name="description" content="Channel doctors at <?= e($hospital) ?>. See doctor timetables and book your appointment number online.">
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=3">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="masthead">
  <a class="masthead__brand" href="<?= e(base_url('index.php')) ?>">
    <img src="<?= e(base_url('assets/img/logo.png')) ?>" alt="" width="52" height="66">
    <span class="brand__text">
      <strong><?= e(setting('hospital_name')) ?></strong>
      <span><?= e(setting('hospital_town')) ?></span>
    </span>
  </a>

  <div class="masthead__menus">
    <nav class="toplinks" aria-label="Hospital information">
      <?php foreach ($topLinks as $key => [$href, $label]): ?>
        <a href="<?= e(base_url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <nav id="site-nav" class="mainnav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e(base_url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="mainnav__extra">
        <?php foreach ($topLinks as $key => [$href, $label]): ?>
          <a href="<?= e(base_url($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </span>
    </nav>
  </div>

  <a class="masthead__cta masthead__cta--book" href="<?= e(base_url('doctors.php')) ?>">
    <span>Channel</span><strong>your doctor</strong>
  </a>
  <a class="masthead__cta masthead__cta--call" href="<?= e(tel(setting('phone'))) ?>">
    <span>Call us</span><strong><?= e(setting('phone')) ?></strong>
  </a>
  <button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
</header>

<div class="floaters" aria-label="Quick contact">
  <a class="floater floater--red" href="<?= e(tel(setting('emergency_phone'))) ?>"><span>Emergency</span><strong><?= e(setting('emergency_phone')) ?></strong></a>
  <a class="floater" href="<?= e(base_url('doctors.php')) ?>"><span>Channelling</span><strong>Book online</strong></a>
  <?php if (setting('whatsapp')): ?>
    <a class="floater floater--wa" href="https://wa.me/<?= e(sms_number(setting('whatsapp')) ?? '') ?>" target="_blank" rel="noopener"><span>WhatsApp</span><strong><?= e(setting('whatsapp')) ?></strong></a>
  <?php endif; ?>
</div>
<main id="main">
