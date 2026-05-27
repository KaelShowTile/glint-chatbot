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
        $modelName = $this->settings['embedding_model_name'] ?? 'gemini-embedding-001';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:embedContent?key={$apiKey}";
        
        $response = $client->post($url, [
            'json' => [
                'model' => "models/{$modelName}",
                'content' => [
                    'parts' => [['text' => $text]]
                ],
                'outputDimensionality' => 768
            ],
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['embedding']['values'] ?? [];
    }
    
    public function getSearchIntent(string $query): string {
        $prompt = "Extract a clean, emotionless search intent or keyword list from the following user query. Return ONLY the search terms. Do not add any conversational text.\nQuery: " . $query;
        $result = $this->chat($prompt, [['role' => 'user', 'content' => $query]], false);
        return is_array($result) ? $result['text'] : $result;
    }

    public function extractTextFromAudio(string $audioBase64, string $mimeType): string {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Gemini API Key is not set.");
        $modelName = $this->settings['llm_model_name'] ?? 'gemini-2.5-flash';
        
        $client = new Client();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";
        
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => "Please transcribe exactly what is said in this audio. Output ONLY the transcription, without any markdown or extra conversational text."
                        ],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $audioBase64
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1
            ]
        ];

        $response = $client->post($url, [
            'json' => $payload,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $firstCandidate = $data['candidates'][0] ?? null;
        return $firstCandidate['content']['parts'][0]['text'] ?? "";
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

        $agentFunctionsMap = [];
        if ($allowTools) {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM agent_functions");
            $agentFunctions = $stmt->fetchAll();
            
            $functionDeclarations = [];
            
            if (($this->settings['enable_escalate_email'] ?? '') == '1') {
                $functionDeclarations[] = [
                    'name' => 'contact_human',
                    'description' => 'Escalate the conversation to a human customer service representative. Only call this when the user explicitly agrees to be contacted.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'summary' => ['type' => 'STRING', 'description' => 'A detailed summary of the user issue.']
                        ],
                        'required' => ['summary']
                    ]
                ];
            }

            $fixSchemaTypes = function($schema) use (&$fixSchemaTypes) {
                if (is_array($schema)) {
                    foreach ($schema as $k => $v) {
                        if ($k === 'type' && is_string($v)) {
                            $schema[$k] = strtoupper($v);
                        } elseif (is_array($v)) {
                            $schema[$k] = $fixSchemaTypes($v);
                        }
                    }
                }
                return $schema;
            };

            foreach ($agentFunctions as $fn) {
                $agentFunctionsMap[$fn['call_id']] = $fn['js_code'];
                $decl = [
                    'name' => $fn['call_id'],
                    'description' => $fn['description']
                ];
                if (!empty($fn['parameters_schema'])) {
                    $schema = json_decode($fn['parameters_schema'], true);
                    if ($schema) {
                        $decl['parameters'] = $fixSchemaTypes($schema);
                    }
                }
                $functionDeclarations[] = $decl;
            }

            if (!empty($functionDeclarations)) {
                $payload['tools'] = [['functionDeclarations' => $functionDeclarations]];
            }
        }

        $response = $client->post($url, [
            'json' => $payload,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $firstCandidate = $data['candidates'][0] ?? null;
        if (!$firstCandidate) return ['text' => "Error generating response.", 'execute_js' => null];

        $text = "";
        $executeJs = null;
        $executeArgs = [];

        $parts = $firstCandidate['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fnName = $part['functionCall']['name'];
                $args = $part['functionCall']['args'] ?? [];
                if ($fnName === 'contact_human') {
                    $summary = $args['summary'] ?? 'No summary provided.';
                    EmailService::sendEscalationEmail($summary);
                    $text = $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
                } elseif (isset($agentFunctionsMap[$fnName])) {
                    $executeJs = $agentFunctionsMap[$fnName];
                    $executeArgs = $args;
                    if (empty($text)) {
                        $text = "Processing request...";
                    }
                }
            }
        }

        if (empty($text) && !$executeJs) {
            $text = "No response text.";
        }

        return ['text' => $text, 'execute_js' => $executeJs, 'execute_args' => $executeArgs];
    }

    public function chatWithAudioOut(string $systemPrompt, array $messages, bool $allowTools = false) {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Gemini API Key is not set.");
        $modelName = $this->settings['tts_model_name'] ?? $this->settings['llm_model_name'] ?? 'gemini-2.5-flash';

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
                'temperature' => 0.1,
                'responseModalities' => ['TEXT', 'AUDIO']
            ]
        ];

        $agentFunctionsMap = [];
        if ($allowTools) {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM agent_functions");
            $agentFunctions = $stmt->fetchAll();
            
            $functionDeclarations = [];
            
            if (($this->settings['enable_escalate_email'] ?? '') == '1') {
                $functionDeclarations[] = [
                    'name' => 'contact_human',
                    'description' => 'Escalate the conversation to a human customer service representative.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'summary' => ['type' => 'STRING', 'description' => 'Summary of issue']
                        ],
                        'required' => ['summary']
                    ]
                ];
            }

            $fixSchemaTypes = function($schema) use (&$fixSchemaTypes) {
                if (is_array($schema)) {
                    foreach ($schema as $k => $v) {
                        if ($k === 'type' && is_string($v)) {
                            $schema[$k] = strtoupper($v);
                        } elseif (is_array($v)) {
                            $schema[$k] = $fixSchemaTypes($v);
                        }
                    }
                }
                return $schema;
            };

            foreach ($agentFunctions as $fn) {
                $agentFunctionsMap[$fn['call_id']] = $fn['js_code'];
                $decl = [
                    'name' => $fn['call_id'],
                    'description' => $fn['description']
                ];
                if (!empty($fn['parameters_schema'])) {
                    $schema = json_decode($fn['parameters_schema'], true);
                    if ($schema) {
                        $decl['parameters'] = $fixSchemaTypes($schema);
                    }
                }
                $functionDeclarations[] = $decl;
            }

            if (!empty($functionDeclarations)) {
                $payload['tools'] = [['functionDeclarations' => $functionDeclarations]];
            }
        }

        $response = $client->post($url, [
            'json' => $payload,
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $firstCandidate = $data['candidates'][0] ?? null;
        if (!$firstCandidate) return ['text' => "Error generating response.", 'audioBase64' => null, 'execute_js' => null];

        $text = "";
        $audioBase64 = null;
        $executeJs = null;
        $executeArgs = [];

        foreach ($firstCandidate['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
            if (isset($part['inlineData'])) {
                $audioBase64 = $part['inlineData']['data'];
            }
            if (isset($part['functionCall'])) {
                $fnName = $part['functionCall']['name'];
                $args = $part['functionCall']['args'] ?? [];
                if ($fnName === 'contact_human') {
                    $summary = $args['summary'] ?? 'No summary provided.';
                    EmailService::sendEscalationEmail($summary);
                    $text = $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
                } elseif (isset($agentFunctionsMap[$fnName])) {
                    $executeJs = $agentFunctionsMap[$fnName];
                    $executeArgs = $args;
                    $text = "Processing request...";
                }
            }
        }

        return ['text' => trim($text), 'audioBase64' => $audioBase64, 'execute_js' => $executeJs, 'execute_args' => $executeArgs];
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

        $agentFunctionsMap = [];
        if ($allowTools) {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT * FROM agent_functions");
            $agentFunctions = $stmt->fetchAll();
            
            $tools = [];
            if (($this->settings['enable_escalate_email'] ?? '') == '1') {
                $tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => 'contact_human',
                        'description' => 'Escalate the conversation to a human customer service representative. Only call this when the user explicitly agrees to be contacted.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => ['type' => 'string', 'description' => 'A detailed summary of the user issue.']
                            ],
                            'required' => ['summary']
                        ]
                    ]
                ];
            }

            foreach ($agentFunctions as $fn) {
                $agentFunctionsMap[$fn['call_id']] = $fn['js_code'];
                $decl = [
                    'name' => $fn['call_id'],
                    'description' => $fn['description']
                ];
                if (!empty($fn['parameters_schema'])) {
                    $schema = json_decode($fn['parameters_schema'], true);
                    if ($schema) {
                        $decl['parameters'] = $schema;
                    }
                }
                $tools[] = [
                    'type' => 'function',
                    'function' => $decl
                ];
            }

            if (!empty($tools)) {
                $payload['tools'] = $tools;
                $payload['tool_choice'] = 'auto';
            }
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
        if (!$firstChoice) return ['text' => "Error generating response.", 'execute_js' => null];

        $message = $firstChoice['message'] ?? [];
        $text = $message['content'] ?? "No response text.";
        $executeJs = null;
        $executeArgs = [];

        if (isset($message['tool_calls'])) {
            $toolCall = $message['tool_calls'][0] ?? null;
            if ($toolCall) {
                $fnName = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'], true) ?? [];
                if ($fnName === 'contact_human') {
                    $summary = $args['summary'] ?? 'No summary provided.';
                    EmailService::sendEscalationEmail($summary);
                    $text = $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
                } elseif (isset($agentFunctionsMap[$fnName])) {
                    $executeJs = $agentFunctionsMap[$fnName];
                    $executeArgs = $args;
                    $text = "Processing request...";
                }
            }
        }

        return ['text' => $text, 'execute_js' => $executeJs, 'execute_args' => $executeArgs];
    }
}
