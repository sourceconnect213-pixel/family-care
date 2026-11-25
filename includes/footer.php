</main>
<footer class="site-footer">
  <div class="hotlines">
    <div class="wrap hotlines__grid">
      <a href="<?= e(tel(setting('phone'))) ?>"><span>Channelling</span><strong><?= e(setting('phone')) ?></strong></a>
      <a href="<?= e(tel(setting('emergency_phone'))) ?>" class="is-red"><span>Emergency, 24 hours</span><strong><?= e(setting('emergency_phone')) ?></strong></a>
      <?php if (setting('lab_phone')): ?>
        <a href="<?= e(tel(setting('lab_phone'))) ?>"><span>Laboratory</span><strong><?= e(setting('lab_phone')) ?></strong></a>
      <?php else: ?>
        <a href="mailto:<?= e(setting('email')) ?>"><span>Email</span><strong><?= e(setting('email')) ?></strong></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap site-footer__grid">
    <div>
      <img src="<?= e(base_url('assets/img/logo.png')) ?>" alt="<?= e(setting('hospital_name')) ?> logo" width="56" height="71">
      <p class="site-footer__name"><?= e(setting('hospital_name')) ?><br><?= e(setting('hospital_town')) ?></p>
      <p><?= e(setting('address')) ?></p>
    </div>
    <div>
      <h2>Patients</h2>
      <p><a href="<?= e(base_url('doctors.php')) ?>">Find a doctor</a></p>
      <p><a href="<?= e(base_url('timetable.php')) ?>">Doctor timetable</a></p>
      <p><a href="<?= e(base_url('check.php')) ?>">Check or cancel a booking</a></p>
      <p><a href="<?= e(base_url('services.php')) ?>">Services</a></p>
    </div>
    <div>
      <h2>Hospital</h2>
      <?php foreach (pages() as $pg): ?>
        <p><a href="<?= e(base_url('page.php?slug=' . urlencode($pg['slug']))) ?>"><?= e($pg['title']) ?></a></p>
      <?php endforeach; ?>
      <p><a href="<?= e(base_url('news.php')) ?>">Health tips & news</a></p>
    </div>
    <div>
      <h2>Opening hours</h2>
      <p><?= nl2br(e(setting('opening_hours'))) ?></p>
    </div>
  </div>
  <div class="wrap site-footer__base">
    <span>&copy; <?= date('Y') ?> <?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?></span>
    <span><a href="<?= e(base_url('portal/login.php')) ?>">Doctor sign in</a> &nbsp; <a href="<?= e(base_url('admin/login.php')) ?>">Staff sign in</a></span>
  </div>
</footer>
<script src="<?= e(base_url('assets/js/site.js')) ?>?v=3" defer></script>
</body>
</html>
