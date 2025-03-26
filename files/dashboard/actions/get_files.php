<?php
define('ACCESS_CHECK', true);
require_once __DIR__ . '/../../../private/config.php';
require_once __DIR__ . '/../../../private/auth_check.php';

ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    $current_folder = isset($_GET['folder']) && $_GET['folder'] !== 'null' ? (int)$_GET['folder'] : null;
    
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $folders_query = "SELECT * FROM folders WHERE user_id = ? AND parent_id " . 
                    ($current_folder ? "= ?" : "IS NULL");
    $folders = $db->prepare($folders_query);
    $folders->execute($current_folder ? [$_SESSION['user_id'], $current_folder] : [$_SESSION['user_id']]);
    
    $files_query = "SELECT * FROM files WHERE user_id = ? AND folder_id " . 
                  ($current_folder ? "= ?" : "IS NULL");
    $files = $db->prepare($files_query);
    $files->execute($current_folder ? [$_SESSION['user_id'], $current_folder] : [$_SESSION['user_id']]);
    
    $response = [
        'success' => true,
        'folders' => $folders->fetchAll(PDO::FETCH_ASSOC),
        'files' => $files->fetchAll(PDO::FETCH_ASSOC)
    ];
    
    die(json_encode($response));
    
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]));
}