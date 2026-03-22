<?php
class ImageFingerprinting {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }
    public function registerImage($entity_id, $filepath, $type) {
        $stmt = $this->conn->prepare("INSERT INTO unit_images (unit_id, image_path, upload_date) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $entity_id, $filepath);
        return $stmt->execute();
    }
}
