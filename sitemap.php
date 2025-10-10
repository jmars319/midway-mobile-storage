<?php
// sitemap.php - dynamically emit sitemap XML from data/content.json
header('Content-Type: application/xml; charset=utf-8');
$contentFile = __DIR__ . '/data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];
$host = 'https://midwaymobilestorage.com';
$lastmodRaw = !empty($content['last_updated']) ? $content['last_updated'] : null;
$lastmod = $lastmodRaw ? date('c', strtotime($lastmodRaw)) : date('c');
$lastmodRfc = $lastmodRaw ? gmdate('D, d M Y H:i:s', strtotime($lastmodRaw)) . ' GMT' : gmdate('D, d M Y H:i:s') . ' GMT';

$urls = [];
// Root
$urls[] = ['loc' => $host . '/', 'lastmod' => $lastmod, 'changefreq' => 'weekly', 'priority' => '1.0'];
// Static pages
$static = ['/privacy-policy.html', '/terms-of-service.html', '/order.php'];
foreach ($static as $p) {
    $urls[] = ['loc' => $host . $p, 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.5'];
}

// Optionally include menu sections as separate anchor entries
if (!empty($content['menu']) && is_array($content['menu'])) {
    foreach ($content['menu'] as $section) {
        if (!empty($section['id'])) {
            $urls[] = ['loc' => $host . '/#' . $section['id'], 'lastmod' => $lastmod, 'changefreq' => 'monthly', 'priority' => '0.6'];
        }
    }
}

// Build XML into a string so we can compute ETag and support conditional GET
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    $xml .= "  <url>\n";
    $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>\n';
    if (!empty($u['lastmod'])) $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1, 'UTF-8') . '</lastmod>\n';
    if (!empty($u['changefreq'])) $xml .= '    <changefreq>' . $u['changefreq'] . '</changefreq>\n';
    if (!empty($u['priority'])) $xml .= '    <priority>' . $u['priority'] . '</priority>\n';
    $xml .= "  </url>\n";
}
$xml .= '</urlset>' . "\n";

// Caching: ETag and Last-Modified
$etag = '"' . sha1($xml) . '"';
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastmodRfc);
header('Cache-Control: public, max-age=86400'); // cache for 1 day

// Handle conditional requests
$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
if ($ifNoneMatch && $ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}
if ($ifModifiedSince && $ifModifiedSince === $lastmodRfc) {
    http_response_code(304);
    exit;
}

echo $xml;
