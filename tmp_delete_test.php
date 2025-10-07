<?php
// tmp_delete_test.php - simulate a POST to admin/delete-image.php with a generated CSRF token
require __DIR__ . '/admin/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['admin_logged_in'] = true;
$token = generate_csrf_token();
chdir(__DIR__ . '/admin');
// simulate POST
$_POST['filename'] = 'logo/logo.png';
$_POST['csrf_token'] = $token;
$_SERVER['REQUEST_METHOD'] = 'POST';
include 'delete-image.php';
