<?php
/**
 * Conexão com Banco usando .env
 */

$config = require __DIR__ . '/config_env.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        global $config;

        try {
            $dsn = "mysql:host=" . $config['DB_HOST'] .
                   ";dbname=" . $config['DB_NAME'] .
                   ";charset=" . $config['DB_CHARSET'];

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO(
                $dsn,
                $config['DB_USER'],
                $config['DB_PASS'],
                $options
            );

        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Erro de conexão com o banco de dados.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}
