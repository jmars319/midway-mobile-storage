<?php
require_once __DIR__ . '/config.php';
require_admin();

// Centralized admin uploads helper (provides admin_image_src())
require_once __DIR__ . '/partials/uploads.php';

$base = __DIR__ . '/../uploads/images/';
$types = ['hero', 'logo', 'gallery', 'favicon-set', 'general'];

function list_images_in_dir($dir) {
    $out = [];
    if (!is_dir($dir)) return $out;
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
  $baseReal = realpath(__DIR__ . '/../uploads/images/');
  if ($baseReal === false) $baseReal = rtrim(__DIR__ . '/../uploads/images/', '/');
  foreach ($it as $f) {
    if (!$f->isFile()) continue;
    $fullPath = $f->getPathname();
    $fullReal = realpath($fullPath);
    if ($fullReal !== false && strpos($fullReal, $baseReal) === 0) {
      $rel = str_replace('\\', '/', ltrim(substr($fullReal, strlen($baseReal) + 1), '/'));
    } else {
      // fallback: extract everything after uploads/images/
      $rel = preg_replace('#^.*uploads/images/#i', '', str_replace('\\', '/', $fullPath));
      $rel = ltrim($rel, '/');
    }
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
        <p style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
          <span>Select a folder to browse images and open them in a new tab.</span>
          <span><a href="index.php" class="btn btn-ghost">← Back to Dashboard</a></span>
        </p>
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
                // $img may be a plain filename (for 'general') or a relative path
                $rel = ltrim($img, '/');
                $url = admin_image_src($rel);
            ?>
              <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" style="width:120px;text-align:center;display:inline-block" class="img-thumb-link" data-img="<?php echo htmlspecialchars($url); ?>">
                <img src="<?php echo htmlspecialchars($url); ?>" style="width:100%;height:80px;object-fit:cover;border:1px solid rgba(0,0,0,0.06);border-radius:6px" onerror="this.src='../assets/img/no-preview.svg'">
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

    <!-- Image modal (app-modal style reused) -->
    <div id="img-modal-backdrop" class="modal-backdrop">
      <div id="img-modal" class="app-modal-dialog" role="dialog" aria-modal="true">
        <div style="display:flex;justify-content:flex-end;margin-bottom:.5rem"><button id="img-modal-close" class="btn btn-ghost">Close</button></div>
        <style>
          /* Modal zoomed image state */
          .img-modal-zoomed { cursor: zoom-out; }
          .img-modal-zoomed img { max-width: none !important; max-height: none !important; width: auto; height: auto; }
          .img-modal-controls { display:flex; gap:.5rem; justify-content:center; margin-bottom:.5rem }
        </style>
        <div class="img-modal-controls">
          <button id="img-prev" class="btn btn-ghost">◀ Prev</button>
          <button id="img-download" class="btn btn-ghost">⬇ Download</button>
          <button id="img-zoom" class="btn btn-ghost">🔍 Zoom</button>
          <button id="img-next" class="btn btn-ghost">Next ▶</button>
        </div>
        <div id="img-modal-body" style="text-align:center"><img id="img-modal-img" src="" alt="" style="max-width:100%;max-height:70vh;border-radius:6px;box-shadow:0 8px 30px rgba(0,0,0,0.2)"></div>
      </div>
    </div>

    <script>
      (function(){
          var backdrop = document.getElementById('img-modal-backdrop');
          var imgEl = document.getElementById('img-modal-img');
          var closeBtn = document.getElementById('img-modal-close');
          var prevBtn = document.getElementById('img-prev');
          var nextBtn = document.getElementById('img-next');
          var downloadBtn = document.getElementById('img-download');
          var zoomBtn = document.getElementById('img-zoom');
          var gallery = Array.from(document.querySelectorAll('.img-thumb-link')).map(function(a){ return a.getAttribute('data-img'); });
          var current = -1;
          function showAt(idx){ if (idx < 0 || idx >= gallery.length) return; current = idx; imgEl.src = gallery[idx]; backdrop.style.display='flex'; updateControls(); }
          function openImg(url){ var i = gallery.indexOf(url); if (i === -1) { gallery.push(url); i = gallery.length - 1; } showAt(i); }
          function closeImg(){ backdrop.style.display='none'; imgEl.src=''; current = -1; updateControls(); }
          function updateControls(){ prevBtn.disabled = current <= 0; nextBtn.disabled = current < 0 || current >= gallery.length - 1; downloadBtn.disabled = current < 0; }
          function prev(){ if (current > 0) showAt(current - 1); }
          function next(){ if (current < gallery.length - 1) showAt(current + 1); }
          function download(){ if (current < 0) return; var a = document.createElement('a'); a.href = gallery[current]; a.download = gallery[current].split('/').pop(); a.style.display='none'; document.body.appendChild(a); a.click(); a.remove(); }
          function toggleZoom(){ if (!imgEl) return; var c = imgEl.classList.toggle('img-modal-zoomed'); if (c) zoomBtn.textContent = '🔎 Reset'; else zoomBtn.textContent = '🔍 Zoom'; }

          document.querySelectorAll('.img-thumb-link').forEach(function(a, idx){ a.addEventListener('click', function(e){ e.preventDefault(); var u = this.getAttribute('data-img'); openImg(u); }); });
          closeBtn && closeBtn.addEventListener('click', closeImg);
          prevBtn && prevBtn.addEventListener('click', prev);
          nextBtn && nextBtn.addEventListener('click', next);
          downloadBtn && downloadBtn.addEventListener('click', download);
          zoomBtn && zoomBtn.addEventListener('click', toggleZoom);
          backdrop && backdrop.addEventListener('click', function(e){ if (e.target === backdrop) closeImg(); });
          document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeImg(); else if (e.key === 'ArrowLeft') prev(); else if (e.key === 'ArrowRight') next(); else if (e.key === '+' || e.key === '=' ) toggleZoom(); else if (e.key === '-') toggleZoom(); });
          updateControls();
        })();
    </script>
