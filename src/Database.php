<?php
namespace App;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;

    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $dbPath = __DIR__ . '/../data/database.sqlite';
            try {
                self::$connection = new PDO('sqlite:' . $dbPath);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::initSchema(self::$connection);
            } catch (PDOException $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$connection;
    }

    private static function initSchema(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            );
            
            CREATE TABLE IF NOT EXISTS knowledge (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                content TEXT NOT NULL,
                answer TEXT,
                qdrant_id TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id TEXT UNIQUE NOT NULL,
                sku TEXT,
                hash TEXT NOT NULL,
                qdrant_id TEXT,
                image_url TEXT,
                available_images TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS chat_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT UNIQUE NOT NULL,
                log_file TEXT NOT NULL,
                message_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS agent_functions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                call_id TEXT UNIQUE NOT NULL,
                description TEXT NOT NULL,
                js_code TEXT NOT NULL,
                parameters_schema TEXT,
                hidden_context_template TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS agent_function_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                function_name TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        try {
            $pdo->exec("ALTER TABLE knowledge ADD COLUMN title TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE agent_functions ADD COLUMN parameters_schema TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE agent_functions ADD COLUMN hidden_context_template TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE chat_sessions ADD COLUMN customer_email TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE chat_sessions ADD COLUMN customer_address TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE chat_sessions ADD COLUMN customer_contact_number TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN image_url TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }

        try {
            $pdo->exec("ALTER TABLE products ADD COLUMN available_images TEXT");
        } catch (\PDOException $e) {
            // Column might already exist
        }
    }
}
