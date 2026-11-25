<?php
/** @var string $nfTitle @var string $nfText @var string $nfLink @var string $nfLinkText */
http_response_code(404);
$pageTitle = $nfTitle;
require __DIR__ . '/header.php';
?>
<section class="section"><div class="wrap narrow empty">
  <h1><?= e($nfTitle) ?></h1>
  <p><?= e($nfText) ?></p>
  <a class="btn btn--navy" href="<?= e($nfLink) ?>"><?= e($nfLinkText) ?></a>
</div></section>
<?php require __DIR__ . '/footer.php'; exit; ?>
