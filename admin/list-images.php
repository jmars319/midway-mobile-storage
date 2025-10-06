<?php
/**
 * admin/list-images.php
 * JSON helper used by the admin UI to list available image filenames
 * in the uploads directory. Requires admin auth and returns a simple
 * JSON array { files: [...] }.
 */

session_start();
require_once 'config.php';
checkAuth();

header('Content-Type: application/json');

$dir = UPLOAD_DIR;
$files = [];
if (is_dir($dir)) {
    $all = scandir($dir);
    foreach ($all as $f) {
        if ($f === '.' || $f === '..') continue;
        // skip hidden files and system files (for example .DS_Store)
        if (substr($f, 0, 1) === '.') continue;
        $full = $dir . $f;
        if (!is_file($full)) continue;
        // only return common image file types
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $allowed = ['png','jpg','jpeg','gif','webp','svg','ico'];
        if (!in_array($ext, $allowed, true)) continue;
        $files[] = $f;
    }
}
echo json_encode(['files' => $files]);
