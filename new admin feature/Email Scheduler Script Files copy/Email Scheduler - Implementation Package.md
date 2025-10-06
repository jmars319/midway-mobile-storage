# Email Scheduler System - Implementation Package

## 📋 IMPLEMENTATION CHECKLIST

### Pre-Implementation Requirements
- [ ] PHP 7.4 or higher installed
- [ ] SQLite3 extension enabled
- [ ] cURL extension enabled
- [ ] DOM/XML extension enabled
- [ ] Cron job access available
- [ ] SMTP email credentials ready

### Implementation Steps
1. [ ] Create directory structure
2. [ ] Upload File 1: email_scheduler.php
3. [ ] Upload File 2: email_api.php
4. [ ] Upload File 3: send_scheduled_emails.php
5. [ ] Upload File 4: email-scheduler.html
6. [ ] Set file permissions
7. [ ] Configure API path in HTML file
8. [ ] Set up cron job
9. [ ] Test email configuration
10. [ ] Create test campaign

---

## 📁 DIRECTORY STRUCTURE

```
/your-website-root/
│
├── email_scheduler.php              ← FILE 1 (Core class)
│
├── api/
│   └── email_api.php                ← FILE 2 (REST API)
│
├── cron/
│   └── send_scheduled_emails.php    ← FILE 3 (Cron job)
│
└── admin/
    └── email-scheduler.html         ← FILE 4 (Admin UI)
```

---

## 📄 FILE 1: email_scheduler.php
**Location:** `/email_scheduler.php` (root or appropriate directory)  
**Purpose:** Core class handling all email scheduling logic

