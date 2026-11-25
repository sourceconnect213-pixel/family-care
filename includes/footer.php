</main>
<footer class="site-footer">
  <div class="wrap site-footer__grid">
    <div>
      <img src="<?= e(base_url('assets/img/logo.png')) ?>" alt="<?= e(setting('hospital_name')) ?> logo" width="56" height="71">
      <p class="site-footer__name"><?= e(setting('hospital_name')) ?><br><?= e(setting('hospital_town')) ?></p>
    </div>
    <div>
      <h2>Visit</h2>
      <p><?= e(setting('address')) ?></p>
      <p><?= nl2br(e(setting('opening_hours'))) ?></p>
    </div>
    <div>
      <h2>Call</h2>
      <p>Channelling: <a href="tel:<?= e(preg_replace('/\s+/', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></p>
      <p>Emergency: <a href="tel:<?= e(preg_replace('/\s+/', '', setting('emergency_phone'))) ?>"><?= e(setting('emergency_phone')) ?></a></p>
      <p><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></p>
    </div>
    <div>
      <h2>Patients</h2>
      <p><a href="<?= e(base_url('doctors.php')) ?>">Find a doctor</a></p>
      <p><a href="<?= e(base_url('timetable.php')) ?>">Weekly timetable</a></p>
      <p><a href="<?= e(base_url('check.php')) ?>">Check or cancel a booking</a></p>
    </div>
  </div>
  <div class="wrap site-footer__base">
    <span>&copy; <?= date('Y') ?> <?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?></span>
    <a href="<?= e(base_url('admin/login.php')) ?>">Staff sign in</a>
  </div>
</footer>
<script src="<?= e(base_url('assets/js/site.js')) ?>?v=1" defer></script>
</body>
</html>
