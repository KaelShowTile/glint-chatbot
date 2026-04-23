<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use GuzzleHttp\Client;

class ApiController {
    
    public function listModels(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) {
            return $response->withStatus(401);
        }

        $provider = $request->getQueryParams()['provider'] ?? 'gemini';
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings WHERE key IN ('gemini_api_key', 'groq_api_key')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $client = new Client(['timeout' => 5.0]);
        $models = [];

        try {
            if ($provider === 'groq') {
                $apiKey = $settings['groq_api_key'] ?? '';
                if (empty($apiKey)) throw new \Exception('Groq API key not set');

                $res = $client->get('https://api.groq.com/openai/v1/models', [
                    'headers' => [
                        'Authorization' => "Bearer {$apiKey}"
                    ]
                ]);
                $data = json_decode($res->getBody()->getContents(), true);
                foreach ($data['data'] ?? [] as $model) {
                    $models[] = ['id' => $model['id'], 'name' => $model['id']];
                }
            } else {
                $apiKey = $settings['gemini_api_key'] ?? '';
                if (empty($apiKey)) throw new \Exception('Gemini API key not set');

                $res = $client->get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
                $data = json_decode($res->getBody()->getContents(), true);
                foreach ($data['models'] ?? [] as $model) {
                    if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [])) {
                        $id = str_replace('models/', '', $model['name']);
                        $models[] = ['id' => $id, 'name' => ($model['displayName'] ?? $id) . ' (' . $id . ')'];
                    }
                }
            }

            $response->getBody()->write(json_encode(['models' => $models]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
