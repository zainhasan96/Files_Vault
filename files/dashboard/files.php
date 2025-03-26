<?php
define('ACCESS_CHECK', true);
require_once '../../private/config.php';
require_once '../../private/auth_check.php';
$db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
$user_stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
$folders = $db->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_id " . ($current_folder ? "= ?" : "IS NULL"));
$folders->execute($current_folder ? [$_SESSION['user_id'], $current_folder] : [$_SESSION['user_id']]);
$folders = $folders->fetchAll();
$files = $db->prepare("SELECT * FROM files WHERE user_id = ? AND folder_id " . ($current_folder ? "= ?" : "IS NULL"));
$files->execute($current_folder ? [$_SESSION['user_id'], $current_folder] : [$_SESSION['user_id']]);
$files = $files->fetchAll();
$folder_path = [];
if ($current_folder) {
    $folder = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?");
    $folder->execute([$current_folder, $_SESSION['user_id']]);
    $current = $folder->fetch(PDO::FETCH_ASSOC);
    
    if ($current) {
        while ($current) {
            array_unshift($folder_path, $current);
            if ($current['parent_id']) {
                $parent_stmt = $db->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ?");
                $parent_stmt->execute([$current['parent_id'], $_SESSION['user_id']]);
                $current = $parent_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $current = null;
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files | File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2ecc71;
            --primary-dark: #27ae60;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Quicksand', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            background: #f8f9fa;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 20px 0;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .user-info h3 {
            font-size: 1rem;
            margin: 0;
            color: #333;
        }
        
        .user-info p {
            font-size: 0.8rem;
            margin: 0;
            color: #777;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-item {
            margin-bottom: 5px;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(46, 204, 113, 0.1);
            color: var(--primary);
        }
        
        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
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
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }
        
        .file-card, .folder-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s;
            position: relative;
        }
        
        .file-card:hover, .folder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .file-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-name {
            word-break: break-word;
            font-size: 0.9rem;
        }
        
        .file-size {
            color: #777;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        
        .file-actions {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: #555;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            color: var(--primary);
        }
        
        .new-folder-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        #folderModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
        }

        #uploadModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .upload-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 600px;
        }

        #uploadArea {
            border: 2px dashed var(--primary);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        #uploadArea:hover {
            background: rgba(46, 204, 113, 0.05);
        }

        #fileList {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 5px;
            padding: 10px;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .upload-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .file-card.selected, .folder-card.selected {
            background: rgba(46, 204, 113, 0.1);
            box-shadow: 0 0 0 2px var(--primary);
        }

        .bulk-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            gap: 10px;
        }

        .bulk-action-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .bulk-action-btn:hover {
            background: var(--primary-dark);
        }

        #moveCopyModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .folder-tree {
            max-height: 300px;
            overflow-y: auto;
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .folder-item {
            padding: 8px 0;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .folder-item:hover {
            background: #f5f5f5;
        }

        .folder-item i {
            margin-right: 10px;
            color: var(--primary);
        }
        .page-title {
            font-family: 'Pacifico', cursive;
            color: var(--primary);
            font-size: 1.8rem;
            margin: 0;
            margin-bottom: 20px;
        }

        .loading-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            z-index: 1001;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px;
            border-radius: 5px;
            color: white;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .notification.success {
            background: var(--primary);
        }

        .notification.error {
            background: #e74c3c;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .folder-card {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .folder-card:hover {
            transform: scale(1.02);
        }

        .folder-card:active {
            transform: scale(0.98);
        }
        
        .bulk-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 10px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            gap: 10px;
            z-index: 1000;
            align-items: center;
        }

        .bulk-action-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bulk-action-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .bulk-action-btn i {
            font-size: 1rem;
        }
        
        .bulk-action-btn {
            transition: transform 0.2s, background 0.2s;
        }

       
        .bulk-action-btn::after {
            content: attr(title);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
        }

        .bulk-action-btn:hover::after {
            opacity: 1;
        }
        
        #previewContent {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #previewContent img {
            max-height: 65vh;
            max-width: 100%;
            object-fit: contain;
        }

        #previewModal {
            transition: opacity 0.3s ease;
        }

        #closePreview:hover {
            transform: scale(1.1);
            background: #c0392b !important;
        }

        @media (max-width: 768px) {
            #previewModal > div {
                width: 95%;
                padding: 10px;
            }
            
            #previewContent {
                max-height: 60vh;
            }
        }
        
        .download-btn {
            display: inline-block;
            padding: 8px 15px;
            background: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .download-btn:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        #pdfPrev, #pdfNext {
            transition: all 0.3s;
            display: none;
        }

        #pdfPrev:hover, #pdfNext:hover {
            background: rgba(0,0,0,0.7) !important;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
                <div class="user-info">
                    <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                </div>
            </div>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="files.php" class="nav-link active">
                    <i class="fas fa-folder"></i>
                    <span>Files</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="shared.php" class="nav-link">
                    <i class="fas fa-share-alt"></i>
                    <span>Shared Links</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Files</h1>
            <div style="display: flex; gap: 10px;">
                <button class="new-folder-btn" id="newFolderBtn">
                    <i class="fas fa-folder-plus"></i> New Folder
                </button>
                <button class="new-folder-btn" id="uploadFileBtn" style="background: #3498db;">
                    <i class="fas fa-upload"></i> Upload File
                </button>
            </div>
        </div>
        
        <div class="breadcrumb">
            <a href="files.php"><i class="fas fa-home"></i> Home</a>
            <?php foreach ($folder_path as $folder): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="files.php?folder=<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="file-grid">
            <?php foreach ($folders as $folder): ?>
                <div class="folder-card" data-id="<?= $folder['id'] ?>" data-type="folder">
                    <div class="file-icon">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="file-name"><?= htmlspecialchars($folder['name']) ?></div>
                    <!-- <div class="file-actions">
                        <button class="action-btn open-btn" title="Open">
                            <i class="fas fa-folder-open"></i>
                        </button>
                        <button class="action-btn share-btn" title="Share">
                            <i class="fas fa-share-alt"></i>
                        </button>
                        <button class="action-btn delete-btn" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div> -->
                </div>
            <?php endforeach; ?>
            
            <?php foreach ($files as $file): ?>
                <div class="file-card" data-id="<?= $file['id'] ?>" data-type="<?= htmlspecialchars($file['file_type']) ?>">
                    <div class="file-icon">
                        <?php
                        $icon = 'fa-file';
                        if (strpos($file['file_type'], 'image/') === 0) $icon = 'fa-file-image';
                        elseif (strpos($file['file_type'], 'video/') === 0) $icon = 'fa-file-video';
                        elseif (strpos($file['file_type'], 'application/pdf') === 0) $icon = 'fa-file-pdf';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                    </div>
                    <div class="file-name"><?= htmlspecialchars($file['original_name']) ?></div>
                    <div class="file-size"><?= formatFileSize($file['file_size']) ?></div>
                    <!-- <div class="file-actions">
                        <button class="action-btn download-btn" title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="action-btn share-btn" title="Share">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div> -->
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="folderModal">
        <div class="modal-content">
            <h2 style="margin-top: 0; color: var(--primary);">Create New Folder</h2>
            <form id="folderForm">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Folder Name</label>
                    <input type="text" id="folderName" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <button type="button" id="cancelFolder" style="padding: 8px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Create</button>
                </div>
            </form>
        </div>
    </div>

    <div id="shareModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px;">
            <h2 style="margin-top: 0; color: var(--primary);">Share File</h2>
            <div id="shareForm">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password (optional)</label>
                    <input type="password" id="sharePassword" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Expiry Date (optional)</label>
                    <input type="datetime-local" id="shareExpiry" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Download Limit (optional)</label>
                    <input type="number" id="shareLimit" min="1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <button id="cancelShare" style="padding: 8px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button id="confirmShare" style="padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Create Link</button>
                </div>
            </div>
            <div id="shareResult" style="display: none; margin-top: 20px;">
                <p style="font-weight: 600;">Share link created successfully!</p>
                <div style="display: flex; margin-top: 10px;">
                    <input type="text" id="shareLink" readonly style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px 0 0 4px;">
                    <button id="copyShareLink" style="padding: 8px 15px; background: #3498db; color: white; border: none; border-radius: 0 4px 4px 0; cursor: pointer;">Copy</button>
                </div>
                <button id="closeShareModal" style="margin-top: 15px; padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; width: 100%;">Close</button>
            </div>
        </div>
    </div>

    <div id="uploadModal">
        <div class="upload-container">
            <h2 style="margin-top: 0; color: var(--primary);">Upload Files</h2>
            <div id="uploadArea">
                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;"></i>
                <p>Drag & drop files here or click to browse</p>
                <input type="file" id="fileInput" style="display: none;" multiple>
            </div>
            <div id="fileList"></div>
            <div class="upload-actions">
                <button id="cancelUpload" style="padding: 8px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="confirmUpload" style="padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Upload</button>
            </div>
        </div>
    </div>

    <div id="moveCopyModal">
        <div class="modal-content">
            <h2 style="margin-top: 0; color: var(--primary);" id="moveCopyTitle">Move to</h2>
            <div class="folder-tree" id="folderTree">
            </div>
            <div style="display: flex; justify-content: space-between;">
                <button id="cancelMoveCopy" style="padding: 8px 15px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="confirmMoveCopy" style="padding: 8px 15px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Confirm</button>
            </div>
        </div>
    </div>

    <div class="bulk-actions" id="bulkActions">
        <button class="bulk-action-btn" title="Download" id="bulkDownloadBtn">
            <i class="fas fa-download"></i>
        </button>
        <button class="bulk-action-btn" title="Share" id="bulkShareBtn">
            <i class="fas fa-share-alt"></i>
        </button>
        <button class="bulk-action-btn" title="Move" id="bulkMoveBtn">
            <i class="fas fa-arrows-alt"></i>
        </button>
        <button class="bulk-action-btn" title="Copy" id="bulkCopyBtn">
            <i class="far fa-copy"></i>
        </button>
        <button class="bulk-action-btn" title="Delete" id="bulkDeleteBtn">
            <i class="fas fa-trash"></i>
        </button>
    </div>
    
    <div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(5px);">
        <div style="background: #ffffff; border-radius: 8px; max-width: 90%; max-height: 90%; padding: 20px; position: relative; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
            <button id="closePreview" style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 16px; line-height: 30px;">×</button>
            <div id="previewHeader" style="margin-bottom: 15px; font-size: 18px; font-weight: bold; text-align: center; color: #2ecc71; font-family: 'Pacifico';"></div>
            <div id="previewContent" style="max-width: 800px; max-height: 70vh; overflow: auto; background: #ecf0f1; border-radius: 5px; padding: 10px; text-align: center;">
            </div>
        </div>
    </div>










    
    <script>
        
    let selectedItems = [];
    let currentAction = null;

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    document.querySelectorAll('.open-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const folderId = this.closest('.folder-card').dataset.id;
            window.location.href = `files.php?folder=${folderId}`;
        });
    });
    
    const folderModal = document.getElementById('folderModal');
    const newFolderBtn = document.getElementById('newFolderBtn');
    const cancelFolder = document.getElementById('cancelFolder');
    const folderForm = document.getElementById('folderForm');
    
    newFolderBtn.addEventListener('click', () => {
        folderModal.style.display = 'flex';
    });
    
    cancelFolder.addEventListener('click', () => {
        folderModal.style.display = 'none';
    });
    
    folderForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const folderName = document.getElementById('folderName').value;
        
        try {
            const response = await fetch('actions/create_folder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: folderName,
                    parent_id: <?= $current_folder ?: 'null' ?>
                })
            });
            
            const result = await response.json();
            if (result.success) {
                refreshFileList();
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to create folder');
        }
    });
    
    let lastClickTime = 0;
    let lastClickedElement = null;
    function handleDoubleClick(event, element) {
        const currentTime = new Date().getTime();
        const isDoubleClick = (currentTime - lastClickTime < 300) && (element === lastClickedElement);
        
        lastClickTime = currentTime;
        lastClickedElement = element;

        if (isDoubleClick && element.classList.contains('folder-card')) {
            const folderId = element.dataset.id;
            window.location.href = `files.php?folder=${folderId}`;
        }
    }

    document.querySelectorAll('.folder-card').forEach(folder => {
        folder.addEventListener('click', function(e) {
            if (e.target.classList.contains('action-btn')) return;
            
            handleDoubleClick(e, this);
            
            if (e.ctrlKey || e.metaKey) {
                this.classList.toggle('selected');
            } else {
                document.querySelectorAll('.file-card.selected, .folder-card.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                this.classList.add('selected');
            }
            updateSelectedItems();
        });
    });

    let currentShareItem = null;
    const shareModal = document.getElementById('shareModal');
    
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const card = this.closest('.file-card, .folder-card');
            currentShareItem = {
                id: card.dataset.id,
                isFile: card.classList.contains('file-card')
            };
            document.getElementById('shareForm').style.display = 'block';
            document.getElementById('shareResult').style.display = 'none';
            shareModal.style.display = 'flex';
        });
    });
    
    document.getElementById('cancelShare').addEventListener('click', () => {
        shareModal.style.display = 'none';
    });
    
    document.getElementById('closeShareModal').addEventListener('click', () => {
        shareModal.style.display = 'none';
    });
    
    document.getElementById('confirmShare').addEventListener('click', async () => {
        const password = document.getElementById('sharePassword').value;
        const expiry = document.getElementById('shareExpiry').value;
        const limit = document.getElementById('shareLimit').value;
        
        try {
            const response = await fetch('actions/create_share.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    [currentShareItem.isFile ? 'file_id' : 'folder_id']: currentShareItem.id,
                    password: password || undefined,
                    expiry_date: expiry || undefined,
                    download_limit: limit || undefined
                })
            });
            
            const result = await response.json();
            if (result.success) {
                document.getElementById('shareForm').style.display = 'none';
                document.getElementById('shareResult').style.display = 'block';
                document.getElementById('shareLink').value = result.share_url;
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to create share link');
        }
    });
    
    document.getElementById('copyShareLink').addEventListener('click', () => {
        const shareLink = document.getElementById('shareLink');
        shareLink.select();
        document.execCommand('copy');
        alert('Link copied to clipboard!');
    });
    
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const card = this.closest('.file-card, .folder-card');
            const isFile = card.classList.contains('file-card');
            const id = card.dataset.id;
            
            if (confirm(`Are you sure you want to delete this ${isFile ? 'file' : 'folder'}?`)) {
                try {
                    const response = await fetch('actions/delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: id,
                            type: isFile ? 'file' : 'folder'
                        })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        card.remove();
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    alert('Failed to delete item');
                }
            }
        });
    });
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    document.querySelectorAll('.download-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const fileId = this.closest('.file-card').dataset.id;
            window.open(`../download.php?file_id=${fileId}`, '_blank');
        });
    });

    document.addEventListener('click', function(e) {
        const card = e.target.closest('.file-card, .folder-card');
        if (card && !e.target.classList.contains('action-btn')) {
            if (e.ctrlKey || e.metaKey) {
                card.classList.toggle('selected');
                updateSelectedItems();
            } else {
                document.querySelectorAll('.file-card.selected, .folder-card.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                card.classList.add('selected');
                updateSelectedItems([card]);
            }
        } else if (!card && !e.target.closest('.bulk-actions') && !e.target.closest('#moveCopyModal')) {
            document.querySelectorAll('.file-card.selected, .folder-card.selected').forEach(el => {
                el.classList.remove('selected');
            });
            updateSelectedItems([]);
        }
    });
    
    function updateSelectedItems() {
        selectedItems = Array.from(document.querySelectorAll('.file-card.selected, .folder-card.selected'))
            .map(el => ({
                id: el.dataset.id,
                type: el.classList.contains('file-card') ? 'file' : 'folder'
            }));
        
        const bulkActions = document.getElementById('bulkActions');
        if (selectedItems.length > 0) {
            bulkActions.style.display = 'flex';
            
            const hasFiles = selectedItems.some(item => item.type === 'file');
            const hasFolders = selectedItems.some(item => item.type === 'folder');
            
            document.getElementById('bulkDownloadBtn').style.display = 
                (selectedItems.length === 1 && hasFiles) ? 'flex' : 'none';
            
            document.getElementById('bulkShareBtn').style.display = 
                (selectedItems.length === 1) ? 'flex' : 'none';
        } else {
            bulkActions.style.display = 'none';
        }
    }

    const uploadModal = document.getElementById('uploadModal');
    const uploadFileBtn = document.getElementById('uploadFileBtn');
    const cancelUpload = document.getElementById('cancelUpload');
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.getElementById('uploadArea');
    const fileList = document.getElementById('fileList');
    let filesToUpload = [];

    uploadFileBtn.addEventListener('click', () => {
        filesToUpload = [];
        fileList.innerHTML = '';
        uploadModal.style.display = 'flex';
    });

    cancelUpload.addEventListener('click', () => {
        uploadModal.style.display = 'none';
    });

    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', handleFileSelect);
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.background = 'rgba(46, 204, 113, 0.1)';
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.background = '';
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.background = '';
        fileInput.files = e.dataTransfer.files;
        handleFileSelect({ target: fileInput });
    });

    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        filesToUpload = files;
        
        fileList.innerHTML = files.map(file => `
            <div class="file-item">
                <span>${file.name} (${formatFileSize(file.size)})</span>
                <button class="action-btn remove-file" data-name="${file.name}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
        
        document.querySelectorAll('.remove-file').forEach(btn => {
            btn.addEventListener('click', function() {
                const fileName = this.dataset.name;
                filesToUpload = filesToUpload.filter(f => f.name !== fileName);
                this.parentElement.remove();
            });
        });
    }

    document.getElementById('confirmUpload').addEventListener('click', async () => {
        if (filesToUpload.length === 0) {
            alert('Please select files to upload');
            return;
        }
        
        const formData = new FormData();
        <?php if ($current_folder): ?>
        formData.append('folder_id', <?= $current_folder ?>);
        <?php endif; ?>
        
        filesToUpload.forEach(file => {
            formData.append('files[]', file);
        });
        
        try {
            const response = await fetch('actions/upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to upload files');
        }
    });

    document.getElementById('bulkDownloadBtn').addEventListener('click', function() {
        if (selectedItems.length === 1 && selectedItems[0].type === 'file') {
            window.open(`../download.php?file_id=${selectedItems[0].id}`, '_blank');
        } else if (selectedItems.length > 1) {
            alert('Please select only one file to download');
        } else if (selectedItems.length === 1 && selectedItems[0].type === 'folder') {
            alert('Cannot download folders directly');
        } else {
            alert('Please select a file to download');
        }
    });

    document.getElementById('bulkShareBtn').addEventListener('click', function() {
        if (selectedItems.length === 1) {
            const item = selectedItems[0];
            currentShareItem = {
                id: item.id,
                isFile: item.type === 'file'
            };
            document.getElementById('shareForm').style.display = 'block';
            document.getElementById('shareResult').style.display = 'none';
            shareModal.style.display = 'flex';
        } else {
            alert('Please select exactly one item to share');
        }
    });


    document.getElementById('bulkMoveBtn').addEventListener('click', () => {
    if (selectedItems.length === 0) return;
    currentAction = 'move';
    showFolderTree();
    });

    document.getElementById('bulkCopyBtn').addEventListener('click', () => {
        if (selectedItems.length === 0) return;
        currentAction = 'copy';
        showFolderTree();
    });

    document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
        if (selectedItems.length === 0) return;

        if (confirm(`Are you sure you want to delete ${selectedItems.length} selected items?`)) {
            selectedItems.forEach(item => {
                deleteItem(item.id, item.type);
            });
        }
    });

    async function deleteItem(itemId, itemType) {
        if (!confirm(`Are you sure you want to delete this ${itemType}?`)) {
            return;
        }

        try {
            const response = await fetch('actions/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: itemId,
                    type: itemType
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotification(`${itemType} deleted successfully`, 'success');
                document.querySelector(`.${itemType}-card[data-id="${itemId}"]`)?.remove();
                updateSelectedItems();
            } else {
                throw new Error(result.message || 'Failed to delete item');
            }
        } catch (error) {
            showNotification(`Error: ${error.message}`, 'error');
            console.error('Delete error:', error);
        }
    }

    function showFolderTree() {
    const folderTree = document.getElementById('folderTree');
    document.getElementById('moveCopyTitle').textContent = currentAction === 'move' ? 'Move to' : 'Copy to';
    folderTree.innerHTML = '<div class="folder-item root-folder" data-id="null"><i class="fas fa-home"></i> Home</div>';
    
    fetch(`actions/get_folders.php?current=<?= $current_folder ?: 'null' ?>`)
        .then(response => response.json())
        .then(folders => {
            function renderFolders(parentId, level = 0) {
                const children = folders.filter(f => f.parent_id == parentId);
                if (children.length === 0) return '';
                
                let html = '';
                children.forEach(folder => {
                    html += `
                        <div class="folder-item" data-id="${folder.id}" style="padding-left: ${level * 20 + 10}px">
                            <i class="fas fa-folder"></i> ${folder.name}
                        </div>
                        ${renderFolders(folder.id, level + 1)}
                    `;
                });
                return html;
            }
            
            function showSuccessMessage(message) {
                const msgDiv = document.createElement('div');
                msgDiv.className = 'success-message';
                msgDiv.textContent = message;
                document.body.appendChild(msgDiv);
                setTimeout(() => msgDiv.remove(), 3000);
            }

            folderTree.innerHTML += renderFolders(null);
            
            let selectedFolderId = null;
            document.querySelectorAll('.folder-item').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.folder-item').forEach(i => {
                        i.style.background = '';
                    });
                    this.style.background = 'rgba(46, 204, 113, 0.1)';
                    selectedFolderId = this.dataset.id === 'null' ? null : parseInt(this.dataset.id);
                });
            });
            
            const moveCopyModal = document.getElementById('moveCopyModal');
            moveCopyModal.style.display = 'flex';
            
            document.getElementById('cancelMoveCopy').addEventListener('click', () => {
                moveCopyModal.style.display = 'none';
            });
            
            document.getElementById('confirmMoveCopy').addEventListener('click', async () => {
                if (selectedFolderId === null && !confirm(`Are you sure you want to ${currentAction} to the root folder?`)) {
                    return;
                }

                try {
                    const loadingIndicator = document.createElement('div');
                    loadingIndicator.className = 'loading-indicator';
                    loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    document.body.appendChild(loadingIndicator);

                    const response = await fetch('actions/bulk_action.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: currentAction,
                            target_folder: selectedFolderId,
                            items: selectedItems.map(item => ({
                                id: parseInt(item.id),
                                type: item.type
                            }))
                        })
                    });
                    
                    const result = await response.json();
                    loadingIndicator.remove();

                    if (result.success) {
                        showNotification(`Successfully ${currentAction === 'move' ? 'moved' : 'copied'} ${result.processed_items} items`, 'success');
                        
                        await refreshFileList();
                        document.getElementById('moveCopyModal').style.display = 'none';
                        document.getElementById('bulkActions').style.display = 'none';
                        
                    } else {
                        throw new Error(result.message || 'Operation failed');
                    }
                } catch (error) {
                    showNotification(`Error: ${error.message}`, 'error');
                    console.error('Operation failed:', error);
                }
            });
        })
        .catch(error => {
            console.error('Error loading folder structure:', error);
            alert('Failed to load folder structure');
        });
    }
    async function refreshFileList() {
    try {
        const folderId = <?= $current_folder ?: 'null' ?>;
        const response = await fetch(`actions/get_files.php?folder=${folderId}`);
        
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Invalid response:', text);
            throw new Error('Server did not return JSON');
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load files');
        }
        
        const fileGrid = document.querySelector('.file-grid');
        fileGrid.innerHTML = '';
        
        data.folders.forEach(folder => {
            const folderCard = document.createElement('div');
            folderCard.className = 'folder-card';
            folderCard.dataset.id = folder.id;
            folderCard.innerHTML = `
                <div class="file-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="file-name">${escapeHtml(folder.name)}</div>
                <div class="file-actions">
                    <button class="action-btn open-btn" title="Open">
                        <i class="fas fa-folder-open"></i>
                    </button>
                </div>
            `;
            fileGrid.appendChild(folderCard);
        });
        
        data.files.forEach(file => {
            const icon = getFileIcon(file.file_type);
            const fileCard = document.createElement('div');
            fileCard.className = 'file-card';
            fileCard.dataset.id = file.id;
            fileCard.innerHTML = `
                <div class="file-icon">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="file-name">${escapeHtml(file.original_name)}</div>
                <div class="file-size">${formatFileSize(file.file_size)}</div>
            `;
            fileGrid.appendChild(fileCard);
        });
        
        setupFileEvents();
        
    } catch (error) {
        console.error('Failed to refresh file list:', error);
        showNotification(`Error refreshing files: ${error.message}`, 'error');
    }
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function getFileIcon(fileType) {
    if (!fileType) return 'fa-file';
    if (fileType.startsWith('image/')) return 'fa-file-image';
    if (fileType.startsWith('video/')) return 'fa-file-video';
    if (fileType.includes('pdf')) return 'fa-file-pdf';
    if (fileType.includes('word')) return 'fa-file-word';
    if (fileType.includes('excel')) return 'fa-file-excel';
    return 'fa-file';
    }

    function setupFileEvents() {
    try {
        document.querySelectorAll('.file-card, .folder-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.classList.contains('action-btn')) return;
                
                if (e.ctrlKey || e.metaKey) {
                    this.classList.toggle('selected');
                } else {
                    document.querySelectorAll('.file-card.selected, .folder-card.selected').forEach(el => {
                        el.classList.remove('selected');
                    });
                    this.classList.add('selected');
                }
                updateSelectedItems();
            });
        });
    } catch (error) {
        console.error('Error setting up file events:', error);
    }
    }

    function setupFileEvents() {
        document.querySelectorAll('.file-card, .folder-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    this.classList.toggle('selected');
                } else {
                    document.querySelectorAll('.file-card.selected, .folder-card.selected').forEach(el => {
                        el.classList.remove('selected');
                    });
                    this.classList.add('selected');
                }
                updateSelectedItems();
            });
        });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 3000);
    }


function showDirectPreview(fileId, fileName, fileType) {
    const previewModal = document.getElementById('previewModal');
    const previewContent = document.getElementById('previewContent');
    const previewHeader = document.getElementById('previewHeader');
    
    if (!previewModal || !previewContent || !previewHeader) {
        console.error('Preview elements not found!');
        return;
    }

    previewHeader.textContent = fileName;
    previewModal.style.display = 'flex';
    
    previewContent.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
            <p>Loading preview...</p>
        </div>
    `;

    if (fileType.startsWith('image/')) {
        const img = new Image();
        img.src = `../download.php?file_id=${fileId}`;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '65vh';
        img.style.borderRadius = '5px';
        img.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        
        img.onload = function() {
            previewContent.innerHTML = '';
            previewContent.appendChild(img);
        };
        
        img.onerror = function() {
            previewContent.innerHTML = `
                <div style="padding: 40px; color:#333">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Cannot display this image</p>
                </div>
            `;
        };
    } 
    else if (fileType === 'application/pdf') {
        previewContent.innerHTML = `
            <div id="pdfViewer" style="width: 100%; height: 65vh;"></div>
            <button id="pdfPrev" style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer;">❮</button>
            <button id="pdfNext" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer;">❯</button>
        `;

        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js';
        script.onload = function() {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';
            
            let currentPage = 1;
            let pdfDoc = null;
            
            function renderPage(pageNum) {
                pdfDoc.getPage(pageNum).then(function(page) {
                    const scale = 1.5;
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    
                    document.getElementById('pdfViewer').innerHTML = '';
                    document.getElementById('pdfViewer').appendChild(canvas);
                    
                    page.render({
                        canvasContext: context,
                        viewport: viewport
                    });
                    
                    document.getElementById('pdfPrev').style.display = pageNum > 1 ? 'block' : 'none';
                    document.getElementById('pdfNext').style.display = pageNum < pdfDoc.numPages ? 'block' : 'none';
                });
            }
            
            pdfjsLib.getDocument(`../download.php?file_id=${fileId}`).promise.then(function(pdf) {
                pdfDoc = pdf;
                renderPage(1);
                
                document.getElementById('pdfPrev').addEventListener('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPage(currentPage);
                    }
                });
                
                document.getElementById('pdfNext').addEventListener('click', function() {
                    if (currentPage < pdfDoc.numPages) {
                        currentPage++;
                        renderPage(currentPage);
                    }
                });
            }).catch(function(error) {
                previewContent.innerHTML = `
                    <div style="padding:20px;color:#333">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Could not load PDF: ${error.message}</p>
                    </div>
                `;
            });
        };
        document.head.appendChild(script);
    } 
    else {
        previewContent.innerHTML = `
            <div style="padding: 40px; color: #333; text-align: center;">
                <i class="fas fa-file" style="font-size: 3rem;"></i>
                <h3 style="margin-top: 15px;">Preview not available</h3>
                <p style="margin-bottom: 20px;">File type: ${fileType}</p>
                <a href="../download.php?file_id=${fileId}" 
                   class="download-btn"
                   download="${fileName}">
                   <i class="fas fa-download"></i> Download File
                </a>
            </div>
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.file-card').forEach(fileCard => {
        fileCard.addEventListener('dblclick', function(e) {
            if (e.target.classList.contains('action-btn')) return;
            
            const fileId = this.dataset.id;
            const fileName = this.querySelector('.file-name').textContent;
            const fileType = this.dataset.type;
            
            showDirectPreview(fileId, fileName, fileType);
        });
    });

    document.getElementById('closePreview')?.addEventListener('click', function() {
        document.getElementById('previewModal').style.display = 'none';
    });
});
    
    </script>
</body>
</html>