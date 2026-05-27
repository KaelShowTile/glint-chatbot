<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;

class AgentFunctionController
{
    public function index(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        }

        $db = Database::getConnection();
        $stmt = $db->query("SELECT * FROM agent_functions ORDER BY created_at DESC");
        $functions = $stmt->fetchAll();

        ob_start();
        $pageTitle = 'Agent Functions';
        require __DIR__ . '/../views/agent_functions.php';
        $content = ob_get_clean();
        
        ob_start();
        require __DIR__ . '/../views/layout.php';
        $fullPage = ob_get_clean();
        
        $response->getBody()->write($fullPage);
        return $response;
    }

    public function create(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withStatus(401);
        }

        $data = $request->getParsedBody();
        $name = trim($data['name'] ?? '');
        $call_id = trim($data['call_id'] ?? '');
        $description = trim($data['description'] ?? '');
        $js_code = trim($data['js_code'] ?? '');
        $parameters_schema = trim($data['parameters_schema'] ?? '');

        if (empty($name) || empty($call_id) || empty($description) || empty($js_code)) {
            $response->getBody()->write(json_encode(['error' => 'All fields are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validate call_id (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $call_id)) {
            $response->getBody()->write(json_encode(['error' => 'Call ID must contain only letters, numbers, and underscores.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $id = isset($data['id']) && is_numeric($data['id']) ? (int)$data['id'] : 0;

        try {
            $db = Database::getConnection();
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE agent_functions SET name = ?, call_id = ?, description = ?, js_code = ?, parameters_schema = ? WHERE id = ?");
                $stmt->execute([$name, $call_id, $description, $js_code, $parameters_schema, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO agent_functions (name, call_id, description, js_code, parameters_schema) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $call_id, $description, $js_code, $parameters_schema]);
            }

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (UNIQUE)
                $response->getBody()->write(json_encode(['error' => 'Call ID already exists.']));
            } else {
                $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            }
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withStatus(401);
        }

        $id = $args['id'] ?? 0;
        
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("DELETE FROM agent_functions WHERE id = ?");
            $stmt->execute([$id]);

            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
