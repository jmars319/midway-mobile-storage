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
// reject if basename differs (attempted traversal) or contains slashes
if ($safe === '' || $safe !== $file || strpos($safe, '/') !== false || strpos($safe, '\\') !== false || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $safe)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid file name';
    exit;
}

$candidates = [
    __DIR__ . '/../private_data/resumes/',          // when called from admin dir
    dirname(__DIR__) . '/../private_data/resumes/', // repo-root relative
    __DIR__ . '/../../private_data/resumes/',       // fallback
];
$path = null;
$checked = [];
foreach ($candidates as $cand) {
    $full = $cand . $safe;
    $exists = file_exists($full) && is_file($full);
    $checked[$full] = $exists;
    if ($exists) { $path = $full; break; }
}
if ($path === null) {
    // If admin is authenticated, provide diagnostics to help debugging paths
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "File not found. Diagnostic info:\n";
        echo "Requested file: {$safe}\n";
        echo "Current working dir: " . getcwd() . "\n";
        echo "Checked candidate paths:\n";
        foreach ($checked as $p => $e) {
            echo ($e ? '[FOUND] ' : '[MISSING] ') . $p . "\n";
        }
        exit;
    }
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
