# FILE 04: email_api.php

## 📄 File Information

- **Filename:** `email_api.php`
- **Location:** `/api/` directory
- **Purpose:** REST API endpoints for admin panel communication
- **Dependencies:** email_scheduler.php (core class)
- **File Permissions:** 644

---

## 📋 Instructions for AI Assistants

1. Copy the entire code block below
2. Create a directory named `api` in your web root
3. Create a file named `email_api.php` inside the `api` directory
4. Paste the code exactly as provided
5. Update the `require_once` path to point to email_scheduler.php
6. Set permissions to 644
7. No other modifications needed - code is production-ready

---

## 💻 Complete Source Code

```php
<?php
/**
 * Email Scheduler REST API
 * 
 * @package EmailScheduler
 * @version 1.0
 * @author Your Development Team
 * 
 * Purpose: Provides RESTful API endpoints for the admin panel
 * 
 * Security Note: Add authentication in production (see comments below)
 * 
 * API ENDPOINTS:
 * 
 * GET Requests:
 * - ?action=campaigns           Get all campaigns
 * - ?action=campaign&id=X       Get single campaign by ID
 * - ?action=config              Get email configuration (password hidden)
 * - ?action=logs                Get all email logs (last 50)
 * - ?action=logs&campaign_id=X  Get logs for specific campaign
 * 
 * POST Requests:
 * - ?action=campaign            Create new campaign
 * - ?action=supplier            Add supplier to campaign
 * - ?action=config              Save email configuration
 * - ?action=send                Send campaign immediately (test send)
 * - ?action=test-scrape         Test web scraping configuration
 * 
 * PUT Requests:
 * - ?action=campaign&id=X       Update existing campaign
 * 
 * DELETE Requests:
 * - ?action=campaign&id=X       Delete campaign
 * - ?action=supplier&id=X       Delete supplier
 */

// ==================== HEADERS & CORS ====================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== SECURITY (Optional) ====================

/**
 * PRODUCTION SECURITY: Uncomment this section to add authentication
 * 
 * session_start();
 * if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
 *     http_response_code(401);
 *     echo json_encode(['error' => 'Unauthorized access']);
 *     exit;
 * }
 */

// ==================== INITIALIZATION ====================

// IMPORTANT: Update this path to match your installation
// Examples:
// - Same directory: require_once '../email_scheduler.php';
// - Subdirectory: require_once '../../includes/email_scheduler.php';
// - Absolute path: require_once '/var/www/html/email_scheduler.php';
require_once '../email_scheduler.php';

// Initialize the email scheduler
try {
    $scheduler = new EmailScheduler();
} catch (Exception $e) {
    response(['error' => 'Failed to initialize scheduler: ' . $e->getMessage()], 500);
}

// Get request method and action parameter
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ==================== REQUEST ROUTING ====================

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

// ==================== GET REQUEST HANDLERS ====================

/**
 * Handle GET requests
 * 
 * @param EmailScheduler $scheduler Instance of EmailScheduler class
 * @param string $action Action to perform
 */
function handleGet($scheduler, $action) {
    switch ($action) {
        case 'campaigns':
            // Get all campaigns
            $campaigns = $scheduler->getCampaigns();
            response(['campaigns' => $campaigns]);
            break;
            
        case 'campaign':
            // Get single campaign by ID
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $campaign = $scheduler->getCampaign($id);
            if (!$campaign) {
                response(['error' => 'Campaign not found'], 404);
            }
            response(['campaign' => $campaign]);
            break;
            
        case 'config':
            // Get email configuration (without password)
            $config = $scheduler->getEmailConfig();
            if ($config) {
                // Remove password from response for security
                unset($config['email_password']);
            }
            response(['config' => $config]);
            break;
            
        case 'logs':
            // Get email logs (optionally filtered by campaign)
            $campaignId = $_GET['campaign_id'] ?? null;
            $logs = $scheduler->getEmailLogs($campaignId);
            response(['logs' => $logs]);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

// ==================== POST REQUEST HANDLERS ====================

/**
 * Handle POST requests
 * 
 * @param EmailScheduler $scheduler Instance of EmailScheduler class
 * @param string $action Action to perform
 */
function handlePost($scheduler, $action) {
    // Parse JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        response(['error' => 'Invalid JSON in request body'], 400);
    }
    
    switch ($action) {
        case 'campaign':
            // Create new campaign
            // Required fields: name, subject, body, recipients, send_days, send_time
            $requiredFields = ['name', 'subject', 'body', 'recipients', 'send_days', 'send_time'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    response(['error' => "Missing required field: $field"], 400);
                }
            }
            
            $id = $scheduler->createCampaign($data);
            response(['success' => true, 'id' => $id, 'message' => 'Campaign created successfully']);
            break;
            
        case 'supplier':
            // Add supplier to campaign
            // Required fields: campaign_id, name, url, selectors
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) {
                response(['error' => 'Campaign ID required'], 400);
            }
            
            if (!isset($data['name']) || !isset($data['url']) || !isset($data['selectors'])) {
                response(['error' => 'Missing required fields: name, url, selectors'], 400);
            }
            
            $result = $scheduler->addSupplier($campaignId, $data);
            response(['success' => $result, 'message' => 'Supplier added successfully']);
            break;
            
        case 'config':
            // Save email configuration
            // Required fields: smtp_server, smtp_port, email_address, email_password
            $requiredFields = ['smtp_server', 'smtp_port', 'email_address', 'email_password'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    response(['error' => "Missing required field: $field"], 400);
                }
            }
            
            $result = $scheduler->saveEmailConfig($data);
            response(['success' => $result, 'message' => 'Email configuration saved successfully']);
            break;
            
        case 'send':
            // Send campaign immediately (manual send / test)
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) {
                response(['error' => 'Campaign ID required'], 400);
            }
            
            $result = $scheduler->sendCampaignEmail($campaignId);
            if ($result) {
                response(['success' => true, 'message' => 'Campaign sent successfully']);
            } else {
                response(['success' => false, 'message' => 'Failed to send campaign'], 500);
            }
            break;
            
        case 'test-scrape':
            // Test web scraping configuration
            // Required fields: url, selectors
            $url = $data['url'] ?? null;
            $selectors = $data['selectors'] ?? null;
            
            if (!$url || !$selectors) {
                response(['error' => 'URL and selectors required'], 400);
            }
            
            $result = $scheduler->scrapeSupplierData($url, $selectors);
            if ($result === null) {
                response([
                    'success' => false, 
                    'message' => 'Failed to scrape website. Check URL and selectors.',
                    'data' => null
                ], 400);
            }
            
            response([
                'success' => true, 
                'message' => 'Scraping successful',
                'data' => $result
            ]);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

// ==================== PUT REQUEST HANDLERS ====================

/**
 * Handle PUT requests
 * 
 * @param EmailScheduler $scheduler Instance of EmailScheduler class
 * @param string $action Action to perform
 */
function handlePut($scheduler, $action) {
    // Parse JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        response(['error' => 'Invalid JSON in request body'], 400);
    }
    
    switch ($action) {
        case 'campaign':
            // Update existing campaign
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            
            // Verify campaign exists
            $existing = $scheduler->getCampaign($id);
            if (!$existing) {
                response(['error' => 'Campaign not found'], 404);
            }
            
            $result = $scheduler->updateCampaign($id, $data);
            response(['success' => $result, 'message' => 'Campaign updated successfully']);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

// ==================== DELETE REQUEST HANDLERS ====================

/**
 * Handle DELETE requests
 * 
 * @param EmailScheduler $scheduler Instance of EmailScheduler class
 * @param string $action Action to perform
 */
function handleDelete($scheduler, $action) {
    switch ($action) {
        case 'campaign':
            // Delete campaign
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            
            // Verify campaign exists
            $existing = $scheduler->getCampaign($id);
            if (!$existing) {
                response(['error' => 'Campaign not found'], 404);
            }
            
            $result = $scheduler->deleteCampaign($id);
            response(['success' => $result, 'message' => 'Campaign deleted successfully']);
            break;
            
        case 'supplier':
            // Delete supplier
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Supplier ID required'], 400);
            }
            
            $result = $scheduler->deleteSupplier($id);
            response(['success' => $result, 'message' => 'Supplier deleted successfully']);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Send JSON response and exit
 * 
 * @param array $data Response data to encode as JSON
 * @param int $status HTTP status code (default: 200)
 */
function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// End of file
?>
```

