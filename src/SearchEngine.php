<?php
/**
 * Meilisearch Integration
 */

namespace BOLSearch;

use Exception;

class SearchEngine
{
    private string $host;
    private string $apiKey;
    private string $indexName;
    private array $config;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->host = rtrim($config['host'], '/');
        $this->apiKey = $config['api_key'] ?? '';
        $this->indexName = $config['index_name'];
        $this->config = $config;
        $this->timeout = $config['timeout'] ?? 30;
    }

    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->host . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $headers = ['Content-Type: application/json'];
        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Meilisearch request failed: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = $result['message'] ?? 'Unknown error';
            throw new Exception("Meilisearch error (HTTP $httpCode): $message");
        }
        
        return $result;
    }

    public function health(): bool
    {
        try {
            $this->request('GET', '/health');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getStats(): array
    {
        try {
            return $this->request('GET', "/indexes/{$this->indexName}/stats");
        } catch (Exception $e) {
            return ['numberOfDocuments' => 0, 'isIndexing' => false];
        }
    }

    public function initializeIndex(): void
    {
        try {
            $this->request('POST', '/indexes', [
                'uid' => $this->indexName,
                'primaryKey' => 'id',
            ]);
        } catch (Exception $e) {
            // Index might already exist
        }
        
        if (isset($this->config['index_settings'])) {
            $this->request(
                'PATCH',
                "/indexes/{$this->indexName}/settings",
                $this->config['index_settings']
            );
        }
    }

    public function indexDocument(array $document): void
    {
        $this->indexDocuments([$document]);
    }

    public function indexDocuments(array $documents): array
    {
        if (empty($documents)) {
            return [];
        }
        
        return $this->request(
            'POST',
            "/indexes/{$this->indexName}/documents",
            $documents
        );
    }

    public function deleteDocument(int $id): void
    {
        $this->request('DELETE', "/indexes/{$this->indexName}/documents/$id");
    }

    public function deleteDocuments(array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        
        $this->request(
            'POST',
            "/indexes/{$this->indexName}/documents/delete-batch",
            $ids
        );
    }

    public function search(
        string $query,
        array $filters = [],
        int $page = 1,
        int $perPage = 50,
        ?array $sort = null
    ): array {
        $offset = ($page - 1) * $perPage;
        
        $searchParams = [
            'q' => $query,
            'offset' => $offset,
            'limit' => $perPage,
            'attributesToHighlight' => ['text_content', 'bol_number', 'carrier'],
            'highlightPreTag' => $this->config['search']['highlight_pre_tag'] ?? '<mark>',
            'highlightPostTag' => $this->config['search']['highlight_post_tag'] ?? '</mark>',
            'attributesToCrop' => ['text_content'],
            'cropLength' => $this->config['search']['snippet_length'] ?? 200,
        ];
        
        if (!empty($filters)) {
            $filterParts = [];
            
            foreach ($filters as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                
                if (is_array($value)) {
                    if (isset($value['min']) && $value['min'] !== '') {
                        $filterParts[] = "$field >= " . $this->escapeFilterValue($value['min']);
                    }
                    if (isset($value['max']) && $value['max'] !== '') {
                        $filterParts[] = "$field <= " . $this->escapeFilterValue($value['max']);
                    }
                } else {
                    $filterParts[] = "$field = " . $this->escapeFilterValue($value);
                }
            }
            
            if (!empty($filterParts)) {
                $searchParams['filter'] = $filterParts;
            }
        }
        
        if ($sort !== null && !empty($sort)) {
            $searchParams['sort'] = $sort;
        }
        
        try {
            $result = $this->request(
                'POST',
                "/indexes/{$this->indexName}/search",
                $searchParams
            );
            
            return [
                'hits' => $result['hits'] ?? [],
                'total' => $result['estimatedTotalHits'] ?? 0,
                'page' => $page,
                'perPage' => $perPage,
                'processingTimeMs' => $result['processingTimeMs'] ?? 0,
            ];
        } catch (Exception $e) {
            return [
                'hits' => [],
                'total' => 0,
                'page' => $page,
                'perPage' => $perPage,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function escapeFilterValue($value): string
    {
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        return '"' . str_replace('"', '\\"', $value) . '"';
    }

    public function clearIndex(): void
    {
        $this->request('DELETE', "/indexes/{$this->indexName}/documents");
    }

    public function deleteIndex(): void
    {
        $this->request('DELETE', "/indexes/{$this->indexName}");
    }
}
