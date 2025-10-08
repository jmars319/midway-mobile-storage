<?php
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

// Determine archive directory from candidate locations (match index.php logic)
$candidates = [
  __DIR__ . '/../private_data/resumes/archived/',
  dirname(__DIR__) . '/../private_data/resumes/archived/',
  __DIR__ . '/../../private_data/resumes/archived/',
];
$archDir = null;
foreach ($candidates as $cand) {
  if (is_dir($cand)) { $archDir = rtrim($cand, '/') . '/'; break; }
}
// If archive doesn't exist, pick a default candidate (create it)
if ($archDir === null) {
  $archDir = dirname(__DIR__) . '/../private_data/resumes/archived/';
  if (!is_dir($archDir)) @mkdir($archDir, 0755, true);
  $archDir = rtrim($archDir, '/') . '/';
}

$logFile = dirname(__DIR__) . '/data/resume-deletions.log';

// Helper: append audit line
function append_resume_audit($entry) {
  $logFile = dirname(__DIR__) . '/data/resume-deletions.log';
  if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
  @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

// Handle POST actions on this page: restore or purge single resume
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF token'; exit;
  }
  $action = $_POST['action'] ?? '';
  if ($action === 'restore_resume') {
    $resume = basename((string)($_POST['resume_file'] ?? ''));
    if ($resume && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $resume)) {
      $src = $archDir . $resume;
      if (file_exists($src) && is_file($src)) {
        $destDir = dirname(__DIR__) . '/../private_data/resumes/';
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $dest = rtrim($destDir, '/') . '/' . $resume;
        $i = 0; $base = pathinfo($resume, PATHINFO_FILENAME); $ext = pathinfo($resume, PATHINFO_EXTENSION);
        while (file_exists($dest)) { $i++; $dest = rtrim($destDir, '/') . '/' . $base . '_' . $i . ($ext?'.'.$ext:''); }
        if (@rename($src, $dest)) {
          append_resume_audit([
            'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
            'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'action' => 'restore',
            'archived_filename' => $resume,
            'restored_path' => $dest,
          ]);
          // If AJAX, return JSON success
          $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (strpos(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
          if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'filename' => $resume, 'restored_path' => $dest]);
            exit;
          }
        }
      }
    }
    header('Location: admin-resumes.php'); exit;
  }

  if ($action === 'purge_resume') {
    $resume = basename((string)($_POST['resume_file'] ?? ''));
    if ($resume && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $resume)) {
      $src = $archDir . $resume;
      if (file_exists($src) && is_file($src)) {
        // Move the file to a trash directory so we can support an undo operation
        $trashDir = dirname(__DIR__) . '/../private_data/resumes/trashed/';
        if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
        $uniq = time() . '_' . bin2hex(random_bytes(6));
        $trashName = $uniq . '__' . $resume;
        $trashPath = rtrim($trashDir, '/') . '/' . $trashName;
        if (@rename($src, $trashPath)) {
          append_resume_audit([
            'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
            'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'action' => 'purge',
            'archived_filename' => $resume,
            'trash_name' => $trashName,
            'trash_path' => $trashPath,
          ]);
          // If the client expects JSON (AJAX), return JSON success instead of redirecting
          $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (strpos(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
          if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'filename' => $resume, 'trash_name' => $trashName]);
            exit;
          }
        }
      }
    }
    // Background cleanup: expire trashed files older than N days
    $expire_days = 7;
    $now = time();
    if (isset($trashDir)) {
      $pattern = rtrim($trashDir, '/') . '/*';
      $trashFiles = @glob($pattern);
      if ($trashFiles) {
        foreach ($trashFiles as $tfile) {
          if (!is_file($tfile)) continue;
          if (($now - @filemtime($tfile)) > ($expire_days * 86400)) {
            @unlink($tfile);
            append_resume_audit([
              'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
              'admin' => 'system',
              'ip' => 'localhost',
              'action' => 'expire_trash',
              'trash_path' => $tfile,
            ]);
          }
        }
      }
    }
    // Non-AJAX fallback: redirect back to the list
    header('Location: admin-resumes.php'); exit;
  }
}

