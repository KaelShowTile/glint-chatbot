<?php
require 'src/Database.php';
$db = App\Database::getConnection();
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('gemini_api_key', 'dummy_key')");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('llm_provider', 'gemini')");
$db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('llm_model_name', 'gemini-2.5-flash')");
echo "Done";
