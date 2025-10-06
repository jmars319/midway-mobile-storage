<?php
// Load Composer autoloader if present (for PHPMailer)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
/**
 * Email Scheduler Core Class
 * 
 * @package EmailScheduler
 * @version 1.0
 * 
 * Purpose: Handles email campaigns, scheduling, supplier scraping, and logging
 */

class EmailScheduler {
    private $db;
    private $robotsCache = [];
    private $cacheTtl = 600; // seconds
    private $throttleDelayMicro = 500000; // 0.5s between scrapes
    
    public function __construct($dbPath = null) {
        // default DB location outside web root for security
        if ($dbPath === null) {
            $dbPath = __DIR__ . '/private_data/email_scheduler.sqlite';
        }
        // ensure parent directory exists
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }
    
    private function initDatabase() {
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
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                selectors TEXT NOT NULL,
                last_scraped_at TEXT,
                last_result TEXT,
                FOREIGN KEY (campaign_id) REFERENCES campaigns (id) ON DELETE CASCADE
            )
        ");
        

        // Ensure columns exist (for upgrades where table existed before)
        try {
            $cols = $this->db->query("PRAGMA table_info(suppliers)")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (!in_array('last_scraped_at', $names)) {
                $this->db->exec("ALTER TABLE suppliers ADD COLUMN last_scraped_at TEXT");
            }
            if (!in_array('last_result', $names)) {
                $this->db->exec("ALTER TABLE suppliers ADD COLUMN last_result TEXT");
            }
        } catch (Exception $e) {
            // ignore upgrade errors
        }
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_config (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                smtp_server TEXT NOT NULL,
                smtp_port INTEGER NOT NULL,
                email_address TEXT NOT NULL,
                email_password TEXT NOT NULL
            )
        ");
        
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
     * Return paginated campaigns with optional search and status filter.
     * Returns array: ['total' => int, 'campaigns' => [ ... ]]
     */
    public function listCampaigns($page = 1, $perPage = 10, $q = null, $status = null) {
        $offset = max(0, ($page - 1) * $perPage);
        $where = [];
        $params = [];

        if ($q) {
            $where[] = "(name LIKE :q OR subject LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($status !== null && ($status === 0 || $status === '0' || $status === 1 || $status === '1')) {
            $where[] = "active = :active";
            $params[':active'] = (int)$status;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // total count
        $countStmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM campaigns $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT * FROM campaigns $whereSql ORDER BY created_at DESC LIMIT :lim OFFSET :off";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        $campaigns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['recipients'] = json_decode($row['recipients'], true);
            $row['send_days'] = json_decode($row['send_days'], true);
            $campaigns[] = $row;
        }

        return ['total' => $total, 'campaigns' => $campaigns];
    }

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

