#!/usr/bin/env php
<?php
/**
 * BOLSearch Reindexing CLI Worker
 *
 * Usage: php /var/www/html/BOLSearch/tasks/reindex.php
 *
 * This script should be run via cron (e.g., every 10 minutes)
 */

// Prevent running from web
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

// Set up paths
$baseDir = dirname(__DIR__);
require_once $baseDir . '/src/Database.php';
require_once $baseDir . '/src/SearchEngine.php';
require_once $baseDir . '/src/OCRProcessor.php';
require_once $baseDir . '/src/MetadataExtractor.php';
require_once $baseDir . '/src/IngestionWorker.php';
require_once $baseDir . '/src/Helpers.php';

use BOLSearch\Database;
use BOLSearch\SearchEngine;
use BOLSearch\OCRProcessor;
use BOLSearch\MetadataExtractor;
use BOLSearch\IngestionWorker;
use BOLSearch\Helpers;

// Load configuration
$config = require $baseDir . '/config/config.php';
$searchConfig = require $baseDir . '/config/meilisearch.php';
$ocrConfig = require $baseDir . '/config/ocr.php';

// Set PHP configuration for CLI
ini_set('memory_limit', $config['performance']['memory_limit']);
set_time_limit($config['performance']['max_execution_time']);

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Initialize database
try {
    $db = Database::getInstance($config['db']);
    echo "[INFO] Database connected\n";
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize search engine
try {
    $searchEngine = new SearchEngine($searchConfig);

    // Initialize index if needed
    if (!$searchEngine->health()) {
        echo "[WARNING] Meilisearch is not healthy, but continuing...\n";
    } else {
        $searchEngine->initializeIndex();
        echo "[INFO] Meilisearch connected and initialized\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Meilisearch initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize OCR processor
try {
    $ocrProcessor = new OCRProcessor($ocrConfig);
    echo "[INFO] OCR processor initialized\n";
} catch (Exception $e) {
    echo "[ERROR] OCR processor initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Initialize metadata extractor
$metadataExtractor = new MetadataExtractor();
echo "[INFO] Metadata extractor initialized\n";

// Create ingestion worker
$worker = new IngestionWorker(
    $config,
    $ocrConfig,
    $db,
    $searchEngine,
    $ocrProcessor,
    $metadataExtractor
);

echo "[INFO] Ingestion worker created\n";
echo str_repeat("=", 60) . "\n";

// Check for pending jobs
$pendingJobs = Database::fetchAll('
    SELECT * FROM ingestion_jobs
    WHERE status = "pending"
    ORDER BY created_at ASC
    LIMIT 5
');

if (!empty($pendingJobs)) {
    echo "[INFO] Found " . count($pendingJobs) . " pending job(s)\n\n";

    foreach ($pendingJobs as $job) {
        echo "[JOB] Processing job #{$job['id']} ({$job['job_type']})\n";

        // Mark as running
        Database::execute(
            'UPDATE ingestion_jobs SET status = ?, started_at = NOW() WHERE id = ?',
            ['running', $job['id']]
        );

        try {
            $stats = null;

            switch ($job['job_type']) {
                case 'full':
                    $stats = $worker->runFull();
                    break;

                case 'folder':
                    $stats = $worker->runFolder($job['folder']);
                    break;

                case 'single':
                    $stats = $worker->processSingleFile($job['file_path']);
                    break;
            }

            // Mark as done
            Database::execute(
                'UPDATE ingestion_jobs SET status = ?, finished_at = NOW() WHERE id = ?',
                ['done', $job['id']]
            );

            echo "[JOB] Job #{$job['id']} completed successfully\n";
            if ($stats) {
                echo "[STATS] " . json_encode($stats) . "\n";
            }

        } catch (Exception $e) {
            // Mark as error
            Database::execute(
                'UPDATE ingestion_jobs SET status = ?, error_message = ?, finished_at = NOW() WHERE id = ?',
                ['error', $e->getMessage(), $job['id']]
            );

            echo "[ERROR] Job #{$job['id']} failed: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }
} else {
    echo "[INFO] No pending jobs found\n";
    echo "[INFO] Running automatic full scan...\n\n";

    try {
        $stats = $worker->runFull();
        echo "[INFO] Automatic scan completed\n";
        echo "[STATS] " . json_encode($stats) . "\n";
    } catch (Exception $e) {
        echo "[ERROR] Automatic scan failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo str_repeat("=", 60) . "\n";
echo "[INFO] Reindexing complete\n";
exit(0);
