-- Migration: Add government_id_path to reservations, instant_booking to units,
-- add payout tracking columns and payouts table
-- Run once. Standard MySQL does not support ADD COLUMN IF NOT EXISTS.

ALTER TABLE reservations
ADD COLUMN government_id_path VARCHAR(500) DEFAULT NULL;

ALTER TABLE units
ADD COLUMN instant_booking TINYINT(1) DEFAULT 0;

-- Add payout flag on payments
ALTER TABLE payments
ADD COLUMN payout_released TINYINT(1) DEFAULT 0,
ADD COLUMN payout_released_at DATETIME NULL;

-- Create payouts table to record host payouts
CREATE TABLE IF NOT EXISTS payouts (
  payout_id INT NOT NULL AUTO_INCREMENT,
  host_id INT NOT NULL,
  reservation_id INT DEFAULT NULL,
  payment_id VARCHAR(100) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  method VARCHAR(50) DEFAULT NULL,
  status ENUM('pending','released','failed') DEFAULT 'pending',
  released_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payout_id),
  KEY host_id (host_id),
  KEY reservation_id (reservation_id)
);
