<?php
require __DIR__ . '/../includes/functions.php';
if (is_post()) {
    verify_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
}
redirect('login.php');
