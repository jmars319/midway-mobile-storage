<?php
// Temporary CLI test runner for admin/reserve.php
// Usage: php cli_reserve_runner.php

putenv('DISABLE_EMAILS=1');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'name' => 'CLI QA',
    'phone' => '+15550002222',
    'email' => 'cliqa@example.com',
    'container_size' => '10x10',
    'start_date' => '2025-10-15',
    'quantity' => '1',
];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

include __DIR__ . '/admin/reserve.php';
echo "runner complete\n";
