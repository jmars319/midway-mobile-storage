<?php
/**
 * admin/index.php
 * Main administration UI. This file provides:
 *  - Listing and export of job/contact submissions.
 *  - Management actions (download CSV/JSON, purge logs, manage quotes).
 *
 * Security and assumptions:
 *  - Session-based admin auth is required (handled in `config.php`).
 *  - CSRF tokens are enforced for state-changing POST actions.
 *  - Downloads and archives are read from `data/` and `data/archives`.
 *
 * Developer notes:
 *  - Large exports are streamed; be mindful of memory if the logs
 *    grow significantly. Consider paginating or using gzipped archives
 *    when exporting very large datasets.
 */

require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

// Compatibility filenames — prefer new name but fall back to legacy during transition
$APPLICATIONS_FILE = __DIR__ . '/../data/applications.json';
$LEGACY_MESSAGES_FILE = __DIR__ . '/../data/messages.json';

// Load entries from either the new applications file or the legacy messages file.
function load_entries($newPath, $legacyPath) {
  $entries = [];
  if (file_exists($newPath)) {
    $c = @file_get_contents($newPath);
    $entries = $c ? json_decode($c, true) : [];
  } elseif (file_exists($legacyPath)) {
    $c = @file_get_contents($legacyPath);
    $entries = $c ? json_decode($c, true) : [];
  }
  if (!is_array($entries)) $entries = [];
  return $entries;
}

// Load archived entries from both new and legacy archive filename patterns.
function load_archived_entries($archiveDir) {
  $entries = [];
  if (!is_dir($archiveDir)) return $entries;
  $patterns = ['/applications-*.json.gz', '/messages-*.json.gz'];
  foreach ($patterns as $p) {
    $files = glob($archiveDir . $p);
    if ($files) {
      foreach ($files as $f) {
        $gz = @file_get_contents($f);
        if ($gz === false) continue;
        $json = @gzdecode($gz);
        if ($json === false) continue;
        $arr = json_decode($json, true);
        if (is_array($arr)) $entries = array_merge($entries, $arr);
      }
    }
  }
  return $entries;
}

