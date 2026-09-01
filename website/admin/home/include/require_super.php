<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id'])) {
    header('location:../index.php');
    exit;
}

require_once dirname(__DIR__) . '/../php-includes/functions.php';

if (!get_admin($_SESSION['username'], 'super')) {
    header('Location: index.php');
    exit;
}
