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
// Quick sanity-check: ensure file is reasonably-sized and has expected magic bytes
$size = filesize($path);
$minSize = 256; // files smaller than this are suspicious (likely truncated)
$fh = @fopen($path, 'rb');
$head = '';
if ($fh) {
    $head = fread($fh, 16);
    fclose($fh);
}
$headHex = bin2hex($head);
$valid = true;
// PDF starts with %PDF-
if (strpos($head, '%PDF-') === 0) {
    $valid = $size >= 64; // small PDFs may still be valid but require minimal bytes
} elseif (substr($head,0,4) === "PK\x03\x04") {
    // DOCX is a ZIP container (PK..)
    $valid = $size >= $minSize;
} elseif (substr($head,0,4) === "\xD0\xCF\x11\xE0") {
    // legacy MS OLE (old .doc)
    $valid = $size >= $minSize;
} else {
    // unknown signature; also treat very small files as invalid
    if ($size < $minSize) $valid = false;
}

if (!$valid) {
    // For admins, provide helpful diagnostics to aid debugging
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Resume file appears invalid or corrupted.\n";
        echo "Requested file: {$safe}\n";
        echo "Resolved path: {$path}\n";
        echo "Size: {$size} bytes\n";
        echo "Detected MIME: {$mime}\n";
        echo "First bytes (hex): {$headHex}\n";
        echo "Expected signatures: PDF (%PDF-), PKZIP (PK\x03\x04 for .docx), or OLE (d0cf11e0 for .doc).\n";
        echo "Suggested actions: restore from backup, re-upload the file via the public form, or check file storage permissions.\n";
        exit;
    }
    // For non-admins, show a friendly error without internals
    header('HTTP/1.1 400 Bad Request');
    echo 'The requested resume appears to be missing or corrupted. Please contact an administrator.';
    exit;
}

// Serve the file as an attachment
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('Content-Disposition: attachment; filename="' . $safe . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