```php
<?php
/**
 * Email Scheduler Core Class
 * Version: 1.0
 * Purpose: Handles email campaigns, scheduling, and supplier scraping
 * Dependencies: PDO, SQLite, cURL, DOMDocument
 */

class EmailScheduler {
    private $db;
    
    /**
     * Constructor - Initializes database connection
     * @param string $dbPath - Path to SQLite database file
     */
    public function __construct($dbPath = 'email_scheduler.sqlite') {
        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }
    
    /**
     * Initialize database tables
     * Creates all necessary tables if they don't exist
     */
    private function initDatabase() {
        // Campaigns table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                recipients TEXT NOT NULL,
                send_days TEXT NOT NULL,
                send_time TEXT NOT NULL,
                active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Suppliers table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                selectors TEXT NOT NULL,
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
            )
        ");
        
        // Email configuration table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_config (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                smtp_server TEXT NOT NULL,
                smtp_port INTEGER NOT NULL,
                email_address TEXT NOT NULL,
                email_password TEXT NOT NULL
            )
        ");
        
        // Email logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status TEXT,
                message TEXT,
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
            )
        ");
    }
    
    /* ==================== CAMPAIGN MANAGEMENT ==================== */
    
    /**
     * Create a new campaign
     * @param array $data - Campaign data (name, subject, body, recipients, send_days, send_time, active)
     * @return int - Campaign ID
     */
    public function createCampaign($data) {
        $stmt = $this->db->prepare("
            INSERT INTO campaigns (name, subject, body, recipients, send_days, send_time, active)
            VALUES (:name, :subject, :body, :recipients, :send_days, :send_time, :active)
        ");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':subject' => $data['subject'],
            ':body' => $data['body'],
            ':recipients' => json_encode($data['recipients']),
            ':send_days' => json_encode($data['send_days']),
            ':send_time' => $data['send_time'],
            ':active' => $data['active'] ?? 1
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get all campaigns
     * @return array - Array of campaigns with decoded JSON fields
     */
    public function getCampaigns() {
        $stmt = $this->db->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        $campaigns = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['recipients'] = json_decode($row['recipients'], true);
            $row['send_days'] = json_decode($row['send_days'], true);
            $campaigns[] = $row;
        }
        
        return $campaigns;
    }
    
    /**
     * Get a single campaign by ID
     * @param int $id - Campaign ID
     * @return array|false - Campaign data or false if not found
     */
    public function getCampaign($id) {
        $stmt = $this->db->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($campaign) {
            $campaign['recipients'] = json_decode($campaign['recipients'], true);
            $campaign['send_days'] = json_decode($campaign['send_days'], true);
            $campaign['suppliers'] = $this->getSuppliersByCampaign($id);
        }
        
        return $campaign;
    }
    
    /**
     * Update an existing campaign
     * @param int $id - Campaign ID
     * @param array $data - Updated campaign data
     * @return bool - Success status
     */
    public function updateCampaign($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE campaigns 
            SET name = :name, subject = :subject, body = :body, 
                recipients = :recipients, send_days = :send_days, 
                send_time = :send_time, active = :active
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':subject' => $data['subject'],
            ':body' => $data['body'],
            ':recipients' => json_encode($data['recipients']),
            ':send_days' => json_encode($data['send_days']),
            ':send_time' => $data['send_time'],
            ':active' => $data['active'] ?? 1
        ]);
    }
    
    /**
     * Delete a campaign
     * @param int $id - Campaign ID
     * @return bool - Success status
     */
    public function deleteCampaign($id) {
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /* ==================== SUPPLIER MANAGEMENT ==================== */
    
    /**
     * Add a supplier to a campaign
     * @param int $campaignId - Campaign ID
     * @param array $data - Supplier data (name, url, selectors)
     * @return bool - Success status
     */
    public function addSupplier($campaignId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO suppliers (campaign_id, name, url, selectors)
            VALUES (:campaign_id, :name, :url, :selectors)
        ");
        
        return $stmt->execute([
            ':campaign_id' => $campaignId,
            ':name' => $data['name'],
            ':url' => $data['url'],
            ':selectors' => json_encode($data['selectors'])
        ]);
    }
    
    /**
     * Get all suppliers for a campaign
     * @param int $campaignId - Campaign ID
     * @return array - Array of suppliers
     */
    public function getSuppliersByCampaign($campaignId) {
        $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $suppliers = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['selectors'] = json_decode($row['selectors'], true);
            $suppliers[] = $row;
        }
        
        return $suppliers;
    }
    
    /**
     * Delete a supplier
     * @param int $id - Supplier ID
     * @return bool - Success status
     */
    public function deleteSupplier($id) {
        $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /* ==================== EMAIL CONFIGURATION ==================== */
    
    /**
     * Save email configuration
     * @param array $config - SMTP configuration (smtp_server, smtp_port, email_address, email_password)
     * @return bool - Success status
     */
    public function saveEmailConfig($config) {
        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO email_config (id, smtp_server, smtp_port, email_address, email_password)
            VALUES (1, :smtp_server, :smtp_port, :email_address, :email_password)
        ");
        
        return $stmt->execute([
            ':smtp_server' => $config['smtp_server'],
            ':smtp_port' => $config['smtp_port'],
            ':email_address' => $config['email_address'],
            ':email_password' => $config['email_password']
        ]);
    }
    
    /**
     * Get email configuration
     * @return array|false - Email configuration or false if not set
     */
    public function getEmailConfig() {
        $stmt = $this->db->query("SELECT * FROM email_config WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /* ==================== WEB SCRAPING ==================== */
    
    /**
     * Scrape data from supplier website
     * @param string $url - Website URL
     * @param array $selectors - CSS selectors map (e.g., ['price' => '.price-class'])
     * @return array|null - Scraped data or null on error
     */
    public function scrapeSupplierData($url, $selectors) {
        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_TIMEOUT => 10
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Check for errors
        if ($httpCode != 200 || !$html) {
            return null;
        }
        
        // Parse HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Extract data using selectors
        $data = [];
        foreach ($selectors as $key => $selector) {
            $xpathQuery = $this->cssToXpath($selector);
            $nodes = $xpath->query($xpathQuery);
            
            if ($nodes->length > 0) {
                $data[$key] = trim($nodes->item(0)->textContent);
            } else {
                $data[$key] = 'N/A';
            }
        }
        
        return $data;
    }
    
    /**
     * Convert CSS selector to XPath (basic conversion)
     * @param string $css - CSS selector
     * @return string - XPath query
     */
    private function cssToXpath($css) {
        $css = trim($css);
        
        if (strpos($css, '#') === 0) {
            // ID selector: #elementId
            return "//*[@id='" . substr($css, 1) . "']";
        } elseif (strpos($css, '.') === 0) {
            // Class selector: .className
            return "//*[contains(@class, '" . substr($css, 1) . "')]";
        } else {
            // Element selector: div, span, etc.
            return "//" . $css;
        }
    }
    
    /* ==================== EMAIL SENDING ==================== */
    
    /**
     * Send email for a campaign
     * @param int $campaignId - Campaign ID
     * @return bool - Success status
     */
    public function sendCampaignEmail($campaignId) {
        $campaign = $this->getCampaign($campaignId);
        $config = $this->getEmailConfig();
        
        if (!$campaign || !$config) {
            $this->logEmail($campaignId, 'error', 'Campaign or email config not found');
            return false;
        }
        
        try {
            // Scrape supplier data if configured
            $supplierData = [];
            foreach ($campaign['suppliers'] as $supplier) {
                $data = $this->scrapeSupplierData($supplier['url'], $supplier['selectors']);
                if ($data) {
                    $supplierData[$supplier['name']] = $data;
                }
            }
            
            // Build email body with supplier data
            $body = $campaign['body'];
            if (!empty($supplierData)) {
                $body .= "\n\n--- Supplier Information ---\n";
                foreach ($supplierData as $name => $data) {
                    $body .= "\n$name:\n";
                    foreach ($data as $key => $value) {
                        $body .= "  $key: $value\n";
                    }
                }
            }
            
            // Send email
            $this->sendEmail($config, $campaign['recipients'], $campaign['subject'], $body);
            
            $this->logEmail($campaignId, 'success', 'Email sent successfully');
            return true;
            
        } catch (Exception $e) {
            $this->logEmail($campaignId, 'error', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP mail() function
     * Note: For production, consider using PHPMailer for better deliverability
     * @param array $config - SMTP configuration
     * @param array $recipients - Array of email addresses
     * @param string $subject - Email subject
     * @param string $body - Email body
     */
    private function sendEmail($config, $recipients, $subject, $body) {
        $headers = "From: {$config['email_address']}\r\n";
        $headers .= "Reply-To: {$config['email_address']}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        foreach ($recipients as $recipient) {
            mail($recipient, $subject, $body, $headers);
        }
    }
    
    /* ==================== LOGGING ==================== */
    
    /**
     * Log email sending attempt
     * @param int $campaignId - Campaign ID
     * @param string $status - 'success' or 'error'
     * @param string $message - Log message
     */
    private function logEmail($campaignId, $status, $message) {
        $stmt = $this->db->prepare("
            INSERT INTO email_logs (campaign_id, status, message)
            VALUES (:campaign_id, :status, :message)
        ");
        
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':status' => $status,
            ':message' => $message
        ]);
    }
    
    /**
     * Get email logs
     * @param int|null $campaignId - Optional campaign ID filter
     * @return array - Array of log entries
     */
    public function getEmailLogs($campaignId = null) {
        if ($campaignId) {
            $stmt = $this->db->prepare("SELECT * FROM email_logs WHERE campaign_id = ? ORDER BY sent_at DESC LIMIT 50");
            $stmt->execute([$campaignId]);
        } else {
            $stmt = $this->db->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 50");
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// End of file
?>
```

