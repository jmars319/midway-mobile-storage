<?php
// Image preview page removed — keep a lightweight responder to avoid 404s old links
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Image preview page has been removed. Use /admin/index.php for image management.\n";
exit;
