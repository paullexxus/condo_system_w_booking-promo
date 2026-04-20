<?php
// config/db.php - Database connection and basic helper functions only

// Load constants
require_once __DIR__ . '/constants.php';

// Create connection using constants
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set connection charset to ensure consistent collation for string literals
$conn->set_charset('utf8mb4');

// Database helper functions
if (!function_exists('get_single_result')) {
    function get_single_result($sql, $params = []) {
        global $conn;
        
        if (!$conn) {
            error_log("Database connection is null in get_single_result");
            return false;
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return false;
        }
        
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            foreach ($params as $param) {
                if ($param === null) {
                    $types .= 's';
                    $bind_params[] = null;
                } elseif (is_int($param)) {
                    $types .= 'i';
                    $bind_params[] = $param;
                } elseif (is_float($param)) {
                    $types .= 'd';
                    $bind_params[] = $param;
                } else {
                    $types .= 's';
                    $bind_params[] = (string)$param;
                }
            }
            $bind_refs = [$types];
            foreach ($bind_params as $key => $val) {
                $bind_refs[$key + 1] = &$bind_params[$key];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bind_refs)) {
                error_log("Bind param failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $row = null;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
        }
        $stmt->close();
        return $row;
    }
}

if (!function_exists('get_multiple_results')) {
    function get_multiple_results($sql, $params = []) {
        global $conn;
        
        if (!$conn) {
            error_log("Database connection is null in get_multiple_results");
            return [];
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return [];
        }
        
        if (!empty($params)) {
            $types = '';
            $bind_params = [];
            foreach ($params as $param) {
                if ($param === null) {
                    $types .= 's';
                    $bind_params[] = null;
                } elseif (is_int($param)) {
                    $types .= 'i';
                    $bind_params[] = $param;
                } elseif (is_float($param)) {
                    $types .= 'd';
                    $bind_params[] = $param;
                } else {
                    $types .= 's';
                    $bind_params[] = (string)$param;
                }
            }
            $bind_refs = [$types];
            foreach ($bind_params as $key => $val) {
                $bind_refs[$key + 1] = &$bind_params[$key];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bind_refs)) {
                error_log("Bind param failed: " . $stmt->error);
                $stmt->close();
                return [];
            }
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        
        $result = $stmt->get_result();
        $results = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        $stmt->close();
        return $results;
    }
}

if (!function_exists('execute_query')) {
    function execute_query($sql, $params = []) {
        global $conn;
        
        if (!$conn) {
            error_log("Database connection is null in execute_query");
            return false;
        }
        
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error . " | SQL: " . $sql);
                return false;
            }
            
            if (!empty($params)) {
                // Build proper type string based on parameter types
                $types = '';
                $bind_params = [];
                
                foreach ($params as $param) {
                    if ($param === null) {
                        $types .= 's';  // null values treated as strings
                        $bind_params[] = null;
                    } elseif (is_int($param)) {
                        $types .= 'i';
                        $bind_params[] = $param;
                    } elseif (is_float($param)) {
                        $types .= 'd';
                        $bind_params[] = $param;
                    } else {
                        $types .= 's';
                        $bind_params[] = (string)$param;
                    }
                }
                
                // Convert bind_params to references for bind_param
                $bind_refs = [];
                $bind_refs[] = $types;
                foreach ($bind_params as $key => $val) {
                    $bind_refs[$key + 1] = &$bind_params[$key];
                }
                
                if (!call_user_func_array([$stmt, 'bind_param'], $bind_refs)) {
                    error_log("Bind param failed: " . $stmt->error . " | Types: " . $types);
                    $stmt->close();
                    return false;
                }
            }
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error . " | SQL: " . $sql);
                $stmt->close();
                return false;
            }
            
            $stmt->close();
            return true;
            
        } catch (Exception $e) {
            error_log("Exception in execute_query: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('close_connection')) {
    function close_connection() {
        global $conn;
        if ($conn instanceof mysqli) {
            try {
                @$conn->close();
            } catch (Throwable $e) {
                // Connection may already be closed; safely ignore
            }
        }
    }
}

register_shutdown_function('close_connection');

// Check if a table column exists (safe guard for older/partial schemas)
if (!function_exists('column_exists')) {
    function column_exists($table, $column) {
        global $conn;
        if (!$conn) return false;
        // Use an escaped, non-prepared SHOW query because some servers don't accept
        // parameter markers for SHOW/metadata statements.
        $tableEsc = $conn->real_escape_string($table);
        $colEsc = $conn->real_escape_string($column);
        $sql = "SHOW COLUMNS FROM `" . $tableEsc . "` LIKE '" . $colEsc . "'";
        $res = $conn->query($sql);
        if ($res === false) return false;
        $exists = ($res->num_rows > 0);
        $res->free();
        return $exists;
    }
}

/**
 * Payout Schema Hardening Helpers
 */
if (!function_exists('get_payout_user_col')) {
    function get_payout_user_col() {
        return column_exists('payouts', 'user_id') ? 'user_id' : 'host_id';
    }
}

if (!function_exists('get_payout_account_col')) {
    function get_payout_account_col() {
        return column_exists('payout_accounts', 'account_details') ? 'account_details' : 'account_number';
    }
}
?>