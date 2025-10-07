<?php
/**
 * scripts/restore-images-from-trash.php
 *
 * Safe utility to recover images accidentally nested in uploads/trash/*
 * and restore them to uploads/images/<type>/. It creates a timestamped
 * backup tarball before moving anything. It will try to infer the
 * target folder (gallery, hero, logo, favicon-set, thumbs) from the
 * source path. It also attempts to repair `data/content.json` entries
 * if those referenced files are restored.
 *
 * Usage: php restore-images-from-trash.php
 */

chdir(dirname(__DIR__));

function ts() { return date('Ymd-His'); }

$projectRoot = getcwd();
$uploadsDir = $projectRoot . '/uploads';
$imagesDir = $uploadsDir . '/images';
$trashDir = $uploadsDir . '/trash';
$contentFile = $projectRoot . '/data/content.json';

if (!is_dir($trashDir)) {
    echo "No trash directory found at $trashDir\n";
    exit(0);
}

// Create backup archive of uploads/trash and uploads/images (non-destructive)
$bakName = $projectRoot . '/tmp/restore_backup_' . ts() . '.tar.gz';
@mkdir($projectRoot . '/tmp', 0755, true);
$cmd = "tar -czf " . escapeshellarg($bakName) . " -C " . escapeshellarg($uploadsDir) . " trash images 2>/dev/null";
echo "Creating backup archive...\n";
exec($cmd, $out, $rc);
if ($rc !== 0) {
    echo "Warning: backup command returned non-zero (" . $rc . ")\n";
} else {
    echo "Backup created at: $bakName\n";
}

// Load content.json basenames to prefer restoring referenced images
$referenced = [];
if (file_exists($contentFile)) {
    $j = @file_get_contents($contentFile);
    $cdata = $j ? json_decode($j, true) : null;
    if (is_array($cdata)) {
        array_walk_recursive($cdata, function($v, $k) use (&$referenced) {
            if (is_string($v) && $v !== '') {
                $referenced[] = basename($v);
            }
        });
    }
}

// Helper to ensure target directory exists
function ensure_dir($d) {
    if (!is_dir($d)) { @mkdir($d, 0755, true); }
}

$moved = [];
$skipped = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($trashDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    // skip metadata files we wrote earlier
    if (preg_match('/\.json$/i', $path)) { $skipped[] = $path; continue; }
    $fname = $f->getFilename();
    $lower = strtolower($path);
    // infer target folder
    $targetSub = null;
    if (strpos($lower, '/gallery/') !== false || strpos($fname, 'gallery-') === 0) $targetSub = 'gallery';
    elseif (strpos($lower, '/hero/') !== false || strpos($fname, 'hero-') === 0) $targetSub = 'hero';
    elseif (strpos($lower, '/logo/') !== false || strpos($fname, 'logo-') === 0) $targetSub = 'logo';
    elseif (strpos($lower, 'favicon') !== false || strpos($lower, '/favicon-set') !== false) $targetSub = 'favicon-set';
    elseif (strpos($lower, '/thumbs/') !== false || strpos($fname, 'thumb') !== false) $targetSub = 'thumbs';
    else {
        // prefer restoring referenced images from content.json
        if (in_array($fname, $referenced, true)) {
            // try to infer folder name from the referenced entries
            // find first referenced full path that ends with this basename
            foreach ($referenced as $r) {
                if ($r === $fname) { $targetSub = ''; break; }
            }
        }
    }

    if ($targetSub === null) {
        // fallback: place into images/others
        $targetSub = 'others';
    }

    // build dest dir and name
    $destDir = rtrim($imagesDir, '/\\') . '/' . $targetSub;
    ensure_dir($destDir);
    $destPath = $destDir . '/' . $fname;

    // avoid overwriting existing file
    if (file_exists($destPath)) {
        $skipped[] = $path;
        continue;
    }

    // move the file
    if (@rename($path, $destPath)) {
        $moved[] = [$path, $destPath];
    } else {
        // try copy/unlink
        if (@copy($path, $destPath) && @unlink($path)) {
            $moved[] = [$path, $destPath];
        } else {
            $skipped[] = $path;
        }
    }
}

echo "\nRestore summary:\n";
echo "Moved: " . count($moved) . " files\n";
foreach ($moved as $pair) { echo " - " . $pair[0] . " -> " . $pair[1] . "\n"; }
echo "Skipped: " . count($skipped) . " files\n";

// If content.json referenced basenames that were moved, update their paths
if (file_exists($contentFile)) {
    $raw = @file_get_contents($contentFile);
    $content = $raw ? json_decode($raw, true) : null;
    if (is_array($content)) {
        $changed = false;
        // map basename -> moved dest (relative)
        $map = [];
        foreach ($moved as $pair) {
            $src = $pair[0]; $dst = $pair[1];
            $bn = basename($dst);
            // compute relative inside uploads/images
            $rel = str_replace(rtrim($imagesDir, '/\\') . '/', '', $dst);
            $map[$bn] = $rel;
        }
        if (!empty($map)) {
            array_walk_recursive($content, function (&$v, $k) use (&$changed, $map) {
                if (!is_string($v) || $v === '') return;
                $bn = basename($v);
                if (isset($map[$bn]) && $v !== $map[$bn]) { $v = $map[$bn]; $changed = true; }
            });
            if ($changed) {
                $content['last_updated'] = date('Y-m-d H:i:s');
                $out = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($out !== false) {
                    file_put_contents($contentFile . '.tmp', $out, LOCK_EX);
                    @rename($contentFile . '.tmp', $contentFile);
                    echo "Updated content.json with restored filenames.\n";
                }
            }
        }
    }
}

echo "Done. Please verify the moved files are present under uploads/images and that the site/admin shows images.\n";
