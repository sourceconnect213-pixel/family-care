<?php
require __DIR__ . '/_layout.php';
if (current_doctor()) redirect('index.php');

$error = '';
if (is_post()) {
    verify_csrf();
    if (login_blocked()) {
        $error = 'Too many failed attempts. Wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM doctors WHERE username = ? AND login_enabled = 1');
        $st->execute([strtolower(trim((string)($_POST['username'] ?? '')))]);
        $doc = $st->fetch();
        if ($doc && $doc['password_hash'] && password_verify((string)($_POST['password'] ?? ''), $doc['password_hash'])) {
            login_succeeded();
            $_SESSION['doctor_id'] = (int)$doc['id'];
            $_SESSION['doctor_seen'] = time();
            redirect('index.php');
        }
        login_failed();
        $error = 'Wrong username or password.';
    }
}

portal_header('Doctor sign in');
?>
<div class="login">
  <img src="../assets/img/logo.png" alt="" width="70" height="89">
  <h1>Doctor sign in</h1>
  <p class="muted">See the patients who have channelled you at <?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?>.</p>
  <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autocomplete="username" autofocus></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--navy" type="submit">Sign in</button>
  </form>
  <p class="muted">No login yet? Ask the hospital admin to set one up for you.</p>
</div>
<?php portal_footer(); ?>
