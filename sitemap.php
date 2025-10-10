<?php
// sitemap.php - dynamically emit sitemap XML from data/content.json
header('Content-Type: application/xml; charset=utf-8');
$contentFile = __DIR__ . '/data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
$host = 'https://midwaymobilestorage.com';
$lastmod = !empty($content['last_updated']) ? date('c', strtotime($content['last_updated'])) : date('c');

$urls = [];
// Root
$urls[] = ['loc' => $host . '/', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '1.0'];
// Static pages
$static = ['/privacy-policy.html', '/terms-of-service.html', '/order.php'];
foreach ($static as $p) {
    $urls[] = ['loc' => $host . $p, 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.5'];
}

// Optionally include menu sections as separate anchor-less entries if menu.id exists
if (!empty($content['menu']) && is_array($content['menu'])) {
    foreach ($content['menu'] as $section) {
        if (!empty($section['id'])) {
            // link to root with fragment-less section id for crawlability (site is single-page)
            $urls[] = ['loc' => $host . '/#' . $section['id'], 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.6'];
        }
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>\n';
    if (!empty($u['lastmod'])) echo '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>\n';
    if (!empty($u['changefreq'])) echo '    <changefreq>' . $u['changefreq'] . '</changefreq>\n';
    if (!empty($u['priority'])) echo '    <priority>' . $u['priority'] . '</priority>\n';
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
