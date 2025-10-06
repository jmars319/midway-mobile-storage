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
function ferrs() { global $form_flash; if (!$form_flash || empty($form_flash['errors'])) return ''; $out = '<div class="card" style="border-left:4px solid var(--danger);padding:.5rem;margin-bottom:1rem"><strong>There were errors with your submission:</strong><ul>'; foreach ($form_flash['errors'] as $e) { $out .= '<li>'.htmlspecialchars($e).'</li>'; } $out .= '</ul></div>'; return $out; }
?>
<header class="header">
    <div class="container">
        <nav class="navbar">
            <?php
                $logoFile = $content['images']['logo'] ?? '';
                $logoUrl = '';
                if ($logoFile) { $logoUrl = preg_match('#^https?://#i', $logoFile) ? $logoFile : 'uploads/images/'.ltrim($logoFile, '/'); }
            ?>
            <a href="/" class="logo">
                <?php if ($logoUrl): ?>
                    <?php
                      // prefer optimized responsive variants when available
                      $base = dirname($logoUrl) . '/' . pathinfo($logoUrl, PATHINFO_FILENAME);
                      $logo48 = 'uploads/images/' . ($content['images']['logo'] ? preg_replace('/\.png$/i','-48.png', $content['images']['logo']) : 'logo-48.png');
                      $logo96 = 'uploads/images/' . ($content['images']['logo'] ? preg_replace('/\.png$/i','-96.png', $content['images']['logo']) : 'logo-96.png');
                      $logo192 = 'uploads/images/' . ($content['images']['logo'] ? preg_replace('/\.png$/i','-192.png', $content['images']['logo']) : 'logo-192.png');
                    ?>
                                        <picture>
                                            <source type="image/webp" srcset="/uploads/images/logo-48.webp 1x, /uploads/images/logo-96.webp 2x, /uploads/images/logo-192.webp 4x">
                                            <img src="/uploads/images/logo-48.png" srcset="/uploads/images/logo-48.png 1x, /uploads/images/logo-96.png 2x, /uploads/images/logo-192.png 4x" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>" class="site-logo-img">
                                        </picture>
                <?php else: ?>
                    <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>
                <?php endif; ?>
            </a>

            <ul class="nav-menu">
                <li><a class="nav-link" href="#units">Units</a></li>
                <li><a class="nav-link" href="#reservation">Reserve</a></li>
                <li><a class="nav-link" href="#about">About</a></li>
                <li><a class="nav-link" href="#job-application">Careers</a></li>
                <li><a class="nav-link" href="#contact">Contact</a></li>
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
            $heroBtn2 = getContent($content, 'hero.btn2_text', 'Reserve a Unit');
            $heroBtn2Link = getContent($content, 'hero.btn2_link', '#reservation');
            $heroBackgroundStyleTag = '';
            if ($heroImage) {
                // prefer full URL when provided
                $imgUrl = preg_match('#^https?://#i', $heroImage) ? $heroImage : 'uploads/images/'.ltrim($heroImage, '/');
                // emit a small style block to avoid inline style attributes on the section element
                $heroBackgroundStyleTag = '<style>.hero{background-image: url("' . htmlspecialchars((string)$imgUrl, ENT_QUOTES, 'UTF-8') . '"); background-size: cover; background-position: center;}</style>';
            }
        ?>
        <?php echo $heroBackgroundStyleTag; ?>
        <section class="hero" aria-label="Hero">
            <div class="container">
                <h1 class="hero-title"><?php echo htmlspecialchars($heroTitle); ?></h1>
                <?php if ($heroSubtitle): ?><p class="hero-subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p><?php endif; ?>
                <div style="margin-top:1rem;display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap">
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
                    $units = $content['units'] ?? null;
                    if (is_array($units) && count($units)) {
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
                    } else {
                        $placeholders = [
                            ['title'=>'Small Unit','size'=>'5x5','description'=>'Ideal for boxes and small furniture.','price'=>'25'],
                            ['title'=>'Medium Unit','size'=>'10x10','description'=>'Fits contents of a one-bedroom apartment.','price'=>'60'],
                            ['title'=>'Large Unit','size'=>'10x20','description'=>'Great for larger moves or business storage.','price'=>'110']
                        ];
                        foreach ($placeholders as $ph) {
                            echo '<div class="card unit-card">';
                            echo '<div class="card-header"><h3 class="card-title">'.htmlspecialchars($ph['title']).'</h3><p class="card-description">'.htmlspecialchars($ph['size']).'</p></div>';
                            echo '<div class="card-body"><p>'.htmlspecialchars($ph['description']).'</p><div class="small">Starting at <strong>$'.htmlspecialchars($ph['price']).'</strong></div></div>';
                            echo '</div>';
                        }
                    }
                ?>
            </div>
        </div>
    </section>
    <section class="section" id="reservation">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Make a Reservation</h2>
                <p class="section-subtitle">Request a reservation and we'll confirm availability.</p>
            </div>
            <div class="container-narrow">
                <form id="reservation-form" class="card" method="post" action="admin/reserve.php" data-no-ajax="1">
                    <div class="grid grid-2">
                        <div>
                            <label for="res-name" class="form-label">Name *</label>
                            <input type="text" id="res-name" name="name" required class="form-input">
                        </div>
                        <div>
                            <label for="res-phone" class="form-label">Phone *</label>
                            <input type="tel" id="res-phone" name="phone" required class="form-input">
                        </div>
                    </div>
                    <div class="grid grid-2">
                        <div>
                            <label for="res-date" class="form-label">Date *</label>
                            <input type="date" id="res-date" name="date" required class="form-input">
                        </div>
                        <div>
                            <label for="res-time" class="form-label">Time *</label>
                            <input type="time" id="res-time" name="time" required class="form-input">
                        </div>
                    </div>
                    <div>
                        <label for="res-event" class="form-label">Event type (optional)</label>
                        <input type="text" id="res-event" name="event_type" class="form-input" placeholder="Birthday, meeting, etc.">
                    </div>
                    <div>
                        <label for="res-guests" class="form-label">Number of Guests *</label>
                        <div style="display:flex;align-items:center;gap:.5rem">
                            <div class="stepper" aria-label="Number of guests" role="group">
                                <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease guests"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                                <input type="number" id="res-guests" name="guests" required class="form-input" min="1" value="1" aria-describedby="res-guests-note res-guests-error">
                                <button type="button" class="stepper-btn" data-step="up" aria-label="Increase guests">+</button>
                            </div>
                        </div>
                        <?php $phone = htmlspecialchars($content['business_info']['phone'] ?? ''); ?>
                        <div id="res-guests-note" class="form-note small text-muted" style="display:none;margin-top:.35rem">For parties of 8 or more, please call us at <?php echo $phone ?: 'the restaurant'; ?> to arrange seating.</div>
                        <div id="res-guests-error" class="form-error small" style="display:none;margin-top:.35rem">Please call us for very large parties (over 50 guests).</div>
                    </div>
                    <div id="reservation-confirm" style="margin-top:1rem; display:none">
                        <div class="card form-success" id="reservation-confirm-msg" tabindex="-1">
                            <button id="reservation-confirm-close" type="button" aria-label="Dismiss confirmation" style="float:right;background:none;border:none;font-weight:bold;font-size:1.1rem;cursor:pointer">✕</button>
                            <div id="reservation-confirm-text"></div>
                        </div>
                    </div>
                    <div style="margin-top:1rem">
                        <button type="submit" class="btn btn-primary">Request Reservation</button>
                    </div>
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
                    <h2 class="section-title">About Midway Mobile Storage</h2>
                    <p class="mb-4">Midway Mobile Storage provides secure, convenient mobile storage solutions for residents and businesses in the Midway area.</p>
                    <p>We offer flexible unit sizes, month-to-month rentals, and friendly local service. If you'd like to see our location or get directions, use the map to the right or click "Open map" below.</p>
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
              style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;border:0;">
                <!-- Personal Information -->
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Personal Information</h3>
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
                        <div id="age-note" class="form-note small" style="margin-top:.35rem">Minimum age to apply is 16 years.</div>
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
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Position Information</h3>
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <div class="small footer-left">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>. All rights reserved.</div>
                <div class="small footer-center center">
                    <?php $biz = $content['business_info'] ?? []; ?>
                    <div class="biz-name" style="font-weight:700"><?php echo htmlspecialchars($biz['name'] ?? 'Midway Mobile Storage'); ?></div>
                    <div class="small biz-address"><?php echo htmlspecialchars($biz['address'] ?? ''); ?></div>
                    <div class="small biz-contact"><a href="tel:<?php echo preg_replace('/[^0-9+]/','', $biz['phone'] ?? ''); ?>"><?php echo htmlspecialchars($biz['phone'] ?? ''); ?></a> &nbsp;|&nbsp; <a href="mailto:<?php echo htmlspecialchars($biz['email'] ?? ''); ?>"><?php echo htmlspecialchars($biz['email'] ?? ''); ?></a></div>
                    <div class="small" style="margin-top:.25rem"><a href="#" id="footer-hours-link">Hours</a></div>
                </div>
                <div class="small footer-right right">
                    <nav class="footer-links" aria-label="Footer navigation">
                        <a href="#menu">Menu</a>
                        <a href="#reservation">Reservations</a>
                        <a href="#about">About</a>
                        <a href="#job-application">Careers</a>
                        <a href="#contact">Contact</a>
                    </nav>
                </div>
            </div>
        </div>
    </footer>

    <script>window.__HOURS_DATA = <?php echo json_encode($content['hours'] ?? new stdClass()); ?>;</script>
    <script src="assets/js/inline.js" defer></script>
</body>
</html>