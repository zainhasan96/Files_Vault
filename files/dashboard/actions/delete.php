<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'], $input['type'])) {
        throw new Exception('Invalid request');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();

    $itemId = (int)$input['id'];
    $userId = $_SESSION['user_id'];
    $itemType = $input['type'];

    if ($itemType === 'file') {
        $stmt = $db->prepare("SELECT file_path FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new Exception('File not found or you do not have permission');
        }

        $filePath = STORAGE_PATH . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $stmt = $db->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);

    } elseif ($itemType === 'folder') {
        deleteFolderContents($db, $itemId, $userId);

        $stmt = $db->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $userId]);
    } else {
        throw new Exception('Invalid item type');
    }

    $db->commit();
    $response = [
        'success' => true,
        'message' => ucfirst($itemType) . ' deleted successfully'
    ];

} catch (PDOException $e) {
    $db->rollBack();
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);

function deleteFolderContents($db, $folderId, $userId) {
    $stmt = $db->prepare("SELECT id, file_path FROM files WHERE folder_id = ? AND user_id = ?");
    $stmt->execute([$folderId, $userId]);
    $files = $stmt->fetchAll();

    foreach ($files as $file) {
        $filePath = STORAGE_PATH . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$file['id']]);
    }

    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ? AND user_id = ?");
    $stmt->execute([$folderId, $userId]);
    $subfolders = $stmt->fetchAll();

    foreach ($subfolders as $subfolder) {
        deleteFolderContents($db, $subfolder['id'], $userId);
        $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
        $stmt->execute([$subfolder['id']]);
    }
}