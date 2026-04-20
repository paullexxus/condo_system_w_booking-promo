<?php
require_once 'migrations/fix_payout_schema.php';
require_once 'migrations/expand_unit_metadata.php';
require_once 'migrations/harden_amenity_requests.php';
echo "Migrations attempted. Check output.";