// Handle POST actions: logout, download csv/json
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  if (!verify_csrf($token)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid CSRF token';
    exit;
  }

  if ($action === 'logout') {
    do_logout();
    header('Location: login.php');
    exit;
  }

  if ($action === 'download_csv') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="applications.csv"');
    $out = fopen('php://output', 'w');
  fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','certifications','resume_file','mail_sent','ip']);
    foreach ($entries as $e) {
  fputcsv($out, [
        $e['timestamp'] ?? '',
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $e['email'] ?? '',
        $e['phone'] ?? '',
        $e['address'] ?? '',
        $e['age'] ?? '',
        $e['eligible_to_work'] ?? '',
        $e['position_desired'] ?? '',
        $e['employment_type'] ?? '',
        $e['desired_salary'] ?? '',
        $e['start_date'] ?? '',
        is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
        $e['shift_preference'] ?? '',
        $e['hours_per_week'] ?? '',
        $e['restaurant_experience'] ?? '',
        $e['other_experience'] ?? '',
        $e['why_work_here'] ?? '',
        $e['references'] ?? '',
        is_array($e['certifications']) ? implode('|', $e['certifications']) : ($e['certifications'] ?? ''),
        $e['resume_file'] ?? '',
        !empty($e['mail_sent']) ? '1' : '0',
        $e['ip'] ?? ''
      ]);
    }
    fclose($out);
    exit;
  }

  if ($action === 'download_json') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="applications.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
  }
  
  // Delete an uploaded resume file and clear references in applications.json
  if ($action === 'delete_resume') {
    $resume = $_POST['resume_file'] ?? '';
    $resume = basename((string)$resume);
    if ($resume && preg_match('/^[a-zA-Z0-9_\-\.]+$/', $resume)) {
      $candidates = [
        __DIR__ . '/../private_data/resumes/',
        dirname(__DIR__) . '/../private_data/resumes/',
        __DIR__ . '/../../private_data/resumes/',
      ];
      $moved = false; $foundAt = null;
      $archDir = dirname(__DIR__) . '/../private_data/resumes/archived/';
      if (!is_dir($archDir)) @mkdir($archDir, 0755, true);
      foreach ($candidates as $cand) {
        $full = $cand . $resume;
        if (file_exists($full) && is_file($full)) {
          $dest = rtrim($archDir, '/') . '/' . $resume;
          // ensure unique dest name if collision
          $i = 0; $base = pathinfo($resume, PATHINFO_FILENAME); $ext = pathinfo($resume, PATHINFO_EXTENSION);
          while (file_exists($dest)) { $i++; $dest = rtrim($archDir, '/') . '/' . $base . '_' . $i . ($ext?'.'.$ext:''); }
          if (@rename($full, $dest)) { $moved = true; $foundAt = $full; $archivedAs = basename($dest); }
          break;
        }
      }
      // Audit log for deletions (JSON lines)
      if ($moved) {
        $audit = [
          'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
          'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
          'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
          'original_path' => $foundAt,
          'archived_filename' => $archivedAs,
        ];
        $logDir = dirname(__DIR__) . '/data';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logFile = $logDir . '/resume-deletions.log';
        @file_put_contents($logFile, json_encode($audit, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
      }
      // Clear resume_file references in live applications file
      $appsPath = $APPLICATIONS_FILE;
      $apps = [];
      if (file_exists($appsPath)) {
        $raw = @file_get_contents($appsPath);
        $apps = $raw ? json_decode($raw, true) : [];
        if (!is_array($apps)) $apps = [];
      }
      $changed = false;
      foreach ($apps as &$a) {
        if (!empty($a['resume_file']) && basename($a['resume_file']) === $resume) { $a['resume_file'] = ''; $changed = true; }
      }
      if ($changed) {
        $tmp = $appsPath . '.tmp';
        $json = json_encode($apps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) { file_put_contents($tmp, $json, LOCK_EX); @rename($tmp, $appsPath); }
      }
    }
    header('Location: index.php'); exit;
  }
  
  // Permanently delete all files in the archived resumes directory
  if ($action === 'empty_resume_archive') {
    $archDir = dirname(__DIR__) . '/../private_data/resumes/archived/';
    if (is_dir($archDir)) {
      $files = glob(rtrim($archDir, '/') . '/*');
      if ($files) {
        foreach ($files as $f) {
          if (is_file($f)) {
            $name = basename($f);
            @unlink($f);
            // write audit line
            $audit = [
              'timestamp' => function_exists('eastern_now') ? eastern_now('c') : date('c'),
              'admin' => $_SESSION['admin_user'] ?? ($_SESSION['admin_logged_in'] ? 'admin' : 'unknown'),
              'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
              'action' => 'empty_archive_delete',
              'archived_filename' => $name,
            ];
            $logDir = dirname(__DIR__) . '/data';
            if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
            $logFile = $logDir . '/resume-deletions.log';
            @file_put_contents($logFile, json_encode($audit, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
          }
        }
      }
    }
    header('Location: index.php'); exit;
  }
  // export all (live + archives)
  if ($action === 'download_all_csv' || $action === 'download_all_json') {
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    // include archives (both new and legacy patterns)
    $archiveDir = __DIR__ . '/../data/archives';
    $archived = load_archived_entries($archiveDir);
    if (!empty($archived)) $entries = array_merge($entries, $archived);

    if ($action === 'download_all_json') {
      header('Content-Type: application/json');
      header('Content-Disposition: attachment; filename="all-applications.json"');
      echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      exit;
    }

    // CSV
    header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="all-applications.csv"');
    $out = fopen('php://output', 'w');
  fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','certifications','resume_file','mail_sent','ip']);
    foreach ($entries as $e) {
  fputcsv($out, [
        $e['timestamp'] ?? '',
        $e['first_name'] ?? '',
        $e['last_name'] ?? '',
        $e['email'] ?? '',
        $e['phone'] ?? '',
        $e['address'] ?? '',
        $e['age'] ?? '',
        $e['eligible_to_work'] ?? '',
        $e['position_desired'] ?? '',
        $e['employment_type'] ?? '',
        $e['desired_salary'] ?? '',
        $e['start_date'] ?? '',
        is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
        $e['shift_preference'] ?? '',
        $e['hours_per_week'] ?? '',
        $e['restaurant_experience'] ?? '',
        $e['other_experience'] ?? '',
        $e['why_work_here'] ?? '',
        $e['references'] ?? '',
        is_array($e['certifications']) ? implode('|', $e['certifications']) : ($e['certifications'] ?? ''),
        $e['resume_file'] ?? '',
        !empty($e['mail_sent']) ? '1' : '0',
        $e['ip'] ?? ''
      ]);
    }
    fclose($out);
    exit;
  }
  
  // quotes export
  if ($action === 'download_quotes') {
    $resFile = __DIR__ . '/../data/quotes.json';
    $rows = [];
    if (file_exists($resFile)) {
      $j = @file_get_contents($resFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quotes.csv"');
    $out = fopen('php://output','w');
    // export a compact CSV with key quote fields
    fputcsv($out, ['timestamp','customer_name','company_name','phone','email','container_size','quantity','rental_duration','start_date','delivery_address','ip']);
    foreach ($rows as $r) {
      fputcsv($out, [ $r['timestamp'] ?? '', $r['customer_name'] ?? '', $r['company_name'] ?? '', $r['phone'] ?? '', $r['email'] ?? '', $r['container_size'] ?? '', $r['quantity'] ?? '', $r['rental_duration'] ?? '', $r['start_date'] ?? '', $r['delivery_address'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
  }

  // download quote audit as CSV
  if ($action === 'download_quote_audit') {
    $auditFile = __DIR__ . '/../data/quote-audit.json';
    $rows = [];
    if (file_exists($auditFile)) {
      $j = @file_get_contents($auditFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quote-audit.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['timestamp','customer_name','phone','container_size','quantity','rental_duration','ip']);
    foreach ($rows as $r) {
      fputcsv($out, [ $r['timestamp'] ?? '', $r['customer_name'] ?? '', $r['phone'] ?? '', $r['container_size'] ?? '', $r['quantity'] ?? '', $r['rental_duration'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
  }

  // clear quote audit
  if ($action === 'clear_quote_audit') {
    $auditFile = __DIR__ . '/../data/quote-audit.json';
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($auditFile . '.tmp', $json, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); }
    header('Location: index.php'); exit;
  }

  // delete quote by index
  if ($action === 'delete_quote') {
    $idx = isset($_POST['idx']) ? (int)$_POST['idx'] : -1;
    $resFile = __DIR__ . '/../data/quotes.json';
    $rows = [];
    if (file_exists($resFile)) {
      $j = @file_get_contents($resFile);
      $rows = $j ? json_decode($j, true) : [];
      if (!is_array($rows)) $rows = [];
    }
    if ($idx >= 0 && isset($rows[$idx])) {
      array_splice($rows, $idx, 1);
  $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($resFile . '.tmp', $json, LOCK_EX); @rename($resFile . '.tmp', $resFile); }
    }
    header('Location: index.php'); exit;
  }

  // purge logs: create backup archive of combined entries, then remove archives and empty live log
  if ($action === 'purge_logs') {
    $archiveDir = __DIR__ . '/../data/archives';
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    $archived = load_archived_entries($archiveDir);
    if (!empty($archived)) $entries = array_merge($entries, $archived);

    // backup combined into purge-backup-<ts>.json.gz
  if (!is_dir($archiveDir)) @mkdir($archiveDir, 0755, true);
  $ts = (function_exists('eastern_now') ? eastern_now('Ymd_His') : date('Ymd_His'));
    $backupName = $archiveDir . "/purge-backup-{$ts}.json.gz";
    $gz = gzencode(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 9);
  if ($gz !== false) { if (file_put_contents($backupName . '.tmp', $gz, LOCK_EX) !== false) @rename($backupName . '.tmp', $backupName); }

    // remove archives
    if (is_dir($archiveDir)) {
      $files = glob($archiveDir . '/*.json.gz');
      if ($files) foreach ($files as $f) @unlink($f);
    }
    // empty live log
  // delete associated resume files for entries being purged
  foreach ($entries as $e) {
    if (!empty($e['resume_file'])) {
      $fn = basename($e['resume_file']);
      $p = __DIR__ . '/../private_data/resumes/' . $fn;
      if (file_exists($p) && is_file($p)) @unlink($p);
    }
  }
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($logFile . '.tmp', $json, LOCK_EX); @rename($logFile . '.tmp', $logFile); }

    // redirect back
    header('Location: index.php');
    exit;
  }
}

$entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);

// --- Search and Pagination (server-side) ---
$search = trim((string)($_GET['search'] ?? ''));
$per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// newest-first
$entries = array_reverse($entries);

// Load current site content for editor
$siteContent = [];
if (file_exists(CONTENT_FILE)) {
  $c = @file_get_contents(CONTENT_FILE);
  $siteContent = $c ? json_decode($c, true) : [];
  if (!is_array($siteContent)) $siteContent = [];
}

// Precompute admin counts so server-side rendering can use them earlier
$live_entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
$applications_live_count = is_array($live_entries) ? count($live_entries) : 0;
$archiveDir = __DIR__ . '/../data/archives';
$archived_entries = load_archived_entries($archiveDir);
$applications_archive_count = is_array($archived_entries) ? count($archived_entries) : 0;
$applications_all_count = $applications_live_count + $applications_archive_count;

// resumes archived count
$resumeCandidates = [
  __DIR__ . '/../private_data/resumes/archived/',
  dirname(__DIR__) . '/../private_data/resumes/archived/',
  __DIR__ . '/../../private_data/resumes/archived/',
];
$resumes_count = 0;
foreach ($resumeCandidates as $rc) {
  if (is_dir($rc)) {
    $g = glob(rtrim($rc, '/') . '/*');
    if ($g) {
      foreach ($g as $f) { if (is_file($f)) $resumes_count++; }
    }
    break;
  }
}

// quotes count
$quotesFile = __DIR__ . '/../data/quotes.json';
$quotes_count = 0;
if (file_exists($quotesFile)) {
  $jq = @file_get_contents($quotesFile);
  $qrows = $jq ? json_decode($jq, true) : [];
  if (is_array($qrows)) $quotes_count = count($qrows);
}

$admin_counts = [
  'applications_live' => $applications_live_count,
  'applications_archive' => $applications_archive_count,
  'applications_all' => $applications_all_count,
  'resumes' => $resumes_count,
  'quotes' => $quotes_count,
];

// filter
$filtered = [];
if ($search === '') {
  $filtered = $entries;
} else {
  $s = mb_strtolower($search);
  foreach ($entries as $e) {
    $hay = '';
    $hay .= ($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '') . ' ';
    $hay .= ($e['email'] ?? '') . ' ';
    $hay .= ($e['phone'] ?? '') . ' ';
    $hay .= ($e['position_desired'] ?? '') . ' ';
    $hay .= ($e['raw_message'] ?? '') . ' ';
    $hay .= ($e['why_work_here'] ?? '') . ' ';
    $hay = mb_strtolower($hay);
    if (mb_strpos($hay, $s) !== false) {
      $filtered[] = $e;
    }
  }
}

$total = count($filtered);
$total_pages = $total ? (int)ceil($total / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$paged_entries = array_slice($filtered, $offset, $per_page);

// CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="submissions.csv"');

    $out = fopen('php://output', 'w');
    // header row
    fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','mail_sent','ip']);
    foreach ($entries as $e) {
        fputcsv($out, [
            $e['timestamp'] ?? '',
            $e['first_name'] ?? '',
            $e['last_name'] ?? '',
            $e['email'] ?? '',
            $e['phone'] ?? '',
            $e['address'] ?? '',
            $e['age'] ?? '',
            $e['eligible_to_work'] ?? '',
            $e['position_desired'] ?? '',
            $e['employment_type'] ?? '',
            $e['desired_salary'] ?? '',
            $e['start_date'] ?? '',
            is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
            $e['shift_preference'] ?? '',
            $e['hours_per_week'] ?? '',
            $e['restaurant_experience'] ?? '',
            $e['other_experience'] ?? '',
            $e['why_work_here'] ?? '',
            $e['references'] ?? '',
            !empty($e['mail_sent']) ? '1' : '0',
            $e['ip'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Download combined (live + archived) applications as CSV or JSON via GET
if (isset($_GET['download']) && $_GET['download'] === 'applications_all') {
    $format = strtolower((string)($_GET['format'] ?? 'json'));
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);
    // include archives (both new and legacy patterns)
    $archiveDir = __DIR__ . '/../data/archives';
    $archived = load_archived_entries($archiveDir);
    if (!empty($archived)) $entries = array_merge($entries, $archived);

    if ($format === 'csv') {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="all-applications.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','certifications','resume_file','mail_sent','ip']);
      foreach ($entries as $e) {
        fputcsv($out, [
          $e['timestamp'] ?? '',
          $e['first_name'] ?? '',
          $e['last_name'] ?? '',
          $e['email'] ?? '',
          $e['phone'] ?? '',
          $e['address'] ?? '',
          $e['age'] ?? '',
          $e['eligible_to_work'] ?? '',
          $e['position_desired'] ?? '',
          $e['employment_type'] ?? '',
          $e['desired_salary'] ?? '',
          $e['start_date'] ?? '',
          is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
          $e['shift_preference'] ?? '',
          $e['hours_per_week'] ?? '',
          $e['restaurant_experience'] ?? '',
          $e['other_experience'] ?? '',
          $e['why_work_here'] ?? '',
          $e['references'] ?? '',
          is_array($e['certifications']) ? implode('|', $e['certifications']) : ($e['certifications'] ?? ''),
          $e['resume_file'] ?? '',
          !empty($e['mail_sent']) ? '1' : '0',
          $e['ip'] ?? ''
        ]);
      }
      fclose($out);
      exit;
    }

    // default to JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="all-applications.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Download current (live) applications as CSV or JSON via GET
if (isset($_GET['download']) && $_GET['download'] === 'applications') {
    $format = strtolower((string)($_GET['format'] ?? 'json'));
    $entries = load_entries($APPLICATIONS_FILE, $LEGACY_MESSAGES_FILE);

    if ($format === 'csv') {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="submissions.csv"');
      $out = fopen('php://output', 'w');
      fputcsv($out, ['timestamp','first_name','last_name','email','phone','address','age','eligible_to_work','position_desired','employment_type','desired_salary','start_date','availability','shift_preference','hours_per_week','restaurant_experience','other_experience','why_work_here','references','certifications','resume_file','mail_sent','ip']);
      foreach ($entries as $e) {
        fputcsv($out, [
          $e['timestamp'] ?? '',
          $e['first_name'] ?? '',
          $e['last_name'] ?? '',
          $e['email'] ?? '',
          $e['phone'] ?? '',
          $e['address'] ?? '',
          $e['age'] ?? '',
          $e['eligible_to_work'] ?? '',
          $e['position_desired'] ?? '',
          $e['employment_type'] ?? '',
          $e['desired_salary'] ?? '',
          $e['start_date'] ?? '',
          is_array($e['availability']) ? implode('|', $e['availability']) : $e['availability'] ?? '',
          $e['shift_preference'] ?? '',
          $e['hours_per_week'] ?? '',
          $e['restaurant_experience'] ?? '',
          $e['other_experience'] ?? '',
          $e['why_work_here'] ?? '',
          $e['references'] ?? '',
          is_array($e['certifications']) ? implode('|', $e['certifications']) : ($e['certifications'] ?? ''),
          $e['resume_file'] ?? '',
          !empty($e['mail_sent']) ? '1' : '0',
          $e['ip'] ?? ''
        ]);
      }
      fclose($out);
      exit;
    }

    // default to JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="submissions.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
  <title>Admin - Job Applications</title>
  <?php require_once __DIR__ . '/partials/head.php'; ?>
  </head>
  <body class="admin">
  <!-- page-level helpers are now in /assets/css/admin.css -->

    <div id="toast-container"></div>
    <div id="modal-backdrop" class="modal-backdrop">
  <div id="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div id="modal-header"><h2 id="modal-title">Confirm action</h2><button id="modal-close" aria-label="Close">✕</button></div>
        <div id="modal-body" class="modal-body">Are you sure?</div>
        <div class="actions"><button id="modal-cancel" type="button" class="btn btn-ghost">Cancel</button><button id="modal-ok" type="button" class="btn btn-primary">Confirm</button></div>
      </div>
    </div>
    <div class="page-wrap">
      <div class="admin-card">
        <div class="header-row">
          <div class="header-left">
            <?php
              // render site logo in admin header if available
              $adminLogo = '';
              if (!empty($siteContent['images']['logo'])) {
                require_once __DIR__ . '/partials/uploads.php';
                $lf = $siteContent['images']['logo'];
                if (preg_match('#^https?://#i', $lf)) $adminLogo = $lf; else $adminLogo = admin_image_src($lf);
              }
            ?>
            <div class="header-brand">
              <a href="../" class="logo logo-inline">
                <?php if ($adminLogo): ?>
                      <?php
                        require_once __DIR__ . '/partials/uploads.php';
                        $logoVal = $siteContent['images']['logo'] ?? '';
                        if (preg_match('#^https?://#i', $logoVal)) {
                          $logo48 = $logo96 = $logo192 = $logoVal;
                          $logo48_webp = $logo96_webp = $logo192_webp = $logoVal;
                        } else {
                          $logo48 = admin_image_src($logoVal ? preg_replace('/\.png$/i','-48.png', $logoVal) : 'logo-48.png');
                          $logo96 = admin_image_src($logoVal ? preg_replace('/\.png$/i','-96.png', $logoVal) : 'logo-96.png');
                          $logo192 = admin_image_src($logoVal ? preg_replace('/\.png$/i','-192.png', $logoVal) : 'logo-192.png');
                          $logo48_webp = preg_replace('/\.png$/i', '.webp', $logo48);
                          $logo96_webp = preg_replace('/\.png$/i', '.webp', $logo96);
                          $logo192_webp = preg_replace('/\.png$/i', '.webp', $logo192);
                        }
                      ?>
                  <picture>
                    <source type="image/webp" srcset="<?php echo htmlspecialchars($logo48_webp); ?> 1x, <?php echo htmlspecialchars($logo96_webp); ?> 2x, <?php echo htmlspecialchars($logo192_webp); ?> 4x">
                    <img src="<?php echo htmlspecialchars($logo48); ?>" srcset="<?php echo htmlspecialchars($logo48); ?> 1x, <?php echo htmlspecialchars($logo96); ?> 2x, <?php echo htmlspecialchars($logo192); ?> 4x" alt="<?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Site'); ?>" class="logo-img">
                  </picture>
                <?php else: ?>
                  <strong><?php echo htmlspecialchars($siteContent['business_info']['name'] ?? 'Admin'); ?></strong>
                <?php endif; ?>
              </a>
              <div>
                <h1 class="m-0">Admin Dashboard</h1>
                <div class="topbar">
                  <a href="../" class="btn btn-ghost" target="_blank">View site</a>
                  <a href="email-scheduler.php" class="btn btn-ghost ml-025">Email Scheduler</a>
                </div>
              </div>
            </div>
          </div>
          <div class="header-actions">
              <div class="profile-wrap">
              <button id="profile-btn" type="button" class="btn btn-ghost"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.34 16.4l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.67 0 1.27-.4 1.51-1a1.65 1.65 0 0 0-.33-1.82l-.06-.06A2 2 0 1 1 7.6 2.34l.06.06c.45.45 1.02.7 1.64.7.22 0 .44-.03.65-.09.56-.17 1.16-.17 1.72 0 .21.06.43.09.65.09.62 0 1.19-.25 1.64-.7l.06-.06A2 2 0 1 1 21.66 7.6l-.06.06c-.17.17-.3.36-.4.57-.2.46-.2.98 0 1.44.1.21.23.4.4.57l.06.06A2 2 0 0 1 19.4 15z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Admin Options ▾</button>
              <div id="profile-menu">
                <div class="pm-item pm-sep">
                    <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v12M8 7l4-4 4 4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download current applications ▾</button>
          <div class="pm-combo-menu">
<?php if (!empty($admin_counts['applications_live'])): ?>
                        <a href="?download=applications&amp;format=csv" class="pm-subitem" role="menuitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['applications_live']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['applications_live']; ?> current applications</span></a>
                        <a href="?download=applications&amp;format=json" class="pm-subitem" role="menuitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['applications_live']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['applications_live']; ?> current applications</span></a>
<?php else: ?>
                        <span class="pm-subitem disabled" role="menuitem" aria-disabled="true"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV <span class="small muted">(0)</span></span>
                        <span class="pm-subitem disabled" role="menuitem" aria-disabled="true"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON <span class="small muted">(0)</span></span>
<?php endif; ?>
          </div>
                  </div>
                </div>
                <div class="pm-item pm-sep">
                  <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 7h18v10H3zM7 3v4M17 3v4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download all applications ▾</button>
          <div class="pm-combo-menu">
<?php if (!empty($admin_counts['applications_all'])): ?>
                        <a href="?download=applications_all&amp;format=csv" class="pm-subitem" role="menuitem" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['applications_all']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['applications_all']; ?> total applications</span></a>
                        <a href="?download=applications_all&amp;format=json" class="pm-subitem" role="menuitem" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5h8v14H8z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['applications_all']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['applications_all']; ?> total applications</span></a>
<?php else: ?>
                        <span class="pm-subitem disabled" role="menuitem" aria-disabled="true" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>CSV <span class="small muted">(0)</span></span>
                        <span class="pm-subitem disabled" role="menuitem" aria-disabled="true" data-confirm="Downloading all applications may create a large file. Continue?"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M8 5h8v14H8z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>JSON <span class="small muted">(0)</span></span>
<?php endif; ?>
          </div>
                  </div>
                </div>
                <div class="pm-item pm-sep">
                    <form method="post" class="pm-form" data-confirm="This will archive and remove all logs — are you sure?">
                      <?php echo csrf_input_field(); ?>
                      <input type="hidden" name="action" value="purge_logs">
                      <button type="submit" class="btn btn-danger-muted"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"></svg></span>Archive & clear all applications</button>
                    </form>
                </div>
                <!-- quote audit submenu handled below -->
                <div class="pm-item">
                    <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 7h18M3 12h18M3 17h18" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Quote Audit ▾</button>
                    <div class="pm-combo-menu">
                      <a href="quote-audit.php" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4zM4 10h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>View full quote audit</a>
                      <a href="quote-audit.php?download=csv" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4zM8 10v6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download CSV <span class="count-badge" aria-hidden="true"><?php echo (int)($admin_counts['quotes'] ?? 0); ?></span><span class="sr-only"><?php echo (int)($admin_counts['quotes'] ?? 0); ?> quote audit entries</span></a>
                      <a href="quote-audit.php?download=json" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download JSON <span class="count-badge" aria-hidden="true"><?php echo (int)($admin_counts['quotes'] ?? 0); ?></span><span class="sr-only"><?php echo (int)($admin_counts['quotes'] ?? 0); ?> quote audit entries</span></a>
                      <form method="post" class="m-0" data-confirm="Clear quote audit? This will remove recent audit entries. Continue?">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="clear_quote_audit">
                
                        <button type="submit" class="btn btn-danger-muted pm-subitem-full"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 7h12M9 7v10M15 7v10" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Clear quote audit</button>
                      </form>
                      <form method="post" class="m-0">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="download_quotes">
                        <button type="submit" class="pm-subitem pm-subitem-full"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v10M8 9l4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download Quotes (CSV)</button>
                      </form>
                    </div>
                  </div>
                </div>
                  <div class="pm-item pm-sep">
                    <div class="pm-combo">
                    <button type="button" class="btn btn-ghost pm-combo-toggle" aria-expanded="false"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Resumes ▾</button>
                    <div class="pm-combo-menu">
                      <a href="admin-resumes.php" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16" stroke-linecap="round" stroke-linejoin="round"/></svg></span>View archived resumes</a>
<?php if (!empty($admin_counts['resumes'])): ?>
                      <a href="admin-resumes.php?download=csv" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download CSV <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['resumes']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['resumes']; ?> archived resumes</span></a>
                      <a href="admin-resumes.php?download=json" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download JSON <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['resumes']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['resumes']; ?> archived resumes</span></a>
                      <a href="download-all-resumes.php" class="pm-subitem"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 5h18v14H3z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download all resumes (zip) <span class="count-badge" aria-hidden="true"><?php echo (int)$admin_counts['resumes']; ?></span><span class="sr-only"><?php echo (int)$admin_counts['resumes']; ?> archived resumes</span></a>
<?php else: ?>
                      <span class="pm-subitem disabled" role="menuitem" aria-disabled="true"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download CSV <span class="count-badge" aria-hidden="true">0</span><span class="sr-only">0 archived resumes</span></span>
                      <span class="pm-subitem disabled" role="menuitem" aria-disabled="true"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 7l10 5-10 5V7z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download JSON <span class="count-badge" aria-hidden="true">0</span><span class="sr-only">0 archived resumes</span></span>
                      <span class="pm-subitem disabled" role="menuitem" aria-disabled="true"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 5h18v14H3z" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Download all resumes (zip) <span class="count-badge" aria-hidden="true">0</span><span class="sr-only">0 archived resumes</span></span>
<?php endif; ?>
                      <form method="post" action="restore-recent-resumes.php" class="m-0" style="display:flex;gap:.5rem;align-items:center;padding:.35rem .5rem;" data-confirm="Restore trashed resumes from the last N days?">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="restore_recent">
                        <label style="font-weight:600;">From last <input type="number" name="days" value="7" min="1" style="width:5rem;margin-left:.4rem;padding:.2rem .4rem;border:1px solid rgba(0,0,0,0.06);border-radius:6px"> days</label>
                        <button type="submit" class="btn btn-ghost" style="margin-left:auto;">Restore recent</button>
                      </form>
                      <form method="post" class="m-0" style="display:flex;gap:.5rem;align-items:center;padding:.35rem .5rem;" data-confirm="Move archived resumes older than the specified days to trash?">
                        <?php echo csrf_input_field(); ?>
                        <input type="hidden" name="action" value="purge_old">
                        <label style="font-weight:600;">Older than <input type="number" name="days" value="30" min="1" style="width:5rem;margin-left:.4rem;padding:.2rem .4rem;border:1px solid rgba(0,0,0,0.06);border-radius:6px"> days</label>
                        <button type="submit" class="btn btn-danger-muted" style="margin-left:auto;">Purge</button>
                      </form>
                    </div>
                  </div>
                </div>
                  <div class="pm-item">
                    <form method="post" action="empty-trash.php" class="pm-form" data-confirm="Empty image trash? This will permanently delete trashed images. Continue?">
                      <?php echo csrf_input_field(); ?>
                      <button type="submit" class="btn btn-danger-muted"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" stroke-linecap="round" stroke-linejoin="round"></svg></span>Empty image trash</button>
                    </form>
                  </div>
                <div class="pm-item pm-sep">
                  <form method="get" action="change-password.php" class="pm-form m-0">
                    <button type="submit" class="btn btn-ghost"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM5 11v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Change admin password</button>
                  </form>
                </div>
                <div class="pm-item">
                  <a class="btn btn-ghost" href="smtp-settings.php"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 4h16v16H4zM4 8l8 5 8-5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>SMTP Settings</a>
                </div>
                <div class="pm-item pm-sep">
                  <form method="post" class="pm-form">
                    <?php echo csrf_input_field(); ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-logout"><span class="pm-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 17l5-5-5-5M21 12H9M13 19v2H5V3h8v2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Log out</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
    <!-- SMTP settings moved to smtp-settings.php -->

    

    <h1>Job Applications</h1>
    <form method="get" class="search-form">
      <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, phone, application details..." class="search-input">
      <label class="perpage-label">Per page:
        <select name="per_page" onchange="this.form.submit()">
          <option value="10" <?php if ($per_page==10) echo 'selected'; ?>>10</option>
          <option value="20" <?php if ($per_page==20) echo 'selected'; ?>>20</option>
          <option value="50" <?php if ($per_page==50) echo 'selected'; ?>>50</option>
          <option value="100" <?php if ($per_page==100) echo 'selected'; ?>>100</option>
        </select>
      </label>
      <button type="submit">Search</button>
    </form>
  <p class="small">Total results: <?php echo (int)$total; ?> (page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?>)</p>
    <?php if (empty($entries)): ?>
      <p>No submissions yet.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date / Time</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Position</th>
            <th>Certifications</th>
            <th>Resume</th>
            <th>Mail Sent</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($paged_entries as $e): ?>
          <tr>
            <td><?php echo htmlspecialchars(admin_format_datetime($e['timestamp'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($e['email'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($e['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($e['position_desired'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars(is_array($e['certifications']) ? implode(', ', $e['certifications']) : ($e['certifications'] ?? '')); ?></td>
            <td>
              <?php if (!empty($e['resume_file'])):
                // resume_file stored as data/resumes/<file>; extract filename
                $rf = basename($e['resume_file']);
              ?>
                <a href="download-resume.php?file=<?php echo urlencode($rf); ?>">Download</a>
                <span class="small ml-05"><?php echo htmlspecialchars($rf); ?></span>
                <div class="mt-04">
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this resume file? This cannot be undone.');">
                    <?php echo csrf_input_field(); ?>
                    <input type="hidden" name="action" value="delete_resume">
                    <input type="hidden" name="resume_file" value="<?php echo htmlspecialchars($rf); ?>">
                    <button type="submit" class="btn btn-danger-muted">Delete</button>
                  </form>
                  <button type="button" class="btn btn-ghost view-app" data-entry="<?php echo htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8'); ?>">View</button>
                </div>
              <?php else: ?>
                — <div class="mt-04"><button type="button" class="btn btn-ghost view-app" data-entry="<?php echo htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8'); ?>">View</button></div>
              <?php endif; ?>
            </td>
            <td><?php echo !empty($e['mail_sent']) ? 'Yes' : 'No'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <hr class="spaced-hr">
    <h2>Units Management</h2>
    <p class="small">Manage storage unit categories and listings shown on the public site.</p>
    <div id="menu-admin-wrap" class="menu-admin-wrap">
  <div id="menu-admin" class="flex-1">
  <div class="mb-05"><button id="add-menu-item" type="button" class="btn btn-primary">Add Unit Category</button></div>
        <div id="menu-list">
          <?php
            // Server-side fallback: render the current menu so the admin can
            // view existing sections even if JS is not running or fails. This
            // is a minimal, read-only rendering to aid discoverability.
            $menu = $siteContent['menu'] ?? [];
            if (is_array($menu) && count($menu)) {
              foreach ($menu as $sec) {
                $stitle = htmlspecialchars($sec['title'] ?? ($sec['id'] ?? 'Section'));
                // optionally emit a data-section-id attribute when available so
                // the client-side editor can correlate persisted state with
                // server-rendered sections
                $secIdAttr = '';
                if (!empty($sec['id'])) {
                  $secIdAttr = ' data-section-id="' . htmlspecialchars($sec['id']) . '"';
                }
                echo '<div class="section-wrap"' . $secIdAttr . '>';
                echo '<div class="menu-section-header"><div class="flex-1"><strong>' . $stitle . '</strong></div></div>';
                $items = [];
                if (isset($sec['items']) && is_array($sec['items'])) { $items = $sec['items']; }
                elseif (is_array($sec)) { $items = $sec; }
                // Wrap items in the same class the client uses and add the
                // `expanded` class so the fallback is visible even when JS is
                // not running or when CSS collapse rules are in effect.
                if (!empty($items)) {
                  echo '<div class="menu-section-items expanded">';
                  echo '<ul class="small m-0 pl-1">';
                  foreach ($items as $it) {
                    $label = is_array($it) ? ($it['title'] ?? ($it['name'] ?? '')) : (string)$it;
                    echo '<li>' . htmlspecialchars($label) . '</li>';
                  }
                  echo '</ul>';
                  echo '</div>';
                } else {
                  echo '<div class="menu-section-items expanded"><div class="small muted">No items</div></div>';
                }
                echo '</div>';
              }
            } else {
              echo '<div class="empty-note">Menu editor ready — click "Add Unit Category" to create a section.</div>';
            }
          ?>
        </div>
      </div>
      <div id="menu-preview" class="menu-preview">
        <h3 class="mt-0">Live Preview</h3>
  <div id="preview-area" class="preview-area"></div>
      </div>
    </div>
    
    <hr class="spaced-hr">
    <?php
  // Compact recent quote summary (last 5 audit entries) (removed - using Quotes table)
  ?>

  <h2>Quotes</h2>
  <p class="small">Review quote requests submitted through the public site.</p>
    <?php
  // Load quotes once and compute a tiny dashboard: latest quote + simple trends
  $resFile = __DIR__ . '/../data/quotes.json';
      $quotes = [];
      if (file_exists($resFile)) {
        $j = @file_get_contents($resFile);
        $quotes = $j ? json_decode($j, true) : [];
        if (!is_array($quotes)) $quotes = [];
      }

  // compute latest quote (newest by timestamp string)
  $latest_quote = null;
  if (!empty($quotes)) {
    // sort a shallow copy by timestamp descending without modifying original order
    $sorted = $quotes;
    usort($sorted, function($a, $b) {
      $ta = $a['timestamp'] ?? '';
      $tb = $b['timestamp'] ?? '';
      // ISO-like strings compare lexicographically; fall back to strcmp
      return strcmp($tb, $ta);
    });
    $latest_quote = $sorted[0];
  }

  // simple trends: total quotes, last 7 days, top container size
  $total_quotes = count($quotes);
  $container_counts = [];
  $quotes_last_7 = 0;
  try {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $sevenAgo = $now->sub(new DateInterval('P7D'));
  } catch (Exception $e) {
    $now = null; $sevenAgo = null;
  }
  foreach ($quotes as $q) {
    $size = $q['container_size'] ?? 'Unknown';
    $container_counts[$size] = ($container_counts[$size] ?? 0) + 1;
    if (!empty($q['timestamp']) && $sevenAgo instanceof DateTimeImmutable) {
      try {
        $dt = new DateTimeImmutable($q['timestamp']);
        if ($dt >= $sevenAgo) $quotes_last_7++;
      } catch (Exception $e) {
        // ignore unparsable timestamps
      }
    }
  }
  arsort($container_counts);
  $top_container = null;
  $top_container_count = 0;
  if (!empty($container_counts)) {
    foreach ($container_counts as $k => $v) { $top_container = $k; $top_container_count = $v; break; }
  }
    ?>

    <div class="cards-row">
      <div class="card" style="display:inline-block;min-width:260px;max-width:420px;margin-right:12px;vertical-align:top;">
        <h3 class="mt-0">Latest quote</h3>
        <?php if ($latest_quote): ?>
          <div class="small muted"><?php echo htmlspecialchars(admin_format_datetime($latest_quote['timestamp'] ?? '')); ?></div>
          <div style="margin-top:6px"><strong><?php echo htmlspecialchars($latest_quote['customer_name'] ?? 'Unknown'); ?></strong></div>
          <div class="small"><?php echo htmlspecialchars($latest_quote['container_size'] ?? ''); ?> — Qty <?php echo htmlspecialchars($latest_quote['quantity'] ?? ''); ?></div>
          <?php if (!empty($latest_quote['phone'])): ?><div class="small">Phone: <?php echo htmlspecialchars($latest_quote['phone']); ?></div><?php endif; ?>
          <div style="margin-top:8px"><a href="quote-audit.php" class="btn btn-ghost small">View audit</a></div>
        <?php else: ?>
          <div class="small muted">No quotes yet.</div>
        <?php endif; ?>
      </div>

      <div class="card" style="display:inline-block;min-width:200px;max-width:320px;vertical-align:top;">
        <h3 class="mt-0">Trends</h3>
        <div class="small">Total quotes: <strong><?php echo (int)$total_quotes; ?></strong></div>
        <div class="small">Last 7 days: <strong><?php echo (int)$quotes_last_7; ?></strong></div>
        <div class="small">Top container: <strong><?php echo htmlspecialchars($top_container ?? '—'); ?></strong> <?php if ($top_container_count) echo '<span class="muted">(' . (int)$top_container_count . ')</span>'; ?></div>
      </div>
    </div>
    <?php if (empty($quotes)): ?>
      <p>No quotes yet.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr><th>Date / Time</th><th>Name</th><th>Phone</th><th>Container</th><th>Quantity</th><th>Duration</th><th></th></tr>
        </thead>
        <tbody>
  <?php foreach ($quotes as $i => $r): ?>
          <tr>
            <td><?php echo htmlspecialchars(admin_format_datetime($r['timestamp'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['container_size'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['quantity'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['rental_duration'] ?? ''); ?></td>
            <td>
              <form method="post" class="d-inline" data-confirm="Delete this quote?">
                <?php echo csrf_input_field(); ?>
                <input type="hidden" name="action" value="delete_quote">
                <input type="hidden" name="idx" value="<?php echo (int)$i; ?>">
                <button type="submit" class="btn btn-danger-muted">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  <hr class="spaced-hr">
    <h2>Site Content Editor</h2>
  <p class="small">Edit named content sections and save. Changes are saved to the site's content store — no manual file edits are required.</p>

    <div id="content-editor" class="content-editor-wrap">
      <label for="section-select">Section</label>
      <select id="section-select" class="section-select"></select>

      <div id="schema-form-wrap" class="schema-wrap">
        <form id="schema-form">
          <?php echo csrf_input_field(); ?>
          <div id="schema-fields"></div>
          <div class="form-actions">
            <button id="save-section" type="submit" class="btn btn-primary">Save Section</button>
          </div>
        </form>
      </div>

      <!-- Admin-only live preview panel -->
      <div id="admin-preview-wrap" class="admin-preview-wrap mt-05">
        <div class="admin-preview-header"><strong>Admin Preview</strong> <span id="preview-last-updated" class="small muted"></span></div>
        <div id="admin-content-preview" class="admin-content-preview small muted">Loading preview&hellip;</div>
        <div class="mt-05"><button id="toggle-preview" type="button" class="btn btn-ghost small">Hide Preview</button></div>
      </div>

      <!-- Application detail modal -->
      <div id="app-modal" role="dialog" aria-hidden="true" class="modal-backdrop">
        <div role="document" class="app-modal-dialog">
          <button id="app-modal-close" type="button" class="btn btn-ghost app-modal-close">Close</button>
          <h3 id="app-modal-title">Application</h3>
          <div id="app-modal-body" class="app-modal-body"></div>
        </div>
      </div>

      <script>
      (function(){
        function el(sel){return document.querySelector(sel)}
        function els(sel){return Array.from(document.querySelectorAll(sel))}
        var modal = el('#app-modal');
        var body = el('#app-modal-body');
        els('.view-app').forEach(function(btn){
          btn.addEventListener('click', function(){
            var data = this.getAttribute('data-entry');
            try { var obj = JSON.parse(data); } catch(e){ obj = null; }
            if (!obj) return;
            var html = '';
            html += '<table class="small app-modal-table">';
            function row(k,v){ return '<tr><th class="app-modal-table">'+k+'</th><td class="app-modal-table">'+(v||'')+'</td></tr>'; }
            html += row('Name', (obj.first_name||'') + ' ' + (obj.last_name||''));
            html += row('Email', obj.email||'');
            html += row('Phone', obj.phone||'');
            html += row('Position', obj.position_desired||'');
            html += row('Employment type', obj.employment_type||'');
            html += row('Age', obj.age||'');
            html += row('Eligible to work', obj.eligible_to_work||'');
            html += row('Availability', Array.isArray(obj.availability) ? obj.availability.join(', ') : (obj.availability||''));
            html += row('Certifications', Array.isArray(obj.certifications) ? obj.certifications.join(', ') : (obj.certifications||''));
            html += row('Experience', obj.restaurant_experience || obj.other_experience || obj.why_work_here || '');
            html += row('References', obj.references || '');
            if (obj.resume_file) {
              var fn = obj.resume_file.split('/').pop();
              html += row('Resume', '<a href="download-resume.php?file='+encodeURIComponent(fn)+'" target="_blank">Download</a> ' + fn);
            }
            html += '</table>';
            body.innerHTML = html;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden','false');
          });
        });
  el('#app-modal-close').addEventListener('click', function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); });
      })();
      </script>
    </div>

    <hr class="spaced-hr">
    <h2>Image Uploads</h2>
  <p class="small">Upload images used on the site. Open a section to upload or replace that specific image type.</p>
    <div class="upload-wrap">
      <?php
        $types = [
          'logo' => 'Logo (site header)',
          'hero' => 'Hero / banner',
          'gallery' => 'Gallery image'
        ];
      ?>
      <?php foreach ($types as $tkey => $tlabel): ?>
        <details class="image-section">
          <summary><?php echo htmlspecialchars($tlabel); ?></summary>
          <div class="image-section-body">
            <?php // show current image preview when available (skip for gallery) ?>
            <?php $current = $siteContent['images'][$tkey] ?? ''; if ($current && $tkey !== 'gallery'): ?>
              <?php $url = preg_match('#^https?://#i', $current) ? $current : admin_upload_url($current); ?>
              <div class="mb-05">
                <div class="small">Current:</div>
                <img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars($tlabel); ?>" class="img-preview-small" />
              </div>
            <?php endif; ?>
            <?php if ($tkey === 'gallery'): ?>
              <div class="mb-05 section-subheader">
                <div>Use the gallery below to manage images.</div>
              </div>
            <?php endif; ?>

            <form method="post" action="upload-image.php" enctype="multipart/form-data" class="mb-05">
              <?php echo csrf_input_field(); ?>
              <input type="hidden" name="type" value="<?php echo htmlspecialchars($tkey); ?>">
              <label class="small">Choose file
                <input type="file" name="image" accept="image/*" required>
              </label>
              <div class="mt-05">
                <button type="submit" class="btn btn-primary">Upload</button>
                <span class="small ml-05">Tip: Use appropriately sized images for best performance.</span>
              </div>
            </form>
            <div class="section-image-list small" data-type="<?php echo htmlspecialchars($tkey); ?>"></div>
            <?php if ($tkey === 'gallery'): ?>
              <div id="gallery-full-list" class="mt-06"></div>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>

      <!-- Button to show a global list of all images (hidden by default content-wise).
           The button is visible so admins can reveal the full list; images are hidden
           until the button is toggled. -->
  <div class="mt-05">
  <button id="show-all-images-btn" type="button" class="btn btn-ghost">See all images</button>
      </div>
    </div>


    <script>
      // bootstrap data for extracted admin JS
      window.__siteContent = <?php echo json_encode($siteContent, JSON_UNESCAPED_SLASHES); ?> || {};
      window.__csrfToken = (document.querySelector('input[name="csrf_token"]') || { value: '' }).value || '';
      window.__schemaUrl = 'content-schemas.json';
      window.__adminCounts = <?php echo json_encode($admin_counts, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
      // disable download links where there are no items to download
      (function(){
        try {
          var counts = window.__adminCounts || {};
          document.querySelectorAll('.pm-subitem[href]').forEach(function(a){
            try {
              var href = a.getAttribute('href');
              if (!href) return;
              var url = new URL(href, window.location.href);
              var page = (url.pathname.split('/').pop() || 'index.php');
              var download = url.searchParams.get('download');
              var disabled = false;

              if (page === '' || page === 'index.php') {
                if (download === 'applications' && (counts.applications_live || 0) === 0) disabled = true;
                if (download === 'applications_all' && (counts.applications_all || 0) === 0) disabled = true;
              } else if (page === 'admin-resumes.php') {
                // any download or zip on resumes should require at least one resume
                if ((counts.resumes || 0) === 0) disabled = true;
              } else if (page === 'quote-audit.php') {
                if ((counts.quotes || 0) === 0) disabled = true;
              }

              if (disabled) {
                a.setAttribute('aria-disabled','true');
                a.classList.add('disabled');
                a.style.opacity = '.6';
                a.style.cursor = 'not-allowed';
                var title = a.getAttribute('title') || a.textContent.trim() || 'Download';
                a.setAttribute('title', 'Nothing to download: ' + title);
                a.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); }, { capture: true });
              }
            } catch (e) { /* ignore per-link errors */ }
          });
        } catch (err) { /* no-op */ }
      })();
    </script>
    <script>
      // admin options submenu toggles for combined CSV/JSON actions with keyboard navigation
      (function(){
        function closeAll() {
          document.querySelectorAll('.pm-combo-menu').forEach(function(m){ m.classList.remove('open'); });
          document.querySelectorAll('.pm-combo-toggle').forEach(function(b){ b.setAttribute('aria-expanded','false'); });
        }

        function openMenu(toggle, menu) {
          closeAll();
          menu.classList.add('open');
          toggle.setAttribute('aria-expanded','true');
          // focus first focusable item in menu
          var first = menu.querySelector('.pm-subitem');
          if (first) first.focus();
        }

        document.addEventListener('click', function(e){
          var t = e.target;
          var toggle = t.closest && t.closest('.pm-combo-toggle');
          if (toggle) {
            var wrap = toggle.parentNode;
            var menu = wrap.querySelector('.pm-combo-menu');
            var isOpen = menu.classList.contains('open');
            if (isOpen) { closeAll(); }
            else { openMenu(toggle, menu); }
            return;
          }
          // click outside closes menus
          if (!t.closest || !t.closest('.pm-combo')) {
            closeAll();
          }
        });

        // keyboard navigation within menus
        document.addEventListener('keydown', function(e){
          if (e.key === 'Escape') { closeAll(); return; }
          var active = document.activeElement;
          var inMenu = active && active.closest && active.closest('.pm-combo-menu');
          if (!inMenu) return;
          var items = Array.prototype.slice.call(active.closest('.pm-combo-menu').querySelectorAll('.pm-subitem'));
          var idx = items.indexOf(active);
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            var next = items[idx+1] || items[0]; next.focus();
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            var prev = items[idx-1] || items[items.length-1]; prev.focus();
          } else if (e.key === 'Enter') {
            // activate focused item
            if (active && active.click) { active.click(); }
          }
        });

        // add confirmation for large exports (download_all)
        document.addEventListener('click', function(e){
          var btn = e.target.closest && e.target.closest('.pm-subitem');
          if (!btn) return;
          var form = btn.tagName.toLowerCase() === 'button' ? btn.form : btn.closest('form');
          if (!form) return;
          var actionInput = form.querySelector('input[name="action"]');
          if (actionInput && actionInput.value && actionInput.value.indexOf('download_all') === 0) {
            if (!confirm('Downloading all applications may create a large file. Continue?')) {
              e.preventDefault();
              return false;
            }
          }
        }, true);
      })();
    </script>
    <script src="/assets/js/admin.js"></script>
    <script>
      // Defensive: if a profile/admin menu is left open due to earlier debug changes
      // or race conditions, close it and ensure clicking outside or Escape will close it.
      (function(){
        try {
          var profileBtn = document.getElementById('profile-btn');
          var profileMenu = document.getElementById('profile-menu');
          if (profileMenu) {
            // remove any forced show-block class that might have been left
            profileMenu.classList.remove('show-block');
            // ensure aria-expanded on button reflects closed state
            if (profileBtn) profileBtn.setAttribute('aria-expanded','false');
            // click outside to close
            document.addEventListener('click', function(e){
              if (!profileMenu.classList.contains('show-block')) return;
              if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove('show-block');
                if (profileBtn) profileBtn.setAttribute('aria-expanded','false');
              }
            });
            // Escape to close
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { if (profileMenu.classList.contains('show-block')) { profileMenu.classList.remove('show-block'); if (profileBtn) profileBtn.setAttribute('aria-expanded','false'); } } });
          }
        } catch (e) { /* ignore */ }
      })();
    </script>
    

    <script>
      // lightweight toast (works even if admin.js scope doesn't expose showToast)
      (function(){
        function toast(msg, type='success', timeout=3500){
          var c = document.getElementById('toast-container');
          if (!c){ c = document.createElement('div'); c.id='toast-container'; document.body.appendChild(c); }
          var el = document.createElement('div'); el.className = 'toast ' + (type==='success' ? 'success' : (type==='error' ? 'error' : ''));
          el.textContent = msg; c.appendChild(el);
          setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, timeout);
        }

        var params = new URLSearchParams(window.location.search);
        if (params.get('msg') === 'trash_emptied') {
          var c = parseInt(params.get('count') || '0', 10);
          if (c > 0) toast('Emptied ' + c + ' files from image trash', 'success');
          else toast('Image trash emptied (no files removed)', 'success');
        }
        if (params.get('msg') === 'notrash') { toast('No trash folder found', 'error'); }
        if (params.get('error') === 'csrf') { toast('Invalid CSRF token', 'error'); }
      })();
    </script>

    <script>
      // Replace native confirm() with a centralized helper provided in assets/js/admin.js
      (function(){
        // if the page's admin.js exposes showAdminConfirm, use it for any forms/buttons with data-confirm
      function delegateConfirmForButtons() {
        document.addEventListener('click', function (e) {
          var el = e.target.closest('button, a');
          if (!el) return;
          // find the closest element that carries the data-confirm attribute
          var elWithConfirm = el.closest('[data-confirm]');
          if (!elWithConfirm) return;

          var msg = elWithConfirm.getAttribute('data-confirm') || 'Are you sure?';

          // If the element itself is an anchor, intercept navigation and confirm first
          if (el.tagName.toLowerCase() === 'a') {
            e.preventDefault();
            if (confirm(msg)) {
              // proceed with navigation
              window.location.href = el.getAttribute('href');
            }
            return;
          }

          // If it's a pm-combo-toggle button (submenu opener) we don't confirm here
          var isToggle = el.classList && el.classList.contains('pm-combo-toggle');
          if (isToggle) {
            // allow toggle to open/close without confirmation
            return;
          }

          // For other buttons, fall back to simple confirm and let default actions run on accept
          e.preventDefault();
          if (confirm(msg)) {
            // If it's a button inside a form, submit it
            var form = el.closest('form');
            if (form) form.submit();
            else if (typeof el.click === 'function') el.click();
          }
        });
      }
        function delegateConfirmForButtons() {
          document.addEventListener('click', async function(e){
            var btn = e.target.closest && e.target.closest('[data-confirm]');
            if (!btn) return;
            // If the element is a button that normally toggles a submenu, show confirm first
            var isToggle = btn.classList && btn.classList.contains('pm-combo-toggle');
            if (!isToggle) return;
            var msg = btn.getAttribute('data-confirm');
            if (!msg) return;
            e.preventDefault();
            try {
              if (window.showAdminConfirm) {
                var ok = await window.showAdminConfirm(msg);
                if (ok) {
                  // trigger original click action by toggling the menu programmatically
                  btn.click();
                }
              } else {
                if (confirm(msg)) btn.click();
              }
            } catch (err) { /* ignore */ }
          }, true);
        }

        function delegateConfirmForForms() {
          // Intercept form submissions for forms (or controls) with data-confirm.
          document.addEventListener('submit', async function(e){
            var form = e.target;
            if (!form || form.tagName.toLowerCase() !== 'form') return;
            // prefer the nearest data-confirm-bearing ancestor, otherwise the form itself
            var elWithConfirm = form.closest('[data-confirm]') || (form.hasAttribute && form.hasAttribute('data-confirm') ? form : null);
            if (!elWithConfirm) return;
            var msg = elWithConfirm.getAttribute('data-confirm') || 'Are you sure?';
            e.preventDefault();
            try {
              var ok;
              if (window.showAdminConfirm) ok = await window.showAdminConfirm(msg);
              else ok = confirm(msg);
              if (ok) {
                // allow the original submission to proceed
                form.submit();
              }
            } catch (err) { /* ignore */ }
          }, true);
        }

        delegateConfirmForForms();
        delegateConfirmForButtons();
      })();
    </script>

    <?php if ($total_pages > 1): ?>
      <div class="pagination-wrap">
        <?php if ($page > 1): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
          <?php if ($p == $page): ?>
            <strong class="current-page"><?php echo (int)$p; ?></strong>
          <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => (int)$p])); ?>"><?php echo (int)$p; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['download']) && $_GET['download'] == '1'): ?>
    <?php
      header('Content-Type: application/json');
      header('Content-Disposition: attachment; filename="submissions.json"');
      echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      exit;
    ?>
    <?php endif; ?>
  <script>
    (function(){
      try{
        var params = new URLSearchParams(window.location.search);
        var msgs = [];
        if (params.has('msg')) {
          var m = params.get('msg');
          if (m === 'trash_emptied') {
            var c = params.get('count') || '0'; msgs.push((parseInt(c,10)||0) + ' trashed images permanently deleted');
          } else if (m === 'notrash') { msgs.push('No trashed images found'); }
          else if (m === 'save_ok') { msgs.push('Settings saved'); }
          else if (m === 'save_failed') { msgs.push('Save failed'); }
          else { msgs.push(m); }
        }
        if (params.has('pw_changed')) { msgs.push('Password changed — you have been logged out'); }
        if (params.has('purged')) { msgs.push((parseInt(params.get('purged')||'0',10) || 0) + ' archived resumes moved to trash'); }
        if (params.has('restored')) { msgs.push((parseInt(params.get('restored')||'0',10) || 0) + ' resumes restored'); }
        if (msgs.length === 0) return;
        var container = document.getElementById('toast-container');
        if (!container) { container = document.createElement('div'); container.id = 'toast-container'; document.body.appendChild(container); }
        msgs.forEach(function(t){ var el = document.createElement('div'); el.className='toast success'; el.textContent = t; container.appendChild(el); setTimeout(function(){ el.classList.add('fade-out'); setTimeout(function(){ el.remove(); }, 350); }, 3500); });
        // clear query
        var url = new URL(window.location.href); ['msg','count','pw_changed','purged','restored'].forEach(function(k){ url.searchParams.delete(k); }); history.replaceState({},'',url.toString());
      } catch(e){ /* no-op */ }
    })();
  </script>
  </body>
 </html>
