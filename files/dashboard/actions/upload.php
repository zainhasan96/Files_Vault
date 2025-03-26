<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (empty($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $uploaded_files = [];

    foreach ($_FILES['files']['tmp_name'] as $index => $tmp_name) {
        if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $original_name = basename($_FILES['files']['name'][$index]);
        $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $file_type = $_FILES['files']['type'][$index];
        $file_size = $_FILES['files']['size'][$index];
        
        $encrypted_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
        
        $type_dir = 'others/';
        if (strpos($file_type, 'image/') === 0) {
            $type_dir = 'images/';
        } elseif (strpos($file_type, 'video/') === 0) {
            $type_dir = 'videos/';
        } elseif (strpos($file_type, 'application/pdf') === 0 || strpos($file_type, 'text/') === 0) {
            $type_dir = 'documents/';
        }
        
        $target_dir = STORAGE_PATH . $type_dir;
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $target_path = $target_dir . $encrypted_name;
        $folder_id = null;
        if (isset($_POST['folder_id']) && $_POST['folder_id'] !== 'null') {
            $folder_id = (int)$_POST['folder_id'];
            
            $stmt = $db->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
            $stmt->execute([$folder_id, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Folder does not exist or you do not have permission');
            }
        }
        if (move_uploaded_file($tmp_name, $target_path)) {
            $stmt = $db->prepare("
                INSERT INTO files 
                (user_id, original_name, encrypted_name, file_type, file_size, file_path, folder_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $original_name,
                $encrypted_name,
                $file_type,
                $file_size,
                $type_dir . $encrypted_name,
                $folder_id
            ]);
            
            $uploaded_files[] = $original_name;
        }
    }
    
    if (count($uploaded_files) > 0) {
        $response = [
            'success' => true,
            'message' => count($uploaded_files) . ' files uploaded successfully',
            'files' => $uploaded_files
        ];
    } else {
        throw new Exception('No files were uploaded');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>