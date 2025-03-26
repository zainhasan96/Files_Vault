<?php
define('ACCESS_CHECK', true);
require_once '../../../private/config.php';
require_once '../../../private/auth_check.php';
header('Content-Type: application/json');
file_put_contents('bulk_action_log.txt', print_r([
    'time' => date('Y-m-d H:i:s'),
    'input' => file_get_contents('php://input'),
    'session' => $_SESSION,
    'post' => $_POST,
    'get' => $_GET
], true), FILE_APPEND);
$response = ['success' => false, 'message' => ''];
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['action'], $input['items'])) {
        throw new Exception('Invalid request format');
    }

    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->beginTransaction();

    $processedItems = 0;
    $target_folder = isset($input['target_folder']) && $input['target_folder'] !== 'null' ? 
        (int)$input['target_folder'] : 
        null;

    foreach ($input['items'] as $item) {
        if (!isset($item['id'], $item['type'])) {
            continue;
        }

        $id = (int)$item['id'];
        $type = $item['type'];

        if ($type === 'file') {
            $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $file = $stmt->fetch();

            if (!$file) continue;

            if ($input['action'] === 'move') {
                $stmt = $db->prepare("UPDATE files SET folder_id = ? WHERE id = ?");
                $stmt->execute([$target_folder, $id]);
                $processedItems++;
            } 
            elseif ($input['action'] === 'copy') {
                $processedItems++;
            }

        } elseif ($type === 'folder') {
            $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $folder = $stmt->fetch();

            if (!$folder) continue;

            if ($input['action'] === 'move') {
                if ($target_folder == $id || isFolderDescendant($db, $id, $target_folder, $_SESSION['user_id'])) {
                    throw new Exception("Cannot move folder into itself or its subfolders");
                }

                $stmt = $db->prepare("UPDATE folders SET parent_id = ? WHERE id = ?");
                $stmt->execute([$target_folder, $id]);
                $processedItems++;
            } 
            elseif ($input['action'] === 'copy') {
                $processedItems++;
            }
        }
    }

    $db->commit();
    $response = [
        'success' => true,
        'message' => "Successfully processed {$processedItems} items"
    ];

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
function isFolderDescendant($db, $parent_id, $child_id, $user_id) {
    if ($parent_id == $child_id) return true;
    
    $current_id = $child_id;
    $depth = 0;
    $max_depth = 10;
    
    while ($depth < $max_depth) {
        $stmt = $db->prepare("SELECT parent_id FROM folders WHERE id = ? AND user_id = ?");
        $stmt->execute([$current_id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || $row['parent_id'] === null) return false;
        if ($row['parent_id'] == $parent_id) return true;
        
        $current_id = $row['parent_id'];
        $depth++;
    }
    
    return false;
}
function deleteFolderRecursive($db, $folder_id, $user_id) {
    $stmt = $db->prepare("SELECT id, file_path FROM files WHERE folder_id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        $file_path = STORAGE_PATH . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$file['id']]);
    }
    
    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subfolders as $subfolder) {
        deleteFolderRecursive($db, $subfolder['id'], $user_id);
    }
    
    $stmt = $db->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
}

function copyFolderRecursive($db, $folder_id, $target_folder_id, $user_id) {
    $stmt = $db->prepare("SELECT name FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) return;
    $stmt = $db->prepare("INSERT INTO folders (user_id, name, parent_id) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, 'Copy of ' . $folder['name'], $target_folder_id]);
    $new_folder_id = $db->lastInsertId();
    $stmt = $db->prepare("SELECT * FROM files WHERE folder_id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        $file_ext = pathinfo($file['file_path'], PATHINFO_EXTENSION);
        $new_encrypted_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
        
        $new_file_path = dirname($file['file_path']) . '/' . $new_encrypted_name;
        $full_old_path = STORAGE_PATH . $file['file_path'];
        $full_new_path = STORAGE_PATH . $new_file_path;
        
        if (file_exists($full_old_path)) {
            if (!copy($full_old_path, $full_new_path)) {
                throw new Exception('Failed to copy file');
            }
            $stmt = $db->prepare("
                INSERT INTO files 
                (user_id, original_name, encrypted_name, file_type, file_size, file_path, folder_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                'Copy of ' . $file['original_name'],
                $new_encrypted_name,
                $file['file_type'],
                $file['file_size'],
                $new_file_path,
                $new_folder_id
            ]);
        }
    }
    $stmt = $db->prepare("SELECT id FROM folders WHERE parent_id = ? AND user_id = ?");
    $stmt->execute([$folder_id, $user_id]);
    $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subfolders as $subfolder) {
        copyFolderRecursive($db, $subfolder['id'], $new_folder_id, $user_id);
    }
}
function isDescendant($db, $parent_id, $child_id, $user_id) {
    if ($child_id === null) return false;
    if ($parent_id == $child_id) return true;
    
    $stmt = $db->prepare("SELECT parent_id FROM folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$child_id, $user_id]);
    $folder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$folder) return false;
    
    return isDescendant($db, $parent_id, $folder['parent_id'], $user_id);
}