<?php
/**
 * admin/quote-audit.php
 * Viewer/exporter for the quote audit file.
 */
require_once __DIR__ . '/config.php';
require_admin();
ensure_csrf_token();

$auditFile = __DIR__ . '/../data/quote-audit.json';
$entries = [];
if (file_exists($auditFile)) {
    $j = @file_get_contents($auditFile);
    $entries = $j ? json_decode($j, true) : [];
    if (!is_array($entries)) $entries = [];
}

// downloads
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="quote-audit.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['timestamp','customer_name','phone','container_size','quantity','rental_duration','ip']);
    foreach ($entries as $r) {
        fputcsv($out, [ $r['timestamp'] ?? '', $r['customer_name'] ?? '', $r['phone'] ?? '', $r['container_size'] ?? '', $r['quantity'] ?? '', $r['rental_duration'] ?? '', $r['ip'] ?? '' ]);
    }
    fclose($out);
    exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="quote-audit.json"');
    echo json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Clear audit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_quote_audit') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) { header('HTTP/1.1 400 Bad Request'); echo 'Invalid CSRF token'; exit; }
  $json = json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json !== false) { file_put_contents($auditFile . '.tmp', $json, LOCK_EX); @rename($auditFile . '.tmp', $auditFile); }
    header('Location: quote-audit.php'); exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Admin - Quote Audit</title>
    <?php require_once __DIR__ . '/partials/head.php'; ?>
  <!-- Page helpers moved to /assets/css/admin.css -->
  </head>
  <body class="admin">
    <div class="page-wrap">
      <div class="admin-card">
        <div class="admin-card-header header-row">
          <div class="header-left">
            <h1 class="admin-card-title m-0">Quote Audit</h1>
            <p class="muted small">Append-only audit entries written to <code>data/quote-audit.json</code>. Newest first.</p>
          </div>
          <div class="top-actions">
            <a href="index.php" class="btn btn-ghost">Back to dashboard</a>
          </div>
        </div>

        <div class="admin-card-body">
          <div class="top-actions">
            <div class="ml-auto">
              <a class="btn btn-ghost" href="quote-audit.php?download=csv">Download CSV</a>
              <a class="btn btn-ghost" href="quote-audit.php?download=json">Download JSON</a>
            </div>
          </div>

          <?php if (empty($entries)): ?>
            <p>No audit entries.</p>
          <?php else: ?>
      <table>
        <thead>
          <tr><th>Time</th><th>Name</th><th>Phone</th><th>Container</th><th>Quantity</th><th>Duration</th><th>IP</th></tr>
        </thead>
        <tbody>
        <?php foreach (array_reverse($entries) as $r): ?>
          <tr>
            <td class="mono"><?php echo htmlspecialchars($r['timestamp'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['customer_name'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['phone'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['container_size'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['quantity'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['rental_duration'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['ip'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" class="mt-1" data-confirm="Clear quote audit? This will remove all entries. Continue?">
      <?php echo csrf_input_field(); ?>
      <input type="hidden" name="action" value="clear_quote_audit">
      <button type="submit" class="btn btn-danger-muted">Clear audit</button>
    </form>
  </body>
</html>
