<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';

try {
    if (!isset($_GET['t']) && !isset($_GET['file_id'])) {
        throw new Exception('Invalid download request');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    if (isset($_GET['t'])) {
        $token = $_GET['t'];
        $stmt = $db->prepare("
            SELECT sl.*, f.original_name, f.file_path, f.file_type 
            FROM shared_links sl
            JOIN files f ON sl.file_id = f.id
            WHERE sl.token = ?
        ");
        $stmt->execute([$token]);
        $share = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$share) {
            throw new Exception('File not found');
        }
        
        if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
            throw new Exception('This share link has expired');
        }
        
        if ($share['download_limit'] && $share['download_count'] >= $share['download_limit']) {
            throw new Exception('Download limit reached');
        }
        
        if ($share['password'] && !isset($_SESSION['share_authenticated'][$token])) {
            header('Location: share.php?t=' . $token);
            exit;
        }
        
        $file_path = STORAGE_PATH . $share['file_path'];
        $file_name = $share['original_name'];
        
    } else {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('Authentication required');
        }
        $file_id = $_GET['file_id'];
        $stmt = $db->prepare("
            SELECT original_name, file_path 
            FROM files 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$file_id, $_SESSION['user_id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            throw new Exception('File not found or access denied');
        }
        
        $file_path = STORAGE_PATH . $file['file_path'];
        $file_name = $file['original_name'];
    }
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found on server');
    }
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    flush();
    $file = fopen($file_path, 'rb');
    while (!feof($file)) {
        print fread($file, 1024);
        flush();
    }
    fclose($file);
    
    exit;
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>