<?php
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/AdminAuth.php';

use BOLSearch\Database;
use BOLSearch\AdminAuth;

$config = require __DIR__ . '/../../config/config.php';
Database::getInstance($config['db']);
$auth = new AdminAuth(Database::getInstance(), $config['app']['session_name'], $config['app']['session_lifetime']);
$auth->logout();
header('Location: login.php');
exit;
