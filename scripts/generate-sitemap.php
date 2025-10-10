<?php
// scripts/generate-sitemap.php
// Generates a static sitemap.xml from data/content.json
$repoRoot = dirname(__DIR__);
$contentFile = $repoRoot . '/data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
$host = 'https://midwaymobilestorage.com';
$lastmod = !empty($content['last_updated']) ? date('c', strtotime($content['last_updated'])) : date('c');
$urls = [];
$urls[] = ['loc' => $host . '/', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '1.0'];
$static = ['/privacy-policy.html', '/terms-of-service.html', '/order.php'];
foreach ($static as $p) $urls[] = ['loc' => $host . $p, 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.5'];
if (!empty($content['menu']) && is_array($content['menu'])) {
    foreach ($content['menu'] as $section) {
        if (!empty($section['id'])) {
            $urls[] = ['loc' => $host . '/#' . $section['id'], 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.6'];
        }
    }
}
$xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>\n';
    if (!empty($u['lastmod'])) $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>\n';
    if (!empty($u['changefreq'])) $xml .= '    <changefreq>' . $u['changefreq'] . '</changefreq>\n';
    if (!empty($u['priority'])) $xml .= '    <priority>' . $u['priority'] . '</priority>\n';
    $xml .= "  </url>\n";
}
$xml .= '</urlset>\n';
file_put_contents($repoRoot . '/sitemap.xml', $xml);
echo "sitemap.xml generated (" . basename($repoRoot) . ")\n";
