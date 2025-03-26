<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    if (empty($firstName) || empty($lastName) || empty($username) || empty($password)) {
        $error = "Please fill in all required fields";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } else {
        try {
            $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = "Username already exists";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $currentTime = date('Y-m-d H:i:s');
                $stmt = $db->prepare("INSERT INTO users (first_name, last_name, username, password, phone, is_password_hashed, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $username,
                    $hashedPassword,
                    $phone,
                    $currentTime,
                    $currentTime
                ]);
                
                $success = "Registration successful! You can now login.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | File Manager</title>
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
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            text-align: center;
        }
        .register-title {
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
        .register-btn {
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
        .register-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .error-message {
            color: #e74c3c;
            margin-top: 15px;
        }
        .success-message {
            color: var(--primary);
            margin-top: 15px;
        }
        .login-link {
            margin-top: 20px;
            font-size: 14px;
        }
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1 class="register-title">File Vault</h1>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php else: ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <label for="first_name"><i class="fas fa-user"></i> First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            
            <div class="input-group">
                <label for="last_name"><i class="fas fa-user"></i> Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            
            <div class="input-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="input-group">
                <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone" name="phone">
            </div>
            
            <div class="input-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="input-group">
                <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="register-btn">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        
        <?php endif; ?>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>