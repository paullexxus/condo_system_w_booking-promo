<?php
/**
 * Database Helper Functions
 * Centralized database access functions
 */

// Include database connection
require_once __DIR__ . '/../../config/db.php';

class DatabaseHelper {
    // Instance variable para sa singleton pattern
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            // Use existing connection if available
            global $conn;
            if ($conn instanceof mysqli && !$conn->connect_error) {
                $this->conn = $conn;
            } else {
                // Create new connection if needed
                $this->conn = new mysqli('localhost', 'root', '', 'condo_rental_reservation_db');
                if ($this->conn->connect_error) {
                    throw new Exception("Connection failed: " . $this->conn->connect_error);
                }
            }
            
            // Set charset
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Singleton pattern para isang instance lang
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DatabaseHelper();
        }
        return self::$instance;
    }
    
    /**
     * Get single row using prepared statement
     * @param string $sql - SQL query
     * @param array $params - Parameters for prepared statement
     * @return array|false - Result array or false if failed
     */
    public function getOne($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Database prepare failed: " . $this->conn->error);
                return false;
            }
            
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                error_log("Database execute failed: " . $stmt->error);
                return false;
            }
            
            $result = $stmt->get_result();
            return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : false;
            
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple rows using prepared statement
     * @param string $sql - SQL query
     * @param array $params - Parameters for prepared statement
     * @return array - Result array (empty if no results or error)
     */
    public function getMany($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Database prepare failed: " . $this->conn->error);
                return [];
            }
            
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                error_log("Database execute failed: " . $stmt->error);
                return [];
            }
            
            $result = $stmt->get_result();
            $rows = [];
            
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            return $rows;
            
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute query (INSERT, UPDATE, DELETE)
     * @param string $sql - SQL query
     * @param array $params - Parameters for prepared statement
     * @return bool - True if successful, false if failed
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Database prepare failed: " . $this->conn->error);
                return false;
            }
            
            if (!empty($params)) {
                $types = str_repeat('s', count($params));
                $stmt->bind_param($types, ...$params);
            }
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get last inserted ID
     * @return int - Last inserted ID
     */
    public function getLastId() {
        return $this->conn->insert_id;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->conn->rollback();
    }
}

// Example usage:
/*
$db = DatabaseHelper::getInstance();

// Select single row
$user = $db->getOne("SELECT * FROM users WHERE user_id = ?", [$user_id]);

// Select multiple rows
$units = $db->getMany("SELECT * FROM units WHERE branch_id = ?", [$branch_id]);

// Insert/Update/Delete
$success = $db->execute(
    "INSERT INTO units (unit_number, branch_id) VALUES (?, ?)", 
    [$unit_number, $branch_id]
);
*/
?>