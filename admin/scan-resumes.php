<?php
/**
 * admin/scan-resumes.php
 * AJAX endpoint to scan private_data/resumes and report suspicious files.
 */
require_once __DIR__ . '/config.php';
require_admin();

// Accept POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($token)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Candidate directories to inspect (same logic used elsewhere)
$candidates = [
    __DIR__ . '/../private_data/resumes/',
    dirname(__DIR__) . '/../private_data/resumes/',
    __DIR__ . '/../../private_data/resumes/',
];

$results = [];

foreach ($candidates as $cand) {
    if (!is_dir($cand)) continue;
    $glob = rtrim($cand, '/') . '/*';
    $files = glob($glob);
    if (!$files) continue;
    foreach ($files as $f) {
        if (!is_file($f)) continue;
        $sz = filesize($f);
        $mime = false;
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) { $mime = finfo_file($fi, $f); finfo_close($fi); }
        }
        $head = @file_get_contents($f, false, null, 0, 16) ?: '';
        $hex = bin2hex($head);

        $suspicious = false;
        $reasons = [];
        if ($sz < 256) { $suspicious = true; $reasons[] = 'too_small'; }
        if (strpos($head, '%PDF-') === 0) { /* pdf ok */ }
        elseif (substr($head,0,4) === "PK\x03\x04") { /* zip/docx ok */ }
        elseif (substr($head,0,4) === "\xD0\xCF\x11\xE0") { /* ole doc ok */ }
        else {
            // unknown signature -> suspicious unless size large enough
            if ($sz < 1024) { $suspicious = true; $reasons[] = 'unknown_signature'; }
        }

        $results[] = [
            'path' => $f,
            'name' => basename($f),
            'size' => $sz,
            'mime' => $mime,
            'hex' => $hex,
            'suspicious' => $suspicious,
            'reasons' => $reasons,
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'files' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
