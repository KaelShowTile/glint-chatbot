<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;

class SettingsController {
    
    private function render(Response $response, string $template, array $data = []): Response {
        extract($data);
        ob_start();
        include __DIR__ . "/../views/{$template}.php";
        $content = ob_get_clean();
        
        ob_start();
        include __DIR__ . "/../views/layout.php";
        $html = ob_get_clean();
        
        $response->getBody()->write($html);
        return $response;
    }

    public function show(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        }

        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'settings', [
            'settings' => $settings,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function update(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $db = Database::getConnection();
        
        $keysToUpdate = [
            'llm_provider', 'llm_model_name', 'embedding_model_name', 'groq_api_key', 'gemini_api_key', 
            'qdrant_url', 'qdrant_api_key', 'admin_email', 
            'escalation_message', 'wp_path', 'product_feed_url',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption',
            'custom_prompt', 'chatbot_header', 'chatbot_name', 'chatbot_avatar_url', 'chatbot_greeting', 'quick_links',
            'tts_provider', 'tts_model_name', 'website_url', 'toggle_icon_html'
        ];

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
            
            foreach ($keysToUpdate as $key) {
                if (isset($data[$key])) {
                    $val = is_array($data[$key]) ? json_encode($data[$key]) : $data[$key];
                    $stmt->execute([$key, $val]);
                } else if ($key === 'quick_links') {
                    // If no quick links submitted (all removed), save empty array
                    $stmt->execute([$key, '[]']);
                }
            }

            // Checkboxes
            $enableWp = isset($data['enable_wp_login']) ? '1' : '0';
            $stmt->execute(['enable_wp_login', $enableWp]);
            
            $enableEscalateEmail = isset($data['enable_escalate_email']) ? '1' : '0';
            $stmt->execute(['enable_escalate_email', $enableEscalateEmail]);

            $db->commit();
            $_SESSION['success'] = 'Settings saved successfully.';
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Failed to save settings: ' . $e->getMessage();
        }

        // Redirect back to the referrer if possible
        $referer = $request->getHeaderLine('Referer');
        if (empty($referer)) {
            $referer = BASE_URL . '/admin/settings';
        }
        return $response->withHeader('Location', $referer)->withStatus(302);
    }

    public function showWidgetUi(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings WHERE key IN ('chatbot_header', 'chatbot_name', 'chatbot_avatar_url', 'chatbot_greeting', 'quick_links', 'toggle_icon_html')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'widget_ui', [
            'settings' => $settings,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function listChatlogs(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM chat_sessions ORDER BY updated_at DESC");
        $sessions = $stmt->fetchAll();

        return $this->render($response, 'chatlogs', [
            'sessions' => $sessions
        ]);
    }

    public function showChatlogDetail(Request $request, Response $response, array $args): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $sessionId = $args['session_id'] ?? '';
        $logService = new \App\Services\ChatLogService();
        $messages = $logService->getHistory($sessionId);

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT customer_email, customer_address FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $sessionData = $stmt->fetch() ?: [];

        return $this->render($response, 'chatlog_detail', [
            'sessionId' => $sessionId,
            'messages' => $messages,
            'customerEmail' => $sessionData['customer_email'] ?? '',
            'customerAddress' => $sessionData['customer_address'] ?? ''
        ]);
    }
}
