<?php
define('ACCESS_CHECK', true);
require_once '../../private/config.php';
require_once '../../private/auth_check.php';
$db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
$user_stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$shared_links = $db->query("
    SELECT sl.*, 
           COALESCE(f.original_name, fl.name) as item_name,
           CASE WHEN sl.file_id IS NOT NULL THEN 'file' ELSE 'folder' END as item_type
    FROM shared_links sl
    LEFT JOIN files f ON sl.file_id = f.id
    LEFT JOIN folders fl ON sl.folder_id = fl.id
    WHERE sl.user_id = {$_SESSION['user_id']}
    ORDER BY sl.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Links | File Manager</title>
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
        
        .links-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .links-table th, .links-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .links-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .copy-link {
            display: flex;
        }
        
        .copy-link input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }
        
        .copy-link button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            padding: 5px;
        }
        .page-title {
            font-family: 'Pacifico', cursive;
            color: var(--primary);
            font-size: 1.8rem;
            margin: 0;
            margin-bottom: 20px;
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
                <a href="files.php" class="nav-link">
                    <i class="fas fa-folder"></i>
                    <span>Files</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="shared.php" class="nav-link active">
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
            <h1 class="page-title">Shared Links</h1>
        </div>
        
        <?php if (empty($shared_links)): ?>
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                <i class="fas fa-share-alt" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                <h3 style="color: #777;">No shared links yet</h3>
                <p>Share files or folders from the Files page</p>
            </div>
        <?php else: ?>
            <table class="links-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Share Link</th>
                        <th>Expires</th>
                        <th>Downloads</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shared_links as $link): ?>
                        <tr>
                            <td><?= htmlspecialchars($link['item_name']) ?></td>
                            <td><?= ucfirst($link['item_type']) ?></td>
                            <td>
                                <div class="copy-link">
                                    <input type="text" value="<?= SITE_URL ?>share.php?t=<?= $link['token'] ?>" readonly>
                                    <button class="copy-btn" data-link="<?= SITE_URL ?>share.php?t=<?= $link['token'] ?>">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td><?= $link['expiry_date'] ? date('Y-m-d H:i', strtotime($link['expiry_date'])) : 'Never' ?></td>
                            <td><?= $link['download_count'] ?> / <?= $link['download_limit'] ?? '∞' ?></td>
                            <td>
                                <button class="action-btn delete-btn" data-id="<?= $link['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
    document.querySelectorAll('.copy-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            navigator.clipboard.writeText(link).then(() => {
                alert('Link copied to clipboard!');
            });
        });
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (confirm('Are you sure you want to delete this share link?')) {
                const id = this.getAttribute('data-id');
                try {
                    const response = await fetch('actions/delete_share.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id })
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        this.closest('tr').remove();
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    alert('Failed to delete share link');
                }
            }
        });
    });
    </script>
</body>
</html>