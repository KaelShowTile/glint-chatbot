<?php
namespace App\Services;

use PDO;
use App\Database;

class AuthService
{

    public static function loginGlobalAdmin(string $username, string $password): bool
    {
        $db = Database::getConnection();
        // Global admin credentials are saved in settings: admin_username, admin_password (hashed)
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_username'");
        $stmt->execute();
        $storedUser = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password'");
        $stmt->execute();
        $storedPass = $stmt->fetchColumn();

        if ($storedUser && $storedPass && $username === $storedUser) {
            return password_verify($password, $storedPass);
        }

        return false;
    }

    public static function loginWpAdmin(string $username, string $password): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'enable_wp_login'");
        $stmt->execute();
        $enableWp = $stmt->fetchColumn();

        if ($enableWp !== '1') {
            return false;
        }

        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'wp_path'");
        $stmt->execute();
        $wpPath = rtrim((string) $stmt->fetchColumn(), '/\\');

        if (empty($wpPath) || !file_exists($wpPath . '/wp-config.php')) {
            return false;
        }

        // Parse wp-config.php for DB credentials
        $wpConfig = file_get_contents($wpPath . '/wp-config.php');

        $dbName = self::extractWpConfigDefine($wpConfig, 'DB_NAME');
        $dbUser = self::extractWpConfigDefine($wpConfig, 'DB_USER');
        $dbPass = self::extractWpConfigDefine($wpConfig, 'DB_PASSWORD');
        $dbHost = self::extractWpConfigDefine($wpConfig, 'DB_HOST');

        error_log("DB: $dbName, User: $dbUser, Pass: " . ($dbPass ? '***' : 'null') . ", Host: $dbHost");

        // Extract table prefix
        preg_match('/\$table_prefix\s*=\s*[\'"](.*?)[\'"]/', $wpConfig, $prefixMatch);
        $prefix = $prefixMatch[1] ?? 'wp_';
        error_log("Prefix: $prefix");

        if (!$dbName || !$dbUser) {
            error_log("Missing dbName or dbUser");
            return false;
        }

        try {
            $wpDb = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
            $wpDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $wpDb->prepare("SELECT user_pass FROM {$prefix}users WHERE user_login = ? OR user_email = ?");
            $stmt->execute([$username, $username]);
            $hash = $stmt->fetchColumn();

            if ($hash) {
                // WordPress 6.x+ modern hashes starting with $wp$
                if (strpos($hash, '$wp') === 0) {
                    $password_to_verify = base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
                    $isValid = password_verify($password_to_verify, substr($hash, 3));
                    if ($isValid)
                        return true;
                }

                // Classic WordPress portable hashes starting with $P$
                if (strpos($hash, '$P$') === 0) {
                    if (file_exists($wpPath . '/wp-includes/class-phpass.php')) {
                        if (!class_exists('PasswordHash')) {
                            require_once $wpPath . '/wp-includes/class-phpass.php';
                        }
                        if (class_exists('PasswordHash')) {
                            $wpHasher = new \PasswordHash(8, true);
                            $isValid = $wpHasher->CheckPassword($password, $hash);
                            if ($isValid)
                                return true;
                        }
                    }
                }

                // Fallback to PHP native password_verify for standard bcrypt
                if (password_verify($password, $hash)) {
                    return true;
                }
            }
        } catch (\PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            return false;
        }

        error_log("End of function reached, returning false");
        return false;
    }

    private static function extractWpConfigDefine(string $config, string $key): ?string
    {
        if (preg_match('/define\(\s*[\'"]' . $key . '[\'"]\s*,\s*[\'"](.*?)[\'"]\s*\)/', $config, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public static function hasGlobalAdminSetup(): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_username'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    }
}
