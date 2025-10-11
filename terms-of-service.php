<?php
// terms-of-service.php - dynamic legal page that reads business info from data/content.json
$contentFile = __DIR__ . '/data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
$biz = $content['business_info'] ?? [];
$cssPath = 'assets/css/styles.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Terms of Service — <?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?></title>
  <meta name="description" content="Terms of service for <?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?>.">
  <link rel="canonical" href="https://midwaymobilestorage.com/terms-of-service.php">
  <link rel="stylesheet" href="/<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
  <main class="container">
    <h1>Terms of Service</h1>
    <p>These terms govern the use of our website and services. By using our site you agree to these terms.</p>
    <p>For legal inquiries, please <a href="/contact.php">contact us</a>.</p>
  </main>
  <footer class="footer">
    <div class="container">
      <div class="footer-row">
        <div class="small footer-left">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?>. All rights reserved.</div>
        <div class="small footer-center center">
          <div class="biz-name"><?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?></div>
          <div class="small biz-contact-row">
            <div class="biz-contact"><a href="tel:<?php echo preg_replace('/[^0-9+]/','', $biz['phone'] ?? ''); ?>"><?php echo htmlspecialchars($biz['phone'] ?? ''); ?></a></div>
            <span class="sep" aria-hidden="true">|</span>
            <div class="mt-025"><a href="#" id="footer-hours-link">Hours</a></div>
          </div>
        </div>
        <div class="small footer-right right">
            <nav class="footer-links" aria-label="Footer navigation">
            <a href="#units">Units</a>
            <a href="#storage-quote">Quotes</a>
            <a href="#about">About</a>
            <a href="#job-application">Careers</a>
            <a href="#contact" id="footer-contact-link">Contact</a>
          </nav>
        </div>
      </div>
      <div class="footer-row footer-legal">
        <div></div>
        <div></div>
        <div class="small footer-right right">
          <nav class="footer-legal-links" aria-label="Legal">
            <a href="/privacy-policy.php" class="open-legal" data-src="/privacy-policy.php">Privacy Policy</a>
            <a href="/terms-of-service.php" class="open-legal" data-src="/terms-of-service.php">Terms of Service</a>
          </nav>
        </div>
      </div>
    </div>
  </footer>
</body>
</html>
