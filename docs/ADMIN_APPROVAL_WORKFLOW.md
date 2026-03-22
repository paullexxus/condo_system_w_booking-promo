# Admin Approval Workflow for Units

## Overview
Hosts upload units with status `pending`. Units are only visible to renters after admin approval.

## Session-Based Role Protection

All admin pages use role checking. Example from `pending_units.php`:

```php
<?php
include '../includes/session.php';
include '../includes/functions.php';
include_once '../config/db.php';

// Only admin can access
checkRole(['admin']);

// Page content...
```

### How checkRole() Works (includes/session.php)
- If user is not logged in → redirect to login
- If user's role is not in the allowed list → redirect to role-specific dashboard
- Admin pages: `checkRole(['admin'])`
- Host pages: `checkRole(['host'])` or `checkRole(['host','manager'])`
- Renter pages: `checkRole(['renter'])`

## Files Modified/Created
- `migrations/006_add_unit_approval_workflow.sql` - Schema
- `admin/pending_units.php` - Admin approval page
- `host/unit_management.php` - INSERT with approval_status='pending'
- `public/browse_units.php`, `includes/functions.php`, etc. - Filter approved only
- `includes/sidebar.php` - Pending Units menu link
