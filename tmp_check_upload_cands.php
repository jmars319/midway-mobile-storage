<?php
chdir('admin');
require 'config.php';
$candidates = [
    'UPLOAD_DIR' => (defined('UPLOAD_DIR')?UPLOAD_DIR:null),
    'cand1' => __DIR__ . '/../uploads/images/',
    'cand2' => dirname(__DIR__) . '/uploads/images/',
    'cand3' => __DIR__ . '/uploads/images/'
];
foreach ($candidates as $k=>$v) {
    echo $k.': '.var_export($v,true)." exists:".(is_dir($v)?'yes':'no')."\n";
}
