<?php
// includes/modal-close.php
// Usage: set $modal_close_label before including, e.g. $modal_close_label = 'Close contact';
if (!isset($modal_close_label)) { $modal_close_label = 'Close'; }
?>
<button type="button" class="modal-close" aria-label="<?php echo htmlspecialchars($modal_close_label, ENT_QUOTES, 'UTF-8'); ?>">
    <svg class="icon-close" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M6 6 L18 18 M6 18 L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </svg>
</button>
