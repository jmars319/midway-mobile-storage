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
// Cache-busting version string for static assets during development
$cssPath = 'assets/css/styles.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Midway Mobile Storage | Midway, NC</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssPath . '?v=' . $cssVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="/uploads/images/favicon-set/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/uploads/images/favicon-set/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/uploads/images/favicon-set/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/uploads/images/favicon-set/favicon-180x180.png">
    <link rel="manifest" href="/site.webmanifest">
    <!-- Typography is self-hosted from assets/fonts/ -->
</head>
<body>
<?php
// Show form flash if present (non-AJAX submission validation errors)
session_start();
$form_flash = $_SESSION['form_flash'] ?? null;
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
                                            <img src="/uploads/images/logo-48.png" srcset="/uploads/images/logo-48.png 1x, /uploads/images/logo-96.png 2x, /uploads/images/logo-192.png 4x" alt="<?php echo htmlspecialchars($content['business_info']['name'] ?? 'Midway Mobile Storage'); ?>" style="height:40px; width:auto; display:inline-block; vertical-align:middle">
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

            </nav>

            <!-- ==============================================
             HERO SECTION
             ============================================== -->
        <?php
            $heroTitle = getContent($content, 'hero.title', 'Secure, convenient storage solutions');
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
                $heroBackgroundStyleTag = '<style>.hero{background-image: url("' . htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') . '"); background-size: cover; background-position: center;}</style>';
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

    <script>
    // Reservation confirmation helper: if the page is loaded with
    // ?success=1&guests=N in the hash/query, show a confirmation message.
    (function(){
        try {
            var params = new URLSearchParams(window.location.hash.replace(/^#/, '?'));
            if (!params || params.get('success') !== '1') return;
            var guests = params.get('guests') || '';
            var wrap = document.getElementById('reservation-confirm');
            var outer = document.getElementById('reservation-confirm-msg');
            var text = document.getElementById('reservation-confirm-text');
            if (!wrap || !outer || !text) return;

            // Respect user dismissal stored in localStorage
            var dismissKey = 'reservation_confirm_dismissed';
            if (localStorage.getItem(dismissKey) === '1') return;

            text.textContent = 'Thank you! Your reservation request has been received' + (guests ? (' for ' + guests + ' guest' + (guests === '1' ? '' : 's')) : '') + '. We will contact you to confirm.';
            wrap.style.display = 'block';
            outer.focus({preventScroll:true});

            // Wire dismiss button to hide and remember dismissal
            var close = document.getElementById('reservation-confirm-close');
            if (close) {
                close.addEventListener('click', function(){
                    wrap.style.display = 'none';
                    try { localStorage.setItem(dismissKey, '1'); } catch(e){}
                });
            }

            // clear hash so refreshing doesn't re-show
            try { history.replaceState(null, '', window.location.pathname + window.location.search + '#reservation'); } catch(e){}
        } catch(e) { /* non-fatal */ }
    })();
    </script>

        <script>
            // Menu card expand/collapse (toggle class + aria and label)
            (function(){
                    function toggleForButton(btn){
                        var card = btn.closest('.menu-card'); if (!card) return;
                        var expanded = card.classList.toggle('expanded');
                        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                        var label = btn.querySelector('.expand-label'); if (label) label.textContent = expanded ? 'Less' : 'View All';
                        return expanded;
                    }

                    document.addEventListener('click', function(e){
                        var btn = e.target.closest && e.target.closest('.expand-btn');
                        if (!btn) return;
                        toggleForButton(btn);
                    });

                    // keyboard activation: support Enter and Space on the button
                    document.addEventListener('keydown', function(e){
                        if (!e.target) return;
                        if (!e.target.classList || !e.target.classList.contains('expand-btn')) return;
                        if (e.key === 'Enter') { e.preventDefault(); toggleForButton(e.target); }
                        if (e.key === ' ') { e.preventDefault(); toggleForButton(e.target); }
                    });
                })();
        </script>

    <!-- Footer will be rendered at the end of the page to ensure it stays below main content -->

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
                            <option value="other" <?php if(fv('position_desired')==='other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="employment-type" class="form-label">Employment Type *</label>
                        <select id="employment-type" name="employment_type" required class="form-input">
                            <option value="">Select Type</option>
                            <option value="full-time" <?php if(fv('employment_type')==='full-time') echo 'selected'; ?>>Full Time</option>
                            <option value="part-time" <?php if(fv('employment_type')==='part-time') echo 'selected'; ?>>Part Time</option>
                            <option value="seasonal" <?php if(fv('employment_type')==='seasonal') echo 'selected'; ?>>Seasonal</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="desired-salary" class="form-label">Desired Salary/Hourly Rate</label>
                        <input type="text" id="desired-salary" name="desired_salary" class="form-input" placeholder="e.g., $15/hour" value="<?php echo fv('desired_salary'); ?>">
                    </div>
                    <div>
                        <label for="start-date" class="form-label">Available Start Date</label>
                        <input type="date" id="start-date" name="start_date" class="form-input" value="<?php echo fv('start_date'); ?>">
                    </div>
                </div>
                
                <!-- Availability -->
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Availability</h3>
                <p style="margin-bottom: 1rem; color: var(--text-secondary);">Check all days you are available to work:</p>
                <div class="grid grid-2">
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="monday" <?php if(!empty($form_flash['values']['availability']) && in_array('monday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Monday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="tuesday" <?php if(!empty($form_flash['values']['availability']) && in_array('tuesday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Tuesday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="wednesday" <?php if(!empty($form_flash['values']['availability']) && in_array('wednesday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Wednesday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="thursday" <?php if(!empty($form_flash['values']['availability']) && in_array('thursday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Thursday</span>
                        </label>
                    </div>
                    <div>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="friday" <?php if(!empty($form_flash['values']['availability']) && in_array('friday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Friday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="saturday" <?php if(!empty($form_flash['values']['availability']) && in_array('saturday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Saturday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="sunday" <?php if(!empty($form_flash['values']['availability']) && in_array('sunday',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Sunday</span>
                        </label>
                        <label class="form-checkbox">
                            <input type="checkbox" name="availability[]" value="holidays" <?php if(!empty($form_flash['values']['availability']) && in_array('holidays',$form_flash['values']['availability'])) echo 'checked'; ?>>
                            <span>Holidays</span>
                        </label>
                    </div>
                </div>
                
                <div class="grid grid-2">
                    <div>
                        <label for="shift-preference" class="form-label">Shift Preference</label>
                        <select id="shift-preference" name="shift_preference" class="form-input">
                            <option value="">No Preference</option>
                            <option value="morning" <?php if(fv('shift_preference')==='morning') echo 'selected'; ?>>Morning (8am-4pm)</option>
                            <option value="evening" <?php if(fv('shift_preference')==='evening') echo 'selected'; ?>>Evening (4pm-close)</option>
                            <option value="night" <?php if(fv('shift_preference')==='night') echo 'selected'; ?>>Night (close)</option>
                        </select>
                    </div>
                    <div>
                        <label for="hours-per-week" class="form-label">Preferred Hours per Week</label>
                        <div class="stepper" role="group" aria-label="Preferred hours per week">
                            <button type="button" class="stepper-btn" data-step="down" aria-label="Decrease hours"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H5v-2h14v2z"/></svg> </button>
                            <input type="number" id="hours-per-week" name="hours_per_week" min="1" max="60" class="form-input" value="<?php echo fv('hours_per_week'); ?>">
                            <button type="button" class="stepper-btn" data-step="up" aria-label="Increase hours"> <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 13H13v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> </button>
                        </div>
                        <div class="form-note small" style="margin-top:.35rem">Typical availability: 20–40 hours per week.</div>
                    </div>
                </div>
                
                <!-- Experience -->
                <h3 style="margin: 2rem 0 1rem 0; color: var(--primary-color);">Experience</h3>
                <div>
                    <label for="restaurant-experience" class="form-label">Storage / Equipment Experience</label>
                    <textarea id="restaurant-experience" name="restaurant_experience" rows="4" placeholder="List experience with storage facilities, material handling, equipment or relevant trade skills..." class="form-input"><?php echo fv('restaurant_experience'); ?></textarea>
                </div>

                <div>
                    <label for="other-experience" class="form-label">Other Relevant Experience</label>
                    <textarea id="other-experience" name="other_experience" rows="3" placeholder="Any other work experience that might be relevant (e.g., customer service, fabrication, welding, driving)..." class="form-input"><?php echo fv('other_experience'); ?></textarea>
                </div>
                
                <div>
                    <label for="why-work-here" class="form-label">Why do you want to work here? *</label>
                    <textarea id="why-work-here" name="why_work_here" rows="3" required placeholder="Tell us why you're interested in joining our team..." class="form-input"><?php echo fv('why_work_here'); ?></textarea>
                </div>
                
                <!-- References -->
                <div>
                    <label class="form-label">Certifications / Licenses</label>
                    <p class="form-note small">Check any that apply (optional):</p>
                    <label class="form-checkbox"><input type="checkbox" name="certifications[]" value="drivers_license" <?php if(!empty($form_flash['values']['certifications']) && in_array('drivers_license', $form_flash['values']['certifications'])) echo 'checked'; ?>> <span>Driver's License</span></label>
                    <label class="form-checkbox"><input type="checkbox" name="certifications[]" value="cdl" <?php if(!empty($form_flash['values']['certifications']) && in_array('cdl', $form_flash['values']['certifications'])) echo 'checked'; ?>> <span>CDL</span></label>
                    <label class="form-checkbox"><input type="checkbox" name="certifications[]" value="forklift" <?php if(!empty($form_flash['values']['certifications']) && in_array('forklift', $form_flash['values']['certifications'])) echo 'checked'; ?>> <span>Forklift / Material Handling</span></label>
                    <label class="form-checkbox"><input type="checkbox" name="certifications[]" value="welding" <?php if(!empty($form_flash['values']['certifications']) && in_array('welding', $form_flash['values']['certifications'])) echo 'checked'; ?>> <span>Welding / Fabrication</span></label>
                    <label class="form-checkbox"><input type="checkbox" name="certifications[]" value="other_cert" <?php if(!empty($form_flash['values']['certifications']) && in_array('other_cert', $form_flash['values']['certifications'])) echo 'checked'; ?>> <span>Other (specify in Other Relevant Experience)</span></label>

                    <div style="margin-top:1rem">
                        <label for="resume" class="form-label">Upload Resume (optional)</label>
                        <input type="file" id="resume" name="resume" accept="application/pdf,.doc,.docx" class="form-input">
                        <div class="form-note small">PDF or Word files only. Max 2MB.</div>
                    </div>

                    <label for="references" class="form-label">References</label>
                    <textarea id="references" name="references" rows="3" placeholder="Please provide 2-3 professional references (name, relationship, phone number)" class="form-input"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary form-submit-btn">Submit Application</button>
            </form>
        </div>
    </div>
</section>
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
                        <a href="#" id="footer-contact-link">Contact</a>
                    </nav>
                </div>
                <!-- footer preferences removed: theme now follows system preference only -->
            </div>
        </div>
    </footer>

    <!-- External JavaScript File -->
    <script>
    // Accessibility helpers:
    // 1) Mark the current nav link with aria-current when the user scrolls to a section.
    // 2) Briefly toggle aria-pressed on map action buttons when clicked to provide
    //    assistive feedback; they remain links (open in new tab) so we don't change default behavior.
    (function(){
        try {
            // aria-current for nav links based on scroll position
            var navLinks = document.querySelectorAll('.nav-link');
            var sections = Array.from(navLinks).map(function(a){
                var href = a.getAttribute('href') || ''; if (!href.startsWith('#')) return null; return document.querySelector(href);
            });
            function updateCurrent(){
                var top = window.scrollY + 96; // header offset
                for (var i=0;i<navLinks.length;i++){
                    var a = navLinks[i]; var sec = sections[i];
                    if (!sec) { a.removeAttribute('aria-current'); continue; }
                    var rect = sec.getBoundingClientRect();
                    var inView = (rect.top + window.scrollY) <= top && (rect.bottom + window.scrollY) > top;
                    if (inView) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
                }
            }
            window.addEventListener('scroll', updateCurrent, {passive:true});
            window.addEventListener('resize', updateCurrent);
            updateCurrent();

            // aria-pressed toggling for map buttons
            var mapBtns = document.querySelectorAll('.map-links a[aria-pressed]');
            mapBtns.forEach(function(b){
                b.addEventListener('click', function(){
                    try { b.setAttribute('aria-pressed','true'); } catch(e){}
                    // revert after a short delay so assistive tech notices the change
                    setTimeout(function(){ try { b.setAttribute('aria-pressed','false'); } catch(e){} }, 1200);
                });
            });

            // Reveal map action buttons with a subtle animation once the iframe has loaded
            var mapLinks = document.querySelector('.map-links');
            if (mapLinks) {
                var iframe = document.querySelector('.about-map');
                var reveal = function(){ mapLinks.classList.add('visible'); };
                if (iframe) {
                    if (iframe.addEventListener) iframe.addEventListener('load', reveal); else iframe.onload = reveal;
                    // fallback reveal in case the load event doesn't fire
                    setTimeout(reveal, 1200);
                } else {
                    // no iframe found, reveal immediately
                    reveal();
                }
            }

            // contact modal elements (safely resolve; modal may be absent on some pages)
            var modal = document.getElementById('contact-modal');
            var footerLink = document.getElementById('footer-contact-link');
            var form = document.getElementById('footer-contact-form');
            var backdrop = modal ? (modal.querySelector('.modal-backdrop') || document.getElementById('contact-modal-backdrop')) : document.getElementById('contact-modal-backdrop');
            var closeBtn = modal ? modal.querySelector('.modal-close') : null;

    var removeFocusTrap = null;
    function openModal(e){
        if (e && e.preventDefault) e.preventDefault();
        // determine opener element (either event target or element passed directly)
        var opener = (e && e.currentTarget) ? e.currentTarget : (e && e.target) ? e.target : null;
        if (e && e.dataset && e.dataset.contactMessage) opener = e;
        modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.style.overflow='hidden';
        // prefill message if opener carries a data-contact-message
        try {
            var msgNode = form.querySelector('[name="message"]');
            if (opener && opener.dataset && opener.dataset.contactMessage && msgNode) {
                msgNode.value = opener.dataset.contactMessage;
                msgNode.focus();
            } else {
                var first = form.querySelector('[name="first_name"]'); if (first) first.focus();
            }
        } catch(e) { var first = form.querySelector('[name="first_name"]'); if (first) first.focus(); }
        removeFocusTrap = trapFocus(modal);
    }
    function closeModal(){ modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.style.overflow=''; if (typeof removeFocusTrap === 'function') { removeFocusTrap(); removeFocusTrap = null; } }

    if (footerLink) footerLink.addEventListener('click', openModal);
    // Also attach to any elements that should open the contact modal (e.g., Learn More button)
    var extraOpeners = document.querySelectorAll('.open-contact');
    extraOpeners.forEach(function(el){ if (el !== footerLink) el.addEventListener('click', openModal); });
    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

        // When the FormHandler shows success message, close the modal automatically
        var observer = new MutationObserver(function(m){
            m.forEach(function(rec){
                if (rec.addedNodes && rec.addedNodes.length) {
                    rec.addedNodes.forEach(function(n){
                        if (n.classList && n.classList.contains('form-success')) {
                            // close the modal after a short delay so user sees the message
                            setTimeout(closeModal, 900);
                        }
                    });
                }
            });
        });
        observer.observe(form.parentNode, { childList: true });

        // Ensure modal form uses the same submission endpoint as other forms
        form.setAttribute('action', '/contact.php');
        // Let FormHandler intercept submission (no data-no-ajax attribute)

        // Move modal styles into assets/css/styles.css; implement focus trap for accessibility
        function trapFocus(modalEl) {
            var focusableSelectors = 'a[href], area[href], input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])';
            var focusable = Array.from(modalEl.querySelectorAll(focusableSelectors)).filter(function(el){ return el.offsetParent !== null; });
            if (!focusable.length) return function(){};
            var first = focusable[0]; var last = focusable[focusable.length - 1];
            function keyHandler(e) {
                if (e.key === 'Tab') {
                    if (e.shiftKey) { // shift + tab
                        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                    } else { // tab
                        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
                    }
                }
                if (e.key === 'Escape') { closeModal(); }
            }
            document.addEventListener('keydown', keyHandler);
            return function remove() { document.removeEventListener('keydown', keyHandler); };
        } catch(e) { /* non-fatal */ }
    })();
        // Hours modal: show business hours from content.json with a safe fallback
        (function(){
                var hoursLink = document.getElementById('footer-hours-link');
                if (!hoursLink) return;
                var hoursData = <?php echo json_encode($content['hours'] ?? new stdClass()); ?>;

                var modal = null, backdrop = null, closeBtn = null, popupWin = null;

                function buildModal() {
                    try {
                        var html = ''+
                        '<div id="hours-modal" class="modal" role="dialog" aria-hidden="true" aria-labelledby="hours-modal-title">'+
                            '<div class="modal-backdrop" id="hours-modal-backdrop"></div>'+
                            '<div class="modal-panel" role="document">'+
                                '<button type="button" class="modal-close" aria-label="Close hours">✕</button>'+
                                '<h2 id="hours-modal-title">Hours</h2>'+
                                '<div class="card">'+
                                    '<div class="hours-list">';
                        var daysMap = {0:'sunday',1:'monday',2:'tuesday',3:'wednesday',4:'thursday',5:'friday',6:'saturday'};
                        var todayKey = daysMap[new Date().getDay()];
                        for (var d in hoursData) {
                            if (!hoursData.hasOwnProperty(d)) continue;
                            var isToday = (d.toLowerCase() === todayKey);
                            var cls = isToday ? 'hours-today' : '';
                            var badge = isToday ? ' <span class="today-badge">Today</span>' : '';
                            html += '<div class="'+cls+'" style="display:flex;justify-content:space-between;padding:.25rem 0"><div style="text-transform:capitalize">'+d.replace(/_/g,' ') + badge +'</div><div>'+hoursData[d]+'</div></div>';
                        }
                        html +=      '</div>'+
                                '</div>'+
                            '</div>'+
                        '</div>';
                        var wrap = document.createElement('div'); wrap.innerHTML = html;
                        document.body.appendChild(wrap.firstChild);
                        modal = document.getElementById('hours-modal');
                        backdrop = document.getElementById('hours-modal-backdrop');
                        closeBtn = modal ? modal.querySelector('.modal-close') : null;
                        return !!modal;
                    } catch (e) {
                        return false;
                    }
                }

                function openPopupFallback() {
                    try {
                        var winOpts = 'width=420,height=520,toolbar=no,menubar=no,location=no,status=no,resizable=yes,scrollbars=yes';
                        popupWin = window.open('', 'BusinessHours', winOpts);
                        if (!popupWin) { alert(formatHoursPlain()); return; }
                        var doc = popupWin.document;
                        doc.open();
                        doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Hours</title>');
                        doc.write('<meta name="viewport" content="width=device-width,initial-scale=1">');
                        doc.write('<style>body{font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:1rem;color:#0f172a}h1{font-size:1.25rem;margin:0 0 .5rem} .hours-list div{display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px dashed #e6eef8} .today{background:rgba(245,158,11,0.06);padding:.35rem;border-radius:6px;font-weight:700}</style>');
                        doc.write('</head><body>');
                        doc.write('<h1>Hours</h1>');
                        doc.write('<div class="hours-list">');
                        var daysMap = {0:'sunday',1:'monday',2:'tuesday',3:'wednesday',4:'thursday',5:'friday',6:'saturday'};
                        var todayKey = daysMap[new Date().getDay()];
                        for (var d in hoursData) {
                            if (!hoursData.hasOwnProperty(d)) continue;
                            var isToday = (d.toLowerCase() === todayKey);
                            var cls = isToday ? 'today' : '';
                            doc.write('<div class="'+cls+'"><div style="text-transform:capitalize">'+d.replace(/_/g,' ')+(isToday? ' <strong>Today</strong>':'')+'</div><div>'+hoursData[d]+'</div></div>');
                        }
                        doc.write('</div>');
                        doc.write('</body></html>');
                        doc.close();
                        popupWin.focus();
                    } catch (e) {
                        // final fallback: simple alert
                        alert(formatHoursPlain());
                    }
                }

                function formatHoursPlain() {
                    var out = '';
                    for (var d in hoursData) { if (!hoursData.hasOwnProperty(d)) continue; out += d.replace(/_/g,' ') + ': ' + hoursData[d] + '\n'; }
                    return out || 'No hours available';
                }

                function openModal() {
                    if (!modal) {
                        if (!buildModal()) { openPopupFallback(); return; }
                        // attach handlers
                        if (backdrop) backdrop.addEventListener('click', closeModal);
                        if (closeBtn) closeBtn.addEventListener('click', closeModal);
                    }
                    modal.setAttribute('aria-hidden','false'); modal.classList.add('open'); document.body.style.overflow='hidden';
                    // focus the close button for keyboard users
                    try { if (closeBtn) closeBtn.focus(); } catch(e){}
                }

                function closeModal() {
                    if (modal) { modal.setAttribute('aria-hidden','true'); modal.classList.remove('open'); document.body.style.overflow=''; }
                    if (popupWin && !popupWin.closed) { try { popupWin.close(); } catch(e){} popupWin = null; }
                }

                hoursLink.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
        })();
        // Toggle fixed footer only when its content fits the configured height
        (function(){
            function updateFooterFixed() {
                var footer = document.querySelector('.footer');
                if (!footer) return;
                // compute required height for footer content
                footer.classList.remove('footer--fixed');
                document.body.classList.remove('footer-has-fixed-footer');
                var required = footer.scrollHeight;
                // read CSS var --footer-height (fallback to 96)
                var cssVal = getComputedStyle(document.documentElement).getPropertyValue('--footer-height') || '96px';
                var footerHeight = parseInt(cssVal, 10) || 96;
                if (required <= footerHeight) {
                    footer.classList.add('footer--fixed');
                    document.body.classList.add('footer-has-fixed-footer');
                } else {
                    footer.classList.remove('footer--fixed');
                    document.body.classList.remove('footer-has-fixed-footer');
                }
            }
            window.addEventListener('load', updateFooterFixed);
            window.addEventListener('resize', function(){ setTimeout(updateFooterFixed, 120); });
            // also attempt after fonts/images load
            window.addEventListener('DOMContentLoaded', function(){ setTimeout(updateFooterFixed, 200); });
        })();
        // Reservation guests advisory: show note for large parties and prevent very large parties
        (function(){
            var guests = document.getElementById('res-guests');
            var note = document.getElementById('res-guests-note');
            var err = document.getElementById('res-guests-error');
            var form = document.getElementById('reservation-form');
            if (!guests || !form) return;
            function update(){
                var v = parseInt(guests.value, 10) || 0;
                if (v >= 8 && v <= 50) { note.style.display = 'block'; err.style.display = 'none'; }
                else if (v > 50) { note.style.display = 'none'; err.style.display = 'block'; }
                else { note.style.display = 'none'; err.style.display = 'none'; }
            }
            guests.addEventListener('input', update);
            form.addEventListener('submit', function(e){
                var v = parseInt(guests.value, 10) || 0;
                if (v > 50) {
                    e.preventDefault();
                    guests.focus();
                    update();
                    return false;
                }
            });
        })();
    </script>
</body>
</html>