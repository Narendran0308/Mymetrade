<?php
require_once 'config.php';

class Database {
    private $connection;

    public function __construct() {
        $this->connect();
    }

    public function connect() {
        try {
            // Parse host and port
            $host = DB_HOST;
            $port = 3306; // default
            
            if (strpos($host, ':') !== false) {
                list($host, $port) = explode(':', $host, 2);
                $port = (int) $port;
            }
            
            $this->connection = new mysqli($host, DB_USER, DB_PASS, DB_NAME, $port);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }

    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result && $this->connection->error) {
            error_log("Query error: " . $this->connection->error . " | SQL: " . $sql);
        }
        return $result;
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
    $dbInstance = new Database();
    $db = $dbInstance->getConnection(); // Return actual mysqli connection for compatibility
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
    die("Database initialization failed.");
}
