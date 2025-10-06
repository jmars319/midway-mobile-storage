# Email Scheduler - Quick Reference Sheet

## 🚀 FAST IMPLEMENTATION (30 Minutes)

### Step 1: Upload Files (5 min)
```
/email_scheduler.php          ← Core class
/api/email_api.php            ← REST API
/cron/send_scheduled_emails.php   ← Cron job
/admin/email-scheduler.html   ← Admin UI
```

### Step 2: Set Permissions (2 min)
```bash
chmod 644 *.php
chmod 755 api/ cron/ admin/
```

### Step 3: Update API Path (1 min)
In `admin/email-scheduler.html` line 428:
```javascript
const API_URL = '/api/email_api.php'; // YOUR PATH HERE
```

### Step 4: Add Cron Job (2 min)
```bash
* * * * * /usr/bin/php /path/to/cron/send_scheduled_emails.php
```

### Step 5: Test (20 min)
1. Open admin panel
2. Configure SMTP (Gmail: smtp.gmail.com:587 + App Password)
3. Create test campaign for 2 minutes from now
4. Wait for email
5. Check logs

---

## 📁 COMPLETE FILE LIST

### FILE 1: email_scheduler.php
- **Location:** Root or include directory
- **Size:** ~15KB
- **Purpose:** Core functionality
- **Dependencies:** PDO, SQLite, cURL, DOM
- **Key Classes:** EmailScheduler
- **Key Methods:**
  - `createCampaign($data)` - Create new campaign
  - `getCampaigns()` - Get all campaigns
  - `sendCampaignEmail($id)` - Send email now
  - `scrapeSupplierData($url, $selectors)` - Web scraping

### FILE 2: api/email_api.php
- **Location:** `/api/` directory
- **Size:** ~5KB
- **Purpose:** REST API endpoints
- **Dependencies:** email_scheduler.php
- **Endpoints:**
  - `GET ?action=campaigns` - List campaigns
  - `POST ?action=campaign` - Create campaign
  - `PUT ?action=campaign&id=X` - Update campaign
  - `DELETE ?action=campaign&id=X` - Delete campaign
  - `POST ?action=send` - Send now
  - `POST ?action=test-scrape` - Test scraping

### FILE 3: cron/send_scheduled_emails.php
- **Location:** `/cron/` directory
- **Size:** ~3KB
- **Purpose:** Scheduled email sending
- **Dependencies:** email_scheduler.php
- **Schedule:** Run every minute via cron
- **Logs:** Creates `cron.log` in same directory

### FILE 4: admin/email-scheduler.html
- **Location:** `/admin/` directory
- **Size:** ~30KB
- **Purpose:** Web admin interface
- **Dependencies:** None (vanilla JS)
- **Features:**
  - Campaign management
  - Email configuration
  - Logs viewer
  - Supplier management (via API)

---

## 🔑 KEY CONFIGURATION POINTS

### 1. Database Path
Default: `email_scheduler.sqlite` (created automatically)
Change in: `email_scheduler.php` constructor

### 2. API Path
Default: `/api/email_api.php`
Change in: `email-scheduler.html` line 428

### 3. Require Paths
All files use relative paths:
- API: `require_once '../email_scheduler.php';`
- Cron: `require_once __DIR__ . '/../email_scheduler.php';`

Update based on your file structure.

### 4. SMTP Settings
Configure in admin panel:
- Gmail: smtp.gmail.com, port 587, App Password
- Outlook: smtp-mail.outlook.com, port 587
- Yahoo: smtp.mail.yahoo.com, port 465/587
- Custom: Check your provider

---

## 🐛 COMMON ISSUES & FIXES

| Issue | Cause | Fix |
|-------|-------|-----|
| "Call to undefined function curl_init" | cURL not installed | `apt-get install php-curl` |
| "Class 'DOMDocument' not found" | XML extension missing | `apt-get install php-xml` |
| "Unable to open database" | Permission denied | `chmod 666 *.sqlite` |
| "SMTP connect failed" | Wrong credentials | Use App Password for Gmail |
| Cron not running | Not added to crontab | Run `crontab -l` to verify |
| Emails not sending | Wrong time/day | Check campaign config |
| API not responding | Wrong path | Check browser console |
| Scraping returns N/A | Wrong selector | Test with DevTools |

---

## 📊 DATABASE SCHEMA

### campaigns
```sql
id, name, subject, body, recipients (JSON), 
send_days (JSON), send_time, active, created_at
```

### suppliers
```sql
id, campaign_id, name, url, selectors (JSON)
```

### email_config
```sql
id, smtp_server, smtp_port, email_address, email_password
```

### email_logs
```sql
id, campaign_id, sent_at, status, message
```

---

## 🔧 API USAGE EXAMPLES

### Create Campaign
```javascript
POST /api/email_api.php?action=campaign
{
  "name": "Weekly Update",
  "subject": "Your Weekly Report",
  "body": "Hello!\n\nThis is your update...",
  "recipients": ["user@example.com"],
  "send_days": ["monday", "friday"],
  "send_time": "09:00",
  "active": 1
}
```

### Add Supplier
```javascript
POST /api/email_api.php?action=supplier
{
  "campaign_id": 1,
  "name": "Supplier A",
  "url": "https://supplier.com/product",
  "selectors": {
    "price": ".price-class",
    "stock": "#stock-id"
  }
}
```

