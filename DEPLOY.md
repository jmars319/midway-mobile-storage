# Deployment checklist

This document lists the minimal steps to deploy the site to a production server. It focuses on security (secrets and permissions), email setup, and scheduled jobs.

1) Install PHP dependencies (Composer)

 - SSH to the server and from the project root run:

```bash
composer install --no-dev --optimize-autoloader
```

This installs PHPMailer and other required packages. On shared hosts without composer, you can run this locally and upload the `vendor/` directory.

2) Create `admin/auth.json` (secrets — NOT in git)

 - Create `admin/auth.json` with SMTP credentials and any other secrets. Example:

```json
{
  "smtp_username": "smtp-user@example.com",
  "smtp_password": "LONG-SECRET-PASSWORD"
}
```

 - Ensure the file is not world-readable:

```bash
chmod 640 admin/auth.json
chown www-data:www-data admin/auth.json
```

3) Configure `admin/config.php`

 - Update `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_FROM_ADDRESS`, and `SMTP_FROM_NAME` as needed.
 - The code prefers credentials from `admin/auth.json` (over constants) so keep sensitive values there.

4) Harden file permissions (optional script)

 - There's a helper script at `scripts/harden-permissions.sh` which prints recommended commands by default. To apply them:

```bash
# dry-run (safe)
./scripts/harden-permissions.sh

# apply as root or appropriate user
sudo ./scripts/harden-permissions.sh --apply --user www-data --group www-data
```

5) SMTP & deliverability

 - Use a reputable SMTP relay for production: SendGrid, Mailgun, Amazon SES, or your provider's SMTP.
 - Recommended settings:
   - Port: 587 with STARTTLS (SMTP_SECURE = 'tls') or 465 with SSL
   - Use API-key style password where possible (e.g., SendGrid's `apikey` user)
 - Set SPF, DKIM, and DMARC DNS records for the sending domain to improve deliverability.

6) Test sending

 - Log into the admin UI -> SMTP Settings -> enter test recipient and click "Send Test Email".
 - Alternatively, run the `admin/send-test-email.php` endpoint via the admin UI.

7) Cron / scheduled emails

 - For scheduled campaigns, add the cron job to run every minute (or as needed):

```cron
* * * * * cd /path/to/project && /usr/bin/php cron/send_scheduled_emails.php >> /var/log/midway-email-cron.log 2>&1
```

8) Backups & logs

 - Regularly back up `data/` and `private_data/` (these contain submissions and uploads).
 - Monitor `cron/cron.log` and the email scheduler DB (SQLite) for send errors.

Appendix: Provider quick-start

- SendGrid: host `smtp.sendgrid.net`, user `apikey`, password = SendGrid API Key. Add SPF/DKIM via the SendGrid dashboard.
- Amazon SES: request production access, create SMTP credentials, host `email-smtp.<region>.amazonaws.com`.
- Gmail: use App Passwords (requires 2FA and app password) or OAuth; not recommended for high-volume transactional email.

If you'd like, I can:

- Create an `admin/auth.json.example` and add it to the repo.
- Add a small `scripts/deploy.sh` that runs composer, applies permissions, and restarts any services.
