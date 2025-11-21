<?php
/**
 * BOL Metadata Extractor
 * Extracts structured metadata from PDF text content
 */

namespace BOLSearch;

class MetadataExtractor
{
    // Configurable regex patterns
    private array $patterns = [
        'bol_number' => [
            '/B\/?O\/?L\s*#?\s*:?\s*([A-Z0-9\-]+)/i',
            '/Bill\s+of\s+Lading\s*#?\s*:?\s*([A-Z0-9\-]+)/i',
            '/BOL\s+Number\s*:?\s*([A-Z0-9\-]+)/i',
            '/Pro\s*#?\s*:?\s*([A-Z0-9\-]+)/i',
        ],
        'carrier' => [
            '/Carrier\s*:?\s*([A-Z][A-Za-z\s&\.\-]+?)(?:\n|$)/i',
            '/SCAC\s*:?\s*([A-Z]{2,4})\b/i',
        ],
        'shipper' => [
            '/Shipper\s*:?\s*([A-Z][A-Za-z0-9\s,\.\-]+?)(?:\n|Consignee|Date)/i',
            '/Ship\s+From\s*:?\s*([A-Z][A-Za-z0-9\s,\.\-]+?)(?:\n|$)/i',
        ],
        'consignee' => [
            '/Consignee\s*:?\s*([A-Z][A-Za-z0-9\s,\.\-]+?)(?:\n|Date|Carrier)/i',
            '/Ship\s+To\s*:?\s*([A-Z][A-Za-z0-9\s,\.\-]+?)(?:\n|$)/i',
            '/Deliver\s+To\s*:?\s*([A-Z][A-Za-z0-9\s,\.\-]+?)(?:\n|$)/i',
        ],
        'trailer_number' => [
            '/Trailer\s*#?\s*:?\s*([A-Z0-9\-]+)/i',
            '/Trailer\s+Number\s*:?\s*([A-Z0-9\-]+)/i',
            '/Trailer\s+ID\s*:?\s*([A-Z0-9\-]+)/i',
        ],
        'plant' => [
            '/Plant\s*#?\s*:?\s*([A-Z0-9\s\-]+?)(?:\n|$)/i',
            '/Location\s*:?\s*([A-Z0-9\s\-]+?)(?:\n|$)/i',
            '/Facility\s*:?\s*([A-Z][A-Za-z0-9\s\-]+?)(?:\n|$)/i',
        ],
        'ship_date' => [
            '/Ship\s+Date\s*:?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
            '/Date\s*:?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i',
            '/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/i',
        ],
        'weight' => [
            '/Weight\s*:?\s*([\d,\.]+)\s*(?:lbs?|pounds?|kg)?/i',
            '/Total\s+Weight\s*:?\s*([\d,\.]+)/i',
            '/Gross\s+Weight\s*:?\s*([\d,\.]+)/i',
        ],
        'pallet_count' => [
            '/Pallets?\s*:?\s*(\d+)/i',
            '/Pallet\s+Count\s*:?\s*(\d+)/i',
            '/Pieces\s*:?\s*(\d+)/i',
        ],
        'seal_number' => [
            '/Seal\s*#?\s*:?\s*([A-Z0-9\-]+)/i',
            '/Seal\s+Number\s*:?\s*([A-Z0-9\-]+)/i',
        ],
    ];

    /**
     * Extract all metadata from text
     */
    public function extract(string $text): array
    {
        $metadata = [
            'bol_number' => $this->extractField($text, 'bol_number'),
            'carrier' => $this->extractField($text, 'carrier'),
            'shipper' => $this->extractField($text, 'shipper'),
            'consignee' => $this->extractField($text, 'consignee'),
            'trailer_number' => $this->extractField($text, 'trailer_number'),
            'plant' => $this->extractField($text, 'plant'),
            'ship_date' => $this->extractDate($text),
            'weight' => $this->extractWeight($text),
            'pallet_count' => $this->extractPalletCount($text),
            'seal_number' => $this->extractField($text, 'seal_number'),
        ];

        return $metadata;
    }

    /**
     * Extract a field using its patterns
     */
    private function extractField(string $text, string $fieldName): ?string
    {
        if (!isset($this->patterns[$fieldName])) {
            return null;
        }

        foreach ($this->patterns[$fieldName] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = trim($matches[1]);

                // Clean up the value
                $value = $this->cleanValue($value);

                if (!empty($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Extract and normalize date
     */
    private function extractDate(string $text): ?string
    {
        foreach ($this->patterns['ship_date'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $dateStr = $matches[1];

                // Try to parse the date
                $date = $this->parseDate($dateStr);
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }

    /**
     * Parse date string into YYYY-MM-DD format
     */
    private function parseDate(string $dateStr): ?string
    {
        // Try various date formats
        $formats = [
            'm/d/Y',
            'm-d-Y',
            'm/d/y',
            'm-d-y',
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Extract weight and convert to decimal
     */
    private function extractWeight(string $text): ?float
    {
        foreach ($this->patterns['weight'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $weightStr = $matches[1];

                // Remove commas and convert to float
                $weight = (float)str_replace(',', '', $weightStr);

                if ($weight > 0) {
                    return $weight;
                }
            }
        }

        return null;
    }

    /**
     * Extract pallet count
     */
    private function extractPalletCount(string $text): ?int
    {
        foreach ($this->patterns['pallet_count'] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $count = (int)$matches[1];

                if ($count > 0) {
                    return $count;
                }
            }
        }

        return null;
    }

    /**
     * Clean extracted value
     */
    private function cleanValue(string $value): string
    {
        // Remove extra whitespace
        $value = preg_replace('/\s+/', ' ', $value);

        // Remove trailing punctuation
        $value = rtrim($value, '.,;:');

        return trim($value);
    }

    /**
     * Update patterns (for runtime configuration)
     */
    public function setPatterns(array $patterns): void
    {
        $this->patterns = array_merge($this->patterns, $patterns);
    }

    /**
     * Get current patterns
     */
    public function getPatterns(): array
    {
        return $this->patterns;
    }
}
