<?php
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/AdminAuth.php';
require_once __DIR__ . '/../../src/Helpers.php';

use BOLSearch\Database;
use BOLSearch\AdminAuth;
use BOLSearch\Helpers;

$config = require __DIR__ . '/../../config/config.php';
Database::getInstance($config['db']);
$auth = new AdminAuth(Database::getInstance(), $config['app']['session_name'], $config['app']['session_lifetime']);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($auth->login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - BOLSearch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">BOLSearch Admin</h3>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= Helpers::escape($error) ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                        </form>
                        <div class="text-center mt-3">
                            <small class="text-muted">Default: admin / admin123</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
