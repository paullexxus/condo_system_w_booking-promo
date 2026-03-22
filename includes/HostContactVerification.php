<?php
/**
 * HostContactVerification Class
 * Detects duplicate listings based on phone/email/payout account linking
 * 
 * Checks:
 * - Phone number cross-check
 * - Email account linking
 * - Payout account connections
 * - Multiple accounts with same contact info
 * 
 * This is Airbnb's #4 duplicate detection method
 */

class HostContactVerification {
    public $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Generate hash of phone number for secure comparison
     */
    public function hashPhoneNumber($phone) {
        // Normalize phone - remove all non-digits
        $normalized = preg_replace('/\D/', '', $phone);
        return hash('sha256', $normalized);
    }
    
    /**
     * Generate hash of email
     */
    public function hashEmail($email) {
        $normalized = strtolower(trim($email));
        return hash('sha256', $normalized);
    }
    
    /**
     * Register host contact information
     */
    public function registerHostContact($host_id, $phone = '', $email = '', $payout_account_id = null) {
        $phone_hash = $phone ? $this->hashPhoneNumber($phone) : null;
        $email_hash = $email ? $this->hashEmail($email) : null;
        
        $sql = "INSERT INTO host_contact_verification 
                (host_id, phone_number, phone_hash, email, email_hash, payout_account_id)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    phone_number = VALUES(phone_number),
                    phone_hash = VALUES(phone_hash),
                    email = VALUES(email),
                    email_hash = VALUES(email_hash),
                    payout_account_id = VALUES(payout_account_id),
                    updated_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in registerHostContact: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('issssi', $host_id, $phone, $phone_hash, $email, $email_hash, $payout_account_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Check if phone number is used by other hosts
     */
    public function checkPhoneUsage($phone, $exclude_host_id = null) {
        $phone_hash = $this->hashPhoneNumber($phone);
        
        $sql = "SELECT DISTINCT hcv.host_id, u.full_name, u.email, 
                       COUNT(DISTINCT u2.unit_id) as listing_count
                FROM host_contact_verification hcv
                JOIN users u ON hcv.host_id = u.user_id
                LEFT JOIN units u2 ON u.user_id = u2.host_id
                WHERE hcv.phone_hash = ?";
        
        $params = [$phone_hash];
        
        if ($exclude_host_id) {
            $sql .= " AND hcv.host_id != ?";
            $params[] = $exclude_host_id;
        }
        
        $sql .= " GROUP BY hcv.host_id";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in checkPhoneUsage: " . $this->conn->error);
            return [];
        }
        
        $types = str_repeat('i', count($params));
        $bind_params = [$types];
        foreach ($params as $val) {
            $bind_params[] = &$val;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hosts = [];
        while ($row = $result->fetch_assoc()) {
            $hosts[] = $row;
        }
        $stmt->close();
        
        return $hosts;
    }
    
    /**
     * Check if email is used by other hosts
     */
    public function checkEmailUsage($email, $exclude_host_id = null) {
        $email_hash = $this->hashEmail($email);
        
        $sql = "SELECT DISTINCT hcv.host_id, u.full_name, u.email,
                       COUNT(DISTINCT u2.unit_id) as listing_count
                FROM host_contact_verification hcv
                JOIN users u ON hcv.host_id = u.user_id
                LEFT JOIN units u2 ON u.user_id = u2.host_id
                WHERE hcv.email_hash = ?";
        
        $params = [$email_hash];
        
        if ($exclude_host_id) {
            $sql .= " AND hcv.host_id != ?";
            $params[] = $exclude_host_id;
        }
        
        $sql .= " GROUP BY hcv.host_id";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in checkEmailUsage: " . $this->conn->error);
            return [];
        }
        
        $types = str_repeat('i', count($params));
        $bind_params = [$types];
        foreach ($params as $val) {
            $bind_params[] = &$val;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hosts = [];
        while ($row = $result->fetch_assoc()) {
            $hosts[] = $row;
        }
        $stmt->close();
        
        return $hosts;
    }
    
    /**
     * Check if payout account is used by other hosts
     */
    public function checkPayoutUsage($payout_account_id, $exclude_host_id = null) {
        if (!$payout_account_id) {
            return [];
        }
        
        $sql = "SELECT DISTINCT hcv.host_id, u.full_name, u.email,
                       COUNT(DISTINCT u2.unit_id) as listing_count
                FROM host_contact_verification hcv
                JOIN users u ON hcv.host_id = u.user_id
                LEFT JOIN units u2 ON u.user_id = u2.host_id
                WHERE hcv.payout_account_id = ?";
        
        $params = [$payout_account_id];
        
        if ($exclude_host_id) {
            $sql .= " AND hcv.host_id != ?";
            $params[] = $exclude_host_id;
        }
        
        $sql .= " GROUP BY hcv.host_id";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in checkPayoutUsage: " . $this->conn->error);
            return [];
        }
        
        $types = str_repeat('i', count($params));
        $bind_params = [$types];
        foreach ($params as $val) {
            $bind_params[] = &$val;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hosts = [];
        while ($row = $result->fetch_assoc()) {
            $hosts[] = $row;
        }
        $stmt->close();
        
        return $hosts;
    }
    
    /**
     * Get all linked hosts (same phone, email, or payout)
     */
    public function getLinkedHosts($host_id) {
        // Get this host's contact info
        $sql = "SELECT phone_hash, email_hash, payout_account_id FROM host_contact_verification WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getLinkedHosts: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param('i', $host_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $contact = $result->fetch_assoc();
        $stmt->close();
        
        if (!$contact) {
            return [];
        }
        
        // Find all hosts with matching contact info
        $linked_hosts = [];
        
        // Phone matches
        if ($contact['phone_hash']) {
            $phone_hosts = $this->checkPhoneUsage('', $host_id);
            $sql_phone = "SELECT DISTINCT u.user_id, u.full_name, u.email 
                         FROM host_contact_verification hcv
                         JOIN users u ON hcv.host_id = u.user_id
                         WHERE hcv.phone_hash = ? AND hcv.host_id != ?
                         LIMIT 20";
            
            $stmt = $this->conn->prepare($sql_phone);
            if ($stmt) {
                $stmt->bind_param('si', $contact['phone_hash'], $host_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $row['link_type'] = 'phone';
                    $linked_hosts[] = $row;
                }
                $stmt->close();
            }
        }
        
        // Email matches
        if ($contact['email_hash']) {
            $sql_email = "SELECT DISTINCT u.user_id, u.full_name, u.email
                         FROM host_contact_verification hcv
                         JOIN users u ON hcv.host_id = u.user_id
                         WHERE hcv.email_hash = ? AND hcv.host_id != ?
                         LIMIT 20";
            
            $stmt = $this->conn->prepare($sql_email);
            if ($stmt) {
                $stmt->bind_param('si', $contact['email_hash'], $host_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $row['link_type'] = 'email';
                    $linked_hosts[] = $row;
                }
                $stmt->close();
            }
        }
        
        // Remove duplicates
        $linked_hosts = array_unique($linked_hosts, SORT_REGULAR);
        
        return $linked_hosts;
    }
    
    /**
     * Get listings by linked hosts
     */
    public function getLinkedHostListings($host_id) {
        $linked = $this->getLinkedHosts($host_id);
        $linked_host_ids = array_column($linked, 'user_id');
        
        if (empty($linked_host_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($linked_host_ids), '?'));
        $sql = "SELECT u.unit_id, u.unit_number, u.building_name, u.street_address,
                       h.user_id, h.full_name as host_name
                FROM units u
                JOIN users h ON u.host_id = h.user_id
                WHERE u.host_id IN ($placeholders)
                ORDER BY u.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getLinkedHostListings: " . $this->conn->error);
            return [];
        }
        
        $types = str_repeat('i', count($linked_host_ids));
        $bind_params = [$types];
        foreach ($linked_host_ids as $id) {
            $bind_params[] = &$id;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $listings = [];
        while ($row = $result->fetch_assoc()) {
            $listings[] = $row;
        }
        $stmt->close();
        
        return $listings;
    }
    
    /**
     * Log contact cross-check duplicate
     */
    public function logContactDuplicate($unit_id, $duplicate_unit_id, $check_type, $details = []) {
        $sql = "INSERT INTO duplicate_detection_logs 
                (unit_id, duplicate_unit_id, detection_type, severity, details)
                VALUES (?, ?, 'phone_cross', 'high', ?)";
        
        $details['check_type'] = $check_type;
        $details_json = json_encode($details);
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in logContactDuplicate: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('iis', $unit_id, $duplicate_unit_id, $details_json);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get contact verification records for a host
     */
    public function getContactRecords($host_id) {
        $sql = "SELECT * FROM host_contact_verification WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getContactRecords: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param('i', $host_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        $stmt->close();
        
        return $record;
    }
}
?>
