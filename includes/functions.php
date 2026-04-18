<?php
// BookIT System Functions
// Multi-branch Condo Rental Reservation System

    include_once __DIR__ . "/../config/db.php";
    require_once __DIR__ . "/../config/constants.php";

    // ==================== CSRF PROTECTION FUNCTIONS ====================
    
    /**
     * Generate CSRF token for forms with rotation and expiry support
     * @return string - CSRF token
     */
    function generateCSRFToken() {
        $now = time();
        // Rotate token if it's older than 1 hour or doesn't exist
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_expiry']) || $now > $_SESSION['csrf_expiry']) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_expiry'] = $now + 3600; // 1 hour expiry
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token from form submission
     * @param string $token - Token from POST data
     * @return bool - True if valid and not expired, false otherwise
     */
    function verifyCSRFToken($token) {
        $now = time();
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_expiry'])) {
            return false;
        }
        if ($now > $_SESSION['csrf_expiry']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_expiry']);
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Force immediate rotation of CSRF token (useful after login/esc)
     */
    function rotateCSRFToken() {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_expiry']);
        return generateCSRFToken();
    }

    // ==================== ADVANCED SECURITY & HARDENING ====================

    /**
     * Implement Global/Endpoint Throttling (Rate Limiting)
     * @param string $action - The identifier for the action (e.g., 'login', 'search')
     * @param int $limit - Max attempts allowed
     * @param int $period - Period in seconds
     * @return bool - True if allowed, False if throttled
     */
    function throttleRequest($action, $limit = 5, $period = 60) {
        global $conn;
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'renter';

        // Role-based limit adjustments
        if ($role === 'admin') $limit *= 10;
        elseif ($role === 'host') $limit *= 5;

        // Cleanup old throttles occasionally (randomly 1% of the time)
        if (rand(1, 100) === 1) {
            execute_query("DELETE FROM request_throttles WHERE last_hit < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        }

        $sql = "SELECT id, hits, first_hit FROM request_throttles 
                WHERE ip_address = ? AND endpoint = ? AND (user_id = ? OR user_id IS NULL) 
                AND last_hit > DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1";
        $row = get_single_result($sql, [$ip, $action, $user_id, $period]);

        if ($row) {
            if ($row['hits'] >= $limit) {
                // Return remaining cooldown (seconds until last_hit + period)
                $cooldown = ($period) - (time() - strtotime($row['first_hit']));
                $cooldown = max(0, $cooldown);

                // Log lockout ONLY on the first threshold breach (Phase 2.2 Refinement)
                if ($row['hits'] == $limit) {
                    execute_query("UPDATE request_throttles SET hits = hits + 1 WHERE id = ?", [$row['id']]);
                    logSystemError("RATE LIMIT LOCKOUT: User/IP throttled on $action", ['ip' => $ip, 'user_id' => $user_id, 'limit' => $limit], 'high');
                }
                return $cooldown; 
            }
            execute_query("UPDATE request_throttles SET hits = hits + 1 WHERE id = ?", [$row['id']]);
        } else {
            execute_query("INSERT INTO request_throttles (ip_address, user_id, endpoint, hits) VALUES (?, ?, ?, 1)", [$ip, $user_id, $action]);
        }
        return true;
    }

    /**
     * Check if an action is idempotent using a unique key
     * @param string $key - The unique key from the client
     * @param string $action - The action name
     * @return bool - True if first time (valid), False if duplicate
     */
    function isIdempotent($key, $action) {
        if (empty($key)) return true; // Fallback if key missing (less secure)
        
        global $conn;
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        
        // 1. Generate Payload Hash (Phase 1.1 Refinement)
        // Detect payload only for state-changing methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $payload_hash = null;
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $payload_data = file_get_contents('php://input') ?: json_encode($_POST);
            $payload_hash = hash('sha256', $payload_data);
        }

        // Use transaction to ensure safe check-and-insert
        $conn->begin_transaction();
        try {
            $sql = "SELECT id, payload_hash FROM idempotency_keys WHERE key_id = ? AND action = ? AND expires_at > NOW() FOR UPDATE";
            $existing = get_single_result($sql, [$key, $action]);
            
            if ($existing) {
                // Validate payload integrity (tamper protection)
                if ($payload_hash !== null && $existing['payload_hash'] !== $payload_hash) {
                    $conn->rollback();
                    logSystemError("IDEMPOTENCY TAMPER DETECTED: Key $key reused with different payload.", ['key' => $key, 'action' => $action], 'high');
                    return false;
                }
                $conn->rollback();
                return false;
            }
            
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 minute expiry
            execute_query("INSERT INTO idempotency_keys (key_id, user_id, action, payload_hash, expires_at) VALUES (?, ?, ?, ?, ?)", [$key, $user_id, $action, $payload_hash, $expires]);
            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }

    /**
     * Validate File Signature (Magic Bytes)
     * @param string $tmpPath - Path to temporary file
     * @param string $expectedExt - Expected extension
     * @return bool - True if signature matches extension
     */
    function validateFileSignature($tmpPath, $expectedExt) {
        if (!file_exists($tmpPath)) return false;
        
        $handle = fopen($tmpPath, 'rb');
        $bytes = fread($handle, 4);
        fclose($handle);
        $hex = bin2hex($bytes);

        $signatures = [
            'jpg'  => ['ffd8'],
            'jpeg' => ['ffd8'],
            'png'  => ['89504e47'],
            'pdf'  => ['25504446']
        ];

        $ext = strtolower($expectedExt);
        if (!isset($signatures[$ext])) return false;

        foreach ($signatures[$ext] as $sig) {
            if (stripos($hex, $sig) === 0) return true;
        }
        return false;
    }

    /**
     * Centralized Error and Audit Logging
     */
    function logSystemError($message, $context = [], $severity = 'medium') {
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $context_str = json_encode($context);
        
        error_log("SYSTEM ERROR: $message | Context: $context_str");
        
        $sql = "INSERT INTO system_errors (user_id, error_type, message, stack_trace, ip_address, user_agent, severity) 
                VALUES (?, 'Application Error', ?, ?, ?, ?, ?)";
        execute_query($sql, [$user_id, $message, $context_str, $ip, $ua, $severity]);
    }

    function logAudit($userId, $actionType, $entityType, $entityId, $details, $fromStatus = null, $toStatus = null) {
        // Enforce INSERT-only via logic (prevent update/delete calls to this table)
        $sql = "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details, from_status, to_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        return execute_query($sql, [$userId, $actionType, $entityType, $entityId, $details, $fromStatus, $toStatus]);
    }

    /**
     * System Health Monitor Data
     */
    function getSystemHealth() {
        global $conn;
        $health = ['status' => 'OK', 'warnings' => []];
        
        if ($conn->connect_error) {
            $health['status'] = 'CRITICAL';
            $health['warnings'][] = "Database Connection Failed";
        }
        
        $critical_errors = get_single_result("SELECT COUNT(*) as count FROM system_errors WHERE is_resolved = 0 AND severity = 'critical' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        if ($critical_errors && $critical_errors['count'] > 0) {
            $health['status'] = 'WARNING';
            $health['warnings'][] = "{$critical_errors['count']} Critical errors in last 24h";
        }
        
        return $health;
    }

    /**
     * Lightweight Caching Helpers
     */
    function getCache($key) {
        $sql = "SELECT cache_value FROM system_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1";
        $row = get_single_result($sql, [$key]);
        return $row ? unserialize($row['cache_value']) : null;
    }

    function setCache($key, $value, $ttl = 30) {
        $val = serialize($value);
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $sql = "INSERT INTO system_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), expires_at = VALUES(expires_at)";
        return execute_query($sql, [$key, $val, $expires]);
    }

    function clearSystemCache($pattern = null) {
        if ($pattern) {
            return execute_query("DELETE FROM system_cache WHERE cache_key LIKE ?", ["%$pattern%"]);
        }
        return execute_query("DELETE FROM system_cache");
    }

    // ==================== USER MANAGEMENT FUNCTIONS ====================

    /**
     * Validate registration inputs before user creation
     * @param string $fullname - full name
     * @param string $email - email address
     * @param string $password - password
     * @param string $confirm_password - confirm password
     * @param string $phone - phone number
     * @return string - error message if validation fails, empty string if valid
     */
    function registerValidation($fullname, $email, $password, $confirm_password, $phone) {
        // Validate full name
        if (strlen($fullname) < 2) {
            return "Name must be at least 2 characters!";
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format!";
        }
        
        // Validate password strength: at least 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
        if (strlen($password) < 8) {
            return "Password must be at least 8 characters!";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            return "Password must contain at least one uppercase letter!";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            return "Password must contain at least one lowercase letter!";
        }
        
        if (!preg_match('/\d/', $password)) {
            return "Password must contain at least one number!";
        }
        
        if (!preg_match('/[\W_]/', $password)) {
            return "Password must contain at least one special character!";
        }
        
        // Validate passwords match
        if ($password !== $confirm_password) {
            return "Passwords do not match!";
        }
        
        // Validate phone number (allow various formats)
        if (!preg_match('/^\+?[0-9]{10,}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            return "Invalid phone number format!";
        }
        
        // If all validations pass, return empty string
        return '';
    }

    /**
     * Mag-register ng bagong user
     * @param string $fullname - buong pangalan ng user
     * @param string $email - email address
     * @param string $password - password (hindi pa encrypted)
     * @param string $phone - phone number
     * @param string $role - role ng user (admin, host, renter)
     * @param int $branch_id - branch ID para sa hosts
     * @return string - success message o error message
     */
    function registerUser($fullname, $email, $password, $phone, $role = 'renter', $branch_id = null) {
        global $conn;
        
        // FIXED HIGH #15: Add email and password validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "Invalid email format!";
        }
        
        if (strlen($password) < 6) {
            return "Password must be at least 6 characters!";
        }
        
        if (strlen($fullname) < 2) {
            return "Name must be at least 2 characters!";
        }
        
        if (!preg_match('/^\+?[0-9]{10,}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
            return "Invalid phone number format!";
        }
        
        // I-check kung existing na ang email
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_result = get_single_result($check_sql, [$email]);
        
        if ($check_result) {
            return "Email already exists!";
        }
        
        // I-encrypt ang password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // I-insert ang bagong user
        $sql = "INSERT INTO users (full_name, email, password, phone, role, branch_id) VALUES (?, ?, ?, ?, ?, ?)";
        
        if (execute_query($sql, [$fullname, $email, $hashed_password, $phone, $role, $branch_id])) {
            return "Successfully registered!";
        } else {
            return "Registration failed!";
        }
    }

    /**
     * Mag-login ng user
     * @param string $email - email address
     * @param string $password - password
     * @return array|false - user data kung successful, false kung failed
     */
    function loginUser($email, $password) {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $user = get_single_result($sql, [$email]);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    }

    // ==================== BRANCH MANAGEMENT FUNCTIONS ====================

    /**
     * Kumuha ng lahat ng branches
     * @return array - listahan ng branches
     */
    function getAllBranches() {
        // If branches.host_id does not exist on this schema, avoid the JOIN
        if (function_exists('column_exists') && column_exists('branches', 'host_id')) {
            $sql = "SELECT b.*, u.full_name as host_name 
                    FROM branches b 
                    LEFT JOIN users u ON b.host_id = u.user_id 
                    WHERE b.is_active = 1 
                    ORDER BY b.branch_name";
        } else {
            $sql = "SELECT b.* FROM branches b WHERE b.is_active = 1 ORDER BY b.branch_name";
        }
        return get_multiple_results($sql);
    }

    /**
     * Kumuha ng specific branch
     * @param int $branch_id - branch ID
     * @return array|false - branch data
     */
    function getBranchById($branch_id) {
        if (function_exists('column_exists') && column_exists('branches', 'host_id')) {
            $sql = "SELECT b.*, u.full_name as host_name 
                    FROM branches b 
                    LEFT JOIN users u ON b.host_id = u.user_id 
                    WHERE b.branch_id = ? AND b.is_active = 1";
        } else {
            $sql = "SELECT b.* FROM branches b WHERE b.branch_id = ? AND b.is_active = 1";
        }
        return get_single_result($sql, [$branch_id]);
    }

    /**
     * Mag-add ng bagong branch
     * @param string $branch_name - pangalan ng branch
     * @param string $address - address ng branch
     * @param string $city - city
     * @param string $contact_number - contact number
     * @param string $email - email ng branch
     * @param int $host_id - host ID
     * @return string - success o error message
     */
    function addBranch($branch_name, $address, $city, $contact_number, $email, $host_id = null) {
        $sql = "INSERT INTO branches (branch_name, address, city, contact_number, email, host_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        // DEBUG: Log the function call
        error_log("=== addBranch() called ===");
        error_log("  branch_name: " . $branch_name);
        error_log("  address: " . $address);
        error_log("  city: " . $city);
        error_log("  contact_number: " . $contact_number);
        error_log("  email: " . $email);
        error_log("  host_id: " . ($host_id === null ? "NULL" : $host_id));
        error_log("  SQL: " . $sql);
        
        $params = [$branch_name, $address, $city, $contact_number, $email, $host_id];
        error_log("  Params count: " . count($params));
        error_log("  Params: " . json_encode($params));
        
        $result = execute_query($sql, $params);
        
        error_log("  execute_query() returned: " . ($result ? "TRUE" : "FALSE"));
        
        if ($result) {
            error_log("  SUCCESS: Branch added!");
            return "Branch added successfully!";
        } else {
            error_log("  FAILURE: Branch NOT added!");
            return "Failed to add branch!";
        }
    }

    // ==================== UNIT MANAGEMENT FUNCTIONS ====================

    /**
     * Kumuha ng available units sa specific branch
     * @param int $branch_id - branch ID
     * @param string $check_in - check-in date
     * @param string $check_out - check-out date
     * @return array - available units
     */
    function getAvailableUnits($branch_id, $check_in, $check_out) {
        $sql = "SELECT u.*, b.branch_name, b.address AS branch_address, b.city AS branch_city
                FROM units u
                JOIN branches b ON u.branch_id = b.branch_id
                WHERE u.branch_id = ?
                AND u.is_available = 1
                AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
                AND u.unit_id NOT IN (
                    SELECT unit_id FROM reservations 
                    WHERE branch_id = ? 
                    AND (
                        status IN ('confirmed', 'checked_in')
                        OR (status = 'pending' AND (hold_expiry IS NULL OR hold_expiry > NOW()))
                    )
                    AND (
                        check_in_date < ? AND check_out_date > ?
                    )
                )
                ORDER BY u.unit_number";

        return get_multiple_results($sql, [$branch_id, $branch_id, $check_out, $check_in]);
    }

    /**
     * Mag-check ng unit availability
     * @param int $unit_id - unit ID
     * @param string $check_in - check-in date
     * @param string $check_out - check-out date
     * @return bool - true kung available, false kung hindi
     */
    function checkUnitAvailability($unit_id, $check_in, $check_out) {
        $sql = "SELECT COUNT(*) as count FROM reservations 
                WHERE unit_id = ? 
                AND (
                    status IN ('confirmed', 'checked_in')
                    OR (status = 'pending' AND (hold_expiry IS NULL OR hold_expiry > NOW()))
                )
                AND (
                    (check_in_date < ? AND check_out_date > ?)
                )";
        
        $result = get_single_result($sql, [$unit_id, $check_out, $check_in]);
        return $result && $result['count'] == 0;
    }

    /**
     * Get unit details with defensive defaults for schema evolution
     */
    function getUnitWithDefaults($unit_id) {
        $sql = "SELECT * FROM units WHERE unit_id = ?";
        $unit = get_single_result($sql, [$unit_id]);
        
        if ($unit) {
            // Apply defensive defaults for newly added or optional columns
            $unit['cancellation_policy'] = $unit['cancellation_policy'] ?? 'Strict';
            $unit['instant_booking'] = (int)($unit['instant_booking'] ?? 0);
            $unit['capacity'] = (int)($unit['capacity'] ?? ($unit['max_occupancy'] ?? 1));
            // Ensure strings are at least empty instead of null for UI consistency
            $unit['building_name'] = $unit['building_name'] ?? '';
            $unit['street_address'] = $unit['street_address'] ?? '';
        }
        
        return $unit;
    }

    // ==================== RESERVATION FUNCTIONS ====================

    /**
     * Mag-create ng bagong reservation
     * @param int $user_id - user ID
     * @param int $unit_id - unit ID
     * @param int $branch_id - branch ID
     * @param string $check_in_date - check-in date
     * @param string $check_out_date - check-out date
     * @param float $total_amount - total amount
     * @param float $security_deposit - security deposit
     * @param string $special_requests - special requests
     * @return int|false - reservation ID kung successful, false kung failed
     */
    function createReservation($user_id, $unit_id, $branch_id, $check_in_date, $check_out_date, $total_amount, $security_deposit = 0, $special_requests = '', $num_adults = 1, $num_children = 0) {
        global $conn;
        
        // Sanitize inputs
        $user_id = (int)$user_id;
        $unit_id = (int)$unit_id;
        $branch_id = (int)$branch_id;
        $check_in_date = sanitize_input($check_in_date);
        $check_out_date = sanitize_input($check_out_date);
        $total_amount = (float)$total_amount;
        $security_deposit = (float)$security_deposit;
        $special_requests = sanitize_input($special_requests);
        $num_adults = max(1, (int)$num_adults);
        $num_children = max(0, (int)$num_children);
        
        // I-check muna kung available pa ang unit
        if (!checkUnitAvailability($unit_id, $check_in_date, $check_out_date)) {
            return false;
        }
        
        // START TRANSACTION for atomic operation
        $conn->begin_transaction();
        
        try {
            // LAYER 1: Check for overlapping reservations for this unit (by ANY user)
            $overlap_sql = "SELECT reservation_id FROM reservations 
                            WHERE unit_id = ?
                            AND (
                                status IN ('confirmed', 'checked_in')
                                OR (status = 'pending' AND (hold_expiry IS NULL OR hold_expiry > NOW()))
                            )
                            AND (
                                check_in_date < ? AND check_out_date > ?
                            )
                            LIMIT 1";
            
            $overlap_params = [$unit_id, $check_out_date, $check_in_date];
            $overlap_result = get_single_result($overlap_sql, $overlap_params);
            
            if ($overlap_result) {
                $conn->rollback();
                return false;
            }
            
            // LAYER 2: Check for existing user reservations for same dates
            $existing_sql = "SELECT reservation_id FROM reservations 
                            WHERE user_id = ? 
                            AND unit_id = ?
                            AND (
                                status IN ('confirmed', 'checked_in')
                                OR (status = 'pending' AND (hold_expiry IS NULL OR hold_expiry > NOW()))
                            )
                            AND (
                                check_in_date < ? AND check_out_date > ?
                            )
                            LIMIT 1";
            
            $existing_params = [$user_id, $unit_id, $check_out_date, $check_in_date];
            $existing_result = get_single_result($existing_sql, $existing_params);
            
            if ($existing_result) {
                $conn->rollback();
                return false;
            }
            
            // LAYER 3: Insert reservation within transaction
            $sql = "INSERT INTO reservations (user_id, unit_id, branch_id, check_in_date, check_out_date, total_amount, security_deposit, special_requests, num_adults, num_children, status, payment_status, hold_expiry, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())";
            $insertOk = execute_query($sql, [$user_id, $unit_id, $branch_id, $check_in_date, $check_out_date, $total_amount, $security_deposit, $special_requests, $num_adults, $num_children]);
            
            if ($insertOk) {
                $reservation_id = $conn->insert_id;
                
                // Commit transaction
                $conn->commit();
                
                // Get branch host assigned to this branch
                $branch = get_single_result("SELECT host_id FROM branches WHERE branch_id = ?", [$branch_id]);
                
                // Notify every host that should see this booking (branch manager + unit owner)
                $hostIds = [];
                if (!empty($branch['host_id'])) {
                    $hostIds[] = (int)$branch['host_id'];
                }
                $unitHost = get_single_result("SELECT host_id FROM units WHERE unit_id = ?", [$unit_id]);
                if ($unitHost && !empty($unitHost['host_id'])) {
                    $hostIds[] = (int)$unitHost['host_id'];
                }
                $hostIds = array_values(array_unique(array_filter($hostIds)));
                foreach ($hostIds as $hid) {
                    if ($hid > 0 && $hid !== (int)$user_id) {
                        sendNotification(
                            $hid,
                            'New Booking Request - Awaiting Approval',
                            'New reservation #' . $reservation_id . ' needs your approval. Please review in your dashboard.',
                            'booking',
                            'system'
                        );
                    }
                }
                
                // Send notification to renter that booking was submitted
                sendNotification(
                    $user_id,
                    'Booking Submitted for Approval',
                    'Your reservation #' . $reservation_id . ' has been submitted and is awaiting host approval.',
                    'booking',
                    'system'
                );
                
                return $reservation_id;
            } else {
                $conn->rollback();
                return false;
            }
        } catch (Exception $e) {
            $conn->rollback();
            error_log('Reservation creation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Host approves a reservation
     * @param int $reservation_id - reservation ID
     * @param int $host_id - host ID
     * @return bool - success or failure
     */
    function approveReservation($reservation_id, $host_id) {
        global $conn;
        
        // Verify the reservation exists and belongs to this host's branch
        $reservation = get_single_result(
            "SELECT r.*, b.host_id FROM reservations r 
             JOIN branches b ON r.branch_id = b.branch_id 
             WHERE r.reservation_id = ? AND b.host_id = ?",
            [$reservation_id, $host_id]
        );
        
        if (!$reservation) {
            return false;
        }
        
        // Update reservation status to 'confirmed' (host dashboard + renter checkout expect this)
        $sql = "UPDATE reservations SET status = 'confirmed', approved_at = NOW(), approved_by = ? WHERE reservation_id = ?";
        
        if (execute_query($sql, [$host_id, $reservation_id])) {
            // Send notification to renter
            sendNotification(
                $reservation['user_id'],
                'Booking Approved - Ready for Payment',
                'Your booking #' . $reservation_id . ' has been approved by the host. You can now proceed with payment.',
                'booking',
                'system'
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Host rejects a reservation
     * @param int $reservation_id - reservation ID
     * @param int $host_id - host ID
     * @param string $reason - rejection reason
     * @return bool - success or failure
     */
    function rejectReservation($reservation_id, $host_id, $reason = '') {
        global $conn;
        
        // Verify the reservation exists and belongs to this host's branch
        $reservation = get_single_result(
            "SELECT r.*, b.host_id FROM reservations r 
             JOIN branches b ON r.branch_id = b.branch_id 
             WHERE r.reservation_id = ? AND b.host_id = ?",
            [$reservation_id, $host_id]
        );
        
        if (!$reservation) {
            return false;
        }
        
        // Update reservation status to 'rejected'
        $sql = "UPDATE reservations SET status = 'rejected', rejected_at = NOW(), rejection_reason = ?, rejected_by = ? WHERE reservation_id = ?";
        
        if (execute_query($sql, [$reason, $host_id, $reservation_id])) {
            // Send notification to renter about rejection
            sendNotification(
                $reservation['user_id'],
                'Booking Rejected',
                'Your booking #' . $reservation_id . ' has been rejected. Reason: ' . $reason,
                'booking',
                'system'
            );
            
            return true;
        }
        
        return false;
    }

    /**
     * Kumuha ng user reservations
     * @param int $user_id - user ID
     * @return array - user reservations
     */
    function getUserReservations($user_id) {
        $sql = "SELECT r.*, u.unit_number, u.unit_type, b.branch_name, b.address 
                FROM reservations r 
                JOIN units u ON r.unit_id = u.unit_id 
                JOIN branches b ON r.branch_id = b.branch_id 
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC";
        
        return get_multiple_results($sql, [$user_id]);
    }

    // ==================== AMENITY MANAGEMENT FUNCTIONS ====================

    /**
     * Amenities associated with any approved/visible unit in a branch (catalog + unit_amenities).
     * Returns amenity_id, amenity_name; hourly_rate is 0 for compatibility with legacy bookable-amenity UI.
     *
     * @param int $branch_id
     * @return array
     */
    function getBranchAmenities($branch_id) {
        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return [];
        }
        $sql = "SELECT DISTINCT a.id AS amenity_id, a.name AS amenity_name, 0 AS hourly_rate
                FROM amenities a
                INNER JOIN unit_amenities ua ON ua.amenity_id = a.id
                INNER JOIN units u ON u.unit_id = ua.unit_id
                WHERE u.branch_id = ?
                  AND u.is_available = 1
                  AND (u.approval_status = 'approved' OR u.approval_status IS NULL)
                ORDER BY a.name";
        return get_multiple_results($sql, [$branch_id]);
    }

    /**
     * Listing amenities for a unit (global amenities catalog + unit_amenities junction).
     * Returns rows with amenity_id and amenity_name for template compatibility.
     *
     * @param int $unit_id
     * @return array
     */
    function getUnitAmenities($unit_id) {
        $unit_id = (int) $unit_id;
        if ($unit_id <= 0) {
            return [];
        }
        $sql = "SELECT a.id AS amenity_id, a.name AS amenity_name
                FROM unit_amenities ua
                INNER JOIN amenities a ON a.id = ua.amenity_id
                WHERE ua.unit_id = ?
                ORDER BY a.name";
        return get_multiple_results($sql, [$unit_id]);
    }

    /**
     * Replace all unit_amenities rows for a unit (only IDs that exist in amenities are kept).
     *
     * @param int $unit_id
     * @param array $amenity_ids
     * @return bool
     */
    function syncUnitAmenities($unit_id, array $amenity_ids) {
        $unit_id = (int) $unit_id;
        if ($unit_id <= 0) {
            return false;
        }
        if (!execute_query("DELETE FROM unit_amenities WHERE unit_id = ?", [$unit_id])) {
            return false;
        }
        $ids = array_unique(array_filter(array_map('intval', $amenity_ids)));
        if (empty($ids)) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valid = get_multiple_results(
            "SELECT id FROM amenities WHERE id IN ($placeholders)",
            array_values($ids)
        );
        foreach ($valid as $row) {
            execute_query(
                "INSERT INTO unit_amenities (unit_id, amenity_id) VALUES (?, ?)",
                [$unit_id, (int) $row['id']]
            );
        }
        return true;
    }

    /**
     * Mag-check kung available ang amenity sa specific time
     * @param int $amenity_id - amenity ID
     * @param string $booking_date - booking date
     * @param string $start_time - start time
     * @param string $end_time - end time
     * @return bool - true kung available
     */
    function checkAmenityAvailability($amenity_id, $booking_date, $start_time, $end_time) {
        // Check kung may overlapping bookings na confirmed
        $sql = "SELECT COUNT(*) as count FROM amenity_bookings 
                WHERE amenity_id = ? 
                AND booking_date = ? 
                AND status = 'confirmed'
                AND (
                    (start_time < ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )";
        
        $result = get_single_result($sql, [$amenity_id, $booking_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time]);
        return !$result || $result['count'] == 0;
    }

    /**
     * Mag-book ng amenity
     * @param int $user_id - user ID
     * @param int $amenity_id - amenity ID
     * @param int $branch_id - branch ID
     * @param string $booking_date - booking date
     * @param string $start_time - start time
     * @param string $end_time - end time
     * @param float $total_amount - total amount
     * @return int|false - booking ID kung successful, false kung failed
     */
    function bookAmenity($user_id, $amenity_id, $branch_id, $booking_date, $start_time, $end_time, $total_amount) {
        global $conn;
        
        // Check kung available ang amenity sa requested time
        if (!checkAmenityAvailability($amenity_id, $booking_date, $start_time, $end_time)) {
            return false; // May conflict na
        }
        
        $sql = "INSERT INTO amenity_bookings (user_id, amenity_id, branch_id, booking_date, start_time, end_time, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        if (execute_query($sql, [$user_id, $amenity_id, $branch_id, $booking_date, $start_time, $end_time, $total_amount])) {
            return $conn->insert_id;
        }
        
        return false;
    }

    // ==================== PAYMENT FUNCTIONS ====================

    /**
     * Mag-process ng payment
     * @param int $reservation_id - reservation ID (optional)
     * @param int $amenity_booking_id - amenity booking ID (optional)
     * @param int $user_id - user ID
     * @param float $amount - amount
     * @param string $payment_method - payment method
     * @param string $transaction_reference - transaction reference
     * @return int|false - payment ID kung successful, false kung failed
     */
    function processPayment($reservation_id, $amenity_booking_id, $user_id, $amount, $payment_method, $transaction_reference = '', $payment_status = 'pending', $payment_proof = '') {
        global $conn;
        
        // FIXED CRITICAL BUG: FRAUD RISK - Don't hardcode to 'completed'
        // Use actual payment status from payment gateway
        if (!in_array($payment_status, ['pending', 'paid', 'completed', 'failed'])) {
            $payment_status = 'pending';
        }
        
        $sql = "INSERT INTO payments (reservation_id, amenity_booking_id, user_id, amount, payment_method, transaction_reference, payment_status, payment_proof) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        if (execute_query($sql, [$reservation_id, $amenity_booking_id, $user_id, $amount, $payment_method, $transaction_reference, $payment_status, $payment_proof])) {
            $payment_id = $conn->insert_id;
            
            // FIXED HIGH #16: Process reservation status and finance logic
            if ($reservation_id) {
                $reservation = get_single_result(
                    "SELECT r.*, u.unit_number, b.branch_name FROM reservations r 
                     JOIN units u ON r.unit_id = u.unit_id 
                     JOIN branches b ON r.branch_id = b.branch_id 
                     WHERE r.reservation_id = ?",
                    [$reservation_id]
                );
                
                if ($reservation) {
                    $user = get_single_result("SELECT * FROM users WHERE user_id = ?", [$user_id]);
                    $total_req = (float)$reservation['total_amount'];
                    $amount_paid = (float)$amount;
                    
                    // ALL payments require verification before confirmation
                    // Even if fully paid, it stays pending until Admin verifies it.
                    $new_payment_status = 'pending';
                    $new_reservation_status = 'pending';
                    
                    // Update reservation to indicate payment was submitted (but pending verification)
                    execute_query(
                        "UPDATE reservations SET payment_status = ? WHERE reservation_id = ?", 
                        [$new_payment_status, $reservation_id]
                    );
                    
                    // Send notifications for Verification Queue
                    sendNotification($user_id, 'Payment Submitted', 'Your payment is under review. Reservation #' . $reservation_id . ' will be confirmed once verified by our team.', 'payment', 'system');

                    // Note: Revenue split and status upgrade (to confirmed) will be executed during Admin Verification.
                }
            }
            
            return $payment_id;
        }
        
        return false;
    }
        
    // ==================== NOTIFICATION FUNCTIONS ====================

    /**
     * Mag-send ng notification
     * @param int $user_id - user ID
     * @param string $title - notification title
     * @param string $message - notification message
     * @param string $type - notification type
     * @param string $sent_via Legacy 5th arg (ignored); kept for backward compatibility with old call sites.
     * @param string|null $admin_message Optional detail shown as "Admin note" in UI (notifications.admin_message).
     * @return bool - true kung successful, false kung failed
     */
    function sendNotification($user_id, $title, $message, $type = 'system', $sent_via = 'system', $admin_message = null) {
        $allowed = ['booking', 'payment', 'reminder', 'system', 'approval'];
        if (!in_array($type, $allowed, true)) {
            $type = 'system';
        }

        // Duplication Prevention: Avoid spamming the same exact unread alert
        $check_sql = "SELECT notification_id FROM notifications WHERE user_id = ? AND title = ? AND message = ? AND is_read = 0 AND is_archived = 0 LIMIT 1";
        $existing = get_single_result($check_sql, [(int)$user_id, $title, $message]);
        if ($existing) {
            return true; // Already exists an exact unread copy, silently drop to avoid spam
        }

        $sql = "INSERT INTO notifications (user_id, title, message, admin_message, status, type) VALUES (?, ?, ?, ?, 'info', ?)";
        return execute_query($sql, [(int) $user_id, $title, $message, $admin_message, $type]);
    }

    /**
     * Kumuha ng user notifications
     * @param int $user_id - user ID
     * @param int $limit - limit ng notifications
     * @return array - notifications
     */
    function getUserNotifications($user_id, $limit = 10) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        return get_multiple_results($sql, [$user_id, $limit]);
    }

    // ==================== UTILITY FUNCTIONS ====================

    /**
     * Mag-format ng currency
     * @param float $amount - amount
     * @return string - formatted currency
     */
    function safe_format($amount, $decimals = 2) {
        return number_format((float)($amount ?? 0), $decimals);
    }

    /**
     * Mag-format ng currency na may Peso sign
     * @param float $amount - amount
     * @return string - formatted currency with ₱ sign
     */
    function format_currency($amount) {
        return '₱' . safe_format($amount);
    }

    /**
     * Mag-format ng percentage
     * @param float $value - percentage value
     * @param int $decimals - number of decimals
     * @return string - formatted percentage
     */
    function format_percentage($value, $decimals = 1) {
        return safe_format($value, $decimals) . '%';
    }

    /**
     * Mag-format ng date
     * @param string $date - date string
     * @return string - formatted date
     */
    function formatDate($date) {
        return date('M d, Y', strtotime($date));
    }

    /**
     * Mag-calculate ng total days
     * @param string $check_in - check-in date
     * @param string $check_out - check-out date
     * @return int - total days
     */
    function calculateDays($check_in, $check_out) {
        $start = new DateTime($check_in);
        $end = new DateTime($check_out);
        return $start->diff($end)->days;
    }

    /**
     * Mag-validate ng date range
     * @param string $check_in - check-in date
     * @param string $check_out - check-out date
     * @return bool - true kung valid, false kung hindi
     */
    function validateDateRange($check_in, $check_out) {
        $today = date('Y-m-d');
        return $check_in >= $today && $check_out > $check_in;
    }

    function fetch_pairs($conn, $sql, $labelKey = null, $valueKey = null) {
    $out = ['labels' => [], 'values' => []];
    if (!($conn instanceof mysqli)) return $out;
    $res = @$conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
        // if keys not provided, take first two columns
        if (!$labelKey) {
            $cols = array_keys($r);
            $label = $r[$cols[0]];
            $value = (int)$r[$cols[1]];
        } else {
            $label = $r[$labelKey];
            $value = (int)$r[$valueKey];
        }
        $out['labels'][] = $label;
        $out['values'][] = $value;
        }
    }
    return $out;
    }

    // ==================== SANITIZATION FUNCTION ====================
    if (!function_exists('sanitize_input')) {
        function sanitize_input($data) {
            if (is_array($data)) {
                return array_map('sanitize_input', $data);
            }
            if ($data === null || $data === '') {
                return '';
            }
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            return $data;
        }
    }

    // -----------------------------
    // Helper functions
    // -----------------------------
    function esc($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    function fetch_all($mysqli, $sql) {
        $rows = [];
        if (!$mysqli) return $rows;
        
        try {
            $res = $mysqli->query($sql);
            if ($res && $res->num_rows > 0) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }
        } catch (Exception $e) {
            // Log error but don't break the page
            error_log("Database query error: " . $e->getMessage());
        }
        
        return $rows;
    }

    // ==================== UNIT MANAGEMENT FUNCTIONS ====================

    /**
     * Mag-add ng bagong unit
     * @param string $unit_number - unit number
     * @param string $unit_type - unit type
     * @param int $branch_id - branch ID
     * @param int $host_id - host ID (manager)
     * @param float $price - price per night
     * @param int $capacity - capacity
     * @param int $bedrooms - number of bedrooms
     * @param int $bathrooms - number of bathrooms
     * @param string $size - unit size
     * @param string $description - unit description
     * @param string $amenities - JSON encoded amenities
     * @return string - success message o error message
     */
    function addUnit($unit_number, $unit_type, $branch_id, $host_id, $price, $capacity, $bedrooms, $bathrooms, $size, $description, $amenities = '[]') {
        global $conn;
        
        // I-check kung existing na ang unit number sa branch na ito
        $check_sql = "SELECT unit_id FROM units WHERE unit_number = ? AND branch_id = ? AND is_active = 1";
        $check_result = get_single_result($check_sql, [$unit_number, $branch_id]);
        
        if ($check_result) {
            return "Unit number already exists in this branch!";
        }
        
        $sql = "INSERT INTO units (unit_number, unit_type, branch_id, host_id, price, max_occupancy, bedrooms, bathrooms, size, description, amenities) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if (execute_query($sql, [$unit_number, $unit_type, $branch_id, $host_id, $price, $capacity, $bedrooms, $bathrooms, $size, $description, $amenities])) {
            return "Unit added successfully!";
        } else {
            return "Failed to add unit!";
        }
    }

    /**
     * Kumuha ng unit by ID
     * @param int $unit_id - unit ID
     * @return array|false - unit data
     */
    function getUnitById($unit_id) {
        $sql = "SELECT u.*, b.branch_name, h.full_name as host_name
                FROM units u 
                LEFT JOIN branches b ON u.branch_id = b.branch_id 
                LEFT JOIN users h ON u.host_id = h.user_id 
                WHERE u.unit_id = ? AND u.is_active = 1";
        return get_single_result($sql, [$unit_id]);
    }

    /**
     * Kumuha ng lahat ng units with filters
     * @param array $filters - search filters
     * @return array - listahan ng units
     */
    function getAllUnits($filters = []) {
        $where = "WHERE u.is_active = 1";
        $params = [];
        
        if (!empty($filters['branch_id'])) {
            $where .= " AND u.branch_id = ?";
            $params[] = $filters['branch_id'];
        }
        
        if (!empty($filters['unit_type'])) {
            $where .= " AND u.unit_type = ?";
            $params[] = $filters['unit_type'];
        }
        
        if (!empty($filters['status'])) {
            $where .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['host_id'])) {
            $where .= " AND u.host_id = ?";
            $params[] = $filters['host_id'];
        }
        
        if (!empty($filters['search'])) {
            $where .= " AND (u.unit_number LIKE ? OR u.description LIKE ?)";
            $search_term = "%{$filters['search']}%";
            array_push($params, $search_term, $search_term);
        }
        
        $sql = "SELECT u.*, b.branch_name, h.full_name as host_name
                FROM units u 
                LEFT JOIN branches b ON u.branch_id = b.branch_id 
                LEFT JOIN users h ON u.host_id = h.user_id 
                $where 
                ORDER BY u.created_at DESC";
        
        return get_multiple_results($sql, $params);
    }

    /**
     * Mag-update ng unit status
     * @param int $unit_id - unit ID
     * @param string $status - new status
     * @return bool - true kung successful, false kung failed
     */
    function updateUnitStatus($unit_id, $status) {
        $sql = "UPDATE units SET status = ? WHERE unit_id = ?";
        return execute_query($sql, [$status, $unit_id]);
    }

    /**
     * Kumuha ng unit performance metrics
     * @param int $unit_id - unit ID
     * @return array - performance data
     */
    function getUnitPerformance($unit_id) {
        $sql = "SELECT 
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                    COUNT(CASE WHEN status IN ('confirmed', 'checked_in') THEN 1 END) as active_bookings,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
                    COALESCE(SUM(CASE WHEN status IN ('completed', 'confirmed') THEN total_amount ELSE 0 END), 0) as total_revenue,
                    AVG(CASE WHEN status = 'completed' THEN DATEDIFF(check_out_date, check_in_date) END) as avg_stay_duration
                FROM reservations 
                WHERE unit_id = ?";
        
        return get_single_result($sql, [$unit_id]);
    }

    /**
     * Kumuha ng unit reservation history
     * @param int $unit_id - unit ID
     * @param int $limit - limit ng results
     * @return array - reservation history
     */
    function getUnitReservationHistory($unit_id, $limit = 10) {
        $sql = "SELECT r.*, u.full_name as customer_name
                FROM reservations r 
                JOIN users u ON r.user_id = u.user_id 
                WHERE r.unit_id = ? 
                ORDER BY r.created_at DESC 
                LIMIT ?";
        
        return get_multiple_results($sql, [$unit_id, $limit]);
    }

    /**
     * Mag-assign ng unit sa host
     * @param int $unit_id - unit ID
     * @param int $host_id - host ID
     * @return bool - true kung successful, false kung failed
     */
    function assignUnitToHost($unit_id, $host_id) {
        $sql = "UPDATE units SET host_id = ? WHERE unit_id = ?";
        return execute_query($sql, [$host_id, $unit_id]);
    }

    /**
     * Mag-delete ng unit (soft delete)
     * @param int $unit_id - unit ID
     * @return bool - true kung successful, false kung failed
     */
    function deleteUnit($unit_id) {
        $sql = "UPDATE units SET is_active = 0 WHERE unit_id = ?";
        return execute_query($sql, [$unit_id]);
    }

    /**
     * Kumuha ng available unit types
     * @return array - unit types
     */
    function getUnitTypes() {
        $sql = "SELECT DISTINCT unit_type FROM units WHERE unit_type IS NOT NULL AND is_active = 1 ORDER BY unit_type";
        return get_multiple_results($sql);
    }

    /**
     * Kumuha ng units by host
     * @param int $host_id - host ID
     * @return array - host units
     */
    function getUnitsByHost($host_id) {
        $sql = "SELECT u.*, b.branch_name
                FROM units u 
                JOIN branches b ON u.branch_id = b.branch_id 
                WHERE u.host_id = ? AND u.is_active = 1 
                ORDER BY u.unit_number";
        
        return get_multiple_results($sql, [$host_id]);
    }

    /**
     * Mag-synchronize ng branch deletion effects
     * @param int $branch_id - branch ID na dinelete
     * @return bool - true kung successful, false kung failed
     */
    function synchronizeBranchDeletion($branch_id) {
        global $conn;
        
        try {
            $conn->begin_transaction();
            
            // 1. I-deactivate ang lahat ng units sa branch
            $sql = "UPDATE units SET is_available = 0 WHERE branch_id = ?";
            if (!execute_query($sql, [$branch_id])) {
                throw new Exception("Failed to deactivate units");
            }
            
            // 2. I-cancel ang lahat ng pending at confirmed reservations
            $sql = "UPDATE reservations SET status = 'cancelled' 
                    WHERE branch_id = ? AND status IN ('pending', 'confirmed')";
            if (!execute_query($sql, [$branch_id])) {
                throw new Exception("Failed to cancel reservations");
            }
            
            // 3. I-cancel ang lahat ng amenity bookings
            $sql = "UPDATE amenity_bookings SET status = 'cancelled' 
                    WHERE branch_id = ? AND status IN ('pending', 'confirmed')";
            if (!execute_query($sql, [$branch_id])) {
                throw new Exception("Failed to cancel amenity bookings");
            }
            
            $conn->commit();
            return true;
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Synchronization failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kumuha ng branch statistics kasama ang revenue
     * @param int $branch_id - branch ID
     * @return array - branch statistics
     */
    function getBranchStatistics($branch_id) {
        $sql = "SELECT 
                    b.branch_name,
                    (SELECT COUNT(*) FROM units WHERE branch_id = b.branch_id AND is_available = 1) as active_units,
                    (SELECT COUNT(*) FROM reservations WHERE branch_id = b.branch_id) as total_reservations,
                    (SELECT SUM(total_amount) FROM reservations WHERE branch_id = b.branch_id AND status IN ('confirmed', 'checked_in', 'completed')) as total_revenue,
                    (SELECT COUNT(DISTINCT unit_id) FROM reservations WHERE branch_id = b.branch_id AND status IN ('confirmed', 'checked_in')) as currently_booked
                FROM branches b
                WHERE b.branch_id = ?";
        return get_single_result($sql, [$branch_id]);
    }

    /**
     * Cleanup expired pending reservations (10-minute hold logic)
     * Called frequently (pseudo-cron) to release unit availability.
     */
    function cleanupExpiredReservations() {
        global $conn;
        
        // Cancel expired reservations
        $sql = "UPDATE reservations 
                SET status = 'cancelled', 
                    cancellation_reason = 'Automated: Payment hold expired (10-minute limit exceeded)' 
                WHERE status = 'pending' 
                AND hold_expiry IS NOT NULL 
                AND hold_expiry < NOW()";
        
        if (execute_query($sql)) {
            // Optional: return count for logging
            return $conn->affected_rows;
        }
        return 0;
    }
?>