**File Permissions:** `644` (readable by web server)

---

## 📄 FILE 2: email_api.php
**Location:** `/api/email_api.php`  
**Purpose:** REST API endpoints for admin panel

```php
<?php
/**
 * Email Scheduler REST API
 * Version: 1.0
 * Purpose: Provides RESTful API endpoints for the admin panel
 * 
 * API ENDPOINTS:
 * GET  ?action=campaigns           - Get all campaigns
 * GET  ?action=campaign&id=X       - Get single campaign
 * GET  ?action=config              - Get email configuration
 * GET  ?action=logs                - Get email logs
 * GET  ?action=logs&campaign_id=X  - Get logs for specific campaign
 * 
 * POST ?action=campaign            - Create new campaign
 * POST ?action=supplier            - Add supplier to campaign
 * POST ?action=config              - Save email configuration
 * POST ?action=send                - Send campaign immediately
 * POST ?action=test-scrape         - Test web scraping
 * 
 * PUT  ?action=campaign&id=X       - Update campaign
 * 
 * DELETE ?action=campaign&id=X     - Delete campaign
 * DELETE ?action=supplier&id=X     - Delete supplier
 */

// CORS and headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// IMPORTANT: Update this path to match your installation
require_once '../email_scheduler.php';

// Initialize scheduler
$scheduler = new EmailScheduler();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_GET['action']) ? $_GET['action'] : '';

// Route requests
try {
    switch ($method) {
        case 'GET':
            handleGet($scheduler, $request);
            break;
        case 'POST':
            handlePost($scheduler, $request);
            break;
        case 'PUT':
            handlePut($scheduler, $request);
            break;
        case 'DELETE':
            handleDelete($scheduler, $request);
            break;
        default:
            response(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    response(['error' => $e->getMessage()], 500);
}

/* ==================== REQUEST HANDLERS ==================== */

/**
 * Handle GET requests
 */
function handleGet($scheduler, $action) {
    switch ($action) {
        case 'campaigns':
            $campaigns = $scheduler->getCampaigns();
            response(['campaigns' => $campaigns]);
            break;
            
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $campaign = $scheduler->getCampaign($id);
            response(['campaign' => $campaign]);
            break;
            
        case 'config':
            $config = $scheduler->getEmailConfig();
            // Don't send password back to client
            if ($config) {
                unset($config['email_password']);
            }
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

/**
 * Handle POST requests
 */
function handlePost($scheduler, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'campaign':
            $id = $scheduler->createCampaign($data);
            response(['success' => true, 'id' => $id]);
            break;
            
        case 'supplier':
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $result = $scheduler->addSupplier($campaignId, $data);
            response(['success' => $result]);
            break;
            
        case 'config':
            $result = $scheduler->saveEmailConfig($data);
            response(['success' => $result]);
            break;
            
        case 'send':
            $campaignId = $data['campaign_id'] ?? null;
            if (!$campaignId) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $result = $scheduler->sendCampaignEmail($campaignId);
            response(['success' => $result]);
            break;
            
        case 'test-scrape':
            $url = $data['url'] ?? null;
            $selectors = $data['selectors'] ?? null;
            if (!$url || !$selectors) {
                response(['error' => 'URL and selectors required'], 400);
            }
            $result = $scheduler->scrapeSupplierData($url, $selectors);
            response(['success' => true, 'data' => $result]);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

/**
 * Handle PUT requests
 */
function handlePut($scheduler, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $result = $scheduler->updateCampaign($id, $data);
            response(['success' => $result]);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete($scheduler, $action) {
    switch ($action) {
        case 'campaign':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Campaign ID required'], 400);
            }
            $result = $scheduler->deleteCampaign($id);
            response(['success' => $result]);
            break;
            
        case 'supplier':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                response(['error' => 'Supplier ID required'], 400);
            }
            $result = $scheduler->deleteSupplier($id);
            response(['success' => $result]);
            break;
            
        default:
            response(['error' => 'Unknown action'], 400);
    }
}

/**
 * Send JSON response and exit
 * @param array $data - Response data
 * @param int $status - HTTP status code
 */
function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// End of file
?>
```

