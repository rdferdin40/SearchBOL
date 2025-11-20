<?php
/**
 * Meilisearch Configuration
 */

return [
    // Meilisearch server settings
    'host' => 'http://127.0.0.1:7700',

    // Master API key (set this from your Meilisearch installation)
    // Leave empty if running without authentication (development only)
    'api_key' => '',

    // Index name for BOL documents
    'index_name' => 'bol_documents',

    // Index settings
    'index_settings' => [
        // Fields that are searchable
        'searchableAttributes' => [
            'bol_number',
            'carrier',
            'shipper',
            'consignee',
            'trailer_number',
            'plant',
            'seal_number',
            'file_name',
            'text_content',
        ],

        // Fields that can be used for filtering
        'filterableAttributes' => [
            'folder',
            'year',
            'carrier',
            'shipper',
            'consignee',
            'trailer_number',
            'plant',
            'ship_date',
            'weight',
            'pallet_count',
            'seal_number',
            'bol_number',
        ],

        // Fields that can be used for sorting
        'sortableAttributes' => [
            'ship_date',
            'carrier',
            'trailer_number',
            'file_name',
            'year',
            'created_at',
        ],

        // Fields to display in search results
        'displayedAttributes' => [
            'id',
            'file_name',
            'file_path',
            'folder',
            'year',
            'bol_number',
            'carrier',
            'shipper',
            'consignee',
            'trailer_number',
            'plant',
            'ship_date',
            'weight',
            'pallet_count',
            'seal_number',
            'text_content',
        ],

        // Ranking rules (order matters)
        'rankingRules' => [
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        ],

        // Stop words (common words to ignore)
        'stopWords' => [],

        // Synonyms
        'synonyms' => [],
    ],

    // Connection timeout (seconds)
    'timeout' => 30,

    // Search settings
    'search' => [
        // Maximum number of results to return
        'limit' => 1000,

        // Snippet length for text_content
        'snippet_length' => 200,

        // Highlighting tags
        'highlight_pre_tag' => '<mark>',
        'highlight_post_tag' => '</mark>',
    ],
];
