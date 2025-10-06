<?php
/**
 * admin/reserve.php
 * Public quote handler (non-authenticated). Validates minimal quote
 * fields and appends quote entries to `data/quotes.json`.
 *
 * Contract:
 *  - Inputs: POST { customer_name, company_name, customer_phone, customer_email, container_size, quantity, rental_duration, start_date, delivery_address, access_type, surface_type, access_restrictions, storage_purpose, items_description, services (array), budget_range, special_requests, how_heard, rush_quote }
 *  - Outputs: redirects back to the public site with success or
 *    error query params. No JSON API provided for this endpoint.
 *
 * Notes:
 *  - This endpoint is intentionally simple; for higher-volume sites
 *    consider adding rate-limiting and stronger validation.
 */

require_once __DIR__ . '/config.php';
// No auth required for public quote submissions
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php#storage-quote');
    exit;
}

// Extract quote form fields
$customer = trim($_POST['customer_name'] ?? '');
$company = trim($_POST['company_name'] ?? '');
$phone = trim($_POST['customer_phone'] ?? '');
$email = trim($_POST['customer_email'] ?? '');
$container = trim($_POST['container_size'] ?? '');
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$duration = trim($_POST['rental_duration'] ?? '');
$start_date = trim($_POST['start_date'] ?? '');
$delivery = trim($_POST['delivery_address'] ?? '');
$access_type = trim($_POST['access_type'] ?? '');
$surface_type = trim($_POST['surface_type'] ?? '');
$access_restrictions = trim($_POST['access_restrictions'] ?? '');
$purpose = trim($_POST['storage_purpose'] ?? '');
$items_description = trim($_POST['items_description'] ?? '');
$services = is_array($_POST['services']) ? $_POST['services'] : (isset($_POST['services']) ? [$_POST['services']] : []);
$budget = trim($_POST['budget_range'] ?? '');
$special = trim($_POST['special_requests'] ?? '');
$how_heard = trim($_POST['how_heard'] ?? '');
$rush = !empty($_POST['rush_quote']) && $_POST['rush_quote'] === 'yes';

$errors = [];
if ($customer === '') $errors[] = 'Full name is required';
if ($phone === '') $errors[] = 'Phone is required';
if ($email === '') $errors[] = 'Email is required';
if ($container === '') $errors[] = 'Container size is required';
if ($duration === '') $errors[] = 'Rental duration is required';
if ($delivery === '') $errors[] = 'Delivery address is required';

if (!empty($errors)) {
    $msg = urlencode(implode('; ', $errors));
    header('Location: ../index.php#storage-quote?error=' . $msg);
    exit;
}

// store quotes in a dedicated file
$quoteFile = __DIR__ . '/../data/quotes.json';
if (!is_dir(dirname($quoteFile))) @mkdir(dirname($quoteFile), 0755, true);

// stricter sanitization & normalization
function clean_text($s, $max=200) { $s = trim((string)$s); $s = preg_replace('/\s+/', ' ', $s); return mb_substr($s,0,$max); }
function clean_email($e) { $e = trim((string)$e); return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : ''; }
function clean_phone($p) { $p = trim((string)$p); $digits = preg_replace('/[^0-9+]/','', $p); return mb_substr($digits,0,32); }

$cust_clean = clean_text($customer,200);
$company_clean = clean_text($company,120);
$phone_clean = clean_phone($phone);
$email_clean = clean_email($email);
$container_clean = clean_text($container,40);
$quantity = max(1, min(100, (int)$quantity));
$duration_clean = clean_text($duration,80);
$start_date_clean = $start_date;
if ($start_date_clean !== '') {
    $d = date_create($start_date_clean);
    $start_date_clean = $d ? $d->format('Y-m-d') : '';
}
$delivery_clean = clean_text($delivery,400);
$access_type = clean_text($access_type,80);
$surface_type = clean_text($surface_type,80);
$access_restrictions = clean_text($access_restrictions,500);
$purpose = clean_text($purpose,120);
$items_description = clean_text($items_description,500);
$services = array_values(array_filter(array_map(function($s){ return clean_text($s,80); }, (array)$services)));
$budget = clean_text($budget,80);
$special = clean_text($special,800);
$how_heard = clean_text($how_heard,120);
$rush = $rush ? true : false;

$entry = [
    'customer_name' => $customer,
    'company_name' => $company,
    'phone' => $phone,
    'email' => $email,
    'container_size' => $container,
    'quantity' => $quantity,
    'rental_duration' => $duration,
    'start_date' => $start_date,
    'delivery_address' => $delivery,
    'access_type' => $access_type,
    'surface_type' => $surface_type,
    'access_restrictions' => $access_restrictions,
    'storage_purpose' => $purpose,
    'items_description' => $items_description,
    'services' => $services,
    'budget_range' => $budget,
    'special_requests' => $special,
    'how_heard' => $how_heard,
    'rush_quote' => $rush,
    'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c')),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
];
$entry_clean = [
    'customer_name' => $cust_clean,
    'company_name' => $company_clean,
    'phone' => $phone_clean,
    'email' => $email_clean,
    'container_size' => $container_clean,
    'quantity' => $quantity,
    'rental_duration' => $duration_clean,
    'start_date' => $start_date_clean,
    'delivery_address' => $delivery_clean,
    'access_type' => $access_type,
    'surface_type' => $surface_type,
    'access_restrictions' => $access_restrictions,
    'storage_purpose' => $purpose,
    'items_description' => $items_description,
    'services' => $services,
    'budget_range' => $budget,
    'special_requests' => $special,
    'how_heard' => $how_heard,
    'rush_quote' => $rush,
    'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c')),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