**File Permissions:** `644` (readable by web server)

---

## 📄 FILE 3: send_scheduled_emails.php
**Location:** `/cron/send_scheduled_emails.php`  
**Purpose:** Cron job script that checks and sends scheduled emails

```php
<?php
/**
 * Cron Job - Send Scheduled Emails
 * Version: 1.0
 * Purpose: Checks for campaigns scheduled to send and sends them
 * 
 * SETUP INSTRUCTIONS:
 * Add to crontab to run every minute:
 * * * * * * /usr/bin/php /path/to/your/website/cron/send_scheduled_emails.php
 * 
 * OR in cPanel:
 * * * * * * /usr/bin/php /home/username/public_html/cron/send_scheduled_emails.php
 */

// IMPORTANT: Update this path to match your installation
require_once __DIR__ . '/../email_scheduler.php';

class CronEmailSender {
    private $scheduler;
    private $logFile;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->scheduler = new EmailScheduler();
        $this->logFile = __DIR__ . '/cron.log';
    }
    
    /**
     * Write to log file
     * @param string $message - Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Main cron job function
     * Checks all active campaigns and sends if scheduled
     */
    public function checkAndSendEmails() {
        $this->log("=== Cron job started ===");
        
        // Get current day and time
        $currentDay = strtolower(date('l')); // monday, tuesday, etc.
        $currentTime = date('H:i');
        
        $this->log("Current day: $currentDay, Current time: $currentTime");
        
        // Get all active campaigns
        $campaigns = $this->scheduler->getCampaigns();
        $this->log("Found " . count($campaigns) . " total campaigns");
        
        $sentCount = 0;
        
        foreach ($campaigns as $campaign) {
            // Skip inactive campaigns
            if (!$campaign['active']) {
                $this->log("Campaign #{$campaign['id']} '{$campaign['name']}' is inactive, skipping");
                continue;
            }
            
            // Check if today is a scheduled day
            if (!in_array($currentDay, $campaign['send_days'])) {
                $this->log("Campaign #{$campaign['id']} '{$campaign['name']}' not scheduled for $currentDay");
                continue;
            }
            
            // Check if current time matches send time
            if ($this->isTimeToSend($currentTime, $campaign['send_time'])) {
                $this->log("TIME TO SEND: Campaign #{$campaign['id']} '{$campaign['name']}'");
                
                try {
                    $result = $this->scheduler->sendCampaignEmail($campaign['id']);
                    
                    if ($result) {
                        $this->log("SUCCESS: Campaign #{$campaign['id']} '{$campaign['name']}' sent successfully");
                        $sentCount++;
                    } else {
                        $this->log("FAILED: Campaign #{$campaign['id']} '{$campaign['name']}' failed to send");
                    }
                } catch (Exception $e) {
                    $this->log("ERROR: Campaign #{$campaign['id']} - " . $e->getMessage());
                }
            } else {
                $this->log("Campaign #{$campaign['id']} '{$campaign['name']}' scheduled for {$campaign['send_time']}, not $currentTime");
            }
        }
        
        $this->log("=== Cron job completed - Sent $sentCount emails ===\n");
    }
    
    /**
     * Check if current time matches scheduled time
     * Allows 1-minute window to account for cron timing
     * @param string $currentTime - Current time in H:i format
     * @param string $sendTime - Scheduled time in H:i format
     * @return bool - True if time to send
     */
    private function isTimeToSend($currentTime, $sendTime) {
        $current = strtotime($currentTime);
        $scheduled = strtotime($sendTime);
        
        // Allow 1-minute window
        $diff = abs($current - $scheduled);
        return $diff < 60;
    }
}

// Execute the cron job
$sender = new CronEmailSender();
$sender->checkAndSendEmails();

// End of file
?>
```

