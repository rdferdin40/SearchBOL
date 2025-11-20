<?php
/**
 * OCR and Text Extraction Configuration
 */

return [
    // Path to Tesseract binary
    'tesseract_path' => '/usr/bin/tesseract',

    // Path to pdftotext binary (poppler-utils)
    'pdftotext_path' => '/usr/bin/pdftotext',

    // Path to pdftoppm binary (poppler-utils)
    'pdftoppm_path' => '/usr/bin/pdftoppm',

    // Path to pdfinfo binary (poppler-utils)
    'pdfinfo_path' => '/usr/bin/pdfinfo',

    // ImageMagick convert binary (alternative to pdftoppm)
    'convert_path' => '/usr/bin/convert',

    // Tesseract settings
    'tesseract' => [
        // Language(s) to use (e.g., 'eng', 'eng+spa')
        'language' => 'eng',

        // OCR Engine Mode (OEM)
        // 0 = Legacy engine only
        // 1 = Neural nets LSTM engine only
        // 2 = Legacy + LSTM engines
        // 3 = Default, based on what is available
        'oem' => 3,

        // Page Segmentation Mode (PSM)
        // 0 = Orientation and script detection (OSD) only
        // 1 = Automatic page segmentation with OSD
        // 3 = Fully automatic page segmentation, but no OSD (Default)
        // 6 = Assume a single uniform block of text
        'psm' => 3,

        // Configuration variables
        'config_vars' => [
            // Preserve interword spaces
            'preserve_interword_spaces' => '1',
        ],

        // Timeout for OCR processing (seconds per page)
        'timeout_per_page' => 60,
    ],

    // PDF to image conversion settings
    'pdf_to_image' => [
        // Resolution (DPI) for rendering PDF pages
        // Higher = better quality but slower and more memory
        'dpi' => 300,

        // Image format (png, jpg, tiff)
        'format' => 'png',

        // JPEG quality (if format is jpg)
        'jpeg_quality' => 90,
    ],

    // Text extraction settings
    'text_extraction' => [
        // Minimum text length to consider a PDF as "digital" (has text)
        'min_digital_text_length' => 50,

        // Maximum text length to store (for very large documents)
        // Set to 0 for unlimited
        'max_text_length' => 0,

        // pdftotext layout mode
        // -layout: maintain original physical layout
        // -raw: keep strings in content stream order
        'pdftotext_mode' => '-layout',
    ],

    // Retry settings
    'retry' => [
        // Number of retry attempts for failed OCR
        'max_attempts' => 2,

        // Alternative settings for retry (more aggressive preprocessing)
        'aggressive_mode' => [
            'dpi' => 400,
            'tesseract_psm' => 6,
        ],
    ],

    // Temporary directory for image conversion
    'temp_dir' => '/tmp/bolsearch_ocr',

    // Maximum number of pages to OCR per document
    // Set to 0 for unlimited (be careful with very large PDFs)
    'max_pages_per_document' => 0,

    // Cleanup temporary files after processing
    'cleanup_temp_files' => true,
];
