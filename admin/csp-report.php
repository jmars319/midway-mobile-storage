<?php
// Simple CSP report endpoint (report-only). Appends incoming JSON to data/csp-reports.log
// NOTE: This endpoint is intentionally minimal. In production you may wish to
// validate, rate-limit, and store reports in a more robust way.

$raw = file_get_contents('php://input');
if (!$raw) { http_response_code(204); exit; }
// Try to decode JSON for readability
$data = json_decode($raw, true);
$logDir = dirname(__DIR__) . '/data';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/csp-reports.log';
$entry = [ 'ts' => date('c'), 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown', 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'report' => $data ?: $raw ];
@file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
http_response_code(204);
exit;