**File Permissions:** `644` (readable by web server and cron)

---

## 📄 FILE 4: email-scheduler.html
**Location:** `/admin/email-scheduler.html`  
**Purpose:** Web-based admin panel for managing email campaigns

**IMPORTANT:** Before uploading, update line 428 with your API path:
```javascript
const API_URL = '/api/email_api.php'; // Update this to match your setup
```

This file is too large to display here in full. Please refer to the complete HTML file provided separately. It contains:
- Modern responsive UI
- Campaign management interface
- Email configuration form
- Supplier management
- Email logs viewer
- All JavaScript for API communication

**File Permissions:** `644` (readable by web server)

---

## 🔧 POST-INSTALLATION CONFIGURATION

### Step 1: Set File Permissions

```bash
# Navigate to your website root
cd /path/to/your/website

# Set permissions for PHP files
chmod 644 email_scheduler.php
chmod 644 api/email_api.php
chmod 644 cron/send_scheduled_emails.php
chmod 644 admin/email-scheduler.html

# Set permissions for directories
chmod 755 api/
chmod 755 cron/
chmod 755 admin/

# Database will be created automatically
# After first run, set permissions:
chmod 666 email_scheduler.sqlite
```

### Step 2: Update API Path

In `admin/email-scheduler.html`, find and update:

```javascript
const API_URL = '/api/email_api.php';
```

Change to match your installation path:
- If in root: `/api/email_api.php`
- If in subdirectory: `/mywebsite/api/email_api.php`
- If on subdomain: `https://admin.yoursite.com/api/email_api.php`

