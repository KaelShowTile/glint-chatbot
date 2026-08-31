<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Database;

class VectorService {
    private Client $client;
    private string $collectionName = 'ai_customer_service';
    private string $apiKey;
    private bool $isConfigured = false;

    public function __construct() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings WHERE key IN ('qdrant_url', 'qdrant_api_key')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $url = rtrim($settings['qdrant_url'] ?? '', '/');
        $this->apiKey = $settings['qdrant_api_key'] ?? '';

        if (!empty($url)) {
            $this->isConfigured = true;
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            if (!empty($this->apiKey)) {
                $headers['api-key'] = $this->apiKey;
            }

            $this->client = new Client([
                'base_uri' => $url . '/',
                'headers' => $headers,
                'timeout' => 10.0
            ]);
            
            $this->ensureCollectionExists();
        }
    }

    private function ensureCollectionExists() {
        try {
            $this->client->get("collections/{$this->collectionName}");
        } catch (\Exception $e) {
            // Collection might not exist, let's create it. Gemini embedding size is 768
            try {
                $this->client->put("collections/{$this->collectionName}", [
                    'json' => [
                        'vectors' => [
                            'size' => 768,
                            'distance' => 'Cosine'
                        ],
                        'sparse_vectors' => [
                            'text_sparse' => [
                                'index' => [
                                    'on_disk' => true
                                ]
                            ]
                        ]
                    ]
                ]);
            } catch (\Exception $e2) {
                error_log("Failed to create Qdrant collection: " . $e2->getMessage());
            }
        }
    }

    public function resetCollection() {
        if (!$this->isConfigured) return;
        try {
            $this->client->delete("collections/{$this->collectionName}");
        } catch (\Exception $e) {
            // Ignore if it doesn't exist
        }
        $this->ensureCollectionExists();
    }

    public function upsert(string $id, array $vector, array $payload, array $sparseVector = []) {
        if (!$this->isConfigured) return;
        
        $point = [
            'id' => $id,
            'vector' => $vector,
            'payload' => $payload
        ];
        
        if (!empty($sparseVector)) {
            $point['vector'] = [
                '' => $vector, // Default dense vector
                'text_sparse' => $sparseVector
            ];
        }
        
        $this->client->put("collections/{$this->collectionName}/points", [
            'json' => [
                'points' => [$point]
            ]
        ]);
    }

    public function delete(string $id) {
        if (!$this->isConfigured) return;
        $this->client->post("collections/{$this->collectionName}/points/delete", [
            'json' => [
                'points' => [$id]
            ]
        ]);
    }

    public function getPoint(string $id): ?array {
        if (!$this->isConfigured) return null;
        try {
            $response = $this->client->get("collections/{$this->collectionName}/points/{$id}");
            $data = json_decode($response->getBody()->getContents(), true);
            if (!empty($data['result'])) {
                return $data['result'];
            }
        } catch (\Exception $e) {
            // Point not found
        }
        return null;
    }

    public function search(array $vector, array $sparseVector = [], int $limit = 5): array {
        if (!$this->isConfigured) return [];
        try {
            if (empty($sparseVector)) {
                // Fallback to dense only search if no sparse vector
                $response = $this->client->post("collections/{$this->collectionName}/points/search", [
                    'json' => [
                        'vector' => $vector,
                        'limit' => $limit,
                        'with_payload' => true
                    ]
                ]);
            } else {
                // Hybrid Search using RRF (Qdrant 1.10+)
                $response = $this->client->post("collections/{$this->collectionName}/points/query", [
                    'json' => [
                        'prefetch' => [
                            [
                                'query' => $vector,
                                'limit' => $limit * 2 // Fetch more for better fusion
                            ],
                            [
                                'query' => $sparseVector,
                                'using' => 'text_sparse',
                                'limit' => $limit * 2
                            ]
                        ],
                        'query' => [
                            'fusion' => 'rrf'
                        ],
                        'limit' => $limit,
                        'with_payload' => true
                    ]
                ]);
            }
            
            $data = json_decode($response->getBody()->getContents(), true);
            $result = $data['result'] ?? [];
            if (isset($result['points'])) {
                return $result['points'];
            }
            return $result;
        } catch (\Exception $e) {
            error_log("Qdrant Search Error: " . $e->getMessage());
            return [];
        }
    }

    public static function generateUuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
