<?php
// منع الوصول المباشر
if (!defined('ACCESS_CHECK')) {
    die('Direct access not allowed!');
}
define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_NAME', '');
define('SITE_URL', 'https://zain-hasan.ru/files/');
define('STORAGE_PATH', __DIR__ . '/storage/');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

?>