// Support downloads via GET: csv or json list of archived resumes
if (isset($_GET['download'])) {
  $fmt = $_GET['download'];
  $files = [];
  $glob = glob($archDir . '*');
  if ($glob) {
    foreach ($glob as $f) {
      if (!is_file($f)) continue;
      $name = basename($f);
      if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) continue;
      $files[] = [ 'name' => $name, 'size' => filesize($f), 'mtime' => filemtime($f) ];
    }
  }
  // If there are no archived resumes, redirect back with a friendly message
  if (empty($files)) {
    header('Location: admin-resumes.php?msg=no_resumes');
    exit;
  }
  if ($fmt === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="archived-resumes.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['filename','size_bytes','archived_at']);
    foreach ($files as $r) { fputcsv($out, [$r['name'], $r['size'], date('c', $r['mtime'])]); }
    fclose($out); exit;
  }
  if ($fmt === 'json') {
    header('Content-Type: application/json');
    echo json_encode(array_values($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// Handle purge-old (move archived files older than N days to trash)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purge_old') {
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF token'; exit; }
  $days = isset($_POST['days']) ? max(1, (int)$_POST['days']) : 30;
  $now = time();
  $trashDir = dirname(__DIR__) . '/../private_data/resumes/trashed/';
  if (!is_dir($trashDir)) @mkdir($trashDir, 0755, true);
  $moved = 0;
  $glob = glob($archDir . '*');
  if ($glob) {
    foreach ($glob as $f) {
      if (!is_file($f)) continue;
      if (($now - filemtime($f)) > ($days * 86400)) {
        $name = basename($f);
        $uniq = time() . '_' . bin2hex(random_bytes(6));
        $trashName = $uniq . '__' . $name;
        $trashPath = rtrim($trashDir, '/') . '/' . $trashName;
        if (@rename($f, $trashPath)) {
          append_resume_audit([
            'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
            'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'action' => 'purge_old',
            'archived_filename' => $name,
            'trash_name' => $trashName,
            'trash_path' => $trashPath,
          ]);
          $moved++;
        }
      }
    }
  }
  // redirect back with a simple query message
  header('Location: admin-resumes.php?purged=' . $moved); exit;
}

$files = [];
$glob = glob($archDir . '*');
if ($glob) {
  foreach ($glob as $f) {
    if (!is_file($f)) continue;
    $name = basename($f);
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name)) continue;
    $files[] = [
      'name' => $name,
      'size' => filesize($f),
      'mtime' => filemtime($f),
      'path' => $f,
    ];
  }
}

// Sort newest first
usort($files, function($a,$b){ return $b['mtime'] <=> $a['mtime']; });
// ----- search and pagination support -----
$search = trim((string)($_GET['search'] ?? ''));
$per_page = (int)($_GET['per_page'] ?? 20);
if ($per_page <= 0) $per_page = 20;
$allowed_per = [10,20,50,100];
if (!in_array($per_page, $allowed_per)) $per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

if ($search !== '') {
  $files = array_values(array_filter($files, function($f) use ($search) {
    return stripos($f['name'], $search) !== false;
  }));
}

