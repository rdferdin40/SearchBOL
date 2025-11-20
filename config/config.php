<?php
/**
 * BOLSearch Main Configuration
 */

return [
    // Database Configuration
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'bolsearch',
        'username' => 'bolsearch_user',
        'password' => 'your_secure_password_here',
        'charset' => 'utf8mb4',
    ],

    // Application Paths
    'paths' => [
        // Base directory where PDFs are stored
        'bols_root' => '/srv/bols',

        // Application root directory
        'app_root' => '/var/www/html/BOLSearch',

        // Public web directory
        'public_root' => '/var/www/html/BOLSearch/public',
    ],

    // Application URLs
    'urls' => [
        // Base URL for the application (no trailing slash)
        'base_url' => 'http://192.168.0.20/BOLSearch',

        // Public base URL
        'public_url' => 'http://192.168.0.20/BOLSearch/public',
    ],

    // Application Settings
    'app' => [
        'name' => 'BOLSearch',
        'version' => '1.0.0',
        'timezone' => 'America/New_York',

        // Search results per page
        'results_per_page' => 50,

        // Maximum file size to process (in bytes, 100MB default)
        'max_file_size' => 100 * 1024 * 1024,

        // Allowed file extensions
        'allowed_extensions' => ['pdf'],

        // Session settings
        'session_name' => 'BOLSEARCH_SESSION',
        'session_lifetime' => 3600, // 1 hour
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'log_file' => '/var/www/html/BOLSearch/logs/app.log',
        'error_log' => '/var/www/html/BOLSearch/logs/errors.log',
        'level' => 'info', // debug, info, warning, error
    ],

    // Year folders to scan (in addition to root)
    'year_folders' => range(2020, 2030),

    // Performance settings
    'performance' => [
        // Number of documents to process in a single batch
        'batch_size' => 100,

        // Memory limit for CLI scripts
        'memory_limit' => '512M',

        // Maximum execution time for CLI scripts (0 = unlimited)
        'max_execution_time' => 0,
    ],
];
