<?php
/**
 * MigrationManager.php
 * Handles database schema versioning and controlled updates.
 * Defense-Grade implementation for BookIT.
 */

class MigrationManager {
    private $db;
    private $migrationsPath;
    private $tableName = 'schema_migrations';

    public function __construct($db, $migrationsPath = null) {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../migrations';
        $this->ensureMigrationTable();
    }

    private function ensureMigrationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `version` VARCHAR(100) UNIQUE NOT NULL,
            `description` TEXT,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";
        
        if (!$this->db->query($sql)) {
            throw new Exception("Failed to create migration table: " . $this->db->error);
        }
    }

    public function getAppliedMigrations() {
        $applied = [];
        $result = $this->db->query("SELECT version FROM `{$this->tableName}`");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $applied[] = $row['version'];
            }
        }
        return $applied;
    }

    public function runPendingMigrations() {
        $applied = $this->getAppliedMigrations();
        $files = glob($this->migrationsPath . '/M*.php');
        sort($files); // Ensure chronological order

        $executed = 0;
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (!in_array($version, $applied)) {
                echo "Applying migration: $version...\n";
                $this->applyMigration($file, $version);
                $executed++;
            }
        }
        return $executed;
    }

    private function applyMigration($file, $version) {
        // We use a transaction if the driver supports it, 
        // but note that DDL in MySQL causes implicit commit.
        // So we rely on defensive coding within the migration files.
        
        try {
            // Migration files should return an array with 'description' and a 'callback' or just have logic.
            // For simplicity and safety, we'll include the file which should define a class or function.
            $migrationData = include $file;
            
            if (!is_array($migrationData) || !isset($migrationData['up'])) {
                throw new Exception("Invalid migration format in $version. Must return array with 'up' callback.");
            }

            // Execute the migration logic
            $migrationData['up']($this->db);

            // Record success
            $stmt = $this->db->prepare("INSERT INTO `{$this->tableName}` (version, description) VALUES (?, ?)");
            $desc = $migrationData['description'] ?? '';
            $stmt->bind_param("ss", $version, $desc);
            $stmt->execute();
            
            echo "Successfully applied $version.\n";
        } catch (Exception $e) {
            error_log("Migration failed for $version: " . $e->getMessage());
            throw new Exception("Migration failed for $version: " . $e->getMessage());
        }
    }
}
