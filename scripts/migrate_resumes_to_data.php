<?php
// One-time helper: move files from uploads/resumes -> data/resumes
$src = __DIR__ . '/../uploads/resumes';
$dst = __DIR__ . '/../data/resumes';
if (!is_dir($src)) { echo "No uploads/resumes directory found.\n"; exit; }
if (!is_dir($dst)) @mkdir($dst, 0755, true);
$moved = 0;
foreach (scandir($src) as $f) {
    if ($f === '.' || $f === '..') continue;
    $s = $src . '/' . $f;
    if (!is_file($s)) continue;
    $d = $dst . '/' . $f;
    if (@rename($s, $d)) $moved++;
}
echo "Moved {$moved} files.\n";
