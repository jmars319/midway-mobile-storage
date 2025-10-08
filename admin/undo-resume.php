<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

// Expect POST { csrf_token, trash_name }
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit; }
$trash = basename((string)($_POST['trash_name'] ?? ''));
if (!$trash || !preg_match('/^[a-zA-Z0-9_\-\.]+__[a-zA-Z0-9_\-\.]+$/', $trash)) {
  header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Invalid trash name']); exit;
}
$trashDir = dirname(__DIR__) . '/../private_data/resumes/trashed/';
$src = rtrim($trashDir, '/') . '/' . $trash;
if (!is_file($src)) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Not found']); exit; }

// restore back to archive (archived folder)
$archDir = dirname(__DIR__) . '/../private_data/resumes/archived/';
if (!is_dir($archDir)) @mkdir($archDir, 0755, true);
// original filename is after the '__' suffix
$parts = explode('__', $trash, 2);
$orig = $parts[1] ?? $trash;
$dest = rtrim($archDir, '/') . '/' . $orig;
$i = 0; $base = pathinfo($orig, PATHINFO_FILENAME); $ext = pathinfo($orig, PATHINFO_EXTENSION);
while (file_exists($dest)) { $i++; $dest = rtrim($archDir, '/') . '/' . $base . '_' . $i . ($ext?'.'.$ext:''); }
if (@rename($src, $dest)) {
  // audit
  $entry = [
    'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
    'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'action' => 'undo_purge',
    'trash_name' => $trash,
    'restored_name' => basename($dest),
    'restored_path' => $dest,
  ];
  $logFile = dirname(__DIR__) . '/data/resume-deletions.log';
  if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
  @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
  header('Content-Type: application/json'); echo json_encode(['success'=>true,'restored'=>basename($dest),'restored_path'=>$dest]); exit;
}
header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Failed to restore']); exit;
