<?php
require_once __DIR__ . '/config.php';
require_admin();

$base = __DIR__ . '/../uploads/images/';
$types = ['hero','logo','gallery','favicon-set','general'];

function list_images_in_dir($dir) {
    $out = [];
    if (!is_dir($dir)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        $rel = str_replace('\\','/', substr($f->getPathname(), strlen(realpath(__DIR__ . '/../uploads/images/')) + 1));
        $out[] = $rel;
    }
    sort($out);
    return $out;
}

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <title>Image preview</title>
</head>
<body class="admin">
  <div class="page-wrap">
    <div class="admin-card">
      <div class="admin-card-header"><h2 class="admin-card-title">Image Preview</h2><div></div></div>
      <div style="padding:1rem">
        <p>Select a folder to browse images and open them in a new tab.</p>
        <?php foreach ($types as $t): ?>
          <h3><?php echo htmlspecialchars($t); ?></h3>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php
              $dir = $base . ($t !== 'general' ? $t . '/' : '');
              $imgs = [];
              if ($t === 'general') {
                // list loose files in base (non-recursive)
                $all = scandir($base);
                foreach ($all as $a) { if ($a === '.' || $a === '..') continue; if (is_file($base.$a)) $imgs[] = $a; }
              } else {
                $imgs = list_images_in_dir($dir);
              }
              if (empty($imgs)) echo '<em>No images</em>';
              foreach ($imgs as $img):
                $url = '/uploads/images/' . ltrim($img, '/');
            ?>
              <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="width:120px;text-align:center;display:inline-block">
                <img src="<?php echo htmlspecialchars($url); ?>" style="width:100%;height:80px;object-fit:cover;border:1px solid rgba(0,0,0,0.06);border-radius:6px" onerror="this.src='/assets/img/no-preview.svg'">
                <div style="font-size:0.7rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars(basename($img)); ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</body>
</html>
