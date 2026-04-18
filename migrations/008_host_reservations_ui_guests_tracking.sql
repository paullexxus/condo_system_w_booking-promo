-- Host reservations UI: guest counts, check-in/out attribution, status enum alignment
-- Run once on your BookIT database. If a column already exists, remove that line and re-run.

-- Guest counts (capacity on host cards)
ALTER TABLE reservations
  ADD COLUMN num_adults INT UNSIGNED NOT NULL DEFAULT 1 AFTER special_requests,
  ADD COLUMN num_children INT UNSIGNED NOT NULL DEFAULT 0 AFTER num_adults;

-- Who performed check-in / check-out (for audit trail on host UI)
ALTER TABLE reservations
  ADD COLUMN checked_in_by INT UNSIGNED NULL DEFAULT NULL AFTER approved_at,
  ADD COLUMN checked_out_by INT UNSIGNED NULL DEFAULT NULL AFTER checked_in_by;

-- Align reservation lifecycle with host dashboard (checkout uses "completed").
-- Keeps legacy "approved" rows readable alongside "confirmed".
ALTER TABLE reservations
  MODIFY COLUMN status ENUM(
    'pending',
    'approved',
    'confirmed',
    'checked_in',
    'checked_out',
    'completed',
    'cancelled'
  ) NOT NULL DEFAULT 'pending';