### Step 3: Configure Cron Job

**Option A: cPanel**
1. Login to cPanel
2. Go to "Cron Jobs"
3. Add new cron job:
   - Common Settings: Every minute (* * * * *)
   - Command: `/usr/bin/php /home/username/public_html/cron/send_scheduled_emails.php`

**Option B: SSH/Terminal**
```bash
crontab -e
```
Add this line:
```
* * * * * /usr/bin/php /path/to/your/website/cron/send_scheduled_emails.php
```

**Option C: Plesk**
1. Go to "Scheduled Tasks"
2. Add new task
3. Command: `php /var/www/vhosts/yoursite.com/httpdocs/cron/send_scheduled_emails.php`
4. Schedule: Every minute

### Step 4: Test the Installation

1. Open admin panel: `https://yourwebsite.com/admin/email-scheduler.html`
2. Go to "Email Config" tab
3. Enter SMTP settings:
   - Gmail: smtp.gmail.com, port 587, use App Password
   - Other: Check your email provider's SMTP settings
4. Create a test campaign:
   - Name: "Test Campaign"
   - Subject: "Test Email"
   - Body: "This is a test"
   - Recipients: Your email address
   - Days: Today's day
   - Time: 2 minutes from now
5. Wait and check your email
6. Check "Email Logs" tab for status

---

## 🔍 VERIFICATION CHECKLIST

After implementation, verify:

- [ ] All 4 files uploaded to correct locations
- [ ] File permissions set correctly
- [ ] Database file (email_scheduler.sqlite) created automatically
- [ ] Admin panel accessible in browser
- [ ] API endpoint responding (check browser console)
- [ ] Cron job added and running
- [ ] Email configuration saved successfully
- [ ] Test email sent successfully
- [ ] Email appears in logs

---

## 🚨 TROUBLESHOOTING GUIDE

### Issue: "Call to undefined function curl_init()"
**Solution:** Enable cURL extension in php.ini
```bash
# Ubuntu/Debian
sudo apt-get install php-curl
sudo service apache2 restart

# cPanel
Enable in PHP Selector or MultiPHP Manager
```

### Issue: "Class 'DOMDocument' not found"
**Solution:** Enable DOM/XML extension
```bash
# Ubuntu/Debian
sudo apt-get install php-xml
sudo service apache2 restart
```

### Issue: "Unable to open database file"
**Solution:** Fix directory permissions
```bash
chmod 755 /path/to/directory
chmod 666 email_scheduler.sqlite
```

### Issue: "SMTP connect() failed"
**Solution:** 
- Verify SMTP server and port
- Use App Password for Gmail (not regular password)
- Check firewall allows outbound SMTP connections
- Test with telnet: `telnet smtp.gmail.com 587`

### Issue: Cron job not running
**Solution:** Check cron logs
```bash
# View cron execution
tail -f /var/log/cron

# View our cron log
tail -f /path/to/cron/cron.log

# Test manually
php /path/to/cron/send_scheduled_emails.php
```

### Issue: Emails not sending at scheduled time
**Solution:**
- Check cron.log for errors
- Verify campaign is active
- Verify send_days includes current day
- Verify send_time matches current time
- Run cron manually to test

### Issue: Web scraping returns "N/A"
**Solution:**
- Verify CSS selectors are correct
- Website may use JavaScript (consider alternative approaches)
- Website may block scrapers (check robots.txt)
- Test selectors with browser DevTools

---

## 📊 MONITORING & MAINTENANCE

### Daily Checks
1. Review Email Logs in admin panel
2. Check cron.log file for errors:
   ```bash
   tail -50 /path/to/cron/cron.log
   ```

### Weekly Maintenance
1. Back up database:
   ```bash
   cp email_scheduler.sqlite email_scheduler_backup_$(date +%Y%m%d).sqlite
   ```
2. Review campaign performance
3. Clean old logs if needed:
   ```sql
   DELETE FROM email_logs WHERE sent_at < datetime('now', '-30 days');
   ```

### Monthly Tasks
1. Review and update supplier selectors (websites change)
2. Test all active campaigns
3. Update recipient lists as needed
4. Archive old campaigns

