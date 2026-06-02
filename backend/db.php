<?php
require_once 'config.php';

class Database {
    private $connection;

    public function __construct() {
        $this->connect();
    }

    public function connect() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8");
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function begin_transaction() {
        return $this->connection->begin_transaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }

    public function close() {
        $this->connection->close();
    }
}

// Initialize database with error handling
try {
    $db = new Database();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}
