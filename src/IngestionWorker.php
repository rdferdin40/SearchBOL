<?php
/**
 * Ingestion Worker - Scans filesystem and processes PDFs
 */

namespace BOLSearch;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IngestionWorker
{
    private array $appConfig;
    private array $ocrConfig;
    private \PDO $db;
    private SearchEngine $searchEngine;
    private OCRProcessor $ocrProcessor;
    private MetadataExtractor $metadataExtractor;
    private string $bolsRoot;

    private array $stats = [
        'processed' => 0,
        'new' => 0,
        'updated' => 0,
        'deleted' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        array $appConfig,
        array $ocrConfig,
        \PDO $db,
        SearchEngine $searchEngine,
        OCRProcessor $ocrProcessor,
        MetadataExtractor $metadataExtractor
    ) {
        $this->appConfig = $appConfig;
        $this->ocrConfig = $ocrConfig;
        $this->db = $db;
        $this->searchEngine = $searchEngine;
        $this->ocrProcessor = $ocrProcessor;
        $this->metadataExtractor = $metadataExtractor;
        $this->bolsRoot = rtrim($appConfig['paths']['bols_root'], '/');
    }

    public function runFull(): array
    {
        $this->log("Starting full ingestion scan...");

        $folders = $this->getFoldersToScan();

        foreach ($folders as $folder) {
            $this->log("Scanning folder: $folder");
            $this->scanFolder($folder);
        }

        $this->markDeletedFiles();

        $this->log("Full ingestion complete", $this->stats);

        return $this->stats;
    }

    public function runFolder(string $folder): array
    {
        $this->log("Starting folder ingestion: $folder");
        $this->scanFolder($folder);
        $this->log("Folder ingestion complete", $this->stats);
        return $this->stats;
    }

    public function processSingleFile(string $filePath): array
    {
        $this->log("Processing single file: $filePath");

        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $result = $this->processFile($filePath);
        $this->log("Single file processing complete", $result);
        return $result;
    }

    private function getFoldersToScan(): array
    {
        $folders = ['root'];

        if (is_dir($this->bolsRoot)) {
            $items = scandir($this->bolsRoot);

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $this->bolsRoot . '/' . $item;

                if (is_dir($path) && preg_match('/^\d{4}$/', $item)) {
                    $folders[] = $item;
                }
            }
        }

