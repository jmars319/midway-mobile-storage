<?php
/**
 * admin/delete-image.php
 * Moves an uploaded image into the repo's trash directory and writes
 * a small metadata file describing the original name and deletion
 * time. Also sweeps references from the content store to avoid broken
 * references.
 *
 * Contract:
 *  - Method: POST
 *  - Inputs: POST { filename, csrf_token } - filename will be basename()'d
 *  - Outputs: JSON { success: bool, message: string, trash?: string }
 *
 * Security and notes:
 *  - Admin auth and CSRF are required. The endpoint does not delete
 *    files permanently; it moves them to `uploads/trash/` with a
 *    timestamped name and writes `<trashname>.json` metadata.
 */

require_once 'config.php';
// config.php starts the session and provides auth helpers
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
    exit;
}

// Validate input
$raw = trim((string)($_POST['filename'] ?? ''));
if ($raw === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No filename']);
    exit;
}

// Normalize and sanitize: collapse backslashes and remove leading slashes
$rel = str_replace('\\', '/', $raw);
$rel = preg_replace('#(^/+)#', '', $rel);

// Disallow traversal components for safety
if (strpos($rel, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid filename']);
    exit;
}

// Candidate base directories to search (in order of preference)
$candPaths = [];
if (defined('UPLOAD_DIR')) $candPaths[] = UPLOAD_DIR;
$candPaths[] = __DIR__ . '/../uploads/images/';
$candPaths[] = dirname(__DIR__) . '/uploads/images/';
$candPaths[] = __DIR__ . '/uploads/images/';

// Normalize candidate realpaths and keep only existing directories
$baseReals = [];
foreach ($candPaths as $p) {
    // remove trailing slashes for realpath consistency
    $norm = rtrim($p, "/\\");
    $rp = @realpath($norm);
    if ($rp && is_dir($rp)) {
        $baseReals[] = rtrim($rp, "/\\") . '/';
    }
}

if (empty($baseReals)) {
    http_response_code(500);
    error_log('delete-image: no upload base dirs found; checked: ' . implode(', ', $candPaths));
    echo json_encode(['success' => false, 'message' => 'Upload directory missing']);
    exit;
}

// Try to find the source file by attempting each base + relative path
$srcReal = false;
$matchedBase = null;
foreach ($baseReals as $baseReal) {
    $candidate = $baseReal . ltrim($rel, '/');
    $rp = @realpath($candidate);
    if ($rp && is_file($rp) && strpos($rp, $baseReal) === 0) {
        $srcReal = $rp;
        $matchedBase = $baseReal;
        break;
    }
}

// If not found, try a basename search across each base directory
if ($srcReal === false) {
    $basename = basename($rel);
    foreach ($baseReals as $baseReal) {
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseReal, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                if ($f->getFilename() === $basename) {
                    $srcReal = $f->getPathname();
                    $matchedBase = $baseReal;
                    break 2;
                }
            }
        } catch (Exception $e) {
            // ignore and continue to next base
            continue;
        }
    }
}

if ($srcReal === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Compute a consistent relative path (path inside uploads/images)
$relInside = ltrim(str_replace('\\', '/', substr($srcReal, strlen($matchedBase))), '/');

// Decide trash directory (stable, inside project uploads)
$trashDir = __DIR__ . '/../uploads/trash/';
if (!is_dir($trashDir)) {
    if (!@mkdir($trashDir, 0755, true)) {
        http_response_code(500);
        error_log('delete-image: failed to create trash dir ' . $trashDir);
        echo json_encode(['success' => false, 'message' => 'Failed to create trash directory']);
        exit;
    }
}

// perform move with fallback (rename; if fails, copy+unlink)
$uniq = time() . '-' . bin2hex(random_bytes(4));
$destName = $uniq . '-' . basename($relInside);
$dest = rtrim($trashDir, '/\\') . '/' . $destName;
$moved = false;
if (@rename($srcReal, $dest)) {
    $moved = true;
} else {
    // try copy then unlink (handles cross-filesystem)
    if (@copy($srcReal, $dest)) {
        if (@unlink($srcReal)) {
            $moved = true;
        } else {
            // cleanup copy if original couldn't be removed
            @unlink($dest);
        }
    }
}

if (!$moved) {
    http_response_code(500);
    error_log('delete-image: failed to move file ' . $srcReal . ' to ' . $dest);
    echo json_encode(['success' => false, 'message' => 'Failed to move file to trash']);
    exit;
}

// write metadata
$meta = [
    'original' => basename($relInside),
    'trash_name' => $destName,
    'deleted_at' => (function_exists('eastern_now') ? eastern_now('c') : date('c')),
    'deleted_by' => $_SESSION['admin_username'] ?? 'admin',
    'original_relative' => $relInside,
    'orig_abs' => $srcReal
];
$json = json_encode($meta, JSON_PRETTY_PRINT);
if ($json !== false) {
    file_put_contents($dest . '.json.tmp', $json, LOCK_EX);
    @rename($dest . '.json.tmp', $dest . '.json');
}

// remove references from content.json (simple sweep)
$contentFile = CONTENT_FILE;
if (file_exists($contentFile)) {
    $raw = @file_get_contents($contentFile);
    $content = $raw ? json_decode($raw, true) : null;
    if (is_array($content)) {
        $changed = false;
        $basename = basename($relInside);
        array_walk_recursive($content, function (&$v, $k) use (&$changed, $relInside, $basename) {
            if (!is_string($v)) return;
            if ($v === $relInside || $v === $basename) { $v = ''; $changed = true; }
        });
        if ($changed) {
            $content['last_updated'] = (function_exists('eastern_now') ? eastern_now('Y-m-d H:i:s') : date('Y-m-d H:i:s'));
            $out = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($out !== false) {
                file_put_contents($contentFile . '.tmp', $out, LOCK_EX);
                @rename($contentFile . '.tmp', $contentFile);
            }
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Moved to trash', 'trash' => $destName]);
