<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['affiliate_id']);

if (!empty($_SESSION['id']) && !empty($_SESSION['username'])) {
    header('Location: /admin/home/index.php');
    exit;
}

header('Location: index.php');
exit;
