<?php
require __DIR__ . '/_layout.php';

// Only usable while no admin account exists
if (admin_count() > 0) redirect('login.php');

$errors = [];
$v = ['name' => '', 'username' => ''];
if (is_post()) {
    verify_csrf();
    $v['name'] = trim((string)($_POST['name'] ?? ''));
    $v['username'] = strtolower(trim((string)($_POST['username'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    if ($v['name'] === '') $errors[] = 'Enter your name.';
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $v['username'])) $errors[] = 'Username: 3–30 letters, numbers, dots or dashes.';
    if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== ($_POST['password2'] ?? '')) $errors[] = 'The two passwords don\'t match.';
    if (!$errors) {
        db()->prepare('INSERT INTO admins (username, name, password_hash, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$v['username'], $v['name'], password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)db()->lastInsertId();
        $_SESSION['admin_seen'] = time();
        flash('success', 'Admin account created. Next, update the hospital details and add your doctors.');
        redirect('settings.php');
    }
}

admin_header('Set up admin account');
?>
<div class="login">
  <img src="../assets/img/logo.png" alt="" width="70" height="89">
  <h1>Create the first admin account</h1>
  <p class="muted">This page only works once. After this, staff sign in at the admin login page.</p>
  <?php foreach ($errors as $err): ?><div class="alert alert--error"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Your name <input name="name" value="<?= e($v['name']) ?>" required></label>
    <label>Username <input name="username" value="<?= e($v['username']) ?>" required autocomplete="username"></label>
    <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <label>Repeat password <input type="password" name="password2" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn--red" type="submit">Create account</button>
  </form>
</div>
<?php admin_footer(); ?>