$total = count($files);
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$page_files = array_slice($files, $offset, $per_page);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Archived Resumes — Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="admin-resumes-page admin">
  <div class="page-wrap">
    <div class="admin-card">
      <div class="admin-card-header header-row">
        <div class="header-left">
          <h1 class="admin-card-title m-0">Archived Resumes</h1>
          <p class="muted small">Archive directory: <?php echo htmlspecialchars($archDir); ?></p>
        </div>
        <div class="top-actions">
          <a href="index.php" class="btn btn-ghost">Back to dashboard</a>
        </div>
      </div>
      <div class="admin-card-body">
        <main class="container">

    <form method="get" class="search-form" style="margin-top:.6rem;display:flex;gap:.5rem;align-items:center;">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search filename..." class="field-input">
      <label style="font-weight:600;">Per page:
        <select name="per_page" onchange="this.form.submit()">
          <?php foreach ([10,20,50,100] as $pp): ?>
            <option value="<?php echo $pp; ?>" <?php if ($pp == $per_page) echo 'selected'; ?>><?php echo $pp; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="btn btn-ghost">Search</button>
      <a class="btn" href="admin-resumes.php">Reset</a>
    </form>

    <?php if (empty($files)): ?>
      <div class="empty-note">No archived resumes found.</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Filename</th><th>Size</th><th>Archived</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($page_files as $f): ?>
            <tr>
              <td><?php echo htmlspecialchars($f['name']); ?></td>
              <td><?php echo number_format($f['size']); ?> bytes</td>
              <td><?php echo date('Y-m-d H:i:s', $f['mtime']); ?></td>
              <td>
                <form method="post" style="display:inline" data-ajax="true" data-fname="<?php echo htmlspecialchars($f['name']); ?>">
                  <?php echo csrf_input_field(); ?>
                  <input type="hidden" name="action" value="restore_resume">
                  <input type="hidden" name="resume_file" value="<?php echo htmlspecialchars($f['name']); ?>">
                  <button type="submit" class="btn" title="Restore">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 5 17 10"/><line x1="12" y1="5" x2="12" y2="21"/></svg></span>
                    Restore
                  </button>
                </form>
                <form method="post" style="display:inline" data-confirm="Permanently delete this archived resume?" data-ajax="true" data-fname="<?php echo htmlspecialchars($f['name']); ?>">
                  <?php echo csrf_input_field(); ?>
                  <input type="hidden" name="action" value="purge_resume">
                  <input type="hidden" name="resume_file" value="<?php echo htmlspecialchars($f['name']); ?>">
                  <button type="submit" class="btn btn-danger" title="Delete">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg></span>
                    Delete
                  </button>
                </form>
                <a class="btn btn-ghost" href="download-resume.php?file=<?php echo urlencode($f['name']); ?>" title="Download">
                  <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>
                  Download
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="pager" style="display:flex;align-items:center;gap:.5rem;margin-top:.75rem;">
        <div class="pager-info">Showing <?php echo ($offset+1); ?>–<?php echo min($offset+count($page_files), $total); ?> of <?php echo $total; ?></div>
        <div style="margin-left:auto;display:flex;gap:.35rem;align-items:center;">
          <?php
            // Numeric pager: show first, last, and a window around current page
            $window = 2; // pages on each side
            $start = max(1, $page - $window);
            $end = min($total_pages, $page + $window);
            // show first page link and ellipsis if needed
            if ($start > 1) {
              echo '<a class="btn btn-ghost" href="?' . http_build_query(array_merge($_GET, ['page'=>1])) . '">1</a>';
              if ($start > 2) echo '<span class="muted">&hellip;</span>';
            }
            for ($p = $start; $p <= $end; $p++) {
              if ($p == $page) {
                echo '<span class="btn current-page">' . $p . '</span>';
              } else {
                echo '<a class="btn btn-ghost" href="?' . http_build_query(array_merge($_GET, ['page'=>$p])) . '">' . $p . '</a>';
              }
            }
            if ($end < $total_pages) {
              if ($end < $total_pages - 1) echo '<span class="muted">&hellip;</span>';
              echo '<a class="btn btn-ghost" href="?' . http_build_query(array_merge($_GET, ['page'=>$total_pages])) . '">' . $total_pages . '</a>';
            }
          ?>
        </div>
      </div>
    <?php endif; ?>
        </main>
      </div>
    </div>
  </div>
</body>
</html>

  <script>
    (function(){
      try{
        var params = new URLSearchParams(window.location.search);
        var msgs = [];
        if (params.has('purged')) {
          var n = parseInt(params.get('purged')||'0',10);
          msgs.push(n ? (n + ' archived resume' + (n===1? '' : 's') + ' moved to trash') : 'No archived resumes were moved');
        }
        if (params.has('restored')) {
          var r = parseInt(params.get('restored')||'0',10);
          msgs.push(r ? (r + ' trashed resume' + (r===1? '' : 's') + ' restored') : 'No resumes restored');
        }
        if (msgs.length === 0) return;
        var container = document.getElementById('toast-container');
        if (!container) { container = document.createElement('div'); container.id = 'toast-container'; document.body.appendChild(container); }
        var el = document.createElement('div'); el.className = 'toast success'; el.textContent = msgs.join(' — ');
        container.appendChild(el);
        setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, 3500);
        // remove query params without reloading
        var url = new URL(window.location.href);
        url.searchParams.delete('purged'); url.searchParams.delete('restored');
        history.replaceState({}, '', url.toString());
      } catch(e){ /* no-op */ }
    })();
  </script>

      <script>
        // show a friendly message when redirected back due to no resumes being available
        (function(){
          try {
            var params = new URLSearchParams(window.location.search);
            if (params.get('msg') === 'no_resumes') {
              var c = document.getElementById('toast-container');
              if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
              var el = document.createElement('div'); el.className = 'toast error'; el.textContent = 'No archived resumes found.';
              c.appendChild(el);
              setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, 3500);
              var url = new URL(window.location.href); url.searchParams.delete('msg'); history.replaceState({}, '', url.toString());
            }
          } catch(e) { /* no-op */ }
        })();
      </script>
