<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';

try {
    if (!isset($_GET['t'])) {
        throw new Exception('Invalid preview request');
    }
    
    $token = $_GET['t'];
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $db->prepare("
        SELECT sl.*, f.original_name, f.file_path, f.file_type 
        FROM shared_links sl
        JOIN files f ON sl.file_id = f.id
        WHERE sl.token = ?
    ");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$share) {
        throw new Exception('File not found');
    }
    if ($share['expiry_date'] && strtotime($share['expiry_date']) < time()) {
        throw new Exception('This share link has expired');
    }
    if ($share['password'] && !isset($_SESSION['share_authenticated'][$token])) {
        header('Location: share.php?t=' . $token);
        exit;
    }
    
    $file_path = STORAGE_PATH . $share['file_path'];
    
    if (!file_exists($file_path)) {
        throw new Exception('File not found on server');
    }
    $mime_type = $share['file_type'];
    $is_image = strpos($mime_type, 'image/') === 0;
    $is_pdf = $mime_type === 'application/pdf';
    
    if (!$is_image && !$is_pdf) {
        throw new Exception('Preview not available for this file type');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview | File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;600&display=swap" rel="stylesheet">
    <?php if ($is_pdf): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
        <style>
            #pdf-viewer {
                width: 100%;
                height: 80vh;
                border: 1px solid #ddd;
                margin-bottom: 20px;
            }
        </style>
    <?php endif; ?>
</head>
<body>
    <div style="max-width: 1000px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2ecc71;"><?= htmlspecialchars($share['original_name']) ?></h1>
        
        <?php if ($is_image): ?>
            <div style="text-align: center; margin: 20px 0;">
                <img src="<?= htmlspecialchars('download.php?t=' . $token) ?>" 
                     style="max-width: 100%; max-height: 80vh; border: 1px solid #ddd;">
            </div>
            
        <?php elseif ($is_pdf): ?>
            <div id="pdf-viewer"></div>
            
            <script>
                pdfjsLib.getDocument('<?= htmlspecialchars('download.php?t=' . $token) ?>').promise.then(function(pdf) {
                    pdf.getPage(1).then(function(page) {
                        const scale = 1.5;
                        const viewport = page.getViewport({ scale: scale });
                        
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        
                        document.getElementById('pdf-viewer').appendChild(canvas);
                        
                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };
                        
                        page.render(renderContext);
                    });
                });
            </script>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="download.php?t=<?= htmlspecialchars($token) ?>" 
               style="display: inline-block; padding: 10px 20px; background: #2ecc71; color: white; text-decoration: none; border-radius: 5px;">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
</body>
</html>