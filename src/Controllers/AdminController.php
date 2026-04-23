<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\AuthService;
use App\Database;

class AdminController {
    
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

    public function showLogin(Request $request, Response $response): Response {
        if (isset($_SESSION['user'])) {
            return $response->withHeader('Location', BASE_URL . '/admin/settings')->withStatus(302);
        }
        
        if (!AuthService::hasGlobalAdminSetup()) {
            return $response->withHeader('Location', BASE_URL . '/admin/init')->withStatus(302);
        }
        
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'enable_wp_login'");
        $stmt->execute();
        $enableWpLogin = (bool) $stmt->fetchColumn();

        return $this->render($response, 'login', [
            'enableWpLogin' => $enableWpLogin,
            'error' => $_SESSION['error'] ?? null,
            'success' => $_SESSION['success'] ?? null
        ]);
    }

    public function processLogin(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $isWpLogin = isset($data['wp_login']) && $data['wp_login'] == '1';

        $success = false;
        if ($isWpLogin) {
            $success = AuthService::loginWpAdmin($username, $password);
        } else {
            $success = AuthService::loginGlobalAdmin($username, $password);
        }

        if ($success) {
            $_SESSION['user'] = $username;
            $_SESSION['is_wp_admin'] = $isWpLogin;
            return $response->withHeader('Location', BASE_URL . '/admin/settings')->withStatus(302);
        }

        $_SESSION['error'] = 'Invalid credentials';
        return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
    }

    public function showInit(Request $request, Response $response): Response {
        if (AuthService::hasGlobalAdminSetup()) {
            return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        }
        return $this->render($response, 'init');
    }

    public function processInit(Request $request, Response $response): Response {
        if (AuthService::hasGlobalAdminSetup()) {
            return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Username and password are required.';
            return $response->withHeader('Location', BASE_URL . '/admin/init')->withStatus(302);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('admin_username', ?), ('admin_password', ?)");
        $stmt->execute([$username, $hashed]);

        $_SESSION['success'] = 'Global admin account created successfully. Please login.';
        return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response {
        session_destroy();
        return $response->withHeader('Location', BASE_URL . '/admin/login')->withStatus(302);
    }
}
