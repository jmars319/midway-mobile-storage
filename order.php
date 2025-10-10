<?php
/**
 * order.php
 * Simple, read-only placeholder for "Order Online" page.
 * - Loads `data/content.json` for display-only content.
 * - Uses file modification time for cache-busting the main stylesheet.
 *
 * This script is intentionally minimal — it does not accept form input
 * and should be safe to serve as a static-like page.
 */

$contentFile = 'data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
function getContent($content, $key, $fallback = '') {
  $keys = explode('.', $key);
  $value = $content;
  foreach ($keys as $k) {
    if (isset($value[$k])) {
      $value = $value[$k];
    } else {
      return $fallback;
    }
  }
  return $value;
}

// Cache-busting for styles
$cssPath = 'assets/css/styles.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Order Online — Coming Soon | Midway Mobile Storage</title>
  <meta name="description" content="Online ordering coming soon for Midway Mobile Storage — flexible container rentals and mobile storage near Winston-Salem.">
  <link rel="canonical" href="<?php $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $host = 'midwaymobilestorage.com'; echo htmlspecialchars($proto . '://' . $host . '/order.php'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <header class="header">
    <div class="container">
      <nav class="navbar">
        <?php
          $logoFile = $content['images']['logo'] ?? '';
          $logoUrl = '';
          if ($logoFile) {
            $logoUrl = preg_match('#^https?://#i', $logoFile) ? $logoFile : '/uploads/images/'.ltrim($logoFile, '/');
          }
        ?>
        <a href="/" class="logo">
          <?php if ($logoUrl): ?>
            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>" class="site-logo-img">
          <?php else: ?>
            Midway Mobile Storage
          <?php endif; ?>
        </a>
      </nav>
    </div>
  </header>

  <main class="container p-3rem-1rem">
    <section class="card center-card">
      <h1>Order Online — Coming Soon</h1>
  <p class="muted mt-1">We're working to add online ordering. Sign up for updates or check back soon.</p>
    <div class="mt-15 flex-center">
        <a href="/" class="btn btn-ghost">Back to Home</a>
        <a href="/contact.php" class="btn btn-primary">Contact Us</a>
      </div>
    </section>
  </main>

  <!-- IMPORTANT: keep this <footer> element outside any .container or .container-narrow wrappers
    so the footer background can span the full viewport width (full-bleed). The inner
    <div class="container"> should be used to center footer content only. Do not nest
    page sections inside this footer. -->
  <footer class="footer">
    <div class="container">
      <div class="footer-row">
        <div class="small footer-left">&copy; <?php echo date('Y'); ?> Midway Mobile Storage. All rights reserved.</div>
        <div class="small footer-center center">
          <div class="biz-name">Midway Mobile Storage</div>
          <div class="small biz-contact-row">
            <div class="biz-contact"><a href="tel:<?php echo preg_replace('/[^0-9+]/','', $content['business_info']['phone'] ?? ''); ?>"><?php echo htmlspecialchars($content['business_info']['phone'] ?? ''); ?></a></div>
            <div class="mt-025"><a href="#" id="footer-hours-link">Hours</a></div>
          </div>
        </div>
        <div class="small footer-right right">
          <nav class="footer-links" aria-label="Footer navigation">
            <a href="#menu">Menu</a>
            <a href="#storage-quote">Quotes</a>
            <a href="#about">About</a>
            <a href="#job-application">Careers</a>
            <a href="#contact">Contact</a>
          </nav>
        </div>
      </div>
      <div class="footer-row footer-legal">
        <div></div>
        <div></div>
        <div class="small footer-right right">
          <nav class="footer-legal-links" aria-label="Legal">
            <a href="/privacy-policy.html">Privacy Policy</a>
            <a href="/terms-of-service.html">Terms of Service</a>
          </nav>
        </div>
      </div>
    </div>
  </footer>
</body>
</html>
