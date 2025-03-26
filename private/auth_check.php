<?php
if (!defined('ACCESS_CHECK')) {die('Direct access not allowed!');}
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>