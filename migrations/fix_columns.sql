ALTER TABLE units ADD COLUMN review_locked_by INT NULL;
ALTER TABLE units ADD COLUMN review_locked_at DATETIME NULL;
ALTER TABLE units ADD COLUMN reviewed_at DATETIME NULL;
CREATE TABLE IF NOT EXISTS unit_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    flag_type VARCHAR(50) NOT NULL,
    details TEXT,
    flagged_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (unit_id)
) ENGINE=InnoDB;
