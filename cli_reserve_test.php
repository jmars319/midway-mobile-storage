<?php
// cli_reserve_test.php
// Simulate a POST submission to admin/reserve.php from the CLI.
// WARNING: this will include the real handler which may call mail() or other side effects.

// Minimal simulated environment
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$_POST = [
    'customer_name' => 'CLI Test User',
    'company_name' => 'CLI Test Co',
    'customer_phone' => '+1-555-010-0100',
    'customer_email' => 'cli-test@example.com',
    'container_size' => '10x10',
    'quantity' => '1',
    'rental_duration' => 'Monthly',
    'start_date' => date('Y-m-d'),
    'delivery_address' => '123 CLI Lane',
    'access_type' => 'Drive-up',
    'surface_type' => 'Gravel',
    'access_restrictions' => 'None',
    'storage_purpose' => 'Test',
    'items_description' => 'Boxes',
    'services' => ['delivery'],
    'budget_range' => '$100-200',
    'special_requests' => 'None',
    'how_heard' => 'CLI',
    'rush_quote' => 'yes'
];

// Run the handler from the project root
chdir(__DIR__);
// Include the handler. It will perform its write and then exit/redirect.
include __DIR__ . '/admin/reserve.php';

// If the handler ever returns, print a message (it usually exits after redirect)
echo "Handler returned without exit.\n";
