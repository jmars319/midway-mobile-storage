<?php
// admin/download-all-resumes.php
require_once __DIR__ . '/config.php';
require_admin();

// Create a temporary zip of all archived resumes and stream it to the client.
$archDirCandidates = [
  __DIR__ . '/../private_data/resumes/archived/',
  dirname(__DIR__) . '/../private_data/resumes/archived/',
  __DIR__ . '/../../private_data/resumes/archived/',
];
$archDir = null;
foreach ($archDirCandidates as $c) { if (is_dir($c)) { $archDir = rtrim($c, '/') . '/'; break; } }
if ($archDir === null) {
  // no archive directory — redirect back to listing with a friendly message
  header('Location: admin-resumes.php?msg=no_resumes');
  exit;
}

$files = glob($archDir . '*');
$files = array_filter($files, 'is_file');
if (empty($files)) {
  // no archived resumes — redirect back to listing with a friendly message
  header('Location: admin-resumes.php?msg=no_resumes');
  exit;
}

// Create a temp file for zip
$tmp = tempnam(sys_get_temp_dir(), 'reszip_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { header('HTTP/1.1 500 Internal Server Error'); echo 'Failed to create zip'; exit; }
foreach ($files as $f) {
  $name = basename($f);
  $zip->addFile($f, $name);
}
$zip->close();

$ts = date('Ymd_His');
$fname = "archived-resumes-{$ts}.zip";
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
