<?php
require __DIR__ . '/_layout.php';

if (admin_count() === 0) redirect('setup.php');
if (current_admin()) redirect('index.php');

$error = '';
// Throttle by IP address: 5 failed tries per 15 minutes
db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([time() - 900]);
$fails = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
$fails->execute([client_ip()]);
$failCount = (int)$fails->fetchColumn();

if (is_post()) {
    verify_csrf();
    if ($failCount >= 5) {
        $error = 'Too many failed attempts. Wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $st->execute([strtolower(trim((string)($_POST['username'] ?? '')))]);
        $admin = $st->fetch();
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_seen'] = time();
            db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
            redirect('index.php');
        }
        db()->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, ?)')->execute([client_ip(), time()]);
        usleep(400000);
        $error = 'Wrong username or password.';
    }
}

admin_header('Staff sign in');
?>
<div class="login">
  <img src="../assets/img/logo.png" alt="" width="70" height="89">
  <h1>Staff sign in</h1>
  <p class="muted"><?= e(setting('hospital_name')) ?>, <?= e(setting('hospital_town')) ?></p>
  <?php if ($error): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Username <input name="username" required autocomplete="username" autofocus></label>
    <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--navy" type="submit">Sign in</button>
  </form>
  <p><a href="../index.php">Back to website</a></p>
</div>
<?php admin_footer(); ?>
