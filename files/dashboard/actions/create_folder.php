<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        throw new Exception('Folder name is required');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    $path = $data['name'];
    if (!empty($data['parent_id'])) {
        $stmt = $db->prepare("SELECT path FROM folders WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['parent_id'], $_SESSION['user_id']]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$parent) {
            throw new Exception('Parent folder not found');
        }
        
        $path = $parent['path'] . '/' . $data['name'];
    }

    $stmt = $db->prepare("
        INSERT INTO folders (name, parent_id, path, user_id) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['name'],
        !empty($data['parent_id']) ? $data['parent_id'] : null,
        $path,
        $_SESSION['user_id']
    ]);
    
    $response = [
        'success' => true,
        'message' => 'Folder created successfully',
        'folder_id' => $db->lastInsertId()
    ];
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>