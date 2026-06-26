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
            $vector = $llm->embed($intent);

            // Step 3: Retrieve context
            $results = $vectorService->search($vector, 5);

            // Log intent and search results for debugging
            error_log("Search Intent: " . $intent);
            error_log("Qdrant Search Results: " . json_encode($results));

            $contextText = "";
            foreach ($results as $result) {
                if (isset($result['payload']['search_content'])) {
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

            if ($isVoiceMode) {
                $replyData = $llm->chatWithAudioOut($systemPrompt, $messages, true, $sessionId);
                $replyText = $replyData['text'];
                $audioBase64 = $replyData['audioBase64'];
                $executeJs = $replyData['execute_js'] ?? null;
                $executeArgs = $replyData['execute_args'] ?? null;
            } else {
                $replyData = $llm->chat($systemPrompt, $messages, true, $sessionId);
                $replyText = $replyData['text'];
                $audioBase64 = null;
                $executeJs = $replyData['execute_js'] ?? null;
                $executeArgs = $replyData['execute_args'] ?? null;
            }

            if (!empty($sessionId)) {
                $logService = new \App\Services\ChatLogService();
                $logService->appendMessages($sessionId, [
                    ['type' => 'user', 'text' => $lastMessage],
                    ['type' => 'bot', 'text' => $replyText]
                ]);
            }

            $responsePayload = [
                'reply' => $replyText,
                'user_text' => $isVoiceMode ? $lastMessage : null
            ];

            if ($audioBase64) {
                $responsePayload['audio'] = $audioBase64;
            }

            if ($executeJs) {
                $responsePayload['execute_js'] = $executeJs;
                if ($executeArgs !== null) {
                    $responsePayload['execute_args'] = $executeArgs;
                }
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
}
