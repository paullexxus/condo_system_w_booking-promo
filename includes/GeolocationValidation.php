<?php
class GeolocationValidation {
    private $conn;
    public function __construct($conn) {
        $this->conn = $conn;
    }
    public function registerGeolocation($entity_id, $lat, $lng) {
        // Stub implementation: do nothing for now
        return true;
    }
}
