-- Add host_id to branches (app expects it for host assignment, manage_branch, etc.).
-- Run once. Skip if branches already has host_id.

ALTER TABLE branches
ADD COLUMN host_id INT UNSIGNED DEFAULT NULL,
ADD KEY branches_host_id_index (host_id);
