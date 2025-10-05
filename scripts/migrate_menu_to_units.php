<?php
// scripts/migrate_menu_to_units.php
// Small CLI helper: back up data/content.json and convert any existing
// `menu` entries into a `units` array (best-effort mapping). Safe to run
// multiple times. If there is no `menu` key nothing is changed.

$root = dirname(__DIR__);
$contentFile = $root . '/data/content.json';
if (!file_exists($contentFile)) {
    fwrite(STDOUT, "content.json not found: {$contentFile}\n");
    exit(0);
}

$raw = file_get_contents($contentFile);
$content = $raw ? json_decode($raw, true) : null;
if (!is_array($content)) $content = [];

// Backup
$bak = $contentFile . '.bak.' . date('YmdHis');
if (file_put_contents($bak, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "Failed to write backup to {$bak}\n");
    exit(1);
}
fwrite(STDOUT, "Backup written to: {$bak}\n");

if (!isset($content['menu'])) {
    fwrite(STDOUT, "No 'menu' key found; nothing to convert.\n");
    exit(0);
}

$menu = $content['menu'];
$units = [];

// Helper to create a unit record from a menu item and optional section
function item_to_unit($item, $section = null) {
    $u = [];
    $u['title'] = $item['title'] ?? ($item['name'] ?? ($section['title'] ?? 'Unit'));
    // size: prefer explicit size field, then try quantity label, then section title
    if (!empty($item['size'])) $u['size'] = $item['size'];
    elseif (!empty($item['quantities']) && is_array($item['quantities'])) {
        $firstQ = $item['quantities'][0];
        $u['size'] = $firstQ['label'] ?? (string)($firstQ['value'] ?? '');
    } else {
        $u['size'] = $section['title'] ?? '';
    }
    // description: prefer description, then short, then section details
    $u['description'] = $item['description'] ?? ($item['short'] ?? ($section['details'] ?? ''));
    // price: prefer item-level price, then first quantity price
    if (isset($item['price']) && $item['price'] !== '') $u['price'] = $item['price'];
    elseif (!empty($item['quantities']) && is_array($item['quantities'])) {
        $firstQ = $item['quantities'][0];
        if (isset($firstQ['price']) && $firstQ['price'] !== '') $u['price'] = $firstQ['price'];
    }
    return $u;
}

// If menu is an array of sections
if (is_array($menu) && count($menu) && isset($menu[0]) && is_array($menu[0]) && array_key_exists('items', $menu[0])) {
    foreach ($menu as $section) {
        $items = is_array($section['items']) ? $section['items'] : [];
        foreach ($items as $it) {
            $units[] = item_to_unit($it, $section);
        }
    }
} elseif (is_array($menu)) {
    // flat array of items
    foreach ($menu as $it) {
        $units[] = item_to_unit($it, null);
    }
} else {
    fwrite(STDOUT, "menu exists but has unexpected format; no conversion performed.\n");
    exit(0);
}

// Place units into content and remove menu
$content['units'] = $units;
unset($content['menu']);

$json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "Failed to encode new content.json\n");
    exit(1);
}

if (file_put_contents($contentFile . '.tmp', $json, LOCK_EX) !== false && @rename($contentFile . '.tmp', $contentFile)) {
    @chmod($contentFile, 0640);
    fwrite(STDOUT, "Migration complete. Converted " . count($units) . " unit(s).\n");
    exit(0);
} else {
    @unlink($contentFile . '.tmp');
    fwrite(STDERR, "Failed to write content.json. Check permissions.\n");
    exit(1);
}

