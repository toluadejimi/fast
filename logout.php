<?php
// logout.php
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/config/config.php';
$admin = isset($_GET['admin']);
session_destroy(); session_start();
header('Location: ' . SITE_URL . ($admin ? '/'.ADMIN_PATH.'/login.php' : '/login.php'));
exit;
