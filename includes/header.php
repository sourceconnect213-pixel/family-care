<?php
/** @var string $pageTitle @var string $active */
$pageTitle = $pageTitle ?? '';
$active = $active ?? '';
$hospital = setting('hospital_name') . ', ' . setting('hospital_town');
$nav = [
    'home'      => ['index.php', 'Home'],
    'doctors'   => ['doctors.php', 'Doctors'],
    'timetable' => ['timetable.php', 'Timetable'],
    'check'     => ['check.php', 'My booking'],
    'contact'   => ['index.php#contact', 'Contact'],
];
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
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=1">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<div class="topbar">
  <div class="wrap topbar__inner">
    <span><?= e(setting('address')) ?></span>
    <a href="tel:<?= e(preg_replace('/\s+/', '', setting('emergency_phone'))) ?>" class="topbar__emergency">Emergency: <?= e(setting('emergency_phone')) ?></a>
  </div>
</div>
<header class="site-header">
  <div class="wrap site-header__inner">
    <a class="brand" href="<?= e(base_url('index.php')) ?>">
      <img src="<?= e(base_url('assets/img/logo.png')) ?>" alt="" width="44" height="56">
      <span class="brand__text">
        <strong><?= e(setting('hospital_name')) ?></strong>
        <span><?= e(setting('hospital_town')) ?></span>
      </span>
    </a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="site-nav">Menu</button>
    <nav id="site-nav" class="site-nav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= e(base_url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--red" href="<?= e(base_url('doctors.php')) ?>">Book appointment</a>
    </nav>
  </div>
</header>
<main id="main">
