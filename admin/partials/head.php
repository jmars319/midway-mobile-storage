<?php
// Shared admin head partial: fonts, favicons, manifest and shared admin stylesheet
//  - This file centralizes head items for all admin pages. Do not add another
//    <link rel="stylesheet" href="/assets/css/admin.css"> elsewhere; update this partial instead.
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700&family=Roboto+Condensed:wght@400;700&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
<meta charset="utf-8">
<!-- Favicons and manifest (admin shares same canonical manifest) -->
<?php require_once __DIR__ . '/uploads.php'; ?>
<link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(admin_image_src('favicon-set/favicon.ico')); ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars(admin_image_src('favicon-set/favicon-32x32.png')); ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars(admin_image_src('favicon-set/favicon-16x16.png')); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars(admin_image_src('favicon-set/favicon-180x180.png')); ?>">
<link rel="manifest" href="../site.webmanifest">
<!-- Admin stylesheet (centralized) with cache-busting based on file modification time -->
<?php
// Include the public site stylesheet first so admin styles can override it.
// Cache-bust using the file modification time when available.
$publicCssPath = realpath(__DIR__ . '/../../assets/css/styles.css');
if ($publicCssPath) {
	$pubver = filemtime($publicCssPath);
		echo '<link rel="stylesheet" href="/assets/css/styles.css?v=' . $pubver . '">' . PHP_EOL;
}

// Prefer a pre-built minified admin bundle when present for production. Fall
// back to the canonical admin.css wrapper which imports the modular files.
$adminMinPath = realpath(__DIR__ . '/../../assets/css/admin.min.css');
// Only prefer the minified bundle when it exists, filemtime() succeeds,
// and it is not a disabled placeholder. This prevents emitting an
// admin.min.css link that contains no rules (which breaks the admin login).
$useMin = false;
if ($adminMinPath && is_file($adminMinPath)) {
	$marker = @file_get_contents($adminMinPath, false, null, 0, 512);
	if ($marker !== false && strpos($marker, 'admin.min.css disabled') !== false) {
		// intentionally disabled placeholder; fall back to canonical admin.css
		$useMin = false;
	} else {
		$ver = @filemtime($adminMinPath);
		if ($ver !== false) $useMin = true;
	}
}

if ($useMin) {
	$ver = @filemtime($adminMinPath) ?: time();
	$href = '/assets/css/admin.min.css?v=' . $ver;
} else {
	$adminCssPath = realpath(__DIR__ . '/../../assets/css/admin.css');
	$ver = $adminCssPath ? @filemtime($adminCssPath) : time();
	$href = '/assets/css/admin.css?v=' . $ver;
}
?>
<link rel="stylesheet" href="<?php echo $href; ?>">
<script>
	// Expose admin uploads base for client scripts to construct image URLs
	window.ADMIN_UPLOADS_BASE = <?php echo json_encode(admin_uploads_base()); ?>;
</script>

<!-- diagnostics removed -->
