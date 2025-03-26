<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    $current_folder = isset($_GET['current']) ? $_GET['current'] : null;
    $stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE user_id = ? AND id != ?");
    $stmt->execute([$_SESSION['user_id'], $current_folder]);
    $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($folders);
} catch (Exception $e) {
    echo json_encode([]);
}
?>