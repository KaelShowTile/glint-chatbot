<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Database;

class LlmService {
    private array $settings;

    public function __construct() {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings");
        $this->settings = [];
        while ($row = $stmt->fetch()) {
            $this->settings[$row['key']] = $row['value'];
        }
    }

    public function embed(string $text): array {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Gemini API Key is not set.");

        $client = new Client();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key={$apiKey}";
        
        $response = $client->post($url, [
            'json' => [
                'model' => 'models/text-embedding-004',
                'content' => [
                    'parts' => [['text' => $text]]
                ]
            ],
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['embedding']['values'] ?? [];
    }
    
    public function getSearchIntent(string $query): string {
        $prompt = "Extract a clean, emotionless search intent or keyword list from the following user query. Return ONLY the search terms. Do not add any conversational text.\nQuery: " . $query;
        return $this->chat($prompt, [['role' => 'user', 'content' => $query]], false);
    }

    public function chat(string $systemPrompt, array $messages, bool $allowTools = false) {
        $provider = $this->settings['llm_provider'] ?? 'gemini';
        
        if ($provider === 'groq') {
            return $this->chatGroq($systemPrompt, $messages, $allowTools);
        } else {
            return $this->chatGemini($systemPrompt, $messages, $allowTools);
        }
    }

    private function chatGemini(string $systemPrompt, array $messages, bool $allowTools) {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Gemini API Key is not set.");

        $modelName = $this->settings['llm_model_name'] ?? 'gemini-2.5-flash';
        $client = new Client();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
        
        $geminiMessages = [];
        foreach ($messages as $msg) {
            $geminiMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'contents' => $geminiMessages,
            'generationConfig' => [
                'temperature' => 0.1
            ]
        ];

        if ($allowTools && ($this->settings['enable_escalate_email'] ?? '') == '1') {
            $payload['tools'] = [
                [
                    'functionDeclarations' => [
                        [
                            'name' => 'contact_human',
                            'description' => 'Escalate the conversation to a human customer service representative. Only call this when the user explicitly agrees to be contacted.',
                            'parameters' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'summary' => [
                                        'type' => 'STRING',
                                        'description' => 'A detailed summary of the user issue.'
                                    ]
                                ],
                                'required' => ['summary']
                            ]
                        ]
                    ]
                ]
            ];
        }

        $response = $client->post($url, [
            'json' => $payload,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        $firstCandidate = $data['candidates'][0] ?? null;
        if (!$firstCandidate) return "Error generating response.";

        $part = $firstCandidate['content']['parts'][0] ?? [];
        if (isset($part['functionCall'])) {
            if ($part['functionCall']['name'] === 'contact_human') {
                $summary = $part['functionCall']['args']['summary'] ?? 'No summary provided.';
                EmailService::sendEscalationEmail($summary);
                return $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
            }
        }

        return $part['text'] ?? "No response text.";
    }

    private function chatGroq(string $systemPrompt, array $messages, bool $allowTools) {
        $apiKey = $this->settings['groq_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Groq API Key is not set.");

        $modelName = $this->settings['llm_model_name'] ?? 'llama3-8b-8192';
        $client = new Client();
        $url = "https://api.groq.com/openai/v1/chat/completions";
        
        $groqMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];
        
        foreach ($messages as $msg) {
            $groqMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $modelName,
            'messages' => $groqMessages,
            'temperature' => 0.1
        ];

        if ($allowTools && ($this->settings['enable_escalate_email'] ?? '') == '1') {
            $payload['tools'] = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'contact_human',
                        'description' => 'Escalate the conversation to a human customer service representative. Only call this when the user explicitly agrees to be contacted.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => [
                                    'type' => 'string',
                                    'description' => 'A detailed summary of the user issue.'
                                ]
                            ],
                            'required' => ['summary']
                        ]
                    ]
                ]
            ];
            $payload['tool_choice'] = 'auto';
        }

        $response = $client->post($url, [
            'json' => $payload,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json'
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        $firstChoice = $data['choices'][0] ?? null;
        if (!$firstChoice) return "Error generating response.";

        $message = $firstChoice['message'] ?? [];
        if (isset($message['tool_calls'])) {
            $toolCall = $message['tool_calls'][0] ?? null;
            if ($toolCall && $toolCall['function']['name'] === 'contact_human') {
                $args = json_decode($toolCall['function']['arguments'], true);
                $summary = $args['summary'] ?? 'No summary provided.';
                EmailService::sendEscalationEmail($summary);
                return $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
            }
        }

        return $message['content'] ?? "No response text.";
    }
}
