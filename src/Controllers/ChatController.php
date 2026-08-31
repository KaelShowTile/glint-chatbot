<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\LlmService;
use App\Services\VectorService;

class ChatController
{
    public function handleChat(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($data) && $request->getBody()->getSize() > 0) {
            $data = json_decode($request->getBody()->getContents(), true);
        }

        $messagesJson = $data['messages'] ?? '[]';
        $messages = is_string($messagesJson) ? json_decode($messagesJson, true) : $messagesJson;
        $sessionId = $data['session_id'] ?? '';
        $image = $data['image'] ?? '';
        
        $mimeType = 'image/jpeg';
        if (!empty($image)) {
            if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $image, $matches)) {
                $mimeType = $matches[1];
                $image = $matches[2];
            }
        }

        $isVoiceMode = isset($uploadedFiles['audio']);
        $lastMessage = '';

        try {
            $llm = new LlmService();
            $vectorService = new VectorService();

            if ($isVoiceMode) {
                $audioFile = $uploadedFiles['audio'];
                $audioBase64 = base64_encode($audioFile->getStream()->getContents());
                $mimeType = $audioFile->getClientMediaType() ?: 'audio/webm';

                $lastMessage = $llm->extractTextFromAudio($audioBase64, $mimeType);
                $messages[] = ['role' => 'user', 'content' => $lastMessage];
            } else {
                if (empty($messages)) {
                    $response->getBody()->write(json_encode(['error' => 'No messages provided']));
                    return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                $lastMessage = end($messages)['content'] ?? '';
            }

            // Step 1: Extract intent
            $intent = $llm->getSearchIntent($lastMessage);

            // Step 2: Vectorize intent
            if (!empty($image)) {
                $vector = $llm->embed($intent, $image, $mimeType);
            } else {
                $vector = $llm->embed($intent);
            }
            $sparseVector = !empty($intent) ? $llm->generateSparseVector($intent) : [];

            // Step 3: Retrieve context
            $results = $vectorService->search($vector, $sparseVector, 5);

            // Log intent and search results for debugging
            error_log("Search Intent: " . $intent);
            error_log("Qdrant Search Results: " . json_encode($results));

            $contextText = "";
            $seenContents = [];
            foreach ($results as $result) {
                if (isset($result['payload']['search_content'])) {
                    $contentHash = md5($result['payload']['search_content']);
                    if (isset($seenContents[$contentHash])) {
                        continue;
                    }
                    $seenContents[$contentHash] = true;
                    
                    $itemContext = "- " . $result['payload']['search_content'];
                    if (!empty($result['payload']['product_id'])) {
                        $itemContext .= " Product ID: " . $result['payload']['product_id'] . ".";
                    }
                    if (!empty($result['payload']['product_url'])) {
                        $itemContext .= " Product URL: " . $result['payload']['product_url'] . ".";
                    }
                    if (!empty($result['payload']['thumbnail_url'])) {
                        $itemContext .= " Image URL: " . $result['payload']['thumbnail_url'] . ".";
                    }
                    $contextText .= $itemContext . "\n";
                }
            }

            $systemPrompt = "You are a helpful customer support AI for an e-commerce website. Answer the user's queries based on the following context. The context may include general information, faq, and product's details. If the user is asking about products, you MUST use the corresponding tool to display the products if you have such a tool available. Pass the exact Product IDs from the context to the tool. Do NOT list products in plain text if a tool is available. If the context does not contain the answer, politely inform the user that you don't know and ask if they would like you to contact sales team member to assist them. If the user explicitly asks or agrees to contact a sales team member(make sure the user has provided the contact email or phone number, if not ask the user for contact email or phone number first), then use the `contact_human` tool.\n\nCRITICAL INSTRUCTION: If the user provides their email address, physical address, or contact number at any point, or if you ask for it and they provide it, you MUST use the `save_customer_info` tool to save this information.\n\nContext:\n{$contextText}";

            $db = \App\Database::getConnection();
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'custom_prompt'");
            $customPrompt = $stmt->fetchColumn();
            if (!empty($customPrompt)) {
                $systemPrompt .= "\n\nAdditional Instructions:\n{$customPrompt}";
            }

            // Inject Knowledge Wiki (text type) directly into prompt
            $stmtWiki = $db->query("SELECT title, content FROM knowledge WHERE type = 'text'");
            $wikiEntries = $stmtWiki->fetchAll();
            if (!empty($wikiEntries)) {
                $systemPrompt .= "\n\nCompany Wiki / General Knowledge:\n";
                foreach ($wikiEntries as $entry) {
                    $systemPrompt .= "Title: {$entry['title']}\n{$entry['content']}\n\n";
                }
            }

            error_log("Generated System Prompt: " . $systemPrompt);

            if ($isVoiceMode) {
                $systemPrompt .= "\n\nCRITICAL INSTRUCTION FOR VOICE MODE: Evaluate if the user's input seems to be just background noise or lyrics (like a TV or music playing in the background). If you suspect it's not a real human asking a question, you must still respond normally, but you MUST include the exact string [FAKE_USER_DETECTED] at the very end of your response.";
            }

            if (!empty($image)) {
                $messages[count($messages) - 1]['image'] = $image;
                $messages[count($messages) - 1]['mimeType'] = $mimeType;
            }

            $replyData = $llm->chat($systemPrompt, $messages, true, $sessionId);
            $replyText = $replyData['text'] ?? '';
            $executeJs = $replyData['execute_js'] ?? null;
            $executeArgs = $replyData['execute_args'] ?? null;
            $hiddenContext = $replyData['hidden_context'] ?? null;
            
            $isFake = false;
            if (strpos($replyText, '[FAKE_USER_DETECTED]') !== false) {
                $isFake = true;
                $replyText = trim(str_replace('[FAKE_USER_DETECTED]', '', $replyText));
            }

            $abortVoice = false;
            $logService = new \App\Services\ChatLogService();
            if ($isVoiceMode && !empty($sessionId)) {
                $history = $logService->getHistory($sessionId);
                $recentHistory = array_slice($history, -20); // 10 turns = 20 messages (user + bot)
                
                $fakeCount = 0;
                foreach ($recentHistory as $msg) {
                    if (isset($msg['is_fake']) && $msg['is_fake']) {
                        $fakeCount++;
                    }
                }
                
                if ($isFake) {
                    $fakeCount++;
                }
                
                if ($fakeCount == 3 && $isFake) {
                    $replyText .= " It seems there's a lot of background noise. Please ensure you are in a quiet environment.";
                } elseif ($fakeCount >= 4) {
                    $abortVoice = true;
                }
            }
            
            $audioBase64 = null;
            if ($isVoiceMode) {
                // Generate audio after appending any warnings
                $audioBase64 = $llm->generateAudioBase64($replyText);
            }

            if (!empty($sessionId)) {
                $botMsg = ['type' => 'bot', 'text' => $replyText];
                if ($isFake) {
                    $botMsg['is_fake'] = true;
                }
                $newMsgs = [
                    ['type' => 'user', 'text' => $lastMessage],
                    $botMsg
                ];
                if ($hiddenContext) {
                    $newMsgs[] = ['type' => 'bot_hidden', 'text' => $hiddenContext];
                }
                $logService->appendMessages($sessionId, $newMsgs);
            }

            $responsePayload = [
                'reply' => $replyText,
                'user_text' => $isVoiceMode ? $lastMessage : null
            ];

            if ($audioBase64) {
                $responsePayload['audio'] = $audioBase64;
            }

            if ($abortVoice) {
                $responsePayload['abort_voice'] = true;
            }

            if ($executeJs) {
                $responsePayload['execute_js'] = $executeJs;
                if ($executeArgs !== null) {
                    $responsePayload['execute_args'] = $executeArgs;
                }
            }

            if ($hiddenContext) {
                $responsePayload['hidden_context'] = $hiddenContext;
            }

            $response->getBody()->write(json_encode($responsePayload));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if ($e instanceof \GuzzleHttp\Exception\ClientException) {
                $errorMsg .= " Response: " . $e->getResponse()->getBody()->getContents();
            }
            error_log("Chat Error: " . $errorMsg);
            $response->getBody()->write(json_encode(['error' => $errorMsg]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function handleLogFallback(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (empty($data) && $request->getBody()->getSize() > 0) {
            $data = json_decode($request->getBody()->getContents(), true);
        }

        $sessionId = $data['session_id'] ?? '';
        $type = $data['type'] ?? '';
        $html = $data['html'] ?? '';

        if (!empty($sessionId) && !empty($type) && !empty($html)) {
            $logService = new \App\Services\ChatLogService();
            $logService->appendMessages($sessionId, [
                ['type' => $type, 'html' => $html]
            ]);
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function detectImage(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $image = $data['image'] ?? '';
        
        if (empty($image)) {
            $response->getBody()->write(json_encode(['error' => 'No image provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mimeType = 'image/jpeg';
        if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $image, $matches)) {
            $mimeType = $matches[1];
            $image = $matches[2];
        }

        try {
            $llm = new LlmService();
            $boxes = $llm->detectObjectsInImage($image, $mimeType);
            
            $response->getBody()->write(json_encode(['boxes' => $boxes]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("detectImage Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function visualSearch(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $image = $data['image'] ?? '';
        $messagesJson = $data['messages'] ?? '[]';
        $messages = is_string($messagesJson) ? json_decode($messagesJson, true) : $messagesJson;
        $sessionId = $data['session_id'] ?? '';
        
        if (empty($image)) {
            $response->getBody()->write(json_encode(['error' => 'No image provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $mimeType = 'image/jpeg';
        if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $image, $matches)) {
            $mimeType = $matches[1];
            $image = $matches[2];
        }

        try {
            $llm = new LlmService();
            $vectorService = new VectorService();
            
            $lastMessage = end($messages)['content'] ?? '';
            $intent = '';
            if (!empty($lastMessage)) {
                $intent = $llm->getSearchIntent($lastMessage);
            }

            $vector = $llm->embed($intent, $image, $mimeType);
            $sparseVector = !empty($intent) ? $llm->generateSparseVector($intent) : [];

            $results = $vectorService->search($vector, $sparseVector, 5);
            
            $contextText = "";
            $seenContents = [];
            foreach ($results as $result) {
                if (isset($result['payload']['search_content'])) {
                    $contentHash = md5($result['payload']['search_content']);
                    if (isset($seenContents[$contentHash])) continue;
                    $seenContents[$contentHash] = true;
                    
                    $itemContext = "- " . $result['payload']['search_content'];
                    if (!empty($result['payload']['product_id'])) {
                        $itemContext .= " Product ID: " . $result['payload']['product_id'] . ".";
                    }
                    if (!empty($result['payload']['product_url'])) {
                        $itemContext .= " Product URL: " . $result['payload']['product_url'] . ".";
                    }
                    if (!empty($result['payload']['thumbnail_url'])) {
                        $itemContext .= " Image URL: " . $result['payload']['thumbnail_url'] . ".";
                    }
                    $contextText .= $itemContext . "\n";
                }
            }

            $systemPrompt = "You are a helpful customer support AI for an e-commerce website. Answer the user's queries based on the following context. The context may include general information, faq, and product's details. If the user is asking about products, you MUST use the corresponding tool to display the products if you have such a tool available. Pass the exact Product IDs from the context to the tool. Do NOT list products in plain text if a tool is available. If the context does not contain the answer, politely inform the user that you don't know and ask if they would like you to contact sales team member to assist them. If the user explicitly asks or agrees to contact a sales team member(make sure the user has provided the contact email or phone number, if not ask the user for contact email or phone number first), then use the `contact_human` tool.\n\nCRITICAL INSTRUCTION: If the user provides their email address, physical address, or contact number at any point, or if you ask for it and they provide it, you MUST use the `save_customer_info` tool to save this information.\n\nContext:\n{$contextText}";

            $db = \App\Database::getConnection();
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'custom_prompt'");
            $customPrompt = $stmt->fetchColumn();
            if (!empty($customPrompt)) {
                $systemPrompt .= "\n\nAdditional Instructions:\n{$customPrompt}";
            }

            $stmtWiki = $db->query("SELECT title, content FROM knowledge WHERE type = 'text'");
            $wikiEntries = $stmtWiki->fetchAll();
            if (!empty($wikiEntries)) {
                $systemPrompt .= "\n\nCompany Wiki / General Knowledge:\n";
                foreach ($wikiEntries as $entry) {
                    $systemPrompt .= "Title: {$entry['title']}\n{$entry['content']}\n\n";
                }
            }

            $llmMessages = $messages;
            if (empty($lastMessage)) {
                $llmMessages[count($llmMessages) - 1]['content'] = "I uploaded an image. The system has already performed a visual search and found the visually similar products listed in the context. Please present these visually similar products to me.";
            }
            if (!empty($image)) {
                $llmMessages[count($llmMessages) - 1]['image'] = $image;
                $llmMessages[count($llmMessages) - 1]['mimeType'] = $mimeType;
            }

            $replyData = $llm->chat($systemPrompt, $llmMessages, true, $sessionId);
            
            if (!empty($sessionId)) {
                $logService = new \App\Services\ChatLogService();
                $logService->appendMessages($sessionId, [
                    ['role' => 'user', 'content' => $lastMessage, 'image' => true],
                    ['role' => 'model', 'content' => $replyData['text']]
                ]);
            }

            $response->getBody()->write(json_encode([
                'reply' => $replyData['text'],
                'execute_js' => $replyData['execute_js'] ?? null,
                'execute_args' => $replyData['execute_args'] ?? null
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Visual search error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
