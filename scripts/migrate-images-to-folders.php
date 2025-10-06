#!/usr/bin/env php
<?php
// scripts/migrate-images-to-folders.php
// Move existing uploads/images files into type-specific folders (hero, logo, gallery, general)
// and update data/content.json references. Dry-run by default; pass --apply to make changes.

declare(strict_types=1);

$root = dirname(__DIR__);
$uploadsDir = $root . '/uploads/images/';
$contentFile = $root . '/data/content.json';
$thumbsDir = $uploadsDir . 'thumbs/';

$allowedExt = ['png','jpg','jpeg','gif','webp','svg','ico'];

$argvFlags = array_slice($argv, 1);
$apply = in_array('--apply', $argvFlags, true);

if (!is_dir($uploadsDir)) {
    echo "Uploads directory not found: $uploadsDir\n";
    exit(1);
}
if (!file_exists($contentFile)) {
    echo "Content file not found: $contentFile\n";
    exit(1);
}

$content = json_decode(file_get_contents($contentFile), true);
if (!is_array($content)) {
    echo "Failed to parse content json\n";
    exit(1);
}

// Helper: recursively find image-like strings inside content
function find_image_strings($data) {
    $out = [];
    $extPattern = '/\.(png|jpg|jpeg|gif|webp|svg|ico)$/i';
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator([$data]));
    // fallback: simple recursive walk
    $stack = [$data];
    while ($stack) {
        $v = array_pop($stack);
        if (is_array($v)) { foreach ($v as $vv) $stack[] = $vv; continue; }
        if (!is_string($v)) continue;
        if (preg_match($extPattern, $v)) $out[] = $v;
    }
    return array_unique($out);
}

$referenced = find_image_strings($content);

// Build list of files in uploads (non-recursive first, but include subdirs)
$files = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($rii as $fi) {
    if (!$fi->isFile()) continue;
    $rel = str_replace('\\', '/', substr($fi->getPathname(), strlen($uploadsDir)));
    // skip thumbs that are in the top-level thumbs directory; we'll handle thumbs separately
    if (strpos($rel, 'thumbs/') === 0) continue;
    // If the file already lives in a subfolder that is NOT a top-level loose file (e.g. favicon-set/),
    // preserve it as-is (don't move). We'll still handle known types below when files are at top-level.
    if (strpos($rel, '/') !== false) {
        // Allow files already under one of the target type folders to remain (they are already organized)
        $top = explode('/', $rel, 2)[0];
        if (in_array($top, ['hero','logo','gallery','general','thumbs'], true)) {
            $files[] = $rel; // include for potential verification/thumbnail moves
        } else {
            // preserve unknown subfolders (favicon-set, etc.) by treating as already in-place
            continue;
        }
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) continue;
    $files[] = $rel;
}

// Determine target folder for each file
function detect_type_for_file(string $relPath, array $referenced): string {
    $lower = strtolower($relPath);
    if (stripos($lower, 'hero') !== false) return 'hero';
    if (stripos($lower, 'logo') !== false) return 'logo';
    if (stripos($lower, 'gallery') !== false) return 'gallery';
    // if file is referenced in content and the key context suggests a type, try to infer
    foreach ($referenced as $r) {
        if (strcasecmp($r, $relPath) === 0 || strcasecmp(basename($r), basename($relPath)) === 0) {
            // check context key names by searching content keys (best-effort)
            // We fallback to general if no hint
            if (stripos($r, 'hero') !== false) return 'hero';
            if (stripos($r, 'logo') !== false) return 'logo';
            if (stripos($r, 'gallery') !== false) return 'gallery';
        }
    }
    return 'general';
}

$moves = [];
foreach ($files as $rel) {
    $type = detect_type_for_file($rel, $referenced);
    // if already in correct folder, skip
    if (strpos($rel, $type . '/') === 0) continue;
    $src = $uploadsDir . $rel;
    $destDir = $uploadsDir . ($type !== 'general' ? $type . '/' : '');
    $dest = $destDir . basename($rel);
    $moves[] = ['src' => $src, 'rel' => $rel, 'dest' => $dest, 'destRel' => ($type !== 'general' ? $type . '/' : '') . basename($rel), 'type' => $type];
}

