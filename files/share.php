<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';

$error = $file_info = $folder_content = $requires_password = false;
$current_folder_id = null;

try {
    if (!isset($_GET['t'])) {
        throw new Exception('Invalid share link');
    }
    
    $token = $_GET['t'];
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $db->prepare("
        SELECT sl.*, 
               f.original_name, f.file_type, f.file_path,
               fl.name as folder_name, fl.id as folder_id
        FROM shared_links sl
        LEFT JOIN files f ON sl.file_id = f.id
        LEFT JOIN folders fl ON sl.folder_id = fl.id
        WHERE sl.token = ?
    ");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        throw new Exception('Share link not found');
    }
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        throw new Exception('This share link has expired');
    }
    if ($share['password'] && !isset($_SESSION['share_authenticated'][$token])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            if (password_verify($_POST['password'], $share['password'])) {
                $_SESSION['share_authenticated'][$token] = true;
            } else {
                throw new Exception('Incorrect password');
            }
        } else {
            $requires_password = true;
        }
    }
    if (!$requires_password) {
        if ($share['file_id']) {
            $file_info = [
                'type' => 'file',
                'name' => $share['original_name'],
                'path' => STORAGE_PATH . $share['file_path'],
                'mime_type' => $share['file_type']
            ];
        } else {
            $file_info = [
                'type' => 'folder',
                'name' => $share['folder_name'],
                'id' => $share['folder_id']
            ];
            if (isset($_GET['folder']) && $_GET['folder'] == $share['folder_id']) {
                $current_folder_id = $share['folder_id'];
                $stmt = $db->prepare("
                    SELECT id, name 
                    FROM folders 
                    WHERE parent_id = ? AND user_id = (
                        SELECT user_id FROM shared_links WHERE token = ?
                    )
                ");
                $stmt->execute([$current_folder_id, $token]);
                $subfolders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt = $db->prepare("
                    SELECT id, original_name, file_type, file_size 
                    FROM files 
                    WHERE folder_id = ? AND user_id = (
                        SELECT user_id FROM shared_links WHERE token = ?
                    )
                ");
                $stmt->execute([$current_folder_id, $token]);
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $folder_content = [
                    'folders' => $subfolders,
                    'files' => $files
                ];
            }
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared File | File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2ecc71;
            --primary-dark: #27ae60;
        }
        
        body {
            font-family: 'Quicksand', sans-serif;
            background: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .share-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            padding: 40px;
            text-align: center;
        }
        
        .file-icon {
            font-size: 5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .file-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 30px;
            word-break: break-word;
        }
        
        .action-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .password-form {
            margin-top: 30px;
        }
        
        .password-input {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
            margin-bottom: 15px;
        }
        
        .error-message {
            color: #e74c3c;
            margin: 20px 0;
        }
        .folder-view {
            margin-top: 30px;
            text-align: left;
        }
        
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .folder-item, .file-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .folder-item:hover, .file-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .item-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .item-name {
            word-break: break-word;
            font-size: 0.9rem;
        }
        
        .file-size {
            color: #777;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 5px;
        }
        
        .breadcrumb-separator {
            color: #777;
        }
    </style>
</head>
<body>
    <div class="share-container">
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            
        <?php elseif ($requires_password): ?>
            <div class="file-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h2>This share is password protected</h2>
            
            <form method="POST" class="password-form">
                <input type="password" name="password" class="password-input" placeholder="Enter password" required>
                <button type="submit" class="action-btn">
                    <i class="fas fa-unlock"></i> Unlock
                </button>
            </form>
            
        <?php elseif ($file_info): ?>
            <div class="file-icon">
                <?php if ($file_info['type'] === 'file'): ?>
                    <?php
                    $icon = 'fa-file';
                    if (strpos($file_info['mime_type'], 'image/') === 0) $icon = 'fa-file-image';
                    elseif (strpos($file_info['mime_type'], 'video/') === 0) $icon = 'fa-file-video';
                    elseif (strpos($file_info['mime_type'], 'application/pdf') === 0) $icon = 'fa-file-pdf';
                    ?>
                    <i class="fas <?= $icon ?>"></i>
                <?php else: ?>
                    <i class="fas fa-folder"></i>
                <?php endif; ?>
            </div>
            
            <div class="file-name">
                <?= htmlspecialchars($file_info['name']) ?>
            </div>
            
            <?php if ($file_info['type'] === 'file'): ?>
                <a href="download.php?t=<?= htmlspecialchars($token) ?>" class="action-btn">
                    <i class="fas fa-download"></i> Download
                </a>
                
                <?php if (strpos($file_info['mime_type'], 'image/') === 0 || 
                          strpos($file_info['mime_type'], 'application/pdf') === 0): ?>
                    <a href="preview.php?t=<?= htmlspecialchars($token) ?>" class="action-btn">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                <?php endif; ?>
                <?php else: ?>
                <a href="share.php?t=<?= htmlspecialchars($token) ?>&folder=<?= $file_info['id'] ?>" class="action-btn">
                    <i class="fas fa-folder-open"></i> Open Folder
                </a>
                <a href="download_folder.php?t=<?= htmlspecialchars($token) ?>" class="action-btn">
                    <i class="fas fa-file-archive"></i> Download as ZIP
                </a>
                
                <?php if ($folder_content): ?>
                    <div class="folder-view">
                        <div class="breadcrumb">
                            <a href="share.php?t=<?= htmlspecialchars($token) ?>">
                                <i class="fas fa-home"></i> <?= htmlspecialchars($file_info['name']) ?>
                            </a>
                        </div>
                        
                        <div class="folder-grid">
                            <?php foreach ($folder_content['folders'] as $folder): ?>
                                <div class="folder-item" 
                                     data-id="<?= $folder['id'] ?>" 
                                     ondblclick="window.location.href='share.php?t=<?= htmlspecialchars($token) ?>&folder=<?= $folder['id'] ?>'">
                                    <div class="item-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <div class="item-name"><?= htmlspecialchars($folder['name']) ?></div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php foreach ($folder_content['files'] as $file): ?>
                                <div class="file-item" 
                                     data-id="<?= $file['id'] ?>" 
                                     data-type="<?= htmlspecialchars($file['file_type']) ?>"
                                     ondblclick="previewFile('<?= $file['id'] ?>', '<?= htmlspecialchars($file['original_name']) ?>', '<?= htmlspecialchars($file['file_type']) ?>')">
                                    <div class="item-icon">
                                        <?php
                                        $icon = 'fa-file';
                                        if (strpos($file['file_type'], 'image/') === 0) $icon = 'fa-file-image';
                                        elseif (strpos($file['file_type'], 'video/') === 0) $icon = 'fa-file-video';
                                        elseif (strpos($file['file_type'], 'application/pdf') === 0) $icon = 'fa-file-pdf';
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div class="item-name"><?= htmlspecialchars($file['original_name']) ?></div>
                                    <div class="file-size"><?= formatFileSize($file['file_size']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>


    <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 2000; justify-content: center; align-items: center;">



    <script>
    function previewFile(fileId, fileName, fileType) {
        const previewModal = document.getElementById('previewModal');
        const previewContent = document.getElementById('previewContent');
        const previewHeader = document.getElementById('previewHeader');
        
        previewHeader.textContent = fileName;
        previewModal.style.display = 'flex';
        
        if (fileType.startsWith('image/')) {
            previewContent.innerHTML = `
                <img src="download.php?t=<?= htmlspecialchars($token) ?>&file_id=${fileId}" 
                     style="max-width: 100%; max-height: 70vh; border-radius: 5px;">
            `;
        } else if (fileType === 'application/pdf') {
            previewContent.innerHTML = `
                <iframe src="download.php?t=<?= htmlspecialchars($token) ?>&file_id=${fileId}" 
                        style="width: 100%; height: 70vh; border: none;"></iframe>
            `;
        } else {
            previewContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: white;">
                    <i class="fas fa-file" style="font-size: 3rem;"></i>
                    <h3>Preview not available</h3>
                    <a href="download.php?t=<?= htmlspecialchars($token) ?>&file_id=${fileId}" 
                       class="action-btn" 
                       style="display: inline-block; margin-top: 20px;">
                       <i class="fas fa-download"></i> Download File
                    </a>
                </div>
            `;
        }
    }
    
    document.getElementById('closePreview')?.addEventListener('click', function() {
        document.getElementById('previewModal').style.display = 'none';
    });
    </script>
</body>
</html>