---

## 🔐 SECURITY RECOMMENDATIONS

### 1. Protect Admin Panel

Create `.htaccess` in `/admin/` directory:
```apache
AuthType Basic
AuthName "Restricted Access"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

Create `.htpasswd` file:
```bash
htpasswd -c /path/to/.htpasswd admin_username
```

### 2. Protect API Endpoint

Add to top of `api/email_api.php`:
```php
<?php
session_start();

// Simple authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
?>
```

### 3. Move Database Outside Web Root

Update in `email_scheduler.php`:
```php
public function __construct($dbPath = '/var/secure/email_scheduler.sqlite')
```

### 4. Use Environment Variables for Sensitive Data

Create `.env` file (outside web root):
```
SMTP_SERVER=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
```

### 5. Enable HTTPS

Ensure admin panel and API use HTTPS only:
```apache
# In .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 📈 OPTIONAL ENHANCEMENTS

### Enhancement 1: Use PHPMailer for Better Deliverability

Install PHPMailer:
```bash
composer require phpmailer/phpmailer
```

Update `sendEmail()` method in `email_scheduler.php`:
```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

private function sendEmail($config, $recipients, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_server'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_address'];
        $mail->Password = $config['email_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];
        
        // Sender
        $mail->setFrom($config['email_address']);
        
        // Recipients
        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
    } catch (Exception $e) {
        throw new Exception("Email could not be sent. Error: {$mail->ErrorInfo}");
    }
}
```

### Enhancement 2: Add Email Templates

Create `templates/` directory with template files:

```php
// In email_scheduler.php, add method:
public function loadTemplate($templateName, $variables) {
    $templatePath = __DIR__ . "/templates/{$templateName}.html";
    if (!file_exists($templatePath)) {
        return null;
    }
    
    $template = file_get_contents($templatePath);
    
    foreach ($variables as $key => $value) {
        $template = str_replace("{{" . $key . "}}", $value, $template);
    }
    
    return $template;
}
```

### Enhancement 3: Add Attachment Support

Update campaign table:
```sql
ALTER TABLE campaigns ADD COLUMN attachments TEXT;
```

Store attachment paths as JSON and include in emails.

### Enhancement 4: Add Email Preview

Add to API:
```php
case 'preview':
    $campaignId = $_GET['id'] ?? null;
    $campaign = $scheduler->getCampaign($campaignId);
    // Build email body with supplier data
    // Return preview HTML
    break;
```

### Enhancement 5: Add Statistics Dashboard

Track opens/clicks by adding tracking parameters:
```php
// Add tracking pixel to emails
$trackingPixel = "<img src='https://yoursite.com/track.php?c={$campaignId}&r={$recipientId}' width='1' height='1'>";
```

---

## 🎓 USAGE EXAMPLES

### Example 1: Weekly Newsletter
```javascript
// Create campaign via API
fetch('/api/email_api.php?action=campaign', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    name: 'Weekly Newsletter',
    subject: 'This Week in Business',
    body: 'Hello!\n\nHere are this week\'s updates...',
    recipients: ['subscriber1@example.com', 'subscriber2@example.com'],
    send_days: ['monday'],
    send_time: '09:00',
    active: 1
  })
});
```

### Example 2: Daily Price Alerts with Supplier Data
```javascript
// Create campaign with supplier scraping
const campaignData = {
  name: 'Daily Price Alert',
  subject: 'Today\'s Supplier Prices',
  body: 'Good morning!\n\nHere are today\'s prices from our suppliers:',
  recipients: ['purchasing@company.com'],
  send_days: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
  send_time: '08:00',
  active: 1
};

// Create campaign
const response = await fetch('/api/email_api.php?action=campaign', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify(campaignData)
});

const result = await response.json();
const campaignId = result.id;

// Add supplier
await fetch('/api/email_api.php?action=supplier', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    campaign_id: campaignId,
    name: 'Supplier ABC',
    url: 'https://supplierabc.com/products/widget',
    selectors: {
      'Product Name': 'h1.product-title',
      'Current Price': '.price-current',
      'Stock Status': '.stock-status',
      'Lead Time': '.lead-time'
    }
  })
});
```

