<?php
/**
 * Helper Functions
 */

namespace BOLSearch;

class Helpers
{
    /**
     * Sanitize output for HTML
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format file size
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor] ?? 'TB');
    }

    /**
     * Format date
     */
    public static function formatDate(?string $date, string $format = 'Y-m-d'): string
    {
        if (empty($date)) {
            return 'N/A';
        }

        $dt = new \DateTime($date);
        return $dt->format($format);
    }

    /**
     * Truncate text
     */
    public static function truncate(string $text, int $length = 100, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - strlen($suffix)) . $suffix;
    }

    /**
     * Get excerpt with highlighting
     */
    public static function getExcerpt(string $text, int $length = 200): string
    {
        $text = strip_tags($text, '<mark>');
        return self::truncate($text, $length);
    }

    /**
     * Build URL with query parameters
     */
    public static function buildUrl(string $baseUrl, array $params = []): string
    {
        if (empty($params)) {
            return $baseUrl;
        }

        $query = http_build_query($params);
        $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

        return $baseUrl . $separator . $query;
    }

    /**
     * JSON response
     */
    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Error response
     */
    public static function errorResponse(string $message, int $statusCode = 400): void
    {
        self::jsonResponse(['error' => $message], $statusCode);
    }

    /**
     * Success response
     */
    public static function successResponse($data = null, string $message = null): void
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::jsonResponse($response);
    }

    /**
     * Validate required fields
     */
    public static function validateRequired(array $data, array $required): ?string
    {
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return "Field '$field' is required";
            }
        }

        return null;
    }

    /**
     * Get client IP address
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }

    /**
     * Log to file
     */
    public static function log(string $message, string $logFile = null): void
    {
        if ($logFile === null) {
            error_log($message);
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";

        file_put_contents($logFile, $line, FILE_APPEND);
    }
}
