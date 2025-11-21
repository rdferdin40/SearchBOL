<?php
/**
 * OCR and Text Extraction Processor
 */

namespace BOLSearch;

use Exception;

class OCRProcessor
{
    private array $config;
    private string $tempDir;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tempDir = $config['temp_dir'];

        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Process a PDF file and extract text
     *
     * @param string $pdfPath Path to PDF file
     * @return array ['text' => string, 'ocr_status' => string, 'method' => string]
     */
    public function processFile(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new Exception("PDF file not found: $pdfPath");
        }

        // First, try digital text extraction
        $digitalText = $this->extractDigitalText($pdfPath);

        $minLength = $this->config['text_extraction']['min_digital_text_length'];
        if (strlen(trim($digitalText)) >= $minLength) {
            return [
                'text' => $this->truncateText($digitalText),
                'ocr_status' => 'none', // No OCR needed
                'method' => 'digital',
            ];
        }

        // No digital text, try OCR
        try {
            $ocrText = $this->performOCR($pdfPath);

            if (!empty(trim($ocrText))) {
                return [
                    'text' => $this->truncateText($ocrText),
                    'ocr_status' => 'done',
                    'method' => 'ocr',
                ];
            } else {
                return [
                    'text' => '',
                    'ocr_status' => 'failed',
                    'method' => 'ocr',
                    'error' => 'OCR produced no text',
                ];
            }
        } catch (Exception $e) {
            return [
                'text' => '',
                'ocr_status' => 'failed',
                'method' => 'ocr',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract digital text from PDF using pdftotext
     */
    private function extractDigitalText(string $pdfPath): string
    {
        $pdftotextPath = $this->config['pdftotext_path'];
        $mode = $this->config['text_extraction']['pdftotext_mode'];

        if (!file_exists($pdftotextPath)) {
            return '';
        }

        // Create temporary output file
        $outputFile = tempnam($this->tempDir, 'txt_');

        // Run pdftotext
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellcmd($pdftotextPath),
            $mode,
            escapeshellarg($pdfPath),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $returnCode);

        $text = '';
        if ($returnCode === 0 && file_exists($outputFile)) {
            $text = file_get_contents($outputFile);
            unlink($outputFile);
        } elseif (file_exists($outputFile)) {
            unlink($outputFile);
        }

        return $text;
    }

    /**
     * Perform OCR on a PDF file
     */
    private function performOCR(string $pdfPath, bool $aggressive = false): string
    {
        // Get number of pages
        $pageCount = $this->getPageCount($pdfPath);

        if ($pageCount === 0) {
            throw new Exception('Could not determine page count');
        }

        // Check max pages limit
        $maxPages = $this->config['max_pages_per_document'];
        if ($maxPages > 0 && $pageCount > $maxPages) {
            $pageCount = $maxPages;
        }

        $allText = [];

        // Process each page
        for ($page = 1; $page <= $pageCount; $page++) {
            try {
                $pageText = $this->ocrPage($pdfPath, $page, $aggressive);
                if (!empty(trim($pageText))) {
                    $allText[] = $pageText;
                }
            } catch (Exception $e) {
                // Log but continue with other pages
                error_log("OCR failed for page $page of $pdfPath: " . $e->getMessage());
            }
        }

        return implode("\n\n", $allText);
    }

    /**
     * OCR a single page
     */
    private function ocrPage(string $pdfPath, int $page, bool $aggressive = false): string
    {
        // Convert PDF page to image
        $imagePath = $this->convertPageToImage($pdfPath, $page, $aggressive);

        if (!$imagePath || !file_exists($imagePath)) {
            throw new Exception("Failed to convert page $page to image");
        }

        try {
            // Run Tesseract
            $text = $this->runTesseract($imagePath, $aggressive);
            return $text;
        } finally {
            // Cleanup
            if ($this->config['cleanup_temp_files'] && file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    /**
     * Convert PDF page to image
     */
    private function convertPageToImage(string $pdfPath, int $page, bool $aggressive = false): ?string
    {
        $pdftoppmPath = $this->config['pdftoppm_path'];

        if (!file_exists($pdftoppmPath)) {
            return null;
        }

        $dpi = $aggressive
            ? $this->config['retry']['aggressive_mode']['dpi']
            : $this->config['pdf_to_image']['dpi'];

        $format = $this->config['pdf_to_image']['format'];

        // Output file
        $outputPrefix = $this->tempDir . '/page_' . uniqid();

        // pdftoppm command
        $command = sprintf(
            '%s -f %d -l %d -%s -r %d %s %s 2>&1',
            escapeshellcmd($pdftoppmPath),
            $page,
            $page,
            $format,
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return null;
        }

        // pdftoppm creates files like: prefix-01.png
        $pattern = $outputPrefix . '-*.' . $format;
        $files = glob($pattern);

        return !empty($files) ? $files[0] : null;
    }

    /**
     * Run Tesseract OCR on an image
     */
    private function runTesseract(string $imagePath, bool $aggressive = false): string
    {
        $tesseractPath = $this->config['tesseract_path'];
        $language = $this->config['tesseract']['language'];
        $oem = $this->config['tesseract']['oem'];

        $psm = $aggressive
            ? $this->config['retry']['aggressive_mode']['tesseract_psm']
            : $this->config['tesseract']['psm'];

        // Output file (without extension, Tesseract adds .txt)
        $outputFile = tempnam($this->tempDir, 'ocr_');

        // Build config string
        $configVars = [];
        foreach ($this->config['tesseract']['config_vars'] as $key => $value) {
            $configVars[] = "-c $key=$value";
        }
        $configString = implode(' ', $configVars);

        // Tesseract command
        $command = sprintf(
            '%s %s %s -l %s --oem %d --psm %d %s 2>&1',
            escapeshellcmd($tesseractPath),
            escapeshellarg($imagePath),
            escapeshellarg($outputFile),
            escapeshellarg($language),
            $oem,
            $psm,
            $configString
        );

        exec($command, $output, $returnCode);

        // Tesseract creates output_file.txt
        $textFile = $outputFile . '.txt';

        $text = '';
        if (file_exists($textFile)) {
            $text = file_get_contents($textFile);
            unlink($textFile);
        }

        if (file_exists($outputFile)) {
            unlink($outputFile);
        }

        if ($returnCode !== 0 && empty($text)) {
            throw new Exception('Tesseract OCR failed: ' . implode("\n", $output));
        }

        return $text;
    }

    /**
     * Get page count of PDF
     */
    private function getPageCount(string $pdfPath): int
    {
        $pdfinfoPath = $this->config['pdfinfo_path'];

        if (!file_exists($pdfinfoPath)) {
            // Default to 1 page if we can't determine
            return 1;
        }

        $command = sprintf(
            '%s %s 2>&1',
            escapeshellcmd($pdfinfoPath),
            escapeshellarg($pdfPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            foreach ($output as $line) {
                if (preg_match('/^Pages:\s+(\d+)/', $line, $matches)) {
                    return (int)$matches[1];
                }
            }
        }

        return 1;
    }

    /**
     * Truncate text if needed
     */
    private function truncateText(string $text): string
    {
        $maxLength = $this->config['text_extraction']['max_text_length'];

        if ($maxLength > 0 && strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }

        return $text;
    }

    /**
     * Retry OCR with aggressive settings
     */
    public function retryWithAggressiveSettings(string $pdfPath): array
    {
        try {
            $ocrText = $this->performOCR($pdfPath, true);

            if (!empty(trim($ocrText))) {
                return [
                    'text' => $this->truncateText($ocrText),
                    'ocr_status' => 'done',
                    'method' => 'ocr_aggressive',
                ];
            } else {
                return [
                    'text' => '',
                    'ocr_status' => 'failed',
                    'method' => 'ocr_aggressive',
                    'error' => 'Aggressive OCR produced no text',
                ];
            }
        } catch (Exception $e) {
            return [
                'text' => '',
                'ocr_status' => 'failed',
                'method' => 'ocr_aggressive',
                'error' => $e->getMessage(),
            ];
        }
    }
}
