<?php
require __DIR__ . '/_layout.php';
$me = require_admin();
$pdo = db();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'password') {
        $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $st->execute([$me['id']]);
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), (string)$st->fetchColumn())) flash('error', 'Current password is wrong.');
        elseif (strlen($new) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif ($new !== ($_POST['new2'] ?? '')) flash('error', 'The two new passwords don\'t match.');
        else {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    } elseif ($action === 'add') {
        $u = strtolower(trim((string)($_POST['username'] ?? '')));
        $n = trim((string)($_POST['name'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        $exists = $pdo->prepare('SELECT 1 FROM admins WHERE username = ?');
        $exists->execute([$u]);
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $u)) flash('error', 'Username: 3–30 letters, numbers, dots or dashes.');
        elseif ($exists->fetchColumn()) flash('error', 'That username is taken.');
        elseif ($n === '') flash('error', 'Enter the staff member\'s name.');
        elseif (strlen($p) < 8) flash('error', 'Password must be at least 8 characters.');
        else {
            $pdo->prepare('INSERT INTO admins (username, name, password_hash, created_at) VALUES (?,?,?,?)')->execute([$u, $n, password_hash($p, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            flash('success', "Account created for $n. Give them the username and password in person.");
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['admin_id'];
        if ($id === (int)$me['id']) flash('error', 'You can\'t delete your own account.');
        else { $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]); flash('success', 'Account removed.'); }
    }
    redirect('account.php');
}

$admins = $pdo->query('SELECT id, username, name, created_at FROM admins ORDER BY name')->fetchAll();
admin_header('Staff accounts', 'account');
?>
<div class="two-col">
<section class="panel">
  <h2>Change your password</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password <input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <label>Repeat new password <input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
    <div><button class="btn btn--navy" type="submit">Change password</button></div>
  </form>
</section>
<section class="panel">
  <h2>Add a staff account</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>Name <input name="name" required></label>
    <label>Username <input name="username" required autocomplete="off"></label>
    <label>Password <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
    <div><button class="btn btn--red" type="submit">Create account</button></div>
  </form>
</section>
</div>
<section class="panel">
  <h2>Who can sign in</h2>
  <table class="table">
    <thead><tr><th>Name</th><th>Username</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= e($a['name']) ?><?= (int)$a['id'] === (int)$me['id'] ? ' <small class="muted">(you)</small>' : '' ?></td>
        <td><?= e($a['username']) ?></td>
        <td><?= e(fmt_date($a['created_at'], 'j M Y')) ?></td>
        <td><?php if ((int)$a['id'] !== (int)$me['id']): ?>
          <form method="post" class="inline" onsubmit="return confirm('Remove this account?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>"><button class="btn btn--danger btn--small">Remove</button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_footer(); ?>
