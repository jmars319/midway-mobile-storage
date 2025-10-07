<?php
// tmp/browser_delete_test.php
// Access this URL via HTTP to run a browser-level test of admin/delete-image.php
// run from project root so paths to uploads/ are correct
chdir(dirname(__DIR__));
header('Content-Type: text/plain');

require_once 'admin/config.php';
// config.php already starts the session; do not call session_start() again here
// mark session as admin
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_pw_version'] = get_admin_password_version();

// find a sample image
$projectRoot = getcwd();
$sample = null;
$dirs = [$projectRoot . '/uploads/images/gallery', $projectRoot . '/uploads/images/hero', $projectRoot . '/uploads/images/logo'];
foreach ($dirs as $d) {
    if (!is_dir($d)) continue;
    $it = new DirectoryIterator($d);
    foreach ($it as $f) {
        if ($f->isFile()) { $sample = $f->getPathname(); break 2; }
    }
}
if (!$sample) { echo "No sample image found to test delete.\n"; exit(1); }

// create temp copy in uploads/images
$uniq = time() . '-' . bin2hex(random_bytes(4));
$ext = pathinfo($sample, PATHINFO_EXTENSION);
$tmpRel = 'tmp-delete-' . $uniq . '.' . $ext;
$tmpPath = $projectRoot . '/uploads/images/' . $tmpRel;
if (!@copy($sample, $tmpPath)) { echo "Failed to copy sample to $tmpPath\n"; exit(1); }

// perform POST to admin/delete-image.php by including it server-side while simulating POST
$_POST = ['filename' => $tmpRel, 'csrf_token' => generate_csrf_token()];
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
include 'admin/delete-image.php';
$out = ob_get_clean();
echo "Delete endpoint response:\n";
echo $out . "\n";

$parsed = json_decode($out, true);

// try to locate trashed file
$trashDir = $projectRoot . '/uploads/trash/';
$found = null;
if (is_dir($trashDir)) {
    foreach (new DirectoryIterator($trashDir) as $entry) {
        if ($entry->isDot()) continue;
        if ($entry->isFile()) continue;
        // search inside
        $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry->getPathname(), RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rit as $rf) {
            if (!$rf->isFile()) continue;
            if (strpos($rf->getFilename(), $uniq) !== false) { $found = $rf->getPathname(); break 2; }
        }
    }
}

if ($found) {
    echo "Found trashed file at: $found\n";
    // restore
    $restored = $tmpPath;
    if (@rename($found, $restored)) {
        echo "Restored trashed file back to: $restored\n";
        if (file_exists($found . '.json')) @unlink($found . '.json');
    } else {
        if (@copy($found, $restored)) { @unlink($found); echo "Copied trashed file back to: $restored\n"; if (file_exists($found . '.json')) @unlink($found . '.json'); }
        else { echo "Failed to restore trashed file automatically.\n"; }
    }
} else {
    // check direct trash files
    foreach (glob($trashDir . "*{$tmpRel}*") as $g) { $found = $g; break; }
    if ($found) {
        echo "Found direct trash file: $found\n";
        if (@rename($found, $tmpPath)) { echo "Restored direct trash file to: $tmpPath\n"; if (file_exists($found . '.json')) @unlink($found . '.json'); }
        else { echo "Could not restore direct trash file: $found\n"; }
    } else {
        echo "No trashed file matching the test was found.\n";
    }
}

// cleanup temp file
if (file_exists($tmpPath)) { @unlink($tmpPath); echo "Cleaned up temporary test file: $tmpPath\n"; }

echo "Browser-level test complete.\n";

?>
