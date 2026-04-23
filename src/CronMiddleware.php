<?php
namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Database;

class CronMiddleware implements MiddlewareInterface {
    public function process(Request $request, RequestHandler $handler): Response {
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT value FROM settings WHERE key = 'cron_next_run'");
            $nextRun = $stmt->fetchColumn();

            $currentTime = time();
            
            if (!$nextRun) {
                // Not set yet, set it to tomorrow 4:00 AM
                $tomorrow4am = strtotime('tomorrow 04:00:00');
                $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('cron_next_run', ?)");
                $stmt->execute([$tomorrow4am]);
            } elseif ($currentTime >= (int)$nextRun) {
                // Determine next 4 AM (or just add 24 hours)
                $newNextRun = strtotime('tomorrow 04:00:00');
                $stmt = $db->prepare("UPDATE settings SET value = ? WHERE key = 'cron_next_run'");
                $stmt->execute([$newNextRun]);
                
                // Run sync in shutdown function to not block the current response completely
                register_shutdown_function(['\App\Services\SyncService', 'syncProducts']);
            }
        } catch (\Exception $e) {
            // Ignore DB errors during cron check to not break the app
        }

        return $handler->handle($request);
    }
}
