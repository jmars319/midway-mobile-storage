<?php
/**
 * scripts/migrate-gallery-to-folder.php
 * Move existing gallery images into uploads/images/gallery/ and update data/content.json
 *
 * Usage: php scripts/migrate-gallery-to-folder.php
 * This script is interactive-safe but will perform file moves when run.
 */

chdir(__DIR__ . '/..'); // repo root
require_once 'admin/config.php';

echo "Starting gallery migration...\n";

$uploadBase = rtrim(UPLOAD_DIR, '/') . '/';
$galleryDir = $uploadBase . 'gallery/';
if (!is_dir($galleryDir)) {
    if (!@mkdir($galleryDir, 0755, true)) {
        echo "Failed to create gallery directory: $galleryDir\n";
        exit(1);
    }
}

// Read upload audit to prefer files known to be type 'gallery'
$auditFile = __DIR__ . '/../data/upload-audit.log';
$galleryCandidates = [];
if (file_exists($auditFile)) {
    $lines = file($auditFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $ln) {
        $j = json_decode($ln, true);
        if (!is_array($j)) continue;
        if (!empty($j['type']) && $j['type'] === 'gallery' && !empty($j['stored_name'])) {
            $galleryCandidates[] = $j['stored_name'];
        }
    }
}

// Also scan uploads/images root for files with 'gallery' in basename
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadBase, RecursiveDirectoryIterator::SKIP_DOTS));
$toMove = [];
foreach ($it as $fi) {
    if (!$fi->isFile()) continue;
    $rel = str_replace('\\', '/', substr($fi->getPathname(), strlen(realpath($uploadBase)) + 1));
    $basename = $fi->getFilename();
    // skip files already under gallery/
    if (strpos($rel, 'gallery/') === 0) continue;
    // candidate if audit says it's gallery or name contains 'gallery'
    if (in_array($basename, $galleryCandidates, true) || stripos($basename, 'gallery') !== false) {
        $toMove[] = $rel;
    }
}

if (empty($toMove)) {
    echo "No gallery files found to migrate.\n";
    exit(0);
}

echo "Found " . count($toMove) . " candidate(s) to move to gallery/:\n";
foreach ($toMove as $t) echo " - $t\n";

// Load content.json for reference updates
$contentFile = CONTENT_FILE;
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
if ($content === null) { echo "Failed to parse content.json\n"; $content = []; }

$moved = 0;
foreach ($toMove as $rel) {
    $src = $uploadBase . $rel;
    if (!file_exists($src)) continue;
    $destRel = 'gallery/' . basename($rel);
    $dest = $uploadBase . $destRel;
    // avoid overwrite: if dest exists, append numeric suffix
    $base = pathinfo($dest, PATHINFO_FILENAME);
    $ext = pathinfo($dest, PATHINFO_EXTENSION);
    $i = 0;
    while (file_exists($dest)) {
        $i++;
        $dest = $uploadBase . 'gallery/' . $base . '-' . $i . '.' . $ext;
        $destRel = 'gallery/' . basename($dest);
    }
    if (!@rename($src, $dest)) {
        echo "Failed to move $rel -> $destRel\n";
        continue;
    }
    // move thumbs if present in original folder: look for thumbs/<basename>
    $origDir = dirname($uploadBase . $rel);
    $thumbSrc = $origDir . '/thumbs/' . basename($rel);
    $thumbDestDir = $uploadBase . 'gallery/thumbs/';
    if (file_exists($thumbSrc)) {
        if (!is_dir($thumbDestDir)) @mkdir($thumbDestDir, 0755, true);
        @rename($thumbSrc, $thumbDestDir . basename($rel));
    }
    // Update content.json references: replace occurrences of original rel or basename with destRel
    $basename = basename($rel);
    $replaced = false;
    array_walk_recursive($content, function (&$v, $k) use ($rel, $basename, $destRel, &$replaced) {
        if ($v === $rel || $v === $basename) { $v = $destRel; $replaced = true; }
    });
    if ($replaced) {
        $content['last_updated'] = (function_exists('eastern_now') ? eastern_now('Y-m-d H:i:s') : date('Y-m-d H:i:s'));
    }
    $moved++;
    echo "Moved: $rel -> $destRel" . ($replaced ? ' (references updated)' : '') . "\n";
}

// Write back content.json if modified
$jsonOut = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonOut !== false) {
    file_put_contents($contentFile . '.tmp', $jsonOut, LOCK_EX);
    @rename($contentFile . '.tmp', $contentFile);
}

echo "Migration complete. Moved $moved file(s).\n";

exit(0);
