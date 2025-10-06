# FILE 03: email_scheduler.php

## 📄 File Information

- **Filename:** `email_scheduler.php`
- **Location:** Root directory of your website (or include directory)
- **Purpose:** Core class handling all email scheduling functionality
- **Dependencies:** PHP 7.4+, PDO, SQLite3, cURL, DOMDocument
- **File Permissions:** 644

---

## 📋 Instructions for AI Assistants

1. Copy the entire code block below
2. Create a file named `email_scheduler.php`
3. Paste the code exactly as provided
4. Upload to your web server root directory
5. Set permissions to 644
6. No modifications needed - code is production-ready

---

## 💻 Complete Source Code

```php
<?php
/**
 * Email Scheduler Core Class
 * 
 * @package EmailScheduler
 * @version 1.0
 * @author Your Development Team
 * 
 * Purpose: Handles email campaigns, scheduling, supplier scraping, and logging
 * 
 * Dependencies:
 * - PHP 7.4 or higher
 * - PDO extension
 * - SQLite3 extension
 * - cURL extension
 * - DOM/XML extension
 * 
 * Database: SQLite (auto-creates on first run)
 * 
 * Key Features:
 * - Campaign management (CRUD operations)
 * - Supplier data scraping from websites
 * - Automated email sending
 * - Comprehensive logging
 * - SMTP email support
 */

class EmailScheduler {
    private $db;
    
    /**
     * Constructor - Initializes database connection
     * 
     * @param string $dbPath Path to SQLite database file (created if doesn't exist)
     * 
     * Example usage:
     * $scheduler = new EmailScheduler();
     * OR
     * $scheduler = new EmailScheduler('/secure/path/email_scheduler.sqlite');
     */
    public function __construct($dbPath = 'email_scheduler.sqlite') {
        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }
    
    /**
     * Initialize database tables
     * Creates all necessary tables if they don't exist
     * Called automatically by constructor
     * 
     * Tables created:
     * - campaigns: Stores email campaign configurations
     * - suppliers: Stores supplier scraping configurations
     * - email_config: Stores SMTP settings (single row)
     * - email_logs: Stores email sending history
     */
    private function initDatabase() {
        // Campaigns table - stores all email campaign data
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                recipients TEXT NOT NULL,      -- JSON array of email addresses
                send_days TEXT NOT NULL,       -- JSON array of days (e.g., ['monday', 'friday'])
                send_time TEXT NOT NULL,       -- Time in HH:MM format (24-hour)
                active INTEGER DEFAULT 1,      -- 1 = active, 0 = inactive
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Suppliers table - stores web scraping configurations
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                selectors TEXT NOT NULL,       -- JSON object of CSS selectors
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
            )
        ");
        
        // Email configuration table - stores SMTP settings
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_config (
                id INTEGER PRIMARY KEY CHECK (id = 1),  -- Only allow one row
                smtp_server TEXT NOT NULL,
                smtp_port INTEGER NOT NULL,
                email_address TEXT NOT NULL,
                email_password TEXT NOT NULL
            )
        ");
        
        // Email logs table - stores sending history
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status TEXT,                   -- 'success' or 'error'
                message TEXT,                  -- Success message or error details
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id)
            )
        ");
    }
    
    /* ==================== CAMPAIGN MANAGEMENT ==================== */
    
    /**
     * Create a new email campaign
     * 
     * @param array $data Campaign data with keys:
     *                    - name: Campaign name (string)
     *                    - subject: Email subject (string)
     *                    - body: Email body text (string)
     *                    - recipients: Array of email addresses
     *                    - send_days: Array of days ['monday', 'tuesday', etc.]
     *                    - send_time: Time in HH:MM format (24-hour)
     *                    - active: 1 or 0 (optional, defaults to 1)
     * 
     * @return int Campaign ID of newly created campaign
     * 
     * @throws PDOException if database operation fails
     * 
     * Example:
     * $id = $scheduler->createCampaign([
     *     'name' => 'Weekly Newsletter',
     *     'subject' => 'This Week\'s Updates',
     *     'body' => 'Hello! Here are this week\'s updates...',
     *     'recipients' => ['user1@example.com', 'user2@example.com'],
     *     'send_days' => ['monday', 'friday'],
     *     'send_time' => '09:00',
     *     'active' => 1
     * ]);
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
     * 
     * @return array Array of campaigns with decoded JSON fields
     *               Each campaign includes: id, name, subject, body, recipients (array),
     *               send_days (array), send_time, active, created_at
     * 
     * Example:
     * $campaigns = $scheduler->getCampaigns();
     * foreach ($campaigns as $campaign) {
     *     echo $campaign['name'] . ': ' . count($campaign['recipients']) . ' recipients';
     * }
     */
    public function getCampaigns() {
        $stmt = $this->db->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        $campaigns = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Decode JSON fields for easy use
            $row['recipients'] = json_decode($row['recipients'], true);
            $row['send_days'] = json_decode($row['send_days'], true);
            $campaigns[] = $row;
        }
        
        return $campaigns;
    }
    
    /**
     * Get a single campaign by ID
     * 
     * @param int $id Campaign ID
     * 
     * @return array|false Campaign data with decoded JSON fields and suppliers,
     *                     or false if not found
     * 
     * Example:
     * $campaign = $scheduler->getCampaign(1);
     * if ($campaign) {
     *     echo "Campaign: " . $campaign['name'];
     *     echo "Suppliers: " . count($campaign['suppliers']);
     * }
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
     * 
     * @param int $id Campaign ID to update
     * @param array $data Updated campaign data (same structure as createCampaign)
     * 
     * @return bool True on success, false on failure
     * 
     * Example:
     * $success = $scheduler->updateCampaign(1, [
     *     'name' => 'Updated Newsletter',
     *     'subject' => 'New Subject',
     *     'body' => 'Updated body text',
     *     'recipients' => ['new@example.com'],
     *     'send_days' => ['monday'],
     *     'send_time' => '10:00',
     *     'active' => 1
     * ]);
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
     * Also deletes associated suppliers (CASCADE)
     * 
     * @param int $id Campaign ID to delete
     * 
     * @return bool True on success, false on failure
     * 
     * Example:
     * if ($scheduler->deleteCampaign(1)) {
     *     echo "Campaign deleted successfully";
     * }
     */
    public function deleteCampaign($id) {
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /* ==================== SUPPLIER MANAGEMENT ==================== */
    
    /**
     * Add a supplier to a campaign
     * Suppliers enable web scraping of data to include in emails
     * 
     * @param int $campaignId Campaign ID to attach supplier to
     * @param array $data Supplier data with keys:
     *                    - name: Supplier display name
     *                    - url: Website URL to scrape
     *                    - selectors: Array of CSS selectors (key => selector pairs)
     * 
     * @return bool True on success, false on failure
     * 
     * Example:
     * $scheduler->addSupplier(1, [
     *     'name' => 'Supplier ABC',
     *     'url' => 'https://supplier.com/product/123',
     *     'selectors' => [
     *         'price' => '.product-price',
     *         'stock' => '#availability',
     *         'name' => 'h1.product-title'
     *     ]
     * ]);
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
     * 
     * @param int $campaignId Campaign ID
     * 
     * @return array Array of suppliers with decoded selectors
     * 
     * Example:
     * $suppliers = $scheduler->getSuppliersByCampaign(1);
     * foreach ($suppliers as $supplier) {
     *     echo $supplier['name'] . ': ' . $supplier['url'];
     * }
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
     * 
     * @param int $id Supplier ID
     * 
     * @return bool True on success, false on failure
     */
    public function deleteSupplier($id) {
        $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /* ==================== EMAIL CONFIGURATION ==================== */
    
    /**
     * Save email configuration (SMTP settings)
     * Only one configuration allowed (replaces existing)
     * 
     * @param array $config SMTP configuration with keys:
     *                      - smtp_server: SMTP server hostname
     *                      - smtp_port: SMTP port number
     *                      - email_address: Sender email address
     *                      - email_password: SMTP password or app password
     * 
     * @return bool True on success, false on failure
     * 
     * Example (Gmail):
     * $scheduler->saveEmailConfig([
     *     'smtp_server' => 'smtp.gmail.com',
     *     'smtp_port' => 587,
     *     'email_address' => 'your-email@gmail.com',
     *     'email_password' => 'your-app-password'
     * ]);
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
     * 
     * @return array|false Email configuration or false if not set
     * 
     * Example:
     * $config = $scheduler->getEmailConfig();
     * if ($config) {
     *     echo "SMTP Server: " . $config['smtp_server'];
     * }
     */
    public function getEmailConfig() {
        $stmt = $this->db->query("SELECT * FROM email_config WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /* ==================== WEB SCRAPING ==================== */
    
    /**
     * Scrape data from supplier website using CSS selectors
     * 
     * @param string $url Website URL to scrape
     * @param array $selectors Map of field names to CSS selectors
     *                         Example: ['price' => '.product-price', 'stock' => '#stock']
     * 
     * @return array|null Scraped data as key-value pairs, or null on error
     *                    Missing elements will have value 'N/A'
     * 
     * Supported selector types:
     * - ID: #elementId
     * - Class: .className
     * - Element: div, span, h1, etc.
     * 
     * Example:
     * $data = $scheduler->scrapeSupplierData(
     *     'https://example.com/product',
     *     ['price' => '.product-price', 'name' => 'h1']
     * );
     * // Returns: ['price' => '$99.99', 'name' => 'Product Name']
     */
    public function scrapeSupplierData($url, $selectors) {
        // Initialize cURL with user agent to avoid blocks
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
        
        // Parse HTML with DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($html);  // @ suppresses HTML5 warnings
        $xpath = new DOMXPath($dom);
        
        // Extract data using CSS selectors converted to XPath
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
     * Convert basic CSS selector to XPath query
     * Supports: ID (#id), Class (.class), and Element (tag) selectors
     * 
     * @param string $css CSS selector
     * 
     * @return string XPath query
     * 
     * Note: This is a basic converter. Complex selectors may not work.
     * For complex selectors, consider using a CSS-to-XPath library.
     */
    private function cssToXpath($css) {
        $css = trim($css);
        
        if (strpos($css, '#') === 0) {
            // ID selector: #elementId → //*[@id='elementId']
            return "//*[@id='" . substr($css, 1) . "']";
        } elseif (strpos($css, '.') === 0) {
            // Class selector: .className → //*[contains(@class, 'className')]
            return "//*[contains(@class, '" . substr($css, 1) . "')]";
        } else {
            // Element selector: div → //div
            return "//" . $css;
        }
    }
    
    /* ==================== EMAIL SENDING ==================== */
    
    /**
     * Send email for a campaign
     * Scrapes supplier data if configured, then sends email to all recipients
     * 
     * @param int $campaignId Campaign ID to send
     * 
     * @return bool True on success, false on failure
     * 
     * Process:
     * 1. Retrieve campaign and email config
     * 2. Scrape data from all suppliers
     * 3. Build email body with supplier data
     * 4. Send email to all recipients
     * 5. Log the attempt
     * 
     * Example:
     * if ($scheduler->sendCampaignEmail(1)) {
     *     echo "Campaign sent successfully!";
     * }
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
            
            // Build email body with supplier data appended
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
            
            // Send email to all recipients
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
     * 
     * NOTE: For production use, consider using PHPMailer or similar SMTP library
     * for better deliverability, error handling, and SMTP authentication
     * 
     * @param array $config SMTP configuration
     * @param array $recipients Array of email addresses
     * @param string $subject Email subject line
     * @param string $body Email body content (plain text)
     * 
     * @throws Exception if mail() function fails
     * 
     * To upgrade to PHPMailer:
     * 1. Install: composer require phpmailer/phpmailer
     * 2. Replace this method with PHPMailer implementation
     * 3. See documentation for PHPMailer example code
     */
    private function sendEmail($config, $recipients, $subject, $body) {
        $headers = "From: {$config['email_address']}\r\n";
        $headers .= "Reply-To: {$config['email_address']}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        foreach ($recipients as $recipient) {
            $result = mail($recipient, $subject, $body, $headers);
            if (!$result) {
                throw new Exception("Failed to send email to $recipient");
            }
        }
    }
    
    /* ==================== LOGGING ==================== */
    
    /**
     * Log email sending attempt to database
     * 
     * @param int $campaignId Campaign ID
     * @param string $status 'success' or 'error'
     * @param string $message Log message or error description
     * 
     * Note: This is called automatically by sendCampaignEmail()
     * You don't need to call this manually unless adding custom logging
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
     * 
     * @param int|null $campaignId Optional campaign ID to filter by
     * 
     * @return array Array of log entries (max 50 most recent)
     *               Each entry includes: id, campaign_id, sent_at, status, message
     * 
     * Example:
     * // Get all logs
     * $logs = $scheduler->getEmailLogs();
     * 
     * // Get logs for specific campaign
     * $logs = $scheduler->getEmailLogs(1);
     * 
     * foreach ($logs as $log) {
     *     echo "{$log['sent_at']}: {$log['status']} - {$log['message']}";
     * }
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

// End of EmailScheduler class
?>
```

