<?php
// admin/restore-recent-resumes.php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF'; exit; }
$days = isset($_POST['days']) ? max(1, (int)$_POST['days']) : 7;
$since = time() - ($days * 86400);

$trashDir = dirname(__DIR__) . '/../private_data/resumes/trashed/';
$archDir = dirname(__DIR__) . '/../private_data/resumes/archived/';
if (!is_dir($trashDir)) { header('Location: admin-resumes.php?restored=0'); exit; }
if (!is_dir($archDir)) @mkdir($archDir, 0755, true);

$moved = 0;
$entries = scandir($trashDir);
foreach ($entries as $e) {
  if ($e === '.' || $e === '..') continue;
  $full = rtrim($trashDir, '/') . '/' . $e;
  if (!is_file($full)) continue;
  if (!preg_match('/^[0-9]+_[a-f0-9]{12}__/', $e)) {
    // fallback: accept any trashed filename pattern
  }
  if (filemtime($full) >= $since) {
    // determine original name after the '__' if present
    $parts = explode('__', $e, 2);
    $orig = $parts[1] ?? $e;
    $dest = rtrim($archDir, '/') . '/' . $orig;
    $i = 0; $base = pathinfo($orig, PATHINFO_FILENAME); $ext = pathinfo($orig, PATHINFO_EXTENSION);
    while (file_exists($dest)) { $i++; $dest = rtrim($archDir, '/') . '/' . $base . '_' . $i . ($ext?'.'.$ext:''); }
    if (@rename($full, $dest)) {
      $moved++;
      // audit
      $entry = [ 'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'), 'admin' => $_SESSION['admin_user'] ?? 'admin', 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'action' => 'restore_recent', 'trash_name' => $e, 'restored_name' => basename($dest), 'restored_path' => $dest ];
      $logFile = dirname(__DIR__) . '/data/resume-deletions.log';
      if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
      @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
  }
}
header('Location: admin-resumes.php?restored=' . $moved);
exit;
