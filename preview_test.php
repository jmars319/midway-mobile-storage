<?php
$base = realpath(__DIR__ . '/uploads/images');
if (!$base) {
    echo "uploads/images not found at: " . __DIR__ . "/uploads/images\n";
    exit(1);
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $path = $f->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($base) + 1));
    $url = '/uploads/images/' . ltrim($rel, '/');
    printf("%s => %s (exists=%s, size=%d)\n", $rel, $url, file_exists($path) ? 'yes' : 'no', filesize($path));
}
