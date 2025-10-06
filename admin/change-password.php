<?php
/**
 * admin/change-password.php
 * Simple form to change the admin password. This script performs the
 * following steps:
 *  - verifies the current password using the stored hash
 *  - enforces a minimum length for new passwords
 *  - writes the new bcrypt hash into `admin/config.php` (attempts an
 *    atomic write using a tmp file)
 *
 * Notes for developers:
 *  - Writing PHP files to update credentials is a quick solution for
 *    small self-hosted projects; in larger systems prefer a secure
 *    storage mechanism outside of code files.
 */

require_once __DIR__ . '/config.php';

// ensure user is authenticated (uses session version checks)
require_admin();
ensure_csrf_token();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf($token)) {
        $error = 'Invalid CSRF token';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // verify current against ADMIN_PASSWORD_HASH
        $ok = false;
        $storedHash = get_admin_hash();
        if (!empty($storedHash) && password_verify($current, $storedHash)) {
            $ok = true;
        }

        if (!$ok) {
            $error = 'Current password incorrect';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            // generate new hash and store it in admin/auth.json (preferred)
            $hash = password_hash($new, PASSWORD_DEFAULT);
            if (set_admin_hash_and_bump_version($hash)) {
                // force logout so the new credentials take effect
                do_logout();
                header('Location: login.php?pw_changed=1');
                exit;
            } else {
                $error = 'Failed to save new password. Check filesystem permissions.';
            }
        }
    }
}

// CSRF helpers are provided by admin/config.php (generate_csrf_token(), csrf_input_field(), verify_csrf_token())
?>
<!doctype html>
<html>
    <head>
    <?php require_once __DIR__ . '/partials/head.php'; ?>
        <meta charset="utf-8">
        <title>Change Admin Password</title>
        <link rel="stylesheet" href="/assets/css/styles.css">
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>
    <body class="admin">
    <div class="container container-narrow pad-2rem">
            <div class="card">
                <h1 class="card-title">Change Password</h1>

                <?php if ($error): ?><div class="form-error mb-1" role="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="form-note mb-1 text-success" role="status"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <form method="post">
                    <?php echo csrf_input_field(); ?>
                    <div class="form-group">
                        <label class="form-label">Current password</label>
                        <input name="current_password" type="password" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">New password</label>
                        <input name="new_password" type="password" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm new password</label>
                        <input name="confirm_password" type="password" required class="form-input">
                    </div>

                    <div class="mt-1">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                        <a href="index.php" class="btn btn-secondary ml-05">Back</a>
                    </div>
                </form>
            </div>
        </div>
                <script>
                    (function(){
                        var form = document.querySelector('form');
                        var newInput = document.querySelector('input[name="new_password"]');
                        var strengthEl = document.createElement('div');
                        strengthEl.className = 'small muted';
                        strengthEl.style.marginTop = '.25rem';
                        newInput.parentNode.appendChild(strengthEl);

                        function scorePassword(pw) {
                            var score = 0;
                            if (!pw) return score;
                            if (pw.length >= 8) score += 1;
                            if (/[A-Z]/.test(pw)) score += 1;
                            if (/[0-9]/.test(pw)) score += 1;
                            if (/[^A-Za-z0-9]/.test(pw)) score += 1;
                            return score;
                        }

                        newInput.addEventListener('input', function(){
                            var s = scorePassword(this.value);
                            var text = ['Very weak','Weak','Okay','Strong','Very strong'][s];
                            strengthEl.textContent = 'Strength: ' + text;
                        });

                        form.addEventListener('submit', function(e){
                            var s = scorePassword(newInput.value);
                            if (s < 2) {
                                if (!confirm('The new password appears weak. Are you sure you want to use it?')) { e.preventDefault(); return; }
                            }
                        });
                    })();
                </script>
    </body>
</html>
