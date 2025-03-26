<?php
define('ACCESS_CHECK', true);
require_once '../private/config.php';
$_SESSION = array();
session_destroy();
header('Location: login.php');
exit;
?>