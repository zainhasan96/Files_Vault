<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    $token = bin2hex(random_bytes(16));
    $password = !empty($input['password']) ? password_hash($input['password'], PASSWORD_DEFAULT) : null;
    
    $stmt = $db->prepare("
        INSERT INTO shared_links 
        (file_id, folder_id, token, password, expiry_date, download_limit) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $input['file_id'] ?? null,
        $input['folder_id'] ?? null,
        $token,
        $password,
        $input['expiry_date'] ?? null,
        $input['download_limit'] ?? null
    ]);
    
    $response = [
        'success' => true,
        'share_url' => SITE_URL . "share.php?t=$token"
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>