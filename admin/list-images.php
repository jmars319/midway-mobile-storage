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

// include helper for admin_image_src
require_once __DIR__ . '/partials/uploads.php';

// Prevent aggressive browser caching — this endpoint returns a dynamic JSON list
// and should always be fetched fresh by admin UIs.
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$dir = UPLOAD_DIR;
$context = isset($_GET['context']) ? trim($_GET['context']) : '';
$files = [];
// recursively scan the uploads/images directory so images can be organized in subfolders
if (is_dir($dir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    $allowed = ['png','jpg','jpeg','gif','webp','svg','ico'];
    foreach ($iterator as $fileinfo) {
        if (!$fileinfo->isFile()) continue;
        $filename = $fileinfo->getFilename();
        // skip hidden/system files
        if (substr($filename, 0, 1) === '.') continue;
        $ext = strtolower($fileinfo->getExtension());
        if (!in_array($ext, $allowed, true)) continue;

        // compute a relative path from the uploads/images directory so client can use
        // ../uploads/images/<relative-path> as the src for thumbnails
        $fullPath = $fileinfo->getPathname();
        // Try to resolve the real path for both base and file so '..' segments are normalized.
        $baseReal = realpath($dir);
        if ($baseReal === false) {
            $baseReal = rtrim($dir, '/');
        }
        $fullReal = realpath($fullPath);
        if ($fullReal !== false && strpos($fullReal, $baseReal) === 0) {
            // file path resolved within the expected base directory
            $relPath = str_replace('\\', '/', ltrim(substr($fullReal, strlen($baseReal) + 1), '/'));
        } else {
            // fallback: extract everything after 'uploads/images/' to be robust
            $relPath = preg_replace('#^.*uploads/images/#i', '', str_replace('\\', '/', $fullPath));
            $relPath = ltrim($relPath, '/');
        }

        if ($context === 'hero') {
            // Strict hero filtering: path or filename must include the substring 'hero' (case-insensitive)
            if (stripos($relPath, 'hero') === false) {
                continue;
            }
        }

        // Skip thumbnail files stored in a 'thumbs' subdirectory to avoid
        // returning both the original image and its thumbnail as separate
        // entries in the admin UI (which causes duplicate visible items).
        if (preg_match('#(^|/)thumbs(/|$)#i', $relPath)) {
            continue;
        }

        // avoid duplicates in the returned list (same relative path found twice via different filesystem entries)
        if (!in_array($relPath, array_column($files, 'relative'), true)) {
            $files[] = [ 'relative' => $relPath, 'url' => admin_image_src($relPath) ];
        }
    }
}
echo json_encode(['files' => $files]);
