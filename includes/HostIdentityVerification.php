<?php
/**
 * HostIdentityVerification Class
 * Manages host verification including ID, Face, and Profile verification
 * 
 * Checks:
 * - ID document verification
 * - Face photo verification
 * - Profile information verification
 * - Verification score calculation
 * 
 * This is Airbnb's #5 duplicate detection method
 */

class HostIdentityVerification {
    public $conn;
    public $max_verification_score = 300;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Create or update host verification record
     */
    public function initializeVerification($host_id) {
        $sql = "INSERT INTO host_verification (host_id, verification_score)
                VALUES (?, 0)
                ON DUPLICATE KEY UPDATE 
                    updated_at = NOW()";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in initializeVerification: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('i', $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Update ID verification status
     */
    public function updateIDVerification($host_id, $doc_path, $status = 'pending') {
        $verified_date = $status === 'verified' ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE host_verification 
                SET id_document_path = ?, 
                    id_verification_status = ?,
                    id_verified_date = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updateIDVerification: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('sssi', $doc_path, $status, $verified_date, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Update face verification status
     */
    public function updateFaceVerification($host_id, $photo_path, $status = 'pending') {
        $verified_date = $status === 'verified' ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE host_verification 
                SET face_photo_path = ?,
                    face_verification_status = ?,
                    face_verified_date = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updateFaceVerification: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('sssi', $photo_path, $status, $verified_date, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Update profile verification status
     */
    public function updateProfileVerification($host_id, $status = 'pending') {
        $verified_date = $status === 'verified' ? date('Y-m-d H:i:s') : null;
        
        $sql = "UPDATE host_verification 
                SET profile_verification_status = ?,
                    profile_verified_date = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updateProfileVerification: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('ssi', $status, $verified_date, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Update phone verification status
     */
    public function updatePhoneVerification($host_id, $verified = true) {
        $sql = "UPDATE host_verification 
                SET phone_number_verified = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updatePhoneVerification: " . $this->conn->error);
            return false;
        }
        
        $verified_int = $verified ? 1 : 0;
        $stmt->bind_param('ii', $verified_int, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Update email verification status
     */
    public function updateEmailVerification($host_id, $verified = true) {
        $sql = "UPDATE host_verification 
                SET email_verified = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updateEmailVerification: " . $this->conn->error);
            return false;
        }
        
        $verified_int = $verified ? 1 : 0;
        $stmt->bind_param('ii', $verified_int, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Update payout verification status
     */
    public function updatePayoutVerification($host_id, $payout_account_id, $verified = true) {
        $sql = "UPDATE host_verification 
                SET payout_account_id = ?,
                    payout_verified = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updatePayoutVerification: " . $this->conn->error);
            return false;
        }
        
        $verified_int = $verified ? 1 : 0;
        $stmt->bind_param('iii', $payout_account_id, $verified_int, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->updateVerificationScore($host_id);
        }
        
        return $result;
    }
    
    /**
     * Calculate verification score (0-300)
     */
    public function calculateVerificationScore($host_id) {
        $sql = "SELECT 
                    CASE WHEN id_verification_status = 'verified' THEN 50 ELSE 0 END +
                    CASE WHEN face_verification_status = 'verified' THEN 50 ELSE 0 END +
                    CASE WHEN profile_verification_status = 'verified' THEN 50 ELSE 0 END +
                    CASE WHEN phone_number_verified = TRUE THEN 30 ELSE 0 END +
                    CASE WHEN email_verified = TRUE THEN 30 ELSE 0 END +
                    CASE WHEN payout_verified = TRUE THEN 50 ELSE 0 END
                AS score
                FROM host_verification
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in calculateVerificationScore: " . $this->conn->error);
            return 0;
        }
        
        $stmt->bind_param('i', $host_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return intval($row['score'] ?? 0);
    }
    
    /**
     * Update verification score and host status
     */
    public function updateVerificationScore($host_id) {
        $score = $this->calculateVerificationScore($host_id);
        $is_verified_host = ($score >= 230); // 77% verification required
        
        $sql = "UPDATE host_verification 
                SET verification_score = ?,
                    is_verified_host = ?
                WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in updateVerificationScore: " . $this->conn->error);
            return false;
        }
        
        $is_verified_int = $is_verified_host ? 1 : 0;
        $stmt->bind_param('iii', $score, $is_verified_int, $host_id);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get host verification status
     */
    public function getVerificationStatus($host_id) {
        $sql = "SELECT * FROM host_verification WHERE host_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getVerificationStatus: " . $this->conn->error);
            return null;
        }
        
        $stmt->bind_param('i', $host_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $status = $result->fetch_assoc();
        $stmt->close();
        
        return $status;
    }
    
    /**
     * Get verification progress (percentage)
     */
    public function getVerificationProgress($host_id) {
        $status = $this->getVerificationStatus($host_id);
        if (!$status) {
            return 0;
        }
        
        $progress = ($status['verification_score'] / $this->max_verification_score) * 100;
        return round($progress, 2);
    }
    
    /**
     * Check if host meets minimum verification requirements
     */
    public function isMinimumVerified($host_id) {
        $status = $this->getVerificationStatus($host_id);
        if (!$status) {
            return false;
        }
        
        return $status['is_verified_host'] === 1;
    }
    
    /**
     * Log identity-based duplicate detection
     */
    public function logIdentityDuplicate($unit_id, $duplicate_unit_id, $reason = '', $details = []) {
        $sql = "INSERT INTO duplicate_detection_logs 
                (unit_id, duplicate_unit_id, detection_type, severity, details)
                VALUES (?, ?, 'host_identity', 'critical', ?)";
        
        $details['reason'] = $reason;
        $details_json = json_encode($details);
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in logIdentityDuplicate: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param('iis', $unit_id, $duplicate_unit_id, $details_json);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get unverified hosts
     */
    public function getUnverifiedHosts($limit = 50) {
        $sql = "SELECT hv.*, u.full_name, u.email, 
                       COUNT(DISTINCT un.unit_id) as listing_count
                FROM host_verification hv
                JOIN users u ON hv.host_id = u.user_id
                LEFT JOIN units un ON u.user_id = un.host_id
                WHERE hv.is_verified_host = FALSE
                GROUP BY hv.host_id
                ORDER BY hv.verification_score ASC, hv.updated_at ASC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed in getUnverifiedHosts: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param('i', $limit);
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
     * Get verification requirements summary
     */
    public function getVerificationRequirements($host_id) {
        $status = $this->getVerificationStatus($host_id);
        
        return [
            'id_verification' => [
                'required' => true,
                'completed' => $status['id_verification_status'] === 'verified',
                'status' => $status['id_verification_status'],
                'points' => 50
            ],
            'face_verification' => [
                'required' => true,
                'completed' => $status['face_verification_status'] === 'verified',
                'status' => $status['face_verification_status'],
                'points' => 50
            ],
            'profile_verification' => [
                'required' => true,
                'completed' => $status['profile_verification_status'] === 'verified',
                'status' => $status['profile_verification_status'],
                'points' => 50
            ],
            'phone_verification' => [
                'required' => true,
                'completed' => $status['phone_number_verified'] === 1,
                'points' => 30
            ],
            'email_verification' => [
                'required' => true,
                'completed' => $status['email_verified'] === 1,
                'points' => 30
            ],
            'payout_verification' => [
                'required' => true,
                'completed' => $status['payout_verified'] === 1,
                'points' => 50
            ],
            'total_score' => $status['verification_score'],
            'progress' => $this->getVerificationProgress($host_id),
            'is_verified' => $status['is_verified_host'] === 1
        ];
    }
}
?>