        return $folders;
    }

    private function scanFolder(string $folder): void
    {
        $folderPath = $folder === 'root'
            ? $this->bolsRoot
            : $this->bolsRoot . '/' . $folder;

        if (!is_dir($folderPath)) {
            $this->log("Folder not found: $folderPath", [], 'warning');
            return;
        }

        $files = [];

        if ($folder === 'root') {
            $items = scandir($folderPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $folderPath . '/' . $item;

                if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
                    $files[] = $path;
                }
            }
        } else {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'pdf') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->log("Found " . count($files) . " PDFs in folder: $folder");

        foreach ($files as $filePath) {
            try {
                $this->processFile($filePath);
                $this->stats['processed']++;
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logError($filePath, 'other', $e->getMessage());
                $this->log("Error processing $filePath: " . $e->getMessage(), [], 'error');
            }
        }
    }

    private function processFile(string $filePath): array
    {
        $fileInfo = $this->getFileInfo($filePath);
        $existing = $this->getExistingDocument($filePath);

        if ($existing) {
            if ($existing['sha256'] === $fileInfo['sha256'] &&
                $existing['modified_at'] === $fileInfo['modified_at']) {
                $this->stats['skipped']++;
                return ['status' => 'skipped', 'reason' => 'not modified'];
            }

            $isNew = false;
            $documentId = $existing['id'];
        } else {
            $isNew = true;
            $documentId = null;
        }

        $maxSize = $this->appConfig['app']['max_file_size'];
        if ($fileInfo['file_size'] > $maxSize) {
            $this->stats['skipped']++;
            $this->logError($filePath, 'other', "File too large: {$fileInfo['file_size']} bytes");
            return ['status' => 'skipped', 'reason' => 'file too large'];
        }

        try {
            $ocrResult = $this->ocrProcessor->processFile($filePath);
            $textContent = $ocrResult['text'];
            $ocrStatus = $ocrResult['ocr_status'];
        } catch (Exception $e) {
            $this->logError($filePath, 'ocr_failed', $e->getMessage());
            $textContent = '';
            $ocrStatus = 'failed';
        }

        $metadata = [];
        if (!empty($textContent)) {
            try {
                $metadata = $this->metadataExtractor->extract($textContent);
            } catch (Exception $e) {
                $this->log("Metadata extraction failed for $filePath: " . $e->getMessage(), [], 'warning');
            }
        }

        try {
            $this->db->beginTransaction();

            if ($isNew) {
                $documentId = $this->insertDocument($fileInfo, $textContent, $ocrStatus);
                $this->stats['new']++;
            } else {
                $this->updateDocument($documentId, $fileInfo, $textContent, $ocrStatus);
                $this->stats['updated']++;
            }

            $this->saveMetadata($documentId, $metadata);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        try {
            $this->indexDocument($documentId, $fileInfo, $metadata, $textContent);
        } catch (Exception $e) {
            $this->logError($filePath, 'indexing_error', $e->getMessage(), $documentId);
            $this->log("Indexing failed for $filePath: " . $e->getMessage(), [], 'error');
        }

        return [
            'status' => 'success',
            'document_id' => $documentId,
            'is_new' => $isNew,
            'ocr_status' => $ocrStatus,
        ];
    }

    private function getFileInfo(string $filePath): array
    {
        $stat = stat($filePath);

        $fileName = basename($filePath);
        $folder = $this->determineFolder($filePath);
        $year = $this->extractYear($folder, $fileName);

        return [
            'file_path' => $filePath,
            'file_name' => $fileName,
            'folder' => $folder,
            'year' => $year,
            'file_size' => $stat['size'],
            'sha256' => hash_file('sha256', $filePath),
            'created_at' => date('Y-m-d H:i:s', $stat['ctime']),
            'modified_at' => date('Y-m-d H:i:s', $stat['mtime']),
        ];
    }

    private function determineFolder(string $filePath): string
    {
        $relativePath = str_replace($this->bolsRoot . '/', '', $filePath);

        if (strpos($relativePath, '/') === false) {
            return 'root';
        }

        $parts = explode('/', $relativePath);
        return $parts[0];
    }

    private function extractYear(?string $folder, string $fileName): ?int
    {
        if ($folder && preg_match('/^(\d{4})$/', $folder, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/(\d{4})/', $fileName, $matches)) {
            $year = (int)$matches[1];
            if ($year >= 2000 && $year <= 2050) {
                return $year;
            }
        }

        return null;
    }

    private function getExistingDocument(string $filePath): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM documents WHERE file_path = ? LIMIT 1');
        $stmt->execute([$filePath]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function insertDocument(array $fileInfo, string $textContent, string $ocrStatus): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO documents (
                file_path, file_name, folder, year, file_size, sha256,
                text_content, ocr_status, created_at, modified_at, indexed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            $fileInfo['file_path'],
            $fileInfo['file_name'],
            $fileInfo['folder'],
            $fileInfo['year'],
            $fileInfo['file_size'],
            $fileInfo['sha256'],
            $textContent,
            $ocrStatus,
            $fileInfo['created_at'],
            $fileInfo['modified_at'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function updateDocument(int $documentId, array $fileInfo, string $textContent, string $ocrStatus): void
    {
        $stmt = $this->db->prepare('
            UPDATE documents SET
                file_name = ?,
                folder = ?,
                year = ?,
                file_size = ?,
                sha256 = ?,
                text_content = ?,
                ocr_status = ?,
                modified_at = ?,
                indexed_at = NOW(),
                is_deleted = 0
            WHERE id = ?
        ');

        $stmt->execute([
            $fileInfo['file_name'],
            $fileInfo['folder'],
            $fileInfo['year'],
            $fileInfo['file_size'],
            $fileInfo['sha256'],
            $textContent,
            $ocrStatus,
            $fileInfo['modified_at'],
            $documentId,
        ]);
    }

    private function saveMetadata(int $documentId, array $metadata): void
    {
        $stmt = $this->db->prepare('DELETE FROM metadata_bol WHERE document_id = ?');
        $stmt->execute([$documentId]);

        $stmt = $this->db->prepare('
            INSERT INTO metadata_bol (
                document_id, bol_number, carrier, shipper, consignee,
                trailer_number, plant, ship_date, weight, pallet_count, seal_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $documentId,
            $metadata['bol_number'] ?? null,
            $metadata['carrier'] ?? null,
            $metadata['shipper'] ?? null,
            $metadata['consignee'] ?? null,
            $metadata['trailer_number'] ?? null,
            $metadata['plant'] ?? null,
            $metadata['ship_date'] ?? null,
            $metadata['weight'] ?? null,
            $metadata['pallet_count'] ?? null,
            $metadata['seal_number'] ?? null,
        ]);
    }

    private function indexDocument(int $documentId, array $fileInfo, array $metadata, string $textContent): void
    {
        $doc = [
            'id' => $documentId,
            'file_name' => $fileInfo['file_name'],
            'file_path' => $fileInfo['file_path'],
            'folder' => $fileInfo['folder'],
            'year' => $fileInfo['year'],
            'bol_number' => $metadata['bol_number'] ?? null,
            'carrier' => $metadata['carrier'] ?? null,
            'shipper' => $metadata['shipper'] ?? null,
            'consignee' => $metadata['consignee'] ?? null,
            'trailer_number' => $metadata['trailer_number'] ?? null,
            'plant' => $metadata['plant'] ?? null,
            'ship_date' => $metadata['ship_date'] ?? null,
            'weight' => $metadata['weight'] ?? null,
            'pallet_count' => $metadata['pallet_count'] ?? null,
            'seal_number' => $metadata['seal_number'] ?? null,
            'text_content' => $textContent,
            'created_at' => $fileInfo['created_at'],
        ];

        $this->searchEngine->indexDocument($doc);
    }

    private function markDeletedFiles(): void
    {
        $stmt = $this->db->query('SELECT id, file_path FROM documents WHERE is_deleted = 0');

        $deletedIds = [];

        while ($row = $stmt->fetch()) {
            if (!file_exists($row['file_path'])) {
                $deletedIds[] = $row['id'];
            }
        }

        if (!empty($deletedIds)) {
            $placeholders = implode(',', array_fill(0, count($deletedIds), '?'));
            $updateStmt = $this->db->prepare("UPDATE documents SET is_deleted = 1 WHERE id IN ($placeholders)");
            $updateStmt->execute($deletedIds);

            try {
                $this->searchEngine->deleteDocuments($deletedIds);
            } catch (Exception $e) {
                $this->log("Failed to delete documents from search index: " . $e->getMessage(), [], 'error');
            }

            $this->stats['deleted'] = count($deletedIds);
            $this->log("Marked " . count($deletedIds) . " files as deleted");
        }
    }

    private function logError(string $filePath, string $errorType, string $message, ?int $documentId = null): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO ingestion_errors (document_id, file_path, error_type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ');

        $stmt->execute([$documentId, $filePath, $errorType, $message]);
    }

    private function log(string $message, array $context = [], string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        echo "[$timestamp] [$level] $message$contextStr\n";
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}
