<?php
require_once __DIR__ . '/config.php';
require_admin();

$file = $_GET['file'] ?? '';
// Only allow simple filenames; no slashes
if ($file === '' || preg_match('/[\\\/]/', $file)) {
    http_response_code(400); echo 'Invalid file'; exit;
}

$path = __DIR__ . '/../private_data/resumes/' . $file;
if (!file_exists($path) || !is_file($path)) {
    http_response_code(404); echo 'Not found'; exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
