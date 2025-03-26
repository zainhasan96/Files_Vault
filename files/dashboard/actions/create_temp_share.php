<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['file_id'])) {
        throw new Exception('Invalid request');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $db->prepare("SELECT user_id FROM files WHERE id = ?");
    $stmt->execute([$input['file_id']]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found');
    }
    
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }

    $token = bin2hex(random_bytes(16));
    
    $stmt = $db->prepare("
        INSERT INTO shared_links 
        (file_id, token, expiry_date, is_preview, user_id) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 1, ?)
    ");
    $stmt->execute([
        $input['file_id'],
        $token,
        $user_id 
    ]);
    
    $response = [
        'success' => true,
        'token' => $token,
        'message' => 'Temporary preview link created'
    ];
    
} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>