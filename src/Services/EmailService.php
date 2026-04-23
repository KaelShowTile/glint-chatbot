<?php
namespace App\Services;

use App\Database;
// PHPMailer classes
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    public static function sendEscalationEmail(string $summary): bool {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT key, value FROM settings WHERE key IN ('admin_email', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption')");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        if (empty($settings['admin_email'])) {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            if (!empty($settings['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host       = $settings['smtp_host'];
                $mail->SMTPAuth   = !empty($settings['smtp_user']);
                $mail->Username   = $settings['smtp_user'] ?? '';
                $mail->Password   = $settings['smtp_pass'] ?? '';
                
                if (!empty($settings['smtp_encryption'])) {
                    $mail->SMTPSecure = $settings['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->Port       = $settings['smtp_port'] ?: 587;
            }

            // Recipients
            $mail->setFrom($settings['smtp_user'] ?: 'no-reply@localhost', 'AI Customer Service');
            $mail->addAddress($settings['admin_email']);

            // Content
            $mail->isHTML(false);
            $mail->Subject = 'Customer Support Escalation from AI';
            $mail->Body    = "An AI customer service session has been escalated to human staff.\n\nHere is the summary of the customer's needs:\n------------------\n{$summary}\n------------------\n\nPlease follow up accordingly.";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
