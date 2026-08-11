<?php
namespace App\Services;

use GuzzleHttp\Client;
use App\Database;

class LlmService
{
    private array $settings;

    public function __construct()
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings");
        $this->settings = [];
        while ($row = $stmt->fetch()) {
            $this->settings[$row['key']] = $row['value'];
        }
    }

    public function embed(?string $text = null, ?string $imageBase64 = null, ?string $mimeType = 'image/jpeg'): array
    {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey))
            throw new \Exception("Gemini API Key is not set.");

        $client = new Client();
        $modelName = $this->settings['embedding_model_name'] ?? 'gemini-embedding-2'; // Updated to use multimodal by default if not set or set to old one
        if ($modelName === 'gemini-embedding-001') {
            $modelName = 'gemini-embedding-2'; // Force upgrade for multimodal support
        }
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:embedContent?key={$apiKey}";

        $parts = [];
        if (!empty($text)) {
            $parts[] = ['text' => $text];
        }
        if (!empty($imageBase64)) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $mimeType,
                    'data' => $imageBase64
                ]
            ];
        }

        if (empty($parts)) {
            throw new \Exception("Must provide either text or image for embedding.");
        }

        $response = $client->post($url, [
            'json' => [
                'model' => "models/{$modelName}",
                'content' => [
                    'parts' => $parts
                ],
                'outputDimensionality' => 768
            ],
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['embedding']['values'] ?? [];
    }

    public function generateSparseVector(string $text): array
    {
        $text = strtolower($text);
        // Remove punctuation
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $frequencies = array_count_values($words);
        
        $indices = [];
        $values = [];
        
        foreach ($frequencies as $word => $count) {
            // Hashing trick to map words to a fixed size vocabulary (e.g., 1 million)
            $index = crc32($word) % 1000000;
            if ($index < 0) $index += 1000000;
            
            // Handle hash collisions simply by adding counts (simple term frequency)
            $pos = array_search($index, $indices);
            if ($pos !== false) {
                $values[$pos] += $count;
            } else {
                $indices[] = $index;
                $values[] = (float)$count; // Simple TF
            }
        }
        
        // Qdrant requires indices to be sorted
        array_multisort($indices, SORT_ASC, $values);
        
        return [
            'indices' => $indices,
            'values' => $values
        ];
    }

    public function detectObjectsInImage(string $imageBase64, string $mimeType): array
    {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey)) throw new \Exception("Gemini API Key is not set.");

        $client = new Client();
        $modelName = $this->settings['vision_model_name'] ?? 'gemini-2.5-pro';
        if (empty($modelName)) $modelName = 'gemini-2.5-pro';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $prompt = "Detect the main tiles (e.g., wall tiles, floor tiles, ceramic tiles, mosaic tiles) in this image that a user might want to search for in a tile e-commerce store. ONLY detect tiles; ignore people, furniture, plants, or other irrelevant objects. Return the result strictly as a JSON array of objects, where each object has a 'tag' (string) describing the tile, and a 'box' array [ymin, xmin, ymax, xmax] representing the relative bounding box coordinates (values between 0 and 1000, consistent with Gemini 2D spatial coordinates). For example: [{\"tag\": \"blue mosaic tile\", \"box\": [100, 200, 900, 800]}]. If you cannot detect any distinct tiles, return an empty array []. Do not include any other text or markdown formatting outside the JSON array.";

        $response = $client->post($url, [
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            ['inlineData' => ['mimeType' => $mimeType, 'data' => $imageBase64]]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1
                ],
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
                ]
            ],
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $text = '[]';
        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Clean markdown if present
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);
        
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getSearchIntent(string $query): string
    {
        $prompt = "Extract a clean, emotionless search intent or keyword list from the following user query. Return ONLY the search terms. Do not add any conversational text.\nQuery: " . $query;
        $result = $this->chat($prompt, [['role' => 'user', 'content' => $query]], false);
        return is_array($result) ? $result['text'] : $result;
    }

    public function extractTextFromAudio(string $audioBase64, string $mimeType): string
    {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey))
            throw new \Exception("Gemini API Key is not set.");
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

    public function chat(string $systemPrompt, array $messages, bool $allowTools = false, ?string $sessionId = null)
    {
        $provider = $this->settings['llm_provider'] ?? 'gemini';

        if ($provider === 'groq') {
            return $this->chatGroq($systemPrompt, $messages, $allowTools, $sessionId);
        } else {
            return $this->chatGemini($systemPrompt, $messages, $allowTools, $sessionId);
        }
    }

    private function chatGemini(string $systemPrompt, array $messages, bool $allowTools, ?string $sessionId = null)
    {
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        if (empty($apiKey))
            throw new \Exception("Gemini API Key is not set.");

        $modelName = $this->settings['llm_model_name'] ?? 'gemini-2.5-flash';
        $client = new Client();
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $geminiMessages = [];
        foreach ($messages as $msg) {
            $parts = [['text' => $msg['content']]];
            if (!empty($msg['image'])) {
                $parts[] = [
                    'inlineData' => [
                        'mimeType' => $msg['mimeType'] ?? 'image/jpeg',
                        'data' => $msg['image']
                    ]
                ];
            }
            $geminiMessages[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => $parts
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
            if ($sessionId !== null) {
                $functionDeclarations[] = [
                    'name' => 'save_customer_info',
                    'description' => 'Save the customer\'s email address and/or physical address into the database when they provide it.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'customer_email' => ['type' => 'STRING', 'description' => 'The customer\'s email address.'],
                            'customer_address' => ['type' => 'STRING', 'description' => 'The customer\'s physical address.'],
                            'customer_contact_number' => ['type' => 'STRING', 'description' => 'The customer\'s contact phone number.']
                        ]
                    ]
                ];
            }

            $fixSchemaTypes = function ($schema) use (&$fixSchemaTypes) {
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
        if (!$firstCandidate)
            return ['text' => "Error generating response.", 'execute_js' => null];

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
                
                if ($sessionId) {
                    $this->logFunctionCall($sessionId, $fnName);
                }

                if ($fnName === 'contact_human') {
                    $summary = $args['summary'] ?? 'No summary provided.';
                    EmailService::sendEscalationEmail($summary, $sessionId);
                    $text = $this->settings['escalation_message'] ?? 'Got it! I’ll pass it on to our team to assist you. Is there anything I can help you with for now?.';
                } elseif ($fnName === 'save_customer_info' && $sessionId !== null) {
                    $email = $args['customer_email'] ?? null;
                    $address = $args['customer_address'] ?? null;
                    $contact = $args['customer_contact_number'] ?? null;
                    $db = Database::getConnection();
                    $updates = [];
                    $params = [];
                    if ($email) {
                        $updates[] = "customer_email = ?";
                        $params[] = $email;
                    }
                    if ($address) {
                        $updates[] = "customer_address = ?";
                        $params[] = $address;
                    }
                    if ($contact) {
                        $updates[] = "customer_contact_number = ?";
                        $params[] = $contact;
                    }
                    if (!empty($updates)) {
                        $sql = "UPDATE chat_sessions SET " . implode(", ", $updates) . " WHERE session_id = ?";
                        $params[] = $sessionId;
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                    }
                    $text = "Got it! How can I help you now?";
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

    public function chatWithAudioOut(string $systemPrompt, array $messages, bool $allowTools = false, ?string $sessionId = null)
    {
        // 1. Get the normal text/tool response from the main chat method
        $replyData = $this->chat($systemPrompt, $messages, $allowTools, $sessionId);
        $text = $replyData['text'] ?? '';
        $executeJs = $replyData['execute_js'] ?? null;
        $executeArgs = $replyData['execute_args'] ?? null;
        
        $audioBase64 = $this->generateAudioBase64($text);

        return [
            'text' => $text,
            'audioBase64' => $audioBase64,
            'execute_js' => $executeJs,
            'execute_args' => $executeArgs
        ];
    }

    public function generateAudioBase64(string $text): ?string
    {
        $audioBase64 = null;
        $apiKey = $this->settings['gemini_api_key'] ?? '';
        $ttsModelName = $this->settings['tts_model_name'] ?? 'gemini-2.5-flash-preview-tts';
        
        if (!empty($apiKey) && !empty($text) && $text !== 'Processing request...' && $text !== 'No response text.') {
            try {
                $client = new Client();
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$ttsModelName}:generateContent?key={$apiKey}";
                $payload = [
                    'contents' => [['role' => 'user', 'parts' => [['text' => "Please generate audio for this exact text:\n" . $text]]]],
                    'generationConfig' => [
                        'responseModalities' => ['AUDIO']
                    ]
                ];
                $response = $client->post($url, [
                    'json' => $payload,
                    'headers' => ['Content-Type' => 'application/json']
                ]);
                $data = json_decode($response->getBody()->getContents(), true);
                if (isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])) {
                    $rawBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
                    $pcmData = base64_decode($rawBase64);
                    
                    // Gemini TTS returns raw 24kHz 16-bit mono PCM. We must add a 44-byte WAV header.
                    $sampleRate = 24000;
                    $channels = 1;
                    $bitsPerSample = 16;
                    $dataLength = strlen($pcmData);
                    $fileLength = $dataLength + 36;
                    $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
                    $blockAlign = $channels * ($bitsPerSample / 8);

                    $header = pack('a4V a4a4V v v V V v v a4V', 
                        'RIFF', $fileLength, 'WAVE', 'fmt ', 16, 
                        1, $channels, $sampleRate, $byteRate, 
                        $blockAlign, $bitsPerSample, 'data', $dataLength
                    );
                    $wavData = $header . $pcmData;
                    $audioBase64 = base64_encode($wavData);
                }
            } catch (\Exception $e) {
                error_log("TTS Audio Generation Error: " . $e->getMessage());
            }
        }
        return $audioBase64;
    }

    private function chatGroq(string $systemPrompt, array $messages, bool $allowTools, ?string $sessionId = null)
    {
        $apiKey = $this->settings['groq_api_key'] ?? '';
        if (empty($apiKey))
            throw new \Exception("Groq API Key is not set.");

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
            if ($sessionId !== null) {
                $tools[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => 'save_customer_info',
                        'description' => 'Save the customer\'s email address and/or physical address into the database when they provide it.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'customer_email' => ['type' => 'string', 'description' => 'The customer\'s email address.'],
                                'customer_address' => ['type' => 'string', 'description' => 'The customer\'s physical address.'],
                                'customer_contact_number' => ['type' => 'string', 'description' => 'The customer\'s contact phone number.']
                            ]
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
        if (!$firstChoice)
            return ['text' => "Error generating response.", 'execute_js' => null];

        $message = $firstChoice['message'] ?? [];
        $text = $message['content'] ?? "No response text.";
        $executeJs = null;
        $executeArgs = [];

        if (isset($message['tool_calls'])) {
            $toolCall = $message['tool_calls'][0] ?? null;
            if ($toolCall) {
                $fnName = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'], true) ?? [];
                
                if ($sessionId) {
                    $this->logFunctionCall($sessionId, $fnName);
                }

                if ($fnName === 'contact_human') {
                    $summary = $args['summary'] ?? 'No summary provided.';
                    EmailService::sendEscalationEmail($summary, $sessionId);
                    $text = $this->settings['escalation_message'] ?? 'We have escalated your issue to our staff. They will contact you shortly.';
                } elseif ($fnName === 'save_customer_info' && $sessionId !== null) {
                    $email = $args['customer_email'] ?? null;
                    $address = $args['customer_address'] ?? null;
                    $contact = $args['customer_contact_number'] ?? null;
                    $db = Database::getConnection();
                    $updates = [];
                    $params = [];
                    if ($email) {
                        $updates[] = "customer_email = ?";
                        $params[] = $email;
                    }
                    if ($address) {
                        $updates[] = "customer_address = ?";
                        $params[] = $address;
                    }
                    if ($contact) {
                        $updates[] = "customer_contact_number = ?";
                        $params[] = $contact;
                    }
                    if (!empty($updates)) {
                        $sql = "UPDATE chat_sessions SET " . implode(", ", $updates) . " WHERE session_id = ?";
                        $params[] = $sessionId;
                        $stmt = $db->prepare($sql);
                        $stmt->execute($params);
                    }
                    $text = "Got it! How can I help you now?";
                } elseif (isset($agentFunctionsMap[$fnName])) {
                    $executeJs = $agentFunctionsMap[$fnName];
                    $executeArgs = $args;
                    $text = "Processing request...";
                }
            }
        }

        return ['text' => $text, 'execute_js' => $executeJs, 'execute_args' => $executeArgs];
    }

    private function logFunctionCall(string $sessionId, string $functionName)
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("INSERT INTO agent_function_logs (session_id, function_name) VALUES (?, ?)");
            $stmt->execute([$sessionId, $functionName]);
        } catch (\Exception $e) {
            error_log("Failed to log function call: " . $e->getMessage());
        }
    }
}