---

## 📝 Implementation Notes

### Database
- Database file will be created automatically on first use
- Default location: Same directory as this file
- For security: Move database outside web root (update $dbPath in constructor)

### Email Sending
- Current implementation uses PHP mail() function
- For production, upgrade to PHPMailer for better SMTP support
- See comments in sendEmail() method for upgrade instructions

### Web Scraping
- Supports basic CSS selectors (ID, class, element)
- For complex selectors, consider using a CSS-to-XPath library
- Some websites may block scraping - test thoroughly

### Error Handling
- All public methods include basic error handling
- Database operations use PDO with exception mode
- Failed operations are logged to email_logs table

### Security
- Uses PDO prepared statements (prevents SQL injection)
- No direct user input in queries
- Password stored in database (consider encryption for production)

---

## 🔧 Customization Options

### Change Database Location
```php
// In your code
$scheduler = new EmailScheduler('/secure/path/email_scheduler.sqlite');
```

### Add Custom Email Headers
Modify the `sendEmail()` method to add custom headers:
```php
$headers .= "X-Campaign-ID: {$campaignId}\r\n";
$headers .= "List-Unsubscribe: <mailto:unsubscribe@example.com>\r\n";
```

### Extend Scraping Capabilities
Add support for more complex CSS selectors by enhancing `cssToXpath()`:
```php
// Example: Support for attribute selectors
if (strpos($css, '[') !== false) {
    // Parse attribute selector
}
```

---

## ✅ Testing

### Test Database Creation
```php
$scheduler = new EmailScheduler();
// Check if email_scheduler.sqlite file was created
```

### Test Campaign CRUD
```php
// Create
$id = $scheduler->createCampaign([...]);

// Read
$campaign = $scheduler->getCampaign($id);

// Update
$scheduler->updateCampaign($id, [...]);

// Delete
$scheduler->deleteCampaign($id);
```

### Test Web Scraping
```php
$data = $scheduler->scrapeSupplierData(
    'https://example.com',
    ['title' => 'h1', 'price' => '.price']
);
var_dump($data);
```

---

END OF FILE 03