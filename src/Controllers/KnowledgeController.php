<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;
use App\Services\VectorService;
use App\Services\LlmService;

class KnowledgeController {
    
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

    public function listText(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM knowledge WHERE type = 'text' ORDER BY id DESC");
        $items = $stmt->fetchAll();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'text_list', [
            'items' => $items,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function saveText(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $data = $request->getParsedBody();
        $content = trim($data['content'] ?? '');
        $id = $data['id'] ?? null;

        if (empty($content)) {
            $_SESSION['error'] = 'Content cannot be empty.';
            return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
        }

        try {
            $db = Database::getConnection();
            $llm = new LlmService();
            $vectorService = new VectorService();
            
            $vector = $llm->embed($content);
            $qdrantId = VectorService::generateUuid();

            if ($id) {
                $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $oldQdrantId = $stmt->fetchColumn();
                if ($oldQdrantId) $qdrantId = $oldQdrantId;
                
                $stmt = $db->prepare("UPDATE knowledge SET content = ?, qdrant_id = ? WHERE id = ?");
                $stmt->execute([$content, $qdrantId, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO knowledge (type, content, qdrant_id) VALUES ('text', ?, ?)");
                $stmt->execute([$content, $qdrantId]);
                $id = $db->lastInsertId();
            }

            $vectorService->upsert($qdrantId, $vector, [
                'type' => 'text',
                'internal_id' => $id,
                'search_content' => $content
            ]);

            $_SESSION['success'] = 'Information saved successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error saving information: ' . $e->getMessage();
        }

        return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
    }

    public function deleteText(Request $request, Response $response, array $args): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $id = $args['id'] ?? null;
        if ($id) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
            $stmt->execute([$id]);
            $qdrantId = $stmt->fetchColumn();

            if ($qdrantId) {
                $vectorService = new VectorService();
                $vectorService->delete($qdrantId);
                
                $stmt = $db->prepare("DELETE FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Information deleted successfully.';
            }
        }
        
        return $response->withHeader('Location', BASE_URL . '/admin/text')->withStatus(302);
    }

    public function listQa(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM knowledge WHERE type = 'qa' ORDER BY id DESC");
        $items = $stmt->fetchAll();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'qa_list', [
            'items' => $items,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function saveQa(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $data = $request->getParsedBody();
        $question = trim($data['content'] ?? '');
        $answer = trim($data['answer'] ?? '');
        $id = $data['id'] ?? null;

        if (empty($question) || empty($answer)) {
            $_SESSION['error'] = 'Question and Answer cannot be empty.';
            return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
        }

        try {
            $db = Database::getConnection();
            $llm = new LlmService();
            $vectorService = new VectorService();
            
            $vector = $llm->embed($question);
            $qdrantId = VectorService::generateUuid();

            if ($id) {
                $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $oldQdrantId = $stmt->fetchColumn();
                if ($oldQdrantId) $qdrantId = $oldQdrantId;
                
                $stmt = $db->prepare("UPDATE knowledge SET content = ?, answer = ?, qdrant_id = ? WHERE id = ?");
                $stmt->execute([$question, $answer, $qdrantId, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO knowledge (type, content, answer, qdrant_id) VALUES ('qa', ?, ?, ?)");
                $stmt->execute([$question, $answer, $qdrantId]);
                $id = $db->lastInsertId();
            }

            $vectorService->upsert($qdrantId, $vector, [
                'type' => 'qa',
                'internal_id' => $id,
                'search_content' => "Q: {$question} A: {$answer}"
            ]);

            $_SESSION['success'] = 'Q&A saved successfully.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error saving Q&A: ' . $e->getMessage();
        }

        return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
    }

    public function deleteQa(Request $request, Response $response, array $args): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);

        $id = $args['id'] ?? null;
        if ($id) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT qdrant_id FROM knowledge WHERE id = ?");
            $stmt->execute([$id]);
            $qdrantId = $stmt->fetchColumn();

            if ($qdrantId) {
                $vectorService = new VectorService();
                $vectorService->delete($qdrantId);
                
                $stmt = $db->prepare("DELETE FROM knowledge WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['success'] = 'Q&A deleted successfully.';
            }
        }
        
        return $response->withHeader('Location', BASE_URL . '/admin/qa')->withStatus(302);
    }

    public function listProducts(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM products ORDER BY updated_at DESC");
        $products = $stmt->fetchAll();

        $stmtUrl = $db->query("SELECT value FROM settings WHERE key = 'product_feed_url'");
        $feedUrl = $stmtUrl->fetchColumn();

        $success = $_SESSION['success'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['success'], $_SESSION['error']);

        return $this->render($response, 'products', [
            'products' => $products,
            'feedUrl' => $feedUrl,
            'success' => $success,
            'error' => $error
        ]);
    }

    public function triggerSync(Request $request, Response $response): Response {
        if (!isset($_SESSION['user'])) return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        
        $syncService = new \App\Services\SyncService();
        $success = $syncService::syncProducts();

        if ($success) {
            $_SESSION['success'] = 'Products synchronized successfully.';
        } else {
            $_SESSION['error'] = 'Failed to synchronize products. Check the feed URL and format.';
        }

        return $response->withHeader('Location', BASE_URL . '/admin/products')->withStatus(302);
    }
}
