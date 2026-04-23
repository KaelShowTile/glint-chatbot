<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\LlmService;
use App\Services\VectorService;

class ChatController {
    public function handleChat(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        if (empty($data) && $request->getBody()->getSize() > 0) {
            $data = json_decode($request->getBody()->getContents(), true);
        }

        $messages = $data['messages'] ?? [];
        if (empty($messages)) {
            $response->getBody()->write(json_encode(['error' => 'No messages provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $lastMessage = end($messages)['content'] ?? '';

        try {
            $llm = new LlmService();
            $vectorService = new VectorService();

            // Step 1: Extract intent
            $intent = $llm->getSearchIntent($lastMessage);

            // Step 2: Vectorize intent
            $vector = $llm->embed($intent);

            // Step 3: Retrieve context
            $results = $vectorService->search($vector, 5);
            
            $contextText = "";
            foreach ($results as $result) {
                if (isset($result['payload']['search_content'])) {
                    $contextText .= "- " . $result['payload']['search_content'] . "\n";
                }
            }

            // Step 4 & 5: Generate response with strict system prompt
            $systemPrompt = "You are a helpful customer support AI for an e-commerce website. Answer the user's queries based ONLY on the following context. If the context does not contain the answer, politely inform the user that you don't know and ask if they would like you to contact a human staff member to assist them. If the user explicitly asks or agrees to contact a human, use the `contact_human` tool.\n\nContext:\n{$contextText}";

            $reply = $llm->chat($systemPrompt, $messages, true);

            $response->getBody()->write(json_encode([
                'reply' => $reply
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Chat Error: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Sorry, an error occurred while processing your request.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
