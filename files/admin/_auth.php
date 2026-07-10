<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$current_script = basename($_SERVER['SCRIPT_NAME']);
if (!empty($_SESSION['must_change_password']) && $current_script !== 'force_password_change.php') {
    header('Location: force_password_change.php');
    exit;
}