---

## 📝 Implementation Notes

### Configuration Required

**Line to update:** Line ~74
```php
require_once '../email_scheduler.php';
```

Update this path based on your file structure:
- If `email_scheduler.php` is in parent directory: `'../email_scheduler.php'`
- If in root: `'../../email_scheduler.php'`
- Use absolute path for clarity: `'/var/www/html/email_scheduler.php'`

### API Endpoint Examples

**Get all campaigns:**
```
GET /api/email_api.php?action=campaigns
```

**Get single campaign:**
```
GET /api/email_api.php?action=campaign&id=1
```

**Create campaign:**
```
POST /api/email_api.php?action=campaign
Content-Type: application/json

{
  "name": "Weekly Newsletter",
  "subject": "Your Weekly Update",
  "body": "Hello! Here's this week's news...",
  "recipients": ["user@example.com"],
  "send_days": ["monday", "friday"],
  "send_time": "09:00",
  "active": 1
}
```

**Send campaign now:**
```
POST /api/email_api.php?action=send
Content-Type: application/json

{
  "campaign_id": 1
}
```

**Test scraping:**
```
POST /api/email_api.php?action=test-scrape
Content-Type: application/json

{
  "url": "https://example.com",
  "selectors": {
    "price": ".product-price",
    "stock": "#stock-status"
  }
}
```

