-- Migration: add cleaning_fee and service_fee to units
-- Run this once against your BookIT database

ALTER TABLE units
  ADD COLUMN cleaning_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN service_fee DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Optional: backfill zeros for existing rows (default covers it)
UPDATE units SET cleaning_fee = 0 WHERE cleaning_fee IS NULL;
UPDATE units SET service_fee = 0 WHERE service_fee IS NULL;
