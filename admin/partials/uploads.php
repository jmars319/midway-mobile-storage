<?php
// Central small include to expose a PHP-friendly uploads base and helper
// for admin templates. This wraps the functions in config.php but provides
// a short helper name useful in templates.
if (!function_exists('admin_uploads_base')) {
    // config.php should define this; if not, provide a sensible fallback.
    function admin_uploads_base() { return '../uploads/images/'; }
}

// Provide a template-friendly variable and helper function.
if (!isset($ADMIN_UPLOADS_BASE)) {
    $ADMIN_UPLOADS_base_tmp = admin_uploads_base();
    // normalize to a string
    $ADMIN_UPLOADS_BASE = (string)$ADMIN_UPLOADS_base_tmp;
}

if (!function_exists('admin_image_src')) {
    function admin_image_src($relativePath) {
        // prefer the existing helper if available
        if (function_exists('admin_upload_url')) return admin_upload_url($relativePath);
        $rel = ltrim((string)$relativePath, '/');
        return admin_uploads_base() . $rel;
    }
}

?>
