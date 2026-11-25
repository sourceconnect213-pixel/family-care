<?php
require __DIR__ . '/../includes/functions.php';
if (is_post()) {
    verify_csrf();
    unset($_SESSION['doctor_id'], $_SESSION['doctor_seen']);
    session_regenerate_id(true);
}
redirect('login.php');
