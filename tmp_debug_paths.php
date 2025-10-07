<?php
require 'admin/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
echo "UPLOAD_DIR constant: "; var_export(defined('UPLOAD_DIR') ? UPLOAD_DIR : null); echo PHP_EOL;
echo "realpath(UPLOAD_DIR): "; var_export(defined('UPLOAD_DIR') ? realpath(UPLOAD_DIR) : null); echo PHP_EOL;
echo "fallback realpath: "; var_export(realpath(__DIR__ . '/uploads/images/')); echo PHP_EOL;
echo "fallback realpath 2: "; var_export(realpath(__DIR__ . '/../uploads/images/')); echo PHP_EOL;
echo "CWD: ".getcwd().PHP_EOL;
