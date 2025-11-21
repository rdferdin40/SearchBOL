<?php
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/AdminAuth.php';
use BOLSearch\Database;
use BOLSearch\AdminAuth;

$config = require __DIR__ . '/../../config/config.php';
Database::getInstance($config['db']);
$auth = new AdminAuth(Database::getInstance(), $config['app']['session_name'], $config['app']['session_lifetime']);
$auth->requireAuth();
?>
<!DOCTYPE html>
<html><head><title>Duplicates - BOLSearch Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><div class="container mt-4"><h2>Duplicate Documents</h2>
<p>Duplicate detection interface. Backend ready - see IMPLEMENTATION_STATUS.md for full UI code.</p>
<a href="index.php" class="btn btn-secondary">Back to Dashboard</a></div></body></html>
