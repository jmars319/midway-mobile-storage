<?php
/**
 * scripts/purge-old-trash.php
 * Archive and purge trash entries older than a specified number of days.
 * Usage: php purge-old-trash.php [days]
 * Default days: 30
 */

chdir(dirname(__DIR__));
$days = isset($argv[1]) ? (int)$argv[1] : 30;
$uploads = getcwd() . '/uploads';
$trash = $uploads . '/trash';

if (!is_dir($trash)) {
    echo "No trash directory found at $trash\n";
    exit(0);
}

$cutoff = time() - ($days * 86400);

// find entries (files or directories) older than cutoff
$entries = [];
foreach (scandir($trash) as $name) {
    if ($name === '.' || $name === '..') continue;
    $path = $trash . '/' . $name;
    $mtime = filemtime($path);
    if ($mtime === false) continue;
    if ($mtime < $cutoff) $entries[] = $path;
}

if (empty($entries)) {
    echo "No trash entries older than $days days to purge.\n";
    exit(0);
}

@mkdir(getcwd() . '/tmp', 0755, true);
$archive = getcwd() . '/tmp/trash_archive_' . date('Ymd-His') . '.tar.gz';

// build a tar listing of relative paths
$relPaths = '';
foreach ($entries as $e) {
    $relPaths .= ' ' . escapeshellarg('uploads/trash/' . basename($e));
}

// create archive of the entries
$cmd = "tar -czf " . escapeshellarg($archive) . " -C " . escapeshellarg(getcwd()) . $relPaths . " 2>/dev/null";
echo "Creating archive: $archive\n";
exec($cmd, $out, $rc);
if ($rc !== 0) {
    echo "Warning: archive command returned non-zero ($rc). Aborting purge.\n";
    exit(1);
}

// remove archived entries
$deleted = [];
foreach ($entries as $e) {
    // recursive delete
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($e, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    if (is_file($e)) { if (@unlink($e)) $deleted[] = $e; continue; }
    foreach ($it as $file) {
        if ($file->isDir()) @rmdir($file->getPathname()); else @unlink($file->getPathname());
    }
    @rmdir($e);
    $deleted[] = $e;
}

echo "Archived and removed " . count($deleted) . " entries. Archive: $archive\n";

exit(0);
