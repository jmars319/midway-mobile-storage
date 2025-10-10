<?php
/**
 * index.php
 * Public-facing site template. Renders content from the JSON-backed
 * content store (`data/content.json`). This file is intentionally
 * lightweight and uses small helper functions to keep templates
 * easy to read.
 *
 * Contract (high level):
 *  - Inputs: reads `data/content.json` (no user-supplied inputs).
 *  - Outputs: HTML page (status 200) rendering site sections.
 *  - Side effects: none (read-only). Any write operations happen in
 *    admin endpoints which update the JSON file.
 *
 * Important notes for developers:
 *  - The template expects `data/content.json` to be valid JSON. If
 *    absent, an empty content array is used.
 *  - For security, user-provided values are escaped with
 *    `htmlspecialchars()` before rendering.
 *  - This file should avoid performing write operations. Admin
 *    pages handle persistence and validation.
 */

// Load the site content (stored as JSON in data/content.json).
// This file is the single source of truth for editable content (hero text, menu, images, etc.).
$contentFile = 'data/content.json';
$content = file_exists($contentFile) ? json_decode(file_get_contents($contentFile), true) : [];

/**
 * Helper: getContent
 * Fetches a nested key from the $content array using dot notation.
 * Returns $fallback when the key path is missing. This centralizes access
 * so templates don't need to repeatedly check isset() everywhere.
 */
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
?>
<?php
// Start session before any output
session_start();
$form_flash = $_SESSION['form_flash'] ?? null;
// Emit document head (doctype, meta, CSS)
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Montserrat:wght@600;700&family=Roboto+Condensed:wght@400;700&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
</head>
<body>
<?php
// Show form flash if present (non-AJAX submission validation errors)
if (!empty($_SESSION['form_flash'])) {
    $form_flash = $_SESSION['form_flash'];
    // clear it so it doesn't persist
    unset($_SESSION['form_flash']);
} else {
    $form_flash = null;
}
if ($form_flash) {
    // clear it so it doesn't persist
    unset($_SESSION['form_flash']);
}
function fv($k, $fallback='') { global $form_flash; if (!$form_flash) return $fallback; return htmlspecialchars($form_flash['values'][$k] ?? $fallback); }
function ferrs() { global $form_flash; if (!$form_flash || empty($form_flash['errors'])) return ''; $out = '<div class="card alert-error"><strong>There were errors with your submission:</strong><ul>'; foreach ($form_flash['errors'] as $e) { $out .= '<li>'.htmlspecialchars($e).'</li>'; } $out .= '</ul></div>'; return $out; }
?>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <?php
                $logoFile = $content['images']['logo'] ?? '';
                $logoUrl = '';
                if ($logoFile) { $logoUrl = preg_match('#^https?://#i', $logoFile) ? $logoFile : '/uploads/images/'.ltrim($logoFile, '/'); }
            ?>
            <a href="/" class="logo">
                <?php if ($logoUrl): ?>
                    <?php
                        // Compute responsive logo paths based on the stored image path (may be "logo/logo.png" or a URL)
                        $logoVal = $content['images']['logo'] ?? '';
                        if (preg_match('#^https?://#i', $logoVal)) {
                            $logo48 = $logo96 = $logo192 = $logoVal;
                            $logo48_webp = $logo96_webp = $logo192_webp = $logoVal;
                        } else {
                            $logo48 = '/uploads/images/' . ($logoVal ? preg_replace('/\.png$/i', '-48.png', $logoVal) : 'logo-48.png');
                            $logo96 = '/uploads/images/' . ($logoVal ? preg_replace('/\.png$/i', '-96.png', $logoVal) : 'logo-96.png');
                            $logo192 = '/uploads/images/' . ($logoVal ? preg_replace('/\.png$/i', '-192.png', $logoVal) : 'logo-192.png');
                            $logo48_webp = preg_replace('/\.png$/i', '.webp', $logo48);
                            $logo96_webp = preg_replace('/\.png$/i', '.webp', $logo96);
                            $logo192_webp = preg_replace('/\.png$/i', '.webp', $logo192);
                        }
                    ?>
                    <picture>
                        <source type="image/webp" srcset="<?php echo htmlspecialchars($logo48_webp); ?> 1x, <?php echo htmlspecialchars($logo96_webp); ?> 2x, <?php echo htmlspecialchars($logo192_webp); ?> 4x">
                        <img src="<?php echo htmlspecialchars($logo48); ?>" srcset="<?php echo htmlspecialchars($logo48); ?> 1x, <?php echo htmlspecialchars($logo96); ?> 2x, <?php echo htmlspecialchars($logo192); ?> 4x" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>" class="site-logo-img">
                    </picture>
                <?php else: ?>
                    <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>
                <?php endif; ?>
            </a>

            <ul class="nav-menu">
                <li><a class="nav-link" href="#units">Units</a></li>
                <li><a class="nav-link" href="#storage-quote">Get a Quote</a></li>
                <?php
                    // Prepare SEO meta values from content.json (safe defaults provided)
                    $biz = $content['business_info'] ?? [];
                    $bizName = $biz['name'] ?? 'Midway Mobile Storage';
                    $bizAddress = $biz['address'] ?? '';
                    $bizPhone = $biz['phone'] ?? '';

                    // Site URL / canonical
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                // Prefer the canonical .com domain for public-facing metadata
                $host = 'midwaymobilestorage.com';
                    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
                    // canonical: remove query string
                    $uriParts = explode('?', $requestUri, 2);
                    $canonical = rtrim($proto . '://' . $host . $uriParts[0], '/');

                    // Meta description: prefer hero.subtitle or about.body or a composed fallback
                    $metaDescRaw = '';
                    if (!empty($content['hero']['subtitle'])) $metaDescRaw = $content['hero']['subtitle'];
                    elseif (!empty($content['about']['body'])) $metaDescRaw = strip_tags($content['about']['body']);
                    else $metaDescRaw = "$bizName offers mobile storage containers, trailers, and flexible unit rentals near Winston-Salem.";
                    $metaDescription = mb_substr(trim(preg_replace('/\s+/', ' ', $metaDescRaw)), 0, 155);

                    // Choose an image for social previews: hero, gallery, or logo
                    $ogImage = '';
                    if (!empty($content['hero']['image'])) $ogImage = preg_match('#^https?://#i', $content['hero']['image']) ? $content['hero']['image'] : ('/' . ltrim($content['hero']['image'], '/'));
                    elseif (!empty($content['images']['gallery'])) $ogImage = '/' . ltrim($content['images']['gallery'], '/');
                    elseif (!empty($content['images']['logo'])) $ogImage = '/' . ltrim($content['images']['logo'], '/');
                    // ensure absolute URL for Open Graph
                    if ($ogImage && !preg_match('#^https?://#i', $ogImage)) $ogImage = $proto . '://' . $host . $ogImage;

                    // Build a small keywords list from business name, common terms, and city if available
                    $keywords = [$bizName, 'mobile storage', 'storage containers', 'storage units', 'container rental', 'portable storage'];
                    if ($bizAddress) {
                        $parts = explode(',', $bizAddress);
                        if (count($parts) >= 2) $keywords[] = trim($parts[1]);
                    }
                    $metaKeywords = htmlspecialchars(implode(', ', array_unique($keywords)));
                ?>

                <!-- SEO / Social meta -->
                <title><?php echo htmlspecialchars($bizName); ?></title>
                <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
                <meta name="keywords" content="<?php echo $metaKeywords; ?>">
                <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
                <link rel="sitemap" type="application/xml" title="Sitemap" href="<?php echo $proto . '://' . $host; ?>/sitemap.xml">

                <!-- Open Graph -->
                <meta property="og:site_name" content="<?php echo htmlspecialchars($bizName); ?>">
                <meta property="og:title" content="<?php echo htmlspecialchars($bizName); ?>">
                <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
                <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
                <meta property="og:type" content="website">
                <?php if ($ogImage): ?><meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>"><?php endif; ?>

                <!-- Twitter card -->
                <meta name="twitter:card" content="summary_large_image">
                <meta name="twitter:title" content="<?php echo htmlspecialchars($bizName); ?>">
                <meta name="twitter:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
                <?php if ($ogImage): ?><meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>"><?php endif; ?>

                <!-- JSON-LD LocalBusiness / Organization -->
                <?php
                    // Simple address parsing (street, city/state, postal)
                    $street = $city = $region = $postal = '';
                    if ($bizAddress) {
                        $parts = array_map('trim', explode(',', $bizAddress));
                        $street = $parts[0] ?? '';
                        if (isset($parts[1])) {
                            // try to split city and state/zip
                            $cityState = $parts[1];
                            if (preg_match('/^([^\d]+?)\s+([A-Za-z]{2})\s*(\d{5})?$/', $cityState, $m)) {
                                $city = trim($m[1]); $region = trim($m[2]); $postal = $m[3] ?? '';
                            } else {
                                // fallback: put whole part in city
                                $city = $parts[1];
                                if (isset($parts[2])) $postal = trim($parts[2]);
                            }
                        }
                    }
                    $ld = [
                        "@context" => "https://schema.org",
                        "@type" => "LocalBusiness",
                        "name" => $bizName,
                        "url" => $proto . '://' . $host . '/',
                    ];
                    if ($bizPhone) $ld['telephone'] = $bizPhone;
                    if ($bizAddress) {
                        $ld['address'] = [
                            "@type" => "PostalAddress",
                            "streetAddress" => $street,
                            "addressLocality" => $city,
                            "addressRegion" => $region,
                            "postalCode" => $postal,
                        ];
                    }
                    if ($ogImage) $ld['image'] = $ogImage;
                    if (!empty($content['hours'])) {
                        // Convert hours map to openingHoursSpecification where possible
                        $oh = [];
                        foreach ($content['hours'] as $k => $v) {
                            $day = ucfirst(strtolower($k));
                            if (stripos($v, 'closed') !== false) continue;
                            // simple parse like '10:00 AM - 3:00 PM'
                            if (preg_match('/(\d{1,2}:\d{2}\s*(AM|PM))\s*-\s*(\d{1,2}:\d{2}\s*(AM|PM))/i', $v, $m)) {
                                $oh[] = [
                                    '@type' => 'OpeningHoursSpecification',
                                    'dayOfWeek' => ["https://schema.org/{$day}"],
                                    'opens' => date('H:i', strtotime($m[1])),
                                    'closes' => date('H:i', strtotime($m[3])),
                                ];
                            }
                        }
                        if ($oh) $ld['openingHoursSpecification'] = $oh;
                    }
                ?>
                <script type="application/ld+json">
                <?php echo json_encode($ld, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); ?>
                </script>
                <li><a class="nav-link" href="#about">About</a></li>
                <li><a class="nav-link" href="#job-application">Careers</a></li>
                <li><a class="nav-link open-contact" href="#contact">Contact</a></li>
            </ul>

                </div>
            </div>
        </div>
    </section>

    <!-- Close job-application containers so footer is full-bleed -->
    </div>
    </section>

        <?php
            // Hero content variables (provide safe defaults)
            $heroTitle = getContent($content, 'hero.title', $content['business_info']['name'] ?? 'Midway Mobile Storage');
            $heroSubtitle = getContent($content, 'hero.subtitle', 'Flexible unit sizes, affordable pricing, and reliable service.');
            $heroImage = getContent($content, 'hero.image', '');
            $heroBtn1 = getContent($content, 'hero.btn_text', 'View Units');
            $heroBtn1Link = getContent($content, 'hero.btn_link', '#units');
            $heroBtn2 = getContent($content, 'hero.btn2_text', 'Get a Quote');
            $heroBtn2Link = getContent($content, 'hero.btn2_link', '#storage-quote');
            $heroBackgroundStyleTag = '';
            $heroBgInline = '';
            if ($heroImage) {
                // prefer full URL when provided
                $imgUrl = preg_match('#^https?://#i', $heroImage) ? $heroImage : '/uploads/images/'.ltrim($heroImage, '/');
                // Use inline style on the .hero-bg element so the background is applied reliably
                $heroBgInline = 'style="background-image: url(\'' . htmlspecialchars((string)$imgUrl, ENT_QUOTES, 'UTF-8') . '\'); background-size: cover; background-position: center 42%;"';
            }
        ?>
        <?php echo $heroBackgroundStyleTag; ?>
        <section class="hero" aria-label="Hero">
            <?php if ($heroImage): ?>
                <!-- Background container for hero image; animation uses transform to avoid background-size blurring -->
                <div class="hero-bg" aria-hidden="true" <?php echo $heroBgInline; ?>></div>
            <?php endif; ?>
            <div class="container">
                <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>
                <?php if ($heroSubtitle): ?><p class="hero-subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p><?php endif; ?>
                <div class="hero-cta">
                    <a class="btn btn-primary" href="<?php echo htmlspecialchars($heroBtn1Link); ?>"><?php echo htmlspecialchars($heroBtn1); ?></a>
                        <a class="btn btn-outline" href="<?php echo htmlspecialchars($heroBtn2Link); ?>"><?php echo htmlspecialchars($heroBtn2); ?></a>
                </div>
            </div>
        </section>

        <!-- ==============================================
             UNITS SECTION (storage units)
             ============================================== -->
    

    <!-- ==============================================
         UNITS SECTION (storage units)
         ============================================== -->
    <section class="section" id="units">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Units</h2>
                <p class="section-subtitle">See available unit sizes and pricing</p>
            </div>

            <div class="menu-grid">
                <?php
                    // Prefer the 'menu' structure (sections with items and section images) when available
                    $menuSections = $content['menu'] ?? null;
                    if (is_array($menuSections) && count($menuSections)) {
                        foreach ($menuSections as $section) {
                            $secTitle = htmlspecialchars($section['title'] ?? ($section['id'] ?? 'Section'));
                            $secDetails = is_array($section['details']) ? $section['details'] : [];
                            $secImage = $section['image'] ?? '';
                            $items = is_array($section['items']) ? $section['items'] : [];
                            // If a section image exists, expose it as a background immediately to avoid flicker
                            $hasBg = !empty($secImage);
                            $imgUrl = '';
                            if ($hasBg) {
                                $imgUrl = preg_match('#^https?://#i', $secImage) ? $secImage : '/uploads/images/'.ltrim($secImage, '/');
                            }
                            $cardClasses = 'card unit-card menu-card' . ($hasBg ? ' has-bg' : '');
                            $cardStyle = $hasBg ? ' style="--menu-bg: url(\'' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '\')"' : '';
                            echo '<div class="' . $cardClasses . '"' . $cardStyle . '>';
                            // Section image (kept as an inline <img> for accessibility/fallback; CSS hides it when has-bg)
                            if ($hasBg) {
                                echo '<img class="menu-img" src="'.htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($secTitle).'">';
                            }
                            echo '<div class="menu-body card-body">';
                            echo '<div class="card-header"><h3 class="card-title">'.$secTitle.'</h3></div>';
                            // Section details (small text)
                            if (!empty($secDetails)) {
                                echo '<div class="menu-short">' . htmlspecialchars(implode(' · ', $secDetails)) . '</div>';
                            }
                            // Expand / collapse control and items
                            echo '<div class="menu-actions">';
                            echo '<button class="expand-btn" aria-expanded="false"><span class="expand-label">See all units</span></button>';
                            echo '</div>';
                            // Items (hidden until expanded)
                            echo '<div class="menu-details">';
                            if (!empty($items)) {
                                foreach ($items as $it) {
                                    $itTitle = htmlspecialchars($it['title'] ?? '');
                                    $itShort = htmlspecialchars($it['short'] ?? '');
                                    $itDesc = htmlspecialchars($it['description'] ?? '');
                                    // support multiple price entries per item
                                    $prices = is_array($it['prices'] ?? null) ? $it['prices'] : null;
                                    $legacyPrice = isset($it['price']) && $it['price'] !== '' ? $it['price'] : null;
                                    echo '<div class="section-item">';
                                    echo '<div class="menu-item-head"><strong class="menu-title">'.$itTitle.'</strong>';
                                    if ($prices && count($prices)) {
                                        echo '<div class="menu-item-prices">';
                                        foreach ($prices as $p) {
                                            $label = htmlspecialchars($p['label'] ?? '');
                                            $amt = ($p['amount'] !== null && $p['amount'] !== '') ? '$'.htmlspecialchars($p['amount']) : '';
                                            $note = htmlspecialchars($p['note'] ?? '');
                                            echo '<div class="menu-price-entry">';
                                            if ($label) echo '<span class="menu-price-label">'. $label .'</span> ';
                                            if ($amt) echo '<span class="menu-price">'. $amt .'</span>';
                                            if ($note) echo '<div class="menu-price-note">'. $note .'</div>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                    } else if ($legacyPrice !== null) {
                                        echo '<span class="menu-price">$'.htmlspecialchars($legacyPrice).'</span>';
                                    }
                                    echo '</div>';
                                    if ($itShort) echo '<div class="menu-short">'.$itShort.'</div>';
                                    if ($itDesc) echo '<div class="menu-details-desc">'.nl2br($itDesc).'</div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="small muted">No items available</div>';
                            }
                            echo '</div>'; // .menu-details
                            echo '</div>'; // .menu-body
                            echo '</div>'; // .menu-card
                        }
                    } else {
                        // fallback: render legacy flat units array
                        $units = $content['units'] ?? [];
                        if (!is_array($units) || !count($units)) {
                            $units = [
                                ['title'=>'Small Unit','size'=>'5x5','description'=>'Ideal for boxes and small furniture.','price'=>'25'],
                                ['title'=>'Medium Unit','size'=>'10x10','description'=>'Fits contents of a one-bedroom apartment.','price'=>'60'],
                                ['title'=>'Large Unit','size'=>'10x20','description'=>'Great for larger moves or business storage.','price'=>'110']
                            ];
                        }
                        foreach ($units as $u) {
                            $title = htmlspecialchars($u['title'] ?? ($u['id'] ?? 'Unit'));
                            $desc = htmlspecialchars($u['description'] ?? '');
                            $size = htmlspecialchars($u['size'] ?? '');
                            $price = isset($u['price']) && $u['price'] !== '' ? '$'.htmlspecialchars($u['price']) : '';
                            echo '<div class="card unit-card">';
                            echo '<div class="card-header"><h3 class="card-title">'.$title.'</h3><p class="card-description">'.htmlspecialchars($size).'</p></div>';
                            echo '<div class="card-body">';
                            if ($desc) echo '<p>'.nl2br($desc).'</p>';
                            if ($price) echo '<div class="small">Starting at <strong>'.$price.'</strong></div>';
                            echo '</div>';
                            echo '</div>';
                        }
                    }
                ?>
            </div>
        </div>
    </section>
    <!-- ============================================
         STORAGE CONTAINER RENTAL QUOTE FORM
         ============================================ -->

    <section class="section" id="storage-quote">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Get Custom Storage Quote</h2>
                <p class="section-subtitle">Tell us about your specific storage needs for an accurate quote</p>
            </div>
            <div class="container-narrow">
                <form class="card" id="storage-quote-form" method="post" action="admin/reserve.php" data-no-ajax="1">
                    <!-- Customer Information -->
                    <h3 class="accent-heading mb-1rem">Contact Information</h3>
                    <div class="grid grid-2">
                        <div>
                            <label for="customer-name-storage" class="form-label">Full Name *</label>
                            <input type="text" id="customer-name-storage" name="customer_name" required class="form-input">
                        </div>
                        <div>
                            <label for="company-name" class="form-label">Company Name (if applicable)</label>
                            <input type="text" id="company-name" name="company_name" class="form-input">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div>
                            <label for="customer-phone-storage" class="form-label">Phone Number *</label>
                            <input type="tel" id="customer-phone-storage" name="customer_phone" required class="form-input">
                        </div>
                        <div>
                            <label for="customer-email-storage" class="form-label">Email Address *</label>
                            <input type="email" id="customer-email-storage" name="customer_email" required class="form-input">
                        </div>
                    </div>
                    
                    <!-- Storage Requirements -->
                    <h3 class="accent-heading mt-2rem-mb-1rem">Storage Requirements</h3>
                    <div class="grid grid-2">
                        <div>
                            <label for="container-size-storage" class="form-label">Container Size Needed *</label>
                            <select id="container-size-storage" name="container_size" required class="form-input">
                                <option value="">Select Size</option>
                                <option value="10ft">10ft Container (560 cu ft)</option>
                                <option value="20ft">20ft Container (1,120 cu ft)</option>
                                <option value="40ft">40ft Container (2,240 cu ft)</option>
                                <option value="multiple">Multiple Containers</option>
                                <option value="not-sure">Not Sure - Need Recommendation</option>
                            </select>
                        </div>
                        <div>
                            <label for="quantity" class="form-label">Quantity Needed</label>
                            <input type="number" id="quantity" name="quantity" min="1" max="20" value="1" class="form-input">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div>
                            <label for="rental-duration" class="form-label">Rental Duration *</label>
                            <select id="rental-duration" name="rental_duration" required class="form-input">
                                <option value="">Select Duration</option>
                                <option value="1-week">1 Week</option>
                                <option value="2-weeks">2 Weeks</option>
                                <option value="1-month">1 Month</option>
                                <option value="2-months">2 Months</option>
                                <option value="3-months">3 Months</option>
                                <option value="6-months">6 Months</option>
                                <option value="12-months">12+ Months</option>
                                <option value="ongoing">Ongoing/Month-to-Month</option>
                            </select>
                        </div>
                        <div>
                            <label for="start-date-storage" class="form-label">Needed By Date</label>
                            <input type="date" id="start-date-storage" name="start_date" class="form-input">
                        </div>
                    </div>
                    
                    <!-- Delivery Information -->
                    <h3 class="accent-heading mt-2rem-mb-1rem">Delivery Information</h3>
                    <div>
                        <label for="delivery-address" class="form-label">Delivery Address *</label>
                        <textarea id="delivery-address" name="delivery_address" rows="3" required placeholder="Full street address including city, state, and zip code" class="form-input"></textarea>
                    </div>
                    
                    <div class="grid grid-2">
                        <div>
                            <label for="access-type" class="form-label">Property Type</label>
                            <select id="access-type" name="access_type" class="form-input">
                                <option value="">Select Type</option>
                                <option value="residential">Residential Home</option>
                                <option value="apartment">Apartment Complex</option>
                                <option value="commercial">Commercial Property</option>
                                <option value="construction">Construction Site</option>
                                <option value="storage-facility">Storage Facility</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="surface-type" class="form-label">Surface Type</label>
                            <select id="surface-type" name="surface_type" class="form-input">
                                <option value="">Select Surface</option>
                                <option value="concrete">Concrete/Asphalt</option>
                                <option value="gravel">Gravel</option>
                                <option value="dirt">Dirt/Grass</option>
                                <option value="paved">Paved Driveway</option>
                                <option value="unknown">Not Sure</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="access-restrictions" class="form-label">Access Restrictions</label>
                        <textarea id="access-restrictions" name="access_restrictions" rows="3" placeholder="Gates, low overhangs, narrow driveways, stairs, or other delivery challenges?" class="form-input"></textarea>
                    </div>
                    
                    <!-- Usage Information -->
                    <h3 class="accent-heading mt-2rem-mb-1rem">Usage Information</h3>
                    <div>
                        <label for="storage-purpose" class="form-label">What will you be storing? *</label>
                        <select id="storage-purpose" name="storage_purpose" required class="form-input">
                            <option value="">Select Purpose</option>
                            <option value="household-move">Household Moving/Storage</option>
                            <option value="home-renovation">Home Renovation</option>
                            <option value="business-storage">Business Storage</option>
                            <option value="construction-tools">Construction Tools/Materials</option>
                            <option value="seasonal-items">Seasonal Items</option>
                            <option value="commercial-inventory">Commercial Inventory</option>
                            <option value="vehicle-storage">Vehicle Storage</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="items-description" class="form-label">Description of Items</label>
                        <textarea id="items-description" name="items_description" rows="3" placeholder="Briefly describe what you'll be storing (helps us recommend the right size)" class="form-input"></textarea>
                    </div>
                    
                    <div class="grid grid-2">
                        <div>
                            <label class="form-checkbox">
                                <input type="checkbox" name="climate_sensitive" value="yes">
                                <span>Items are temperature/humidity sensitive</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="valuable_items" value="yes">
                                <span>High-value items needing extra security</span>
                            </label>
                        </div>
                        <div>
                            <label class="form-checkbox">
                                <input type="checkbox" name="frequent_access" value="yes">
                                <span>Need frequent access to stored items</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="loading_help" value="yes">
                                <span>Interested in loading/unloading assistance</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Additional Services -->
                    <h3 class="accent-heading mt-2rem-mb-1rem">Additional Services</h3>
                    <div class="grid grid-2">
                        <div>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="locks-provided">
                                <span>Provide high-security locks</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="insurance">
                                <span>Storage insurance options</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="moving-supplies">
                                <span>Moving supplies (boxes, padding)</span>
                            </label>
                        </div>
                        <div>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="pickup-service">
                                <span>Pickup when rental ends</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="relocation">
                                <span>Relocation to different address</span>
                            </label>
                            <label class="form-checkbox">
                                <input type="checkbox" name="services[]" value="extended-hours">
                                <span>After-hours/weekend delivery</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Budget and Special Requests -->
                    <div>
                        <label for="budget-range" class="form-label">Budget Range (optional)</label>
                        <select id="budget-range" name="budget_range" class="form-input">
                            <option value="">Select Range</option>
                            <option value="under-100">Under $100/month</option>
                            <option value="100-200">$100 - $200/month</option>
                            <option value="200-500">$200 - $500/month</option>
                            <option value="500-1000">$500 - $1,000/month</option>
                            <option value="over-1000">Over $1,000/month</option>
                            <option value="flexible">Flexible</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="special-requests" class="form-label">Special Requests or Questions</label>
                        <textarea id="special-requests" name="special_requests" rows="4" placeholder="Any specific requirements, questions about our service, or special circumstances we should know about?" class="form-input"></textarea>
                    </div>
                    
                    <div>
                        <label for="how-heard" class="form-label">How did you hear about us?</label>
                        <select id="how-heard" name="how_heard" class="form-input">
                            <option value="">Select Source</option>
                            <option value="google">Google Search</option>
                            <option value="referral">Referral from Friend/Family</option>
                            <option value="social-media">Social Media</option>
                            <option value="website">Our Website</option>
                            <option value="advertisement">Advertisement</option>
                            <option value="repeat-customer">Previous Customer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="rush_quote" value="yes">
                            <span>This is urgent - please contact me ASAP</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary form-submit-btn">Get My Custom Quote</button>
                </form>
            </div>
        </div>
    </section>

    <!-- ==============================================
         ABOUT SECTION COMPONENT
         Two-column layout with content and image placeholder
         ============================================== -->
    <section class="section" id="about">
        <div class="container">
            <?php
                // About content: left column is descriptive content, right column shows an embedded map
                $bizAddress = $content['business_info']['address'] ?? '';
                $bizName = htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage');
                // allow an explicit embed URL in content.json (content.business_info.map_embed)
                $explicitEmbed = $content['business_info']['map_embed'] ?? '';
            ?>
            <div class="about-grid">
                <div>
                    <?php
                        // Render About content from the editable content store when available.
                        $aboutHeading = getContent($content, 'about.heading', 'About Midway Mobile Storage');
                        $aboutBody = getContent($content, 'about.body', '');
                    ?>
                    <h2 class="section-title"><?php echo htmlspecialchars($aboutHeading); ?></h2>
                    <?php
                        if (is_string($aboutBody) && trim($aboutBody) !== '') {
                            // If admin provided HTML (contains a '<'), render raw HTML. Otherwise escape and preserve line breaks.
                            if (strpos($aboutBody, '<') !== false) {
                                echo $aboutBody;
                            } else {
                                echo '<p class="mb-4">' . nl2br(htmlspecialchars($aboutBody)) . '</p>';
                            }
                        } else {
                            // Fallback copy if no content provided
                            echo '<p class="mb-4">Midway Mobile Storage provides secure, convenient mobile storage solutions for residents and businesses in the Midway area.</p>';
                            echo '<p>We offer flexible unit sizes, month-to-month rentals, and friendly local service. If you\'d like to see our location or get directions, use the map to the right or click "Open map" below.</p>';
                        }
                    ?>
                    <?php
                        // Prepare map links (open map and directions)
                        if ($bizAddress) {
                            $q = rawurlencode($bizAddress);
                            $gmUrl = 'https://www.google.com/maps?q=' . $q;
                            $dirUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $q;
                        } else {
                            $gmUrl = 'https://www.google.com/maps';
                            $dirUrl = 'https://www.google.com/maps';
                        }
                        $pinSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg>';
                        $dirSvg = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 0-.9-3.8L12 18l3.8-8.1c-.9.4-1.9.6-2.8.6C8.7 10.5 6 7.8 6 4.5 6 3.7 6.1 3 6.2 2.2 7.9 3 9.7 3.5 11.5 3.5c6 0 9.5 3.6 9.5 8z"/></svg>';
                        $mapLinksHtml = '<div class="map-links"><a class="btn btn-secondary" href="' . htmlspecialchars($gmUrl) . '" target="_blank" rel="noopener noreferrer" title="Open ' . $bizName . ' in Google Maps" aria-label="Open ' . $bizName . ' in Google Maps" aria-pressed="false">' . $pinSvg . '<span class="label">Open map</span></a> <a class="btn btn-outline" href="' . htmlspecialchars($dirUrl) . '" target="_blank" rel="noopener noreferrer" title="Get directions to ' . $bizName . '" aria-label="Get directions to ' . $bizName . '" aria-pressed="false">' . $dirSvg . '<span class="label">Directions</span></a></div>';
                    ?>
                </div>
                <div>
                    <?php
                        // Map embed: prefer explicit embed URL in content.json, otherwise construct from address
                        if ($explicitEmbed) {
                            $embedSrc = $explicitEmbed;
                        } elseif ($bizAddress) {
                            $q = rawurlencode($bizAddress);
                            $zoom = 15;
                            $embedSrc = "https://www.google.com/maps?q={$q}&z={$zoom}&output=embed";
                        } else {
                            // fallback: show google maps home
                            $embedSrc = 'https://www.google.com/maps';
                        }
                    ?>
                    <div class="map-frame">
                        <iframe class="about-map" src="<?php echo htmlspecialchars($embedSrc); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map showing our location"></iframe>
                        <div class="map-links visible"><a class="btn btn-primary" href="https://www.google.com/maps?q=212%20Fred%20Sink%20Rd%2C%20Winston-Salem%2C%20NC%2027107" target="_blank" rel="noopener noreferrer" title="Open Midway Mobile Storage in Google Maps" aria-label="Open Midway Mobile Storage in Google Maps" aria-pressed="false"><svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"></path></svg><span class="label">Open map</span></a> <a class="btn btn-outline" href="https://www.google.com/maps/dir/?api=1&amp;destination=212%20Fred%20Sink%20Rd%2C%20Winston-Salem%2C%20NC%2027107" target="_blank" rel="noopener noreferrer" title="Get directions to Midway Mobile Storage" aria-label="Get directions to Midway Mobile Storage" aria-pressed="false"><svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 0-.9-3.8L12 18l3.8-8.1c-.9.4-1.9.6-2.8.6C8.7 10.5 6 7.8 6 4.5 6 3.7 6.1 3 6.2 2.2 7.9 3 9.7 3.5 11.5 3.5c6 0 9.5 3.6 9.5 8z"></path></svg><span class="label">Directions</span></a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    </form>

    <!-- ==============================================
     CAREERS / JOB APPLICATION SECTION (moved into main body)
     ============================================== -->
    <section class="section" id="job-application">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Careers</h2>
            <p class="section-subtitle">We're growing — apply for a role at Midway Mobile Storage.</p>
        </div>
        <div class="container-narrow">
                        <form class="card" id="job-application-form" action="/contact.php" method="post" enctype="multipart/form-data">
                    <?php echo ferrs(); ?>
          <!-- Honeypot field to deter bots (hidden from users) -->
          <input type="text" name="hp_field" id="hp-field" autocomplete="off" tabindex="-1"
              class="sr-only" >
                <!-- Personal Information -->
                <h3 class="accent-heading mb-1rem">Personal Information</h3>
                <div class="grid grid-2">
                    <div>
                        <label for="applicant-first-name" class="form-label">First Name *</label>
                        <input type="text" id="applicant-first-name" name="first_name" required class="form-input" value="<?php echo fv('first_name'); ?>">
                    </div>
                    <div>
                        <label for="applicant-last-name" class="form-label">Last Name *</label>
                        <input type="text" id="applicant-last-name" name="last_name" required class="form-input" value="<?php echo fv('last_name'); ?>">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="applicant-email" class="form-label">Email Address *</label>
                        <input type="email" id="applicant-email" name="email" required class="form-input" value="<?php echo fv('email'); ?>">
                    </div>
                    <div>
                        <label for="applicant-phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="applicant-phone" name="phone" required class="form-input" value="<?php echo fv('phone'); ?>">
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <label for="address" class="form-label">Address *</label>
                    <textarea id="address" name="address" rows="2" required placeholder="Street address, city, state, zip" class="form-input"><?php echo fv('address'); ?></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="age" class="form-label">Age *</label>
                        <div class="stepper" role="group" aria-label="Age">
                                <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease age"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                            <input type="number" id="age" name="age" min="16" max="100" required class="form-input" value="<?php echo fv('age'); ?>">
                            <button type="button" class="stepper-btn" data-step="up" aria-label="Increase age"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H13v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> </button>
                        </div>
                        <div id="age-note" class="form-note small mt-035">Minimum age to apply is 16 years.</div>
                    </div>
                    <div>
                        <label for="eligible-to-work" class="form-label">Eligible to work in US? *</label>
                        <select id="eligible-to-work" name="eligible_to_work" required class="form-input">
                            <option value="">Select</option>
                            <option value="yes" <?php if(fv('eligible_to_work')==='yes') echo 'selected'; ?>>Yes</option>
                            <option value="no" <?php if(fv('eligible_to_work')==='no') echo 'selected'; ?>>No</option>
                        </select>
                    </div>
                </div>
                
                <!-- Position Information -->
                <h3 class="accent-heading mt-2rem-mb-1rem">Position Information</h3>
                <div class="grid grid-2">
                    <div>
                        <label for="position-desired" class="form-label">Position Desired *</label>
                        <select id="position-desired" name="position_desired" required class="form-input">
                            <option value="">Select Position</option>
                            <option value="admin" <?php if(fv('position_desired')==='admin') echo 'selected'; ?>>Administrative / Office Staff</option>
                            <option value="driver" <?php if(fv('position_desired')==='driver') echo 'selected'; ?>>Driver</option>
                            <option value="maintenance" <?php if(fv('position_desired')==='maintenance') echo 'selected'; ?>>Maintenance Technician</option>
                            <option value="fabricator" <?php if(fv('position_desired')==='fabricator') echo 'selected'; ?>>Fabricator / Custom Technician</option>
                            <option value="customer-service" <?php if(fv('position_desired')==='customer-service') echo 'selected'; ?>>Customer Service</option>
                            <option value="manager" <?php if(fv('position_desired')==='manager') echo 'selected'; ?>>Manager</option>
                        </select>
                    </div>
                    <div>
                        <label for="employment-type" class="form-label">Employment Type</label>
                        <select id="employment-type" name="employment_type" class="form-input">
                            <option value="">Select</option>
                            <option value="full-time" <?php if(fv('employment_type')==='full-time') echo 'selected'; ?>>Full-time</option>
                            <option value="part-time" <?php if(fv('employment_type')==='part-time') echo 'selected'; ?>>Part-time</option>
                            <option value="seasonal" <?php if(fv('employment_type')==='seasonal') echo 'selected'; ?>>Seasonal</option>
                            <option value="temporary" <?php if(fv('employment_type')==='temporary') echo 'selected'; ?>>Temporary</option>
                            <option value="contract" <?php if(fv('employment_type')==='contract') echo 'selected'; ?>>Contract</option>
                        </select>
                    </div>
                </div>

                <!-- Availability & Certifications -->
                <?php $vals = $form_flash['values'] ?? []; ?>
                <div class="grid grid-2">
                    <div>
                        <label class="form-label">Availability</label>
                        <div class="form-note small">(select all that apply)</div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="availability[]" value="Weekdays" <?php if(!empty($vals['availability']) && is_array($vals['availability']) && in_array('Weekdays',$vals['availability'])) echo 'checked'; ?>> Weekdays</label>
                            <label><input type="checkbox" name="availability[]" value="Weekends" <?php if(!empty($vals['availability']) && is_array($vals['availability']) && in_array('Weekends',$vals['availability'])) echo 'checked'; ?>> Weekends</label>
                            <label><input type="checkbox" name="availability[]" value="Mornings" <?php if(!empty($vals['availability']) && is_array($vals['availability']) && in_array('Mornings',$vals['availability'])) echo 'checked'; ?>> Mornings</label>
                            <label><input type="checkbox" name="availability[]" value="Afternoons" <?php if(!empty($vals['availability']) && is_array($vals['availability']) && in_array('Afternoons',$vals['availability'])) echo 'checked'; ?>> Afternoons</label>
                            <label><input type="checkbox" name="availability[]" value="Evenings" <?php if(!empty($vals['availability']) && is_array($vals['availability']) && in_array('Evenings',$vals['availability'])) echo 'checked'; ?>> Evenings</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Certifications / Licenses</label>
                        <div class="form-note small">(optional)</div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="certifications[]" value="Forklift" <?php if(!empty($vals['certifications']) && is_array($vals['certifications']) && in_array('Forklift',$vals['certifications'])) echo 'checked'; ?>> Forklift</label>
                            <label><input type="checkbox" name="certifications[]" value="CDL" <?php if(!empty($vals['certifications']) && is_array($vals['certifications']) && in_array('CDL',$vals['certifications'])) echo 'checked'; ?>> CDL</label>
                            <label><input type="checkbox" name="certifications[]" value="FirstAid" <?php if(!empty($vals['certifications']) && is_array($vals['certifications']) && in_array('FirstAid',$vals['certifications'])) echo 'checked'; ?>> First Aid / CPR</label>
                            <label><input type="checkbox" name="certifications[]" value="Other" <?php if(!empty($vals['certifications']) && is_array($vals['certifications']) && in_array('Other',$vals['certifications'])) echo 'checked'; ?>> Other</label>
                        </div>
                    </div>
                </div>

                <!-- Resume upload and experience -->
                <div class="grid grid-2">
                    <div>
                        <label for="resume" class="form-label">Upload Resume (PDF or Word, max 2MB)</label>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx" class="form-input">
                    </div>
                    <div>
                        <label for="why-work-here" class="form-label">Tell us why you want to work here *</label>
                        <textarea id="why-work-here" name="why_work_here" rows="4" required class="form-input"><?php echo fv('why_work_here'); ?></textarea>
                    </div>
                </div>

                <div class="grid grid-1">
                    <label for="experience" class="form-label">Additional Experience / Comments</label>
                    <textarea id="experience" name="message" rows="4" class="form-input"><?php echo fv('message'); ?></textarea>
                </div>

                <div class="grid grid-1 mt-1rem">
                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </div>
                </form>

            </div>
        </div>
    </section>

        <!-- Footer -->
        <!-- IMPORTANT: keep this <footer> element outside any .container or .container-narrow wrappers
             so the footer background can span the full viewport width (full-bleed). The inner
             <div class="container"> should be used to center footer content only. Do not nest
             page sections inside this footer. -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-row">
                <div class="small footer-left">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>. All rights reserved.</div>
                <div class="small footer-center center">
                    <?php $biz = $content['business_info'] ?? []; ?>
                    <div class="biz-name"><?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?></div>
                    <div class="small biz-address"><?php echo htmlspecialchars($biz['address'] ?? ''); ?></div>
                    <div class="small biz-contact"><a href="tel:<?php echo preg_replace('/[^0-9+]/','', $biz['phone'] ?? ''); ?>"><?php echo htmlspecialchars($biz['phone'] ?? ''); ?></a> &nbsp;|&nbsp; <a href="mailto:<?php echo htmlspecialchars($biz['email'] ?? ''); ?>"><?php echo htmlspecialchars($biz['email'] ?? ''); ?></a></div>
                    <div class="small mt-025"><a href="#" id="footer-hours-link">Hours</a></div>
                </div>
                <div class="small footer-right right">
                    <nav class="footer-links" aria-label="Footer navigation">
                        <a href="#menu">Menu</a>
                        <a href="#storage-quote">Quotes</a>
                        <a href="#about">About</a>
                        <a href="#job-application">Careers</a>
                        <a href="#contact" id="footer-contact-link">Contact</a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>

    <!-- Contact modal (used on small screens and when footer contact is clicked) -->
    <div id="contact-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="contact-modal-title">
        <div class="modal-backdrop" id="contact-modal-backdrop"></div>
        <div class="modal-panel" role="document">
            <button type="button" class="modal-close" aria-label="Close contact">✕</button>
            <div class="modal-header">
                <?php $biz = $content['business_info'] ?? []; ?>
                <?php if (!empty($biz['logo'])): ?>
                    <img src="<?php echo htmlspecialchars('/uploads/images/' . ltrim($biz['logo'], '/')); ?>" alt="<?php echo htmlspecialchars($biz['name'] ?? ''); ?>" class="modal-logo">
                <?php else: ?>
                    <div class="modal-logo-text"><?php echo htmlspecialchars($biz['name'] ?? ''); ?></div>
                <?php endif; ?>
                <div class="modal-subtitle small muted">Send us a message &mdash; we typically reply within 1 business day.</div>
            </div>
            <h2 id="contact-modal-title">Contact Us</h2>
            <div class="card">
                <form id="footer-contact-form" action="/contact.php" method="post">
                    <label class="form-label">Name <input type="text" name="first_name" class="form-input" required></label>
                    <label class="form-label">Email <input type="email" name="email" class="form-input" required></label>
                    <label class="form-label">Message <textarea name="message" class="form-input" rows="4" required></textarea></label>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Send</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>window.__HOURS_DATA = <?php echo json_encode($content['hours'] ?? new stdClass()); ?>;</script>
    <script src="assets/js/inline.js" defer></script>
</body>
</html>