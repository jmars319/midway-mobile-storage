<?php
// admin/download-resume.php
// Securely serve resume files stored outside the webroot.
require_once __DIR__ . '/config.php';
require_admin();

$file = $_GET['file'] ?? '';
if (!$file) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing file parameter';
    exit;
}

// sanitize filename and prevent traversal
$safe = basename($file);
if ($safe === '' || $safe !== $file || preg_match('/[\\\/]/', $safe) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $safe)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid file name';
    exit;
}

$path = __DIR__ . '/../private_data/resumes/' . $safe;
if (!file_exists($path) || !is_file($path)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found';
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $path) : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

// Serve the file as an attachment
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $safe . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
