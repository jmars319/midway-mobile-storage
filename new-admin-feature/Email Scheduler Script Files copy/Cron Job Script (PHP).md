<?php
/**
 * Cron Job Script - Checks and sends scheduled emails
 * File: cron/send_scheduled_emails.php
 * 
 * Add to crontab to run every minute:
 * * * * * * /usr/bin/php /path/to/your/cron/send_scheduled_emails.php
 */

require_once __DIR__ . '/../email_scheduler.php';

class CronEmailSender {
    private $scheduler;
    private $logFile;
    
    public function __construct() {
        $this->scheduler = new EmailScheduler();
        $this->logFile = __DIR__ . '/cron.log';
    }
    
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
    
    public function checkAndSendEmails() {
        $this->log("Cron job started");
        
        // Get current day and time
        $currentDay = strtolower(date('l')); // monday, tuesday, etc.
        $currentTime = date('H:i');
        
        $this->log("Current day: $currentDay, Current time: $currentTime");
        
        // Get all active campaigns
        $campaigns = $this->scheduler->getCampaigns();
        
        foreach ($campaigns as $campaign) {
            if (!$campaign['active']) {
                continue;
            }
            
            // Check if today is a scheduled day
            if (!in_array($currentDay, $campaign['send_days'])) {
                continue;
            }
            
            // Check if current time matches send time (within 1 minute window)
            $sendTime = $campaign['send_time'];
            if ($this->isTimeToSend($currentTime, $sendTime)) {
                $this->log("Sending campaign: {$campaign['name']} (ID: {$campaign['id']})");
                
                try {
                    $result = $this->scheduler->sendCampaignEmail($campaign['id']);
                    
                    if ($result) {
                        $this->log("Successfully sent campaign: {$campaign['name']}");
                    } else {
                        $this->log("Failed to send campaign: {$campaign['name']}");
                    }
                } catch (Exception $e) {
                    $this->log("Error sending campaign {$campaign['name']}: " . $e->getMessage());
                }
            }
        }
        
        $this->log("Cron job completed\n");
    }
    
    private function isTimeToSend($currentTime, $sendTime) {
        // Check if times match (exact match or within 1 minute)
        $current = strtotime($currentTime);
        $scheduled = strtotime($sendTime);
        
        // Allow 1 minute window to account for cron timing
        $diff = abs($current - $scheduled);
        return $diff < 60;
    }
}

// Run the cron job
$sender = new CronEmailSender();
$sender->checkAndSendEmails();
?>