    public function deleteCampaign($id) {
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ==================== SUPPLIER MANAGEMENT ==================== */

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

    public function deleteSupplier($id) {
        $stmt = $this->db->prepare("DELETE FROM suppliers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Update an existing supplier by id.
     */
    public function updateSupplier($id, $data) {
        $stmt = $this->db->prepare("UPDATE suppliers SET name = :name, url = :url, selectors = :selectors WHERE id = :id");
        return $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'] ?? null,
            ':url' => $data['url'] ?? null,
            ':selectors' => json_encode($data['selectors'] ?? [])
        ]);
    }

    /* ==================== EMAIL CONFIGURATION ==================== */

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

    public function getEmailConfig() {
        $stmt = $this->db->query("SELECT * FROM email_config WHERE id = 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /* ==================== WEB SCRAPING ==================== */

    public function scrapeSupplierData($url, $selectors, $supplierId = null, $force = false) {
        // If supplier id provided and not forcing, return cached result when fresh
        if ($supplierId && !$force) {
            $cached = $this->getCachedSupplierResult($supplierId);
            if ($cached !== null) return $cached;
        }

        // Respect robots.txt (basic check)
        if (!$this->isAllowedByRobots($url)) return null;

        // Throttle to avoid hammering remote hosts. If robots.txt specified crawl-delay for host, respect it.
        $delay = $this->throttleDelayMicro;
        $parts = parse_url($url);
        if ($parts && !empty($parts['scheme']) && !empty($parts['host'])) {
            $hostKey = $parts['scheme'].'://'.$parts['host'];
            if (isset($this->robotsCache[$hostKey]) && !empty($this->robotsCache[$hostKey]['crawl_delay'])) {
                $robotDelay = (int)round($this->robotsCache[$hostKey]['crawl_delay'] * 1000000);
                $delay = max($delay, $robotDelay);
            }
        }
        usleep($delay);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => isset($_SERVER['HTTP_HOST']) ? "Midway-Mobile-Scraper/1.0 (+{$_SERVER['HTTP_HOST']})" : 'Midway-Mobile-Scraper/1.0',
            CURLOPT_TIMEOUT => 10
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || !$html) {
            return null;
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

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

        // If supplier id provided, persist cache (last_result + last_scraped_at)
        if ($supplierId) {
            try {
                $stmt = $this->db->prepare("UPDATE suppliers SET last_scraped_at = :ts, last_result = :res WHERE id = :id");
                $stmt->execute([
                    ':ts' => date('c'),
                    ':res' => json_encode($data),
                    ':id' => $supplierId
                ]);
            } catch (Exception $e) {
                // ignore writeback errors
            }
        }

        return $data;
    }

    public function getCachedSupplierResult($supplierId, $ttl = null){
        $ttl = $ttl ?? $this->cacheTtl;
        $stmt = $this->db->prepare("SELECT last_scraped_at, last_result FROM suppliers WHERE id = ?");
        $stmt->execute([$supplierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row || empty($row['last_scraped_at']) || empty($row['last_result'])) return null;
        $ts = strtotime($row['last_scraped_at']); if(time() - $ts > $ttl) return null;
        return json_decode($row['last_result'], true);
    }

    private function isAllowedByRobots($url){
        // Improved robots.txt check with basic User-agent matching and Crawl-delay support.
        $parts = parse_url($url);
        if(!$parts || empty($parts['host'])) return true;
        $host = $parts['scheme'].'://'.$parts['host'];

        if(isset($this->robotsCache[$host])){
            $entry = $this->robotsCache[$host];
        } else {
            $robotsUrl = $host . '/robots.txt';
            $r = @file_get_contents($robotsUrl);
            $entry = ['disallow'=>[], 'crawl_delay'=>null];
            if($r){
                $lines = preg_split('/\r?\n/', $r);
                $ua = null; $applicable = false; // whether rules apply to our UA
                $myAgent = 'Midway-Mobile-Scraper';
                foreach($lines as $line){
                    $line = trim($line);
                    if($line === '') continue;
                    if(stripos($line,'User-agent:')===0){
                        $ua = trim(substr($line,10));
                        $applicable = ($ua === '*' || stripos($myAgent, $ua) !== false || stripos($ua, $myAgent) !== false);
                        continue;
                    }
                    if(!$applicable) continue;
                    if(stripos($line,'Disallow:')===0){
                        $path = trim(substr($line,9));
                        $entry['disallow'][] = $path;
                    }
                    if(stripos($line,'Crawl-delay:')===0){
                        $val = trim(substr($line,12));
                        if(is_numeric($val)) $entry['crawl_delay'] = (int)$val;
                    }
                }
            }
            $this->robotsCache[$host] = $entry;
        }

        // If a Disallow contains '/' exactly, deny all scraping. Otherwise allow.
        foreach($entry['disallow'] as $d) {
            if($d === '/' ) return false;
        }
        return true;
    }

    private function cssToXpath($css) {
        $css = trim($css);
        
        if (strpos($css, '#') === 0) {
            return "//*[@id='" . substr($css, 1) . "']";
        } elseif (strpos($css, '.') === 0) {
            return "//*[contains(@class, '" . substr($css, 1) . "')]";
        } else {
            return "//" . $css;
        }
    }

    /* ==================== EMAIL SENDING ==================== */

    public function sendCampaignEmail($campaignId) {
        $campaign = $this->getCampaign($campaignId);
        $config = $this->getEmailConfig();
        
        if (!$campaign || !$config) {
            $this->logEmail($campaignId, 'error', 'Campaign or email config not found');
            return false;
        }
        
        try {
            $supplierData = [];
            foreach ($campaign['suppliers'] as $supplier) {
                $data = $this->scrapeSupplierData($supplier['url'], $supplier['selectors']);
                if ($data) {
                    $supplierData[$supplier['name']] = $data;
                }
            }
            
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
            
            $this->sendEmail($config, $campaign['recipients'], $campaign['subject'], $body);
            
            $this->logEmail($campaignId, 'success', 'Email sent successfully');
            return true;
            
        } catch (Exception $e) {
            $this->logEmail($campaignId, 'error', $e->getMessage());
            return false;
        }
    }

    private function sendEmail($config, $recipients, $subject, $body) {
        // Prefer PHPMailer if available for SMTP support and better deliverability
        if (class_exists('\PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = $config['smtp_server'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['email_address'];
                $mail->Password = $config['email_password'];
                // Use STARTTLS if available
                if (defined('\PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS')) {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
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
                return true;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                // Rethrow as generic exception for caller
                throw new Exception('PHPMailer error: ' . $e->getMessage());
            }
        }

        // Fallback to PHP mail() if PHPMailer isn't installed
        $headers = "From: {$config['email_address']}\r\n";
        $headers .= "Reply-To: {$config['email_address']}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        foreach ($recipients as $recipient) {
            $result = mail($recipient, $subject, $body, $headers);
            if (!$result) {
                throw new Exception("Failed to send email to $recipient");
            }
        }
        return true;
    }

    /* ==================== LOGGING ==================== */

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

?>
