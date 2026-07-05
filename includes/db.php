<?php
// includes/db.php — PDO singleton
defined('ATTENDANCE_SYS') or die('Direct access not permitted.');

class DB
{
    private static $instance = null;
    private function __construct() {}
    private function __clone() {}

    public static function conn()
    {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $opts = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $opts);
            } catch (PDOException $e) {
                error_log('DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                die(json_encode(['success'=>false,'message'=>'Database connection failed. Check your config.php settings.']));
            }
        }
        return self::$instance;
    }

    public static function run($sql, $params = [])
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
