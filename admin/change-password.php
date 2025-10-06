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

// ensure user is authenticated
checkAuth();

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($token)) {
        $error = 'Invalid CSRF token';
    } else {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // verify current against ADMIN_PASSWORD_HASH
        $ok = false;
        if (!empty(ADMIN_PASSWORD_HASH) && password_verify($current, ADMIN_PASSWORD_HASH)) {
            $ok = true;
        }

        if (!$ok) {
            $error = 'Current password incorrect';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            // generate new hash and update admin/config.php by replacing the constant
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $cfgFile = __DIR__ . '/config.php';
            $orig = @file_get_contents($cfgFile);
            if ($orig === false) {
                $error = 'Failed to read config file. Check permissions.';
            } else {
                $replacement = "define('ADMIN_PASSWORD_HASH', '" . addslashes($hash) . "');";
                $newContent = preg_replace("/define\(\s*'ADMIN_PASSWORD_HASH'\s*,\s*'[^']*'\s*\);/", $replacement, $orig, 1, $count);
                if ($newContent === null) {
                    $error = 'Failed to update config content';
                } elseif ($count === 0) {
                    // no match - append replacement after ADMIN_USERNAME define
                    $newContent = preg_replace("/(define\(\s*'ADMIN_USERNAME'[^;]+;)/", "$1\n" . $replacement, $orig, 1, $c2);
                }

                if (empty($error)) {
                    // write atomically
                    $tmp = $cfgFile . '.tmp';
                    if (file_put_contents($tmp, $newContent, LOCK_EX) !== false && @rename($tmp, $cfgFile)) {
                        $success = 'Password updated successfully';
                        // update runtime constant (best-effort) by defining if possible
                        // note: constants cannot be redefined; user will need to re-login for new hash to take effect
                    } else {
                        @unlink($tmp);
                        error_log('change-password.php: failed to write config file ' . $cfgFile);
                        $error = 'Failed to write config file. Check permissions.';
                    }
                }
            }
        }
    }
}

// CSRF helpers are provided by admin/config.php (generate_csrf_token(), csrf_input_field(), verify_csrf_token())
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Change Admin Password</title>
        <link rel="stylesheet" href="/assets/css/styles.css">
        <link rel="stylesheet" href="/assets/css/admin.css">
    </head>
    <body class="admin">
        <div class="container container-narrow" style="padding:2rem 0">
            <div class="card">
                <h1 class="card-title">Change Password</h1>

                <?php if ($error): ?><div class="form-error" role="alert" style="margin-bottom:1rem"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="form-note" role="status" style="margin-bottom:1rem;color:var(--success-color)"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

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

                    <div style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                        <a href="index.php" class="btn btn-secondary" style="margin-left:.5rem">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>
