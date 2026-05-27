<?php
namespace App\Services;

use App\Database;

class ChatLogService {
    private string $logDir;

    public function __construct() {
        $this->logDir = __DIR__ . '/../../data/chatlogs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0777, true);
        }
    }

    public function getLogFile(string $sessionId): string {
        // Sanitize session_id to prevent path traversal
        $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
        if (empty($sessionId)) {
            $sessionId = bin2hex(random_bytes(16));
        }
        return $this->logDir . '/' . $sessionId . '.json';
    }

    public function appendMessages(string $sessionId, array $newMessages) {
        $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
        $file = $this->getLogFile($sessionId);
        
        $messages = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $messages = json_decode($content, true) ?: [];
        }

        foreach ($newMessages as $msg) {
            $msg['timestamp'] = date('Y-m-d H:i:s');
            $messages[] = $msg;
        }

        if (!is_dir($this->logDir)) {
            if (!@mkdir($this->logDir, 0777, true)) {
                error_log("ChatLogService Error: Failed to create log directory at " . $this->logDir);
            }
        } elseif (!is_writable($this->logDir)) {
            error_log("ChatLogService Error: Directory is not writable -> " . $this->logDir);
        }

        $result = @file_put_contents($file, json_encode($messages, JSON_PRETTY_PRINT));
        if ($result === false) {
            error_log("ChatLogService Error: Failed to write chat log to " . $file . ". Please check folder permissions.");
        }

        // Update Database
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id FROM chat_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        if ($stmt->fetch()) {
            $stmtUpdate = $db->prepare("UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP, message_count = ? WHERE session_id = ?");
            $stmtUpdate->execute([count($messages), $sessionId]);
        } else {
            $stmtInsert = $db->prepare("INSERT INTO chat_sessions (session_id, log_file, message_count) VALUES (?, ?, ?)");
            $stmtInsert->execute([$sessionId, 'data/chatlogs/' . $sessionId . '.json', count($messages)]);
        }
    }

    public function getHistory(string $sessionId): array {
        $sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $sessionId);
        $file = $this->getLogFile($sessionId);
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }
}
