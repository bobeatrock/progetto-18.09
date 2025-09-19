<?php
// ========================================
// FESTALAUREA - DATABASE CLASS
// ========================================

class Database {
    private static $instance = null;
    private $conn;
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Connection failed: " . $e->getMessage());
            } else {
                die("Connection failed. Please try again later.");
            }
        }
    }
    
    // Singleton pattern
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get connection
    public function getConnection() {
        return $this->conn;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    // Execute query with parameters
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                throw new Exception("Query failed: " . $e->getMessage());
            }
            return false;
        }
    }
    
    // Select multiple rows
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }
    
    // Select single row
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : null;
    }
    
    // Insert data
    public function insert($table, $data) {
        $fields = array_keys($data);
        $values = array_map(function($field) {
            return ':' . $field;
        }, $fields);
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) 
                VALUES (" . implode(', ', $values) . ")";
        
        $this->query($sql, $data);
        return $this->conn->lastInsertId();
    }
    
    // Update data
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_map(function($field) {
            return "`$field` = :$field";
        }, array_keys($data));
        
        $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE $where";
        
        return $this->query($sql, array_merge($data, $whereParams));
    }
    
    // Delete data
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM `$table` WHERE $where";
        return $this->query($sql, $params);
    }
    
    // Count rows
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        $result = $this->selectOne($sql, $params);
        return $result ? $result['count'] : 0;
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->conn->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->conn->rollBack();
    }
    
    // Check if table exists
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $this->query($sql, [$table]);
        return $stmt && $stmt->rowCount() > 0;
    }
    
    // Get last error
    public function getError() {
        return $this->conn->errorInfo();
    }
}