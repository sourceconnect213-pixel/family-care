<?php
require __DIR__ . '/_layout.php';
$doc = require_doctor();

if (is_post()) {
    verify_csrf();
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify((string)($_POST['current'] ?? ''), (string)$doc['password_hash'])) flash('error', 'Current password is wrong.');
    elseif (strlen($new) < 8) flash('error', 'New password must be at least 8 characters.');
    elseif ($new !== ($_POST['new2'] ?? '')) flash('error', 'The two new passwords don\'t match.');
    else {
        db()->prepare('UPDATE doctors SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $doc['id']]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
    }
    redirect('password.php');
}

portal_header('Change password', 'password');
?>
<section class="panel narrow-panel">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Current password <input type="password" name="current" required autocomplete="current-password"></label>
    <label>New password <input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <label>Repeat new password <input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
    <div><button class="btn btn--navy" type="submit">Change password</button></div>
  </form>
</section>
<?php portal_footer(); ?>
