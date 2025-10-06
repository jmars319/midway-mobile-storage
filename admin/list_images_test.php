<?php
// Test script to reproduce admin/list-images.php relative path output
chdir(__DIR__);
require_once __DIR__ . '/config.php';

$dir = UPLOAD_DIR;
echo "UPLOAD_DIR=[$dir]\n";
echo "is_dir(dir)=" . (is_dir($dir) ? 'yes' : 'no') . "\n";
echo "realpath(dir)=[" . (realpath($dir) ?: 'false') . "]\n";
$files = [];
if (is_dir($dir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $fileinfo) {
        if (!$fileinfo->isFile()) continue;
        $fullPath = $fileinfo->getPathname();
        $baseReal = realpath($dir);
        if ($baseReal === false) $baseReal = rtrim($dir, '/');
        $fullReal = realpath($fullPath);
        if ($fullReal !== false && strpos($fullReal, $baseReal) === 0) {
            $relPath = str_replace('\\', '/', ltrim(substr($fullReal, strlen($baseReal) + 1), '/'));
        } else {
            $relPath = preg_replace('#^.*uploads/images/#i', '', str_replace('\\', '/', $fullPath));
            $relPath = ltrim($relPath, '/');
        }
        $files[] = $relPath;
    }
}

echo "JSON output:\n";
echo json_encode(['files' => $files], JSON_PRETTY_PRINT) . "\n\n";
foreach ($files as $f) echo $f . "\n";
