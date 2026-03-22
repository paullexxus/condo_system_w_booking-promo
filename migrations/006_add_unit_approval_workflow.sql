-- Admin Approval Workflow for Units
-- Run once. Backfills existing units as approved so they remain visible to renters.

ALTER TABLE units
ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
ADD COLUMN rejection_reason TEXT NULL;

-- Approve all existing units so they remain visible to renters
UPDATE units SET approval_status = 'approved';
