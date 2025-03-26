<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    try {
        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $valid = ($user['is_password_hashed'] && password_verify($password, $user['password'])) || 
                    (!$user['is_password_hashed'] && $password === $user['password']);
            
            if ($valid) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: dashboard/');
                exit;
            }
        }
        $error = "Invalid username or password";
    } catch (PDOException $e) {
        $error = "Database error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | File Manager</title>
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
        }
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            text-align: center;
        }
        .login-title {
            color: var(--primary);
            margin-bottom: 30px;
            font-size: 2rem;
            font-family: 'Pacifico', cursive;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            max-width: -webkit-fill-available;
        }
        .input-group input:focus {
            border-color: var(--primary);
            outline: none;
        }
        .login-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        .login-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .error-message {
            color: #e74c3c;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">File Vault</h1>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        <div style="margin-top: 20px; font-size: 14px;">
            Don't have an account? <a href="register.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Register here</a>
        </div>
    </div>
</body>
</html>