### Test Scraping
```javascript
POST /api/email_api.php?action=test-scrape
{
  "url": "https://example.com",
  "selectors": {
    "price": ".product-price"
  }
}
```

### Send Immediately
```javascript
POST /api/email_api.php?action=send
{
  "campaign_id": 1
}
```

---

## 🎯 TESTING CHECKLIST

### Manual Tests
- [ ] Admin panel loads
- [ ] Can save email config
- [ ] Can create campaign
- [ ] Can edit campaign
- [ ] Can delete campaign
- [ ] Can send test email
- [ ] Email appears in logs
- [ ] Cron log shows activity

### Automated Tests (Optional)
```php
// Test campaign creation
$scheduler = new EmailScheduler();
$id = $scheduler->createCampaign([
    'name' => 'Test',
    'subject' => 'Test',
    'body' => 'Test',
    'recipients' => ['test@example.com'],
    'send_days' => ['monday'],
    'send_time' => '09:00',
    'active' => 1
]);
assert($id > 0);

// Test campaign retrieval
$campaign = $scheduler->getCampaign($id);
assert($campaign['name'] === 'Test');

// Test scraping
$data = $scheduler->scrapeSupplierData(
    'https://example.com',
    ['title' => 'h1']
);
assert(!empty($data));
```

---

## 💡 TIPS FOR DEVELOPERS

1. **Use browser DevTools** to find CSS selectors - Right-click → Inspect → Copy selector

2. **Test scraping first** before adding suppliers - Use test-scrape endpoint

3. **Check logs regularly** - Both cron.log and email_logs table

4. **Use App Passwords** for Gmail - Regular passwords won't work

5. **Start with test campaigns** - Use your own email first

6. **One minute resolution** - Cron runs every minute, that's the minimum granularity

7. **Time is 24-hour format** - 09:00 for 9 AM, 17:00 for 5 PM

8. **Days are lowercase** - monday, tuesday, etc.

9. **Recipients are arrays** - Even for one recipient: ["email@example.com"]

10. **Check permissions** - SQLite database needs write access

---

## 🔐 SECURITY CHECKLIST

- [ ] Admin panel password protected (.htaccess)
- [ ] API has authentication check
- [ ] Database outside web root
- [ ] HTTPS enabled
- [ ] File permissions set correctly (644 for PHP, 755 for directories)
- [ ] Error display disabled in production
- [ ] Input validation on all API endpoints
- [ ] SQL injection protection (using PDO prepared statements ✓)
- [ ] XSS protection in admin panel
- [ ] CSRF tokens for state-changing operations

---

## 📈 SCALABILITY NOTES

### Current Limits
- SQLite: ~140 TB database size
- Concurrent: Limited by SQLite write locks
- Recipients: No hard limit, but batch large lists
- Campaigns: No limit

### Scaling Up
- **1000+ recipients**: Consider batch sending
- **100+ campaigns**: Migrate to MySQL/PostgreSQL
- **High frequency**: Add queue system (Redis/RabbitMQ)
- **Multiple servers**: Use MySQL + shared storage

---

## 📞 SUPPORT RESOURCES

### Getting CSS Selectors
1. Open webpage in browser
2. Right-click element → Inspect
3. In DevTools, right-click HTML element
4. Copy → Copy selector
5. Test: `document.querySelector('YOUR_SELECTOR')`

### Gmail App Password
1. Google Account → Security
2. 2-Step Verification (enable)
3. App passwords
4. Select Mail + device
5. Use generated 16-character password

### Cron Troubleshooting
```bash
# List cron jobs
crontab -l

# Edit cron jobs
crontab -e

# View cron log
tail -f /var/log/cron

# Test manually
php /path/to/cron/send_scheduled_emails.php

# View our log
tail -f /path/to/cron/cron.log
```

### Common SMTP Ports
- 25: Unencrypted (often blocked)
- 465: SSL/TLS
- 587: STARTTLS (recommended)
- 2525: Alternative (some providers)

---

## 🎓 TRAINING GUIDE FOR USERS

### Creating a Campaign
1. Open admin panel
2. Click "New Campaign"
3. Fill in details:
   - Name: Internal reference
   - Subject: What recipients see
   - Body: Email content
   - Recipients: One per line
   - Days: Check boxes
   - Time: 24-hour format
4. Click Create

### Adding Supplier Data
1. Find CSS selectors using browser
2. Use API or contact developer
3. Test first with test-scrape
4. Monitor logs for errors

### Viewing Logs
1. Click "Email Logs" tab
2. Review sent emails
3. Check for errors
4. Filter by campaign if needed

### Editing Campaigns
1. Click "Campaigns" tab
2. Click "Edit" on campaign
3. Make changes
4. Save

### Testing
1. Create campaign with your email
2. Set time 2 minutes ahead
3. Check email
4. Review logs

---

## ✅ GO-LIVE CHECKLIST

**Pre-Launch**
- [ ] All files uploaded
- [ ] Permissions set
- [ ] Cron configured
- [ ] SMTP tested
- [ ] Test campaign successful
- [ ] Security measures in place
- [ ] Team trained
- [ ] Documentation complete

**Launch Day**
- [ ] Create production campaigns
- [ ] Monitor first sends
- [ ] Check logs hourly
- [ ] Have rollback plan ready

**Post-Launch**
- [ ] Daily log reviews
- [ ] Weekly performance check
- [ ] Monthly maintenance
- [ ] Quarterly security audit

---

END OF QUICK REFERENCE