### Example 3: Test Web Scraping Before Adding Supplier
```javascript
// Test scraping before adding to campaign
fetch('/api/email_api.php?action=test-scrape', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    url: 'https://example.com/product',
    selectors: {
      'price': '.product-price',
      'stock': '#availability'
    }
  })
})
.then(r => r.json())
.then(data => {
  console.log('Scraped data:', data.data);
  // Output: { price: '$99.99', stock: 'In Stock' }
});
```

---

## 📞 SUPPORT & DOCUMENTATION

### Finding CSS Selectors
1. Right-click element on webpage
2. Click "Inspect" or "Inspect Element"
3. In DevTools, right-click the HTML element
4. Select "Copy" → "Copy selector"
5. Use that selector in your configuration

### Common CSS Selector Patterns
- Class: `.product-price`
- ID: `#price-display`
- Element: `span`
- Attribute: `[data-price]`
- Multiple classes: `.product.price.current`

### Gmail App Password Setup
1. Go to Google Account settings
2. Security → 2-Step Verification (enable if not already)
3. App passwords
4. Select "Mail" and your device
5. Use generated password in Email Config

### Testing Email Delivery
Use a test email service:
- [MailTrap.io](https://mailtrap.io/) - Catches test emails
- [SendGrid](https://sendgrid.com/) - Free tier available
- [Mailgun](https://www.mailgun.com/) - Developer-friendly

### Debugging Tips
1. Check PHP error log: `/var/log/apache2/error.log`
2. Enable error reporting (development only):
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```
3. Check browser console for JavaScript errors
4. Use `var_dump()` to debug PHP variables
5. Check cron.log for scheduling issues

---

## 📋 IMPLEMENTATION TIMELINE

**Day 1: Setup**
- [ ] Upload all 4 files
- [ ] Set permissions
- [ ] Configure API paths
- [ ] Set up cron job
- [ ] Test basic functionality

**Day 2: Configuration**
- [ ] Configure email settings
- [ ] Create test campaign
- [ ] Test email sending
- [ ] Review logs

**Day 3: Security**
- [ ] Add .htaccess protection
- [ ] Move database outside web root
- [ ] Enable HTTPS
- [ ] Test authentication

**Day 4: Production**
- [ ] Create real campaigns
- [ ] Add supplier scraping if needed
- [ ] Monitor first sends
- [ ] Document custom configurations

**Ongoing:**
- [ ] Monitor daily logs
- [ ] Update recipient lists
- [ ] Maintain supplier selectors
- [ ] Back up database weekly

---

## ✅ FINAL CHECKLIST

Before going live:
- [ ] All files uploaded and tested
- [ ] Permissions correct
- [ ] Cron job running every minute
- [ ] Email config saved and working
- [ ] Test campaign sent successfully
- [ ] Admin panel password protected
- [ ] HTTPS enabled
- [ ] Database backed up
- [ ] Team trained on using admin panel
- [ ] Documentation shared with team
- [ ] Monitoring plan in place

---

## 📝 NOTES FOR AI IMPLEMENTATION

**For AI Agents implementing this system:**

1. **File Paths:** All require statements use relative paths. Update based on actual file structure.

2. **Database Location:** Default is same directory as PHP files. For security, move outside web root.

3. **Error Handling:** Basic error handling included. Add try-catch blocks for production.

4. **Scalability:** Current implementation uses SQLite. For high volume, migrate to MySQL/PostgreSQL.

5. **Email Sending:** Uses PHP mail() function. For production, implement PHPMailer or SMTP library.

6. **Web Scraping:** Basic CSS selector support. Complex sites may need Selenium or Puppeteer.

7. **Security:** Basic implementation. Add proper authentication, input validation, and CSRF protection.

8. **Testing:** Create unit tests for core functions before deployment.

9. **Logging:** Expand logging for debugging and audit trails.

10. **Documentation:** Update inline comments based on custom implementations.

---

## 🎉 COMPLETION

Once all steps are complete:
1. System will automatically send emails based on schedule
2. Admin panel provides full control
3. Logs track all activity
4. Supplier data automatically scraped and included

**Support:** Check cron.log and email_logs table for any issues.

---

END OF IMPLEMENTATION PACKAGE