<?php
// End-to-end delete test: create a temp image, call admin/delete-image.php
// as an authenticated admin, verify it moved to trash, then restore it.
chdir(__DIR__);

require_once 'admin/config.php';
// config.php will start session if needed
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// mark session as admin
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin';
// ensure password version matches if set
$_SESSION['admin_pw_version'] = get_admin_password_version();

// find a sample existing image to copy
$sample = null;
$searchDirs = [
    __DIR__ . '/uploads/images/gallery',
    __DIR__ . '/uploads/images/hero',
    __DIR__ . '/uploads/images/logo'
];
foreach ($searchDirs as $d) {
    if (!is_dir($d)) continue;
    $it = new DirectoryIterator($d);
    foreach ($it as $f) {
        if ($f->isFile()) { $sample = $f->getPathname(); break 2; }
    }
}
if (!$sample) { echo "No sample image found to test delete.\n"; exit(1); }

// create temp copy
$uniq = time() . '-' . bin2hex(random_bytes(4));
$ext = pathinfo($sample, PATHINFO_EXTENSION);
$tmpRel = 'tmp-delete-' . $uniq . '.' . $ext; // placed in uploads/images root
$tmpPath = __DIR__ . '/uploads/images/' . $tmpRel;
if (!@copy($sample, $tmpPath)) { echo "Failed to copy sample to $tmpPath\n"; exit(1); }

// prepare POST inputs
$_POST = [];
$_POST['filename'] = $tmpRel; // relative path inside uploads/images
$_POST['csrf_token'] = generate_csrf_token();

// set request method so included endpoint treats this as a POST
$_SERVER['REQUEST_METHOD'] = 'POST';

// include delete-image.php and capture JSON output
ob_start();
include 'admin/delete-image.php';
$out = ob_get_clean();
echo "Delete endpoint response:\n";
echo $out . "\n";

// parse response
$parsed = json_decode($out, true);
if (!is_array($parsed)) {
    echo "Did not receive valid JSON from delete endpoint.\n";
    // attempt cleanup
    @unlink($tmpPath);
    exit(1);
}

// check trash for moved file
$trashDir = __DIR__ . '/uploads/trash/';
$found = null;
if (is_dir($trashDir)) {
    $it = new DirectoryIterator($trashDir);
    foreach ($it as $f) {
        if ($f->isFile()) continue; // top-level files may exist; we look into directories too
        if ($f->isDir() && !$f->isDot()) {
            // list files under this directory
            $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f->getPathname(), RecursiveDirectoryIterator::SKIP_DOTS));
            $innerFound = false;
            foreach ($rit as $rf) {
                if (!$rf->isFile()) continue;
                if (strpos($rf->getFilename(), $uniq) !== false) { $found = $rf->getPathname(); $innerFound = true; break; }
            }
            if ($innerFound) break;
        }
    }
}

if ($found) {
    echo "Found trashed file at: $found\n";
    // restore it back to original location
    $restored = $tmpPath; // restore path
    if (@rename($found, $restored)) {
        echo "Restored trashed file back to: $restored\n";
        // remove adjacent metadata file if exists
        if (file_exists($found . '.json')) @unlink($found . '.json');
    } else {
        // try copy/unlink
        if (@copy($found, $restored)) {
            @unlink($found);
            echo "Copied trashed file back to: $restored\n";
            if (file_exists($found . '.json')) @unlink($found . '.json');
        } else {
            echo "Failed to restore trashed file automatically. You may need to restore manually from: $found\n";
        }
    }
} else {
    // maybe delete-image left file in a top-level trash filename
    // check direct trash files
    $bn = basename($tmpRel);
    $direct = null;
    foreach (glob($trashDir . "*{$bn}*") as $g) { $direct = $g; break; }
    if ($direct) {
        echo "Found direct trash file: $direct\n";
        // restore
        if (@rename($direct, $tmpPath)) {
            echo "Restored direct trash file to: $tmpPath\n";
            if (file_exists($direct . '.json')) @unlink($direct . '.json');
        } else {
            echo "Could not restore direct trash file: $direct\n";
        }
    } else {
        echo "No trashed file matching the test was found.\n";
    }
}

// final tidy: ensure temp file is present (restored) or remove if not needed
if (file_exists($tmpPath)) {
    // remove the temp copy now to leave the repo as before
    @unlink($tmpPath);
    echo "Cleaned up temporary test file: $tmpPath\n";
}

echo "Test completed.\n";

?>