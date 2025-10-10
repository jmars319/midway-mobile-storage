<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

header('Content-Type: text/html; charset=utf-8');
// load site content for header
$siteContent = [];
if (file_exists(CONTENT_FILE)) {
  $c = @file_get_contents(CONTENT_FILE);
  $siteContent = $c ? json_decode($c, true) : [];
  if (!is_array($siteContent)) $siteContent = [];
}
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Admin - SMTP Settings</title>
    <?php require_once __DIR__ . '/partials/head.php'; ?>
  <!-- Page helpers moved to /assets/css/admin.css -->
  </head>
  <body class="admin">
    <div class="page-wrap">
      <div class="admin-card">
        <div class="admin-card-header header-row">
          <div class="header-left">
            <h1 class="admin-card-title m-0">SMTP Settings</h1>
            <p class="muted small">Enter the SMTP settings provided by your email provider. (Passwords are not stored in this repository.)</p>
          </div>
          <div class="top-actions">
            <a href="index.php" class="btn btn-ghost">Back to dashboard</a>
          </div>
        </div>

        <div class="admin-card-body">
          <?php if (!file_exists(__DIR__ . '/../vendor/autoload.php')): ?>
            <div class="small mt-05"><span class="muted">Note:</span> <span class="muted-text">A helper library for sending email is not installed. The "Send Test Email" button will be disabled until you install the project's dependencies (see the README).</span></div>
          <?php endif; ?>

          <section class="card card-spaced">
            <form id="smtp-form" method="post" action="save-smtp.php" class="smtp-form">
              <?php echo csrf_input_field(); ?>
              <label class="smtp-label">Username <input type="text" name="smtp_username" value="<?php echo htmlspecialchars($GLOBALS['SMTP_USERNAME_OVERRIDE'] ?? (defined('SMTP_USERNAME') ? SMTP_USERNAME : '')); ?>" class="form-input"></label>
              <div class="small muted">This is usually your email address or the account name your email provider gave you.</div>

              <label class="smtp-label">Password <input type="password" name="smtp_password" value="" placeholder="(leave blank to keep existing)" class="form-input"></label>
              <div class="small muted">The password for that account. Leave blank to keep the current password already configured.</div>

              <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save</button>
                <label class="smtp-test ml-06">Test recipient <input type="email" id="smtp-test-recipient" placeholder="you@yourdomain.com" class="form-input smtp-test-input"></label>
                <button type="button" id="smtp-test" class="btn btn-ghost">Send Test Email</button>
              </div>
            </form>

            <div id="smtp-result" class="small smtp-result"></div>
          </section>
        </div>
      </div>
    </div>

    <div id="toast-container"></div>

        <script>
      // show a toast if redirected after save
      (function(){
        var params = new URLSearchParams(window.location.search);
        var msg = params.get('msg');
        if (!msg) return;
        var c = document.getElementById('toast-container');
        if (!c){ c = document.createElement('div'); c.id='toast-container'; document.body.appendChild(c); }
        if (msg === 'save_ok') {
          var el = document.createElement('div'); el.className = 'toast success'; el.textContent = 'SMTP settings saved'; c.appendChild(el);
          setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, 3000);
        } else if (msg === 'save_failed') {
          var el = document.createElement('div'); el.className = 'toast error'; el.textContent = 'Failed to save SMTP settings'; c.appendChild(el);
          setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, 6000);
        }
      })();

    </script>

    <script>
      (function(){
        var btn = document.getElementById('smtp-test');
        if (!btn) return;
        // disable test if PHPMailer vendor autoload isn't present
        <?php if (!file_exists(__DIR__ . '/../vendor/autoload.php')): ?>
          btn.disabled = true; btn.title = 'Install Composer dependencies to enable SMTP test';
        <?php endif; ?>
        btn.addEventListener('click', function(){
          var recipient = document.getElementById('smtp-test-recipient').value || '';
          var payload = { csrf_token: '<?php echo htmlspecialchars(generate_csrf_token()); ?>' };
          if (recipient) payload.recipient = recipient;
          btn.textContent = 'Sending...'; btn.disabled = true;
          fetch('send-test-email.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type':'application/json' }, body: JSON.stringify(payload) }).then(function(r){ return r.json(); }).then(function(j){
            var out = document.getElementById('smtp-result');
            if (!out) return;
            // clear previous
            out.textContent = '';
            var el = document.createElement('div'); el.className = 'toast ' + (j && j.success ? 'success' : 'error');
            el.textContent = j && j.success ? 'Test email sent successfully.' : ('Test failed: ' + (j && j.message ? j.message : 'unknown'));
            out.appendChild(el);
          }).catch(function(err){ var out = document.getElementById('smtp-result'); if (out) { out.textContent = ''; var el = document.createElement('div'); el.className = 'toast error'; el.textContent = 'Error: ' + err.message; out.appendChild(el); } }).finally(function(){ btn.textContent = 'Send Test Email'; btn.disabled = false; setTimeout(function(){ var container = document.getElementById('smtp-result'); if (container) { var t = container.querySelector('.toast'); if (t) { t.classList.add('fade-out'); setTimeout(function(){ container.textContent = ''; }, 350); } else { container.textContent = ''; } } }, 5000); });
        });
      })();
    </script>
  </body>
</html>