// Also map thumbnails in top-level thumbs/ to the appropriate type thumbs/
$thumbMoves = [];
if (is_dir($thumbsDir)) {
    $thumbFiles = scandir($thumbsDir);
    foreach ($thumbFiles as $tf) {
        if ($tf === '.' || $tf === '..') continue;
        $ext = strtolower(pathinfo($tf, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) continue;
        // find which move includes this basename
        foreach ($moves as $m) {
            if (basename($m['rel']) === $tf || basename($m['destRel']) === $tf) {
                $thumbSrc = $thumbsDir . $tf;
                $thumbDestDir = dirname($m['dest']) . '/thumbs/';
                $thumbDest = $thumbDestDir . $tf;
                $thumbMoves[] = ['src' => $thumbSrc, 'dest' => $thumbDest];
                break;
            }
        }
    }
}

// Prepare content.json updates: replace referenced filenames with new relative paths where applicable
$contentUpdates = [];
foreach ($referenced as $r) {
    $base = basename($r);
    foreach ($moves as $m) {
        if (basename($m['rel']) === $base) {
            $contentUpdates[$r] = $m['destRel'];
            break;
        }
    }
}

// Output plan
echo "Migration plan (dry-run by default). Run with --apply to perform changes.\n\n";
if (empty($moves) && empty($thumbMoves) && empty($contentUpdates)) {
    echo "No files to move or content updates detected.\n";
    exit(0);
}

echo "Files to move:\n";
foreach ($moves as $m) {
    echo sprintf(" - %s -> %s\n", $m['rel'], $m['destRel']);
}

if (!empty($thumbMoves)) {
    echo "\nThumbnails to move:\n";
    foreach ($thumbMoves as $t) echo sprintf(" - %s -> %s\n", str_replace($uploadsDir, '', $t['src']), str_replace($uploadsDir, '', $t['dest']));
}

if (!empty($contentUpdates)) {
    echo "\nContent.json updates (sample):\n";
    foreach ($contentUpdates as $old => $new) echo sprintf(" - %s -> %s\n", $old, $new);
}

if (!$apply) {
    echo "\nDry-run complete. No changes made. To apply, re-run with --apply\n";
    exit(0);
}

// APPLY CHANGES
echo "\nApplying changes...\n";

// perform file moves
foreach ($moves as $m) {
    $destDir = dirname($m['dest']);
    if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
    if (file_exists($m['dest'])) {
        echo "Skipping move (destination exists): {$m['destRel']}\n";
        continue;
    }
    if (@rename($m['src'], $m['dest'])) {
        echo "Moved: {$m['rel']} -> {$m['destRel']}\n";
    } else {
        echo "Failed to move: {$m['rel']} -> {$m['destRel']}\n";
    }
}

foreach ($thumbMoves as $t) {
    $thumbDestDir = dirname($t['dest']);
    if (!is_dir($thumbDestDir)) @mkdir($thumbDestDir, 0755, true);
    if (file_exists($t['dest'])) { echo "Skipping thumb move (exists): {$t['dest']}\n"; continue; }
    if (@rename($t['src'], $t['dest'])) echo "Moved thumb: " . basename($t['src']) . " -> " . str_replace($uploadsDir, '', $t['dest']) . "\n";
    else echo "Failed to move thumb: " . basename($t['src']) . "\n";
}

// Backup content.json
$bak = $contentFile . '.bak-' . date('Ymd-His');
if (!copy($contentFile, $bak)) { echo "Warning: failed to backup content.json to $bak\n"; }
else echo "Backed up content.json to $bak\n";

// Apply content updates (walk content and replace values)
function replace_in_content(&$node, $updates) {
    if (is_array($node)) {
        foreach ($node as $k => &$v) replace_in_content($v, $updates);
        return;
    }
    if (!is_string($node)) return;
    foreach ($updates as $old => $new) {
        if ($node === $old || basename($node) === basename($old)) { $node = $new; }
    }
}

replace_in_content($content, $contentUpdates);

file_put_contents($contentFile, json_encode($content, JSON_PRETTY_PRINT));
echo "Updated content.json with new image paths.\n";

echo "Migration finished.\n";

exit(0);