// write to the quotes file
$writeFile = $quoteFile;
$list = [];
if (file_exists($writeFile)) {
    $j = @file_get_contents($writeFile);
    $list = $j ? json_decode($j, true) : [];
    if (!is_array($list)) $list = [];
}
$list[] = $entry_clean;
$json = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json !== false) { file_put_contents($writeFile . '.tmp', $json, LOCK_EX); @rename($writeFile . '.tmp', $writeFile); } else { error_log('reserve.php: failed to encode quotes'); }

// Append to a simple quote audit log so admins can quickly see recent activity
$auditFile = __DIR__ . '/../data/quote-audit.json';
// Attempt to send notification email if configured (prefer PHPMailer SMTP)
$mailSent = false;
// detect if email should be disabled (useful for CLI tests or CI)
$disableMail = (php_sapi_name() === 'cli') || (getenv('DISABLE_EMAILS') === '1');

// send notification email if QUOTE_NOTIFICATION_EMAIL is defined and emails aren't disabled
 $trySend = defined('QUOTE_NOTIFICATION_EMAIL') && !empty(QUOTE_NOTIFICATION_EMAIL);
 if ($trySend && !$disableMail) {
    $to = QUOTE_NOTIFICATION_EMAIL;
    $subject = 'New Storage Quote Request: ' . ($customer ?: 'Unknown');
    $bodyLines = [];
    $bodyLines[] = "New storage quote request received";
    $bodyLines[] = "";
    $bodyLines[] = "Name: {$customer}";
    if ($company !== '') $bodyLines[] = "Company: {$company}";
    $bodyLines[] = "Phone: {$phone}";
    $bodyLines[] = "Email: {$email}";
    $bodyLines[] = "Container size: {$container}";
    $bodyLines[] = "Quantity: {$quantity}";
    $bodyLines[] = "Rental duration: {$duration}";
    if ($start_date !== '') $bodyLines[] = "Start date: {$start_date}";
    $bodyLines[] = "Delivery address: {$delivery}";
    if ($access_type !== '') $bodyLines[] = "Access type: {$access_type}";
    if ($surface_type !== '') $bodyLines[] = "Surface type: {$surface_type}";
    if ($access_restrictions !== '') $bodyLines[] = "Access restrictions: {$access_restrictions}";
    if ($purpose !== '') $bodyLines[] = "Storage purpose: {$purpose}";
    if ($items_description !== '') $bodyLines[] = "Items description: {$items_description}";
    if (!empty($services)) $bodyLines[] = "Requested services: " . implode(', ', $services);
    if ($budget !== '') $bodyLines[] = "Budget range: {$budget}";
    if ($special !== '') $bodyLines[] = "Special requests: {$special}";
    if ($how_heard !== '') $bodyLines[] = "How heard: {$how_heard}";
    $bodyLines[] = "Rush quote: " . ($rush ? 'Yes' : 'No');
    $bodyLines[] = "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '');
    $body = implode("\n", $bodyLines) . "\n";
    $headers = "From: Quotes <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">\r\nReply-To: {$email}\r\nX-Mailer: PHP/" . phpversion();

    // Try PHPMailer via Composer and SMTP settings in admin/config.php
    $cfg = __DIR__ . '/config.php';
    if (file_exists($cfg)) require_once $cfg;
    $smtpAttempted = false;
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        $smtpUser = $GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '');
        $smtpPass = $GLOBALS['SMTP_PASSWORD_OVERRIDE'] ?? (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
        if (!empty($smtpUser) && !empty($smtpPass)) {
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->Port = SMTP_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->setFrom(SMTP_FROM_ADDRESS, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            $mailSent = (bool) $mail->send();
            $smtpAttempted = true;
        } catch (Exception $e) {
            $smtpAttempted = true;
            $mailSent = false;
        }
        }
    }

    // Fallback to mail() if PHPMailer wasn't available or failed
    if (!$smtpAttempted && function_exists('mail')) {
        $mailSent = (bool) @mail($to, $subject, $body, $headers);
    }
}
else {
    // Emails disabled — do not attempt to send. Keep mailSent false for audit.
    $mailSent = false;
}

$auditEntry = [ 'timestamp' => (function_exists('eastern_now') ? eastern_now('c') : date('c')), 'customer_name' => $cust_clean, 'phone' => $phone_clean, 'container_size' => $container_clean, 'quantity' => $quantity, 'rental_duration' => $duration_clean, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'sent' => $mailSent ];
$auditList = [];
if (file_exists($auditFile)) {
    $aj = @file_get_contents($auditFile);
    $auditList = $aj ? json_decode($aj, true) : [];
    if (!is_array($auditList)) $auditList = [];
}
$auditList[] = $auditEntry;
// keep last 200 entries to avoid unbounded growth
if (count($auditList) > 200) $auditList = array_slice($auditList, -200);
$aj = json_encode($auditList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($aj !== false) { file_put_contents($auditFile . '.tmp', $aj, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); } else { error_log('reserve.php: failed to encode audit list'); }

// Redirect back with success anchor and include guest count for confirmation
header('Location: ../index.php#storage-quote?success=1');
exit;
