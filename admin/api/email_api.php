<?php
// Admin-scoped Email Scheduler API — requires admin login
require_once __DIR__ . '/../../admin/config.php';
require_admin();

header('Content-Type: application/json');

// Initialize scheduler
require_once __DIR__ . '/../../email_scheduler.php';
try { $scheduler = new EmailScheduler(); } catch (Exception $e) { response(['error'=>'Init failed: '.$e->getMessage()],500); }

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// For state-changing requests, verify CSRF token. Token may be sent in
// the 'X-CSRF-Token' header or in the JSON body as 'csrf_token'.
function require_csrf_check() {
    $headers = getallheaders();
    $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? null;
    if (!$token) {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['csrf_token'] ?? null;
    }
    if (!verify_csrf($token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($method) {
        case 'GET': handleGet($scheduler,$action); break;
        case 'POST':
            // POST requests that change state need CSRF
            if (in_array($action, ['campaign','supplier','config','send','test-scrape'])) require_csrf_check();
            handlePost($scheduler,$action);
            break;
        case 'PUT':
            require_csrf_check();
            handlePut($scheduler,$action);
            break;
        case 'DELETE':
            require_csrf_check();
            handleDelete($scheduler,$action);
            break;
        default: response(['error'=>'Method not allowed'],405);
    }
} catch (Exception $e) { response(['error'=>$e->getMessage()],500); }

function handleGet($s,$action){
    switch($action){
            case 'campaigns':
                // support pagination and filtering
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $per = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
                $q = isset($_GET['q']) ? trim($_GET['q']) : null;
                $status = isset($_GET['status']) ? $_GET['status'] : null;
                $res = $s->listCampaigns($page, $per, $q, $status);
                // enrich with suppliers count for UI
                foreach ($res['campaigns'] as &$c) {
                    $cid = $c['id'] ?? null;
                    if ($cid) {
                        $sup = $s->getSuppliersByCampaign($cid);
                        $c['suppliers_count'] = is_array($sup) ? count($sup) : 0;
                    } else {
                        $c['suppliers_count'] = 0;
                    }
                }
                response(['campaigns'=>$res['campaigns'],'total'=>$res['total'],'page'=>$page,'per_page'=>$per]);
                break;
        case 'campaign': $id=$_GET['id']??null; if(!$id) response(['error'=>'Campaign ID required'],400); response(['campaign'=>$s->getCampaign($id)]); break;
        case 'config': $cfg=$s->getEmailConfig(); if($cfg) unset($cfg['email_password']); response(['config'=>$cfg]); break;
        case 'logs': $cid=$_GET['campaign_id']??null; response(['logs'=>$s->getEmailLogs($cid)]); break;
        default: response(['error'=>'Unknown action'],400);
    }
}

function handlePost($s,$action){
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) response(['error'=>'Invalid JSON'],400);
    switch($action){
        case 'campaign':
            // validate
            $errors = [];
            if (empty($data['name']) || strlen(trim($data['name'])) < 2) $errors['name'] = 'Name is required (min 2 chars)';
            if (empty($data['subject']) || strlen(trim($data['subject'])) < 1) $errors['subject'] = 'Subject is required';
            if (empty($data['body']) || strlen(trim($data['body'])) < 1) $errors['body'] = 'Body is required';
            if (empty($data['recipients']) || !is_array($data['recipients']) || count($data['recipients'])==0) $errors['recipients'] = 'At least one recipient required';
            if (empty($data['send_time'])) $errors['send_time'] = 'Send time is required';
            if (!empty($errors)) response(['error'=>'Validation failed','fields'=>$errors],400);
            $id=$s->createCampaign($data); response(['success'=>true,'id'=>$id]); break;
            case 'supplier':
                $cid=$data['campaign_id']??null; if(!$cid) response(['error'=>'Campaign ID required'],400);
                // validate supplier
                $errors = [];
                if (empty($data['name'])) $errors['name'] = 'Supplier name required';
                if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) $errors['url'] = 'Valid URL required';
                if (!isset($data['selectors']) || !is_array($data['selectors'])) $errors['selectors'] = 'Selectors must be an object/array';
                if (!empty($errors)) response(['error'=>'Validation failed','fields'=>$errors],400);
                $ok = $s->addSupplier($cid,$data);
                response(['success'=> (bool)$ok]);
                break;
        case 'supplier': $cid=$data['campaign_id']??null; if(!$cid) response(['error'=>'Campaign ID required'],400); response(['success'=>$s->addSupplier($cid,$data)]); break;
        case 'config':
            $req=['smtp_server','smtp_port','email_address','email_password'];
            $errors = [];
            foreach($req as $r) if(!isset($data[$r]) || $data[$r]==='') $errors[$r] = 'Missing '.$r;
            if (!empty($data['smtp_port']) && !is_numeric($data['smtp_port'])) $errors['smtp_port'] = 'SMTP port must be a number';
            if (!empty($data['email_address']) && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) $errors['email_address'] = 'Invalid email address';
            if (!empty($errors)) response(['error'=>'Validation failed','fields'=>$errors],400);
            response(['success'=>$s->saveEmailConfig($data)]);
            break;
        case 'send': $cid=$data['campaign_id']??null; if(!$cid) response(['error'=>'Campaign ID required'],400); response(['success'=>$s->sendCampaignEmail($cid)]); break;
        case 'test-scrape':
            $url = $data['url'] ?? null;
            $sel = $data['selectors'] ?? null;
            $supplierId = $data['supplier_id'] ?? null;
            $force = !empty($data['force']);
            if (!$url || !$sel) response(['error'=>'URL and selectors required'],400);
            // If supplier id present and force not set, attempt to return cached result
            if ($supplierId && !$force) {
                $cached = $s->getCachedSupplierResult($supplierId);
                if ($cached !== null) response(['success'=>true,'data'=>$cached,'cached'=>true]);
            }
            $r = $s->scrapeSupplierData($url, $sel, $supplierId, $force);
            if ($r === null) response(['success'=>false,'message'=>'Failed to scrape'],400);
            response(['success'=>true,'data'=>$r,'cached'=>false]);
            break;
        default: response(['error'=>'Unknown action'],400);
    }
}

function handlePut($s,$action){
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) response(['error'=>'Invalid JSON'],400);
    switch($action){
        case 'campaign': $id=$_GET['id']??null; if(!$id) response(['error'=>'Campaign ID required'],400); response(['success'=>$s->updateCampaign($id,$data)]); break;
        case 'supplier':
            $id = $_GET['id'] ?? ($data['id'] ?? null); if(!$id) response(['error'=>'Supplier ID required'],400);
            response(['success'=>$s->updateSupplier($id,$data)]);
            break;
        default: response(['error'=>'Unknown action'],400);
    }
}

function handleDelete($s,$action){
    switch($action){
        case 'campaign': $id=$_GET['id']??null; if(!$id) response(['error'=>'Campaign ID required'],400); response(['success'=>$s->deleteCampaign($id)]); break;
        case 'supplier': $id=$_GET['id']??null; if(!$id) response(['error'=>'Supplier ID required'],400); response(['success'=>$s->deleteSupplier($id)]); break;
        default: response(['error'=>'Unknown action'],400);
    }
}

function response($data,$status=200){ http_response_code($status); echo json_encode($data, JSON_PRETTY_PRINT); exit; }

?>
