<?php
/**
 * scripts/repair-content-images.php
 * Scans ./uploads/images and ensures entries in data/content.json point to
 * existing files. If a referenced image is missing but a matching file exists
 * (by basename or folder), this script will update the JSON to point at the
 * correct relative path. It performs a safe backup of data/content.json first.
 */

$contentFile = __DIR__ . '/../data/content.json';
$uploads = __DIR__ . '/../uploads/images/';

if (!file_exists($contentFile)) { echo "No content.json\n"; exit(1); }
$backup = $contentFile . '.bak.' . time(); copy($contentFile, $backup); echo "Backed up content.json to $backup\n";

$content = json_decode(file_get_contents($contentFile), true);
if (!is_array($content)) { echo "content.json invalid\n"; exit(1); }

// Build a map of basenames -> relative paths under uploads/images
$map = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $rel = substr($f->getPathname(), strlen(realpath($uploads)) + 1);
    $rel = str_replace('\\', '/', $rel);
    $map[basename($rel)][] = $rel;
}

$changed = false;
$checkKeys = ['images','hero'];
// walk known image fields and try to repair
if (isset($content['images']) && is_array($content['images'])) {
    foreach ($content['images'] as $k => $v) {
        if (!$v) continue;
        // if file exists as-is, OK
        if (file_exists($uploads . $v)) continue;
        $b = basename($v);
        if (isset($map[$b]) && count($map[$b]) === 1) {
            echo "Repair: images.$k -> " . $map[$b][0] . "\n";
            $content['images'][$k] = $map[$b][0];
            $changed = true;
        }
    }
}
// hero image path same as above (top-level hero.image)
if (isset($content['hero']) && isset($content['hero']['image']) && $content['hero']['image']) {
    $v = $content['hero']['image'];
    if (!file_exists($uploads . $v)) {
        $b = basename($v);
        if (isset($map[$b]) && count($map[$b]) === 1) {
            echo "Repair: hero.image -> " . $map[$b][0] . "\n";
            $content['hero']['image'] = $map[$b][0];
            $changed = true;
        }
    }
}

if ($changed) {
    $content['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents($contentFile . '.tmp', json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($contentFile . '.tmp', $contentFile);
    echo "content.json updated\n";
} else {
    echo "No repairs necessary\n";
}

exit(0);
