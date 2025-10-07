<?php
// Helper to run admin/list-images.php in CLI for diagnostics without header issues.
chdir(__DIR__);
// avoid session conflicts in CLI - create a new session array
if (session_status() !== PHP_SESSION_NONE) {
    // destroy any existing session so include won't complain
    session_write_close();
}
$_SESSION = [];
$_SESSION['admin_logged_in'] = true;
// capture output buffer to prevent header() calls from affecting CLI
ob_start();
require __DIR__ . '/admin/list-images.php';
$out = ob_get_clean();
// list-images.php already echoes JSON; print it cleanly
echo $out;
