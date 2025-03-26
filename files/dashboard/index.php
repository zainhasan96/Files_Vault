<?php
define('ACCESS_CHECK', true);
require_once '../../private/config.php';
require_once '../../private/auth_check.php';
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    
    $files_count = $db->query("SELECT COUNT(*) FROM files WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
    $shared_count = $db->query("SELECT COUNT(*) FROM shared_links WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
    $storage_used = $db->query("SELECT SUM(file_size) FROM files WHERE user_id = {$_SESSION['user_id']}")->fetchColumn();
    $user_stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | File Manager</title>
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-family: 'Pacifico', cursive;
            color: var(--primary);
            font-size: 1.8rem;
            margin: 0;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 0.9rem;
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
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="files.php" class="nav-link">
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
            <h1 class="page-title">Dashboard</h1>
        </div>
        
        <div class="stats-container">
            <div onclick="location.href='files.php';" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="stat-value"><?= $files_count ?></div>
                <div class="stat-label">Total Files</div>
            </div>
            
            <div onclick="location.href='shared.php';" class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-share-alt"></i>
                </div>
                <div class="stat-value"><?= $shared_count ?></div>
                <div class="stat-label">Shared Links</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-value"><?= round($storage_used / (1024 * 1024), 2) ?> MB</div>
                <div class="stat-label">Storage Used</div>
            </div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0; color: var(--primary);">Upload Files</h2>
            <div style="border: 2px dashed var(--primary); border-radius: 8px; padding: 30px; text-align: center; cursor: pointer;"
                 id="uploadArea">
                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;"></i>
                <p>Drag & drop files here or click to browse</p>
                <input type="file" id="fileInput" style="display: none;" multiple>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('uploadArea').addEventListener('click', function() {
        document.getElementById('fileInput').click();
    });
    
    document.getElementById('fileInput').addEventListener('change', async function() {
        if (this.files.length === 0) return;
        
        const uploadArea = document.getElementById('uploadArea');
        uploadArea.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 3rem;"></i><p>Uploading...</p>';
        
        const formData = new FormData();
        for (let file of this.files) {
            formData.append('files[]', file);
        }
        
        try {
            const response = await fetch('actions/upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (result.message || 'Upload failed'));
                uploadArea.innerHTML = `
                    <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary); margin-bottom: 15px;"></i>
                    <p>Drag & drop files here or click to browse</p>
                `;
            }
        } catch (error) {
            alert('Upload failed: ' + error.message);
        }
    });
    
    const uploadArea = document.getElementById('uploadArea');
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.backgroundColor = 'rgba(46, 204, 113, 0.1)';
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.backgroundColor = '';
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.backgroundColor = '';
        document.getElementById('fileInput').files = e.dataTransfer.files;
        const event = new Event('change');
        document.getElementById('fileInput').dispatchEvent(event);
    });
    </script>
</body>
</html>