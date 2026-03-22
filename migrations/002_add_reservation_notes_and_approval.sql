-- Add host_notes, admin_notes, approval, cancellation, and renter feedback columns to reservations
-- Run once. On MySQL (not MariaDB), IF NOT EXISTS is not supported for ADD COLUMN.

ALTER TABLE reservations
ADD COLUMN host_notes TEXT DEFAULT NULL,
ADD COLUMN admin_notes TEXT DEFAULT NULL,
ADD COLUMN cancellation_reason TEXT DEFAULT NULL,
ADD COLUMN approved_by INT UNSIGNED DEFAULT NULL,
ADD COLUMN approved_at DATETIME NULL DEFAULT NULL,
ADD COLUMN renter_rating TINYINT UNSIGNED DEFAULT NULL,
ADD COLUMN renter_feedback TEXT DEFAULT NULL;
