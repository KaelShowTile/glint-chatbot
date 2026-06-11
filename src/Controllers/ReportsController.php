<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Database;

class ReportsController {
    
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

        $params = $request->getQueryParams();
        
        // Default to last 30 days
        $endDateStr = date('Y-m-d');
        $startDateStr = date('Y-m-d', strtotime('-30 days'));

        $startDate = $params['start_date'] ?? $startDateStr;
        $endDate = $params['end_date'] ?? $endDateStr;

        // Ensure end date includes the full day (up to 23:59:59) for SQL BETWEEN comparisons
        $dbStartDate = $startDate . ' 00:00:00';
        $dbEndDate = $endDate . ' 23:59:59';

        $db = Database::getConnection();

        // 1. Daily Session Count
        $stmtSessions = $db->prepare("
            SELECT date(created_at) as log_date, COUNT(id) as session_count 
            FROM chat_sessions 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY date(created_at)
            ORDER BY log_date ASC
        ");
        $stmtSessions->execute([$dbStartDate, $dbEndDate]);
        $dailySessions = $stmtSessions->fetchAll();

        // 2. Daily Average Message Count
        $stmtMessages = $db->prepare("
            SELECT date(created_at) as log_date, AVG(message_count) as avg_messages 
            FROM chat_sessions 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY date(created_at)
            ORDER BY log_date ASC
        ");
        $stmtMessages->execute([$dbStartDate, $dbEndDate]);
        $dailyMessages = $stmtMessages->fetchAll();

        // 3. Daily Agent Function Call Count
        $stmtFunctions = $db->prepare("
            SELECT date(created_at) as log_date, function_name, COUNT(id) as call_count 
            FROM agent_function_logs 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY date(created_at), function_name
            ORDER BY log_date ASC, function_name ASC
        ");
        $stmtFunctions->execute([$dbStartDate, $dbEndDate]);
        $functionLogs = $stmtFunctions->fetchAll();

        return $this->render($response, 'reports', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dailySessions' => $dailySessions,
            'dailyMessages' => $dailyMessages,
            'functionLogs' => $functionLogs
        ]);
    }
}
