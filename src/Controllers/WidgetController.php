<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\Services\ChatLogService;

class WidgetController {
    
    public function getConfig(Request $request, Response $response): Response {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings WHERE key IN ('chatbot_header', 'chatbot_name', 'chatbot_avatar_url', 'chatbot_greeting', 'quick_links', 'website_url', 'toggle_icon_html', 'upload_icon_html')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $quickLinks = [];
        if (!empty($settings['quick_links'])) {
            $quickLinks = json_decode($settings['quick_links'], true) ?: [];
        }

        $config = [
            'header' => $settings['chatbot_header'] ?: 'Customer Support',
            'name' => $settings['chatbot_name'] ?: 'AI Assistant',
            'avatar' => $settings['chatbot_avatar_url'] ?: '',
            'greeting' => $settings['chatbot_greeting'] ?: 'Hello! How can I help you today?',
            'quickLinks' => $quickLinks,
            'website_url' => $settings['website_url'] ?? '',
            'toggle_icon_html' => $settings['toggle_icon_html'] ?? '',
            'upload_icon_html' => $settings['upload_icon_html'] ?? ''
        ];

        $response->getBody()->write(json_encode($config));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getHistory(Request $request, Response $response): Response {
        $params = $request->getQueryParams();
        $sessionId = $params['session_id'] ?? '';
        
        if (empty($sessionId)) {
            $response->getBody()->write(json_encode(['messages' => []]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $logService = new ChatLogService();
        $messages = $logService->getHistory($sessionId);

        $response->getBody()->write(json_encode(['messages' => $messages]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
