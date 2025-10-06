<?php
/**
 * Email Scheduler REST API
 * Minimal implementation (update paths and secure before production)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Adjust the require path if email_scheduler.php is in a different location
require_once __DIR__ . '/../email_scheduler.php';

try {
    $scheduler = new EmailScheduler();
} catch (Exception $e) {
    response(['error' => 'Failed to initialize scheduler: ' . $e->getMessage()], 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($method) {
        case 'GET':
            handleGet($scheduler, $action);
            break;
        case 'POST':
            handlePost($scheduler, $action);
            break;
        case 'PUT':
            handlePut($scheduler, $action);
            break;
        case 'DELETE':
            handleDelete($scheduler, $action);
            break;
        default:
            response(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    response(['error' => $e->getMessage()], 500);
}

function handleGet($scheduler, $action) {
    switch ($action) {
        case 'campaigns':
            $campaigns = $scheduler->getCampaigns();
            response(['campaigns' => $campaigns]);
            break;
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if (!$id) response(['error' => 'Campaign ID required'], 400);
            $campaign = $scheduler->getCampaign($id);
            if (!$campaign) response(['error' => 'Campaign not found'], 404);
            response(['campaign' => $campaign]);
            break;
        case 'config':
            $config = $scheduler->getEmailConfig();
            if ($config) unset($config['email_password']);
            response(['config' => $config]);
            break;
        case 'logs':
            $campaignId = $_GET['campaign_id'] ?? null;
            $logs = $scheduler->getEmailLogs($campaignId);
            response(['logs' => $logs]);
            break;
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

function handlePost($scheduler, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        response(['error' => 'Invalid JSON in request body'], 400);
    }

    switch ($action) {
        case 'campaign':
            $required = ['name','subject','body','recipients','send_days','send_time'];
            foreach ($required as $f) if (!isset($data[$f])) response(['error' => "Missing required field: $f"], 400);
            $id = $scheduler->createCampaign($data);
            response(['success' => true, 'id' => $id]);
            break;
        case 'supplier':
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) response(['error' => 'Campaign ID required'], 400);
            $result = $scheduler->addSupplier($campaignId, $data);
            response(['success' => $result]);
            break;
        case 'config':
            $required = ['smtp_server','smtp_port','email_address','email_password'];
            foreach ($required as $f) if (!isset($data[$f])) response(['error' => "Missing required field: $f"], 400);
            $result = $scheduler->saveEmailConfig($data);
            response(['success' => $result]);
            break;
        case 'send':
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) response(['error' => 'Campaign ID required'], 400);
            $result = $scheduler->sendCampaignEmail($campaignId);
            response(['success' => $result]);
            break;
        case 'test-scrape':
            $url = $data['url'] ?? null; $selectors = $data['selectors'] ?? null;
            if (!$url || !$selectors) response(['error' => 'URL and selectors required'], 400);
            $result = $scheduler->scrapeSupplierData($url, $selectors);
            if ($result === null) response(['success'=>false,'message'=>'Failed to scrape website.'],400);
            response(['success'=>true,'data'=>$result]);
            break;
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

function handlePut($scheduler, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) response(['error' => 'Invalid JSON in request body'], 400);

    switch ($action) {
        case 'campaign':
            $id = $_GET['id'] ?? null; if (!$id) response(['error'=>'Campaign ID required'],400);
            $existing = $scheduler->getCampaign($id); if (!$existing) response(['error'=>'Campaign not found'],404);
            $result = $scheduler->updateCampaign($id, $data);
            response(['success'=>$result]);
            break;
        default:
            response(['error'=>'Unknown action'],400);
    }
}

function handleDelete($scheduler, $action) {
    switch ($action) {
        case 'campaign':
            $id = $_GET['id'] ?? null; if (!$id) response(['error'=>'Campaign ID required'],400);
            $existing = $scheduler->getCampaign($id); if (!$existing) response(['error'=>'Campaign not found'],404);
            $result = $scheduler->deleteCampaign($id);
            response(['success'=>$result]);
            break;
        case 'supplier':
            $id = $_GET['id'] ?? null; if (!$id) response(['error'=>'Supplier ID required'],400);
            $result = $scheduler->deleteSupplier($id);
            response(['success'=>$result]);
            break;
        default:
            response(['error'=>'Unknown action'],400);
    }
}

function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

?>