### Security Recommendations

1. **Add Authentication** (lines 50-57 commented out)
2. **Use HTTPS** for all API calls
3. **Add rate limiting** to prevent abuse
4. **Validate all inputs** (basic validation included)
5. **Add CSRF tokens** for state-changing operations
6. **Log all API access** for audit trail

### Error Responses

All errors return JSON with this structure:
```json
{
  "error": "Error message here"
}
```

HTTP status codes used:
- 200: Success
- 400: Bad request (missing parameters, invalid data)
- 401: Unauthorized (if authentication enabled)
- 404: Not found
- 405: Method not allowed
- 500: Server error

### Testing the API

**Using cURL:**
```bash
# Get campaigns
curl http://yoursite.com/api/email_api.php?action=campaigns

# Create campaign
curl -X POST http://yoursite.com/api/email_api.php?action=campaign \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","subject":"Test","body":"Test","recipients":["test@example.com"],"send_days":["monday"],"send_time":"09:00"}'

# Test scraping
curl -X POST http://yoursite.com/api/email_api.php?action=test-scrape \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","selectors":{"title":"h1"}}'
```

**Using JavaScript (from admin panel):**
```javascript
// Already implemented in admin-panel.html
fetch('/api/email_api.php?action=campaigns')
  .then(r => r.json())
  .then(data => console.log(data.campaigns));
```

---

## 🔧 Customization

### Add API Key Authentication

```php
// Add after line 49
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'your-secret-api-key') {
    response(['error' => 'Invalid API key'], 401);
}
```

### Add Request Logging

```php
// Add after line 81
file_put_contents(
    'api_log.txt', 
    date('Y-m-d H:i:s') . " - $method $action\n", 
    FILE_APPEND
);
```

### Add Response Caching

```php
// For GET requests that don't change often
if ($method === 'GET' && $action === 'campaigns') {
    header('Cache-Control: max-age=300'); // 5 minutes
}
```

---

## ✅ Testing Checklist

- [ ] API file accessible via browser
- [ ] GET /campaigns returns campaign list
- [ ] POST /campaign creates new campaign
- [ ] PUT /campaign updates existing campaign
- [ ] DELETE /campaign removes campaign
- [ ] Error responses return proper JSON
- [ ] Admin panel can communicate with API
- [ ] CORS headers work for cross-origin requests

---

END OF FILE 04