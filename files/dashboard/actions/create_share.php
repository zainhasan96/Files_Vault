<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['file_id']) && empty($data['folder_id'])) {
        throw new Exception('No file or folder specified');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    if (!empty($data['file_id'])) {
        $stmt = $db->prepare("SELECT id FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['file_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('File not found or access denied');
        }
    } else {
        $stmt = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['folder_id'], $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Folder not found or access denied');
        }
    }
    
    $token = bin2hex(random_bytes(16));
    
    $stmt = $db->prepare("
        INSERT INTO shared_links 
        (file_id, folder_id, token, password, expiry_date, download_limit, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        !empty($data['file_id']) ? $data['file_id'] : null,
        !empty($data['folder_id']) ? $data['folder_id'] : null,
        $token,
        !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
        !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        !empty($data['download_limit']) ? $data['download_limit'] : null,
        $_SESSION['user_id']
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Share link created successfully',
        'share_url' => SITE_URL . 'share.php?t=' . $token
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>