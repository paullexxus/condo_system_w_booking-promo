<?php
include_once '../config/db.php';
include_once '../includes/session.php';
include_once '../includes/functions.php';
checkRole(['admin']);

// Check/Create audit_logs table
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int NOT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Check/Create notifications table and fields
mysqli_query($conn, "
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `admin_message` text,
  `status` varchar(50) DEFAULT 'info',
  `type` varchar(50) DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
try {
    @mysqli_query($conn, "ALTER TABLE `notifications` ADD COLUMN `admin_message` text NULL AFTER `message`");
} catch (Exception $e) {}

try {
    @mysqli_query($conn, "ALTER TABLE `notifications` ADD COLUMN `status` varchar(50) DEFAULT 'info' AFTER `admin_message`");
} catch (Exception $e) {}

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $app_id = (int)$_POST['application_id'];
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
    
    if (empty(trim($admin_notes))) {
        $error_msg = "⚠️ Admin message is required.";
    } else {
        // Get application details first
        // Note: ensure we join `users` or select `email`, `full_name` to send emails
        $app_result = mysqli_query($conn, "SELECT h.user_id, h.status, u.email, u.full_name as user_name FROM host_applications h JOIN users u ON h.user_id = u.user_id WHERE h.application_id = $app_id");
        if ($row = mysqli_fetch_assoc($app_result)) {
        $user_id = $row['user_id'];
        $old_status = $row['status'];
        $user_email = $row['email'];
        $user_name = $row['user_name'];
        $admin_id = $_SESSION['user_id'];
        
        $log_action = "";
        $log_details = "";
        
        if ($_POST['action'] === 'approve') {
            $conn->begin_transaction();
            try {
                // Update Application Status
                mysqli_query($conn, "UPDATE host_applications SET status = 'approved', admin_notes = '$admin_notes', reviewed_by = $admin_id, reviewed_at = NOW() WHERE application_id = $app_id");
                
                // Update User Role to Host
                mysqli_query($conn, "UPDATE users SET role = 'host' WHERE user_id = $user_id");
                
                $log_action = $old_status === 'rejected' ? 'Admin Approved Rejected Application' : 'Admin Approved Application';
                $log_details = "Notes: $admin_notes";
                mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES ($admin_id, '$log_action', 'host_application', $app_id, '$log_details')");
                
                // Shoot Approved Email
                @include_once '../includes/email_integration.php';
                if (function_exists('sendHostApplicationApprovedEmail')) {
                    @sendHostApplicationApprovedEmail($user_email, $user_name);
                }

                // Insert Notification
                $notif_title = "🎉 Host Application Approved";
                $notif_msg = "Your application has been approved. You can now start listing your property.";
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, admin_message, status, type) VALUES ($user_id, '$notif_title', '$notif_msg', '$admin_notes', 'approved', 'system')");

                $conn->commit();
                $success_msg = "Host application successfully approved.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Error approving application.";
            }
        } elseif ($_POST['action'] === 'reject') {
            mysqli_query($conn, "UPDATE host_applications SET status = 'rejected', admin_notes = '$admin_notes', reviewed_by = $admin_id, reviewed_at = NOW() WHERE application_id = $app_id");
            
            $log_action = 'Admin Rejected Application';
            $log_details = "Reason: $admin_notes";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES ($admin_id, '$log_action', 'host_application', $app_id, '$log_details')");
            
            // Shoot Rejected Email
            @include_once '../includes/email_integration.php';
            if (function_exists('sendHostApplicationRejectedEmail')) {
                @sendHostApplicationRejectedEmail($user_email, $user_name, $admin_notes);
            }

            // Insert Notification
            $notif_title = "❌ Host Application Rejected";
            $notif_msg = "Your application was not approved. Please check the reason below.";
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, admin_message, status, type) VALUES ($user_id, '$notif_title', '$notif_msg', '$admin_notes', 'rejected', 'system')");

            $success_msg = "Host application rejected.";
        } elseif ($_POST['action'] === 'reopen') {
            mysqli_query($conn, "UPDATE host_applications SET status = 'pending', admin_notes = '$admin_notes', reviewed_by = $admin_id, reviewed_at = NOW() WHERE application_id = $app_id");
            
            $log_action = 'Admin Re-opened Application';
            $log_details = "Notes: $admin_notes";
            mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES ($admin_id, '$log_action', 'host_application', $app_id, '$log_details')");
            
            $success_msg = "Host application re-opened and set to pending.";
        }
        }
    }
}

// Fetch all applications
$query = "SELECT h.*, u.full_name as raw_full_name, u.email as raw_email, u.phone as raw_phone 
          FROM host_applications h 
          JOIN users u ON h.user_id = u.user_id 
          ORDER BY h.created_at DESC";
$result = mysqli_query($conn, $query);
$applications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $applications[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Host Verifications - BookIT Admin</title>
  <link rel="stylesheet" href="../assets/css/admin/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Overwrite Tailwind preflight for base compatibility with Bootstrap/existing CSS if needed -->
  <style>
    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1040; }
    .modal-box { position: fixed; inset: 0; margin: auto; max-width: 800px; height: fit-content; max-height: 90vh; overflow-y: auto; background: white; border-radius: 12px; z-index: 1050; display: none; padding: 24px; }
  </style>
</head>
<body class="bg-gray-100">

<div class="flex min-h-screen">
  <?php include '../includes/sidebar.php'; ?>

  <!-- Main Content Area (Tailwind formatted based on sidebar width) -->
  <main class="flex-1 ml-[250px] p-8 content overflow-y-auto w-full">
    
    <div class="mb-8 flex justify-between items-center">
      <div>
        <h1 class="text-3xl font-bold text-gray-800">Host Verifications</h1>
        <p class="text-gray-600 mt-1">Review and manage pending host applications.</p>
      </div>
    </div>

    <?php if (isset($success_msg)): ?>
      <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline"><?php echo $success_msg; ?></span>
      </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
      <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline"><?php echo $error_msg; ?></span>
      </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Host Info</th>
            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Property</th>
            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Applied</th>
            <th class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (count($applications) > 0): ?>
            <?php foreach ($applications as $app): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-bold">
                      <?php echo strtoupper(substr($app['raw_full_name'], 0, 1)); ?>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['raw_full_name']); ?></div>
                      <div class="text-sm text-gray-500"><?php echo htmlspecialchars($app['raw_email']); ?></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900"><?php echo htmlspecialchars($app['condo_name']); ?></div>
                  <div class="text-sm text-gray-500 truncate max-w-[200px]"><?php echo htmlspecialchars($app['branch_name']); ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <?php if ($app['status'] == 'pending'): ?>
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                  <?php elseif ($app['status'] == 'approved'): ?>
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                  <?php else: ?>
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <button onclick="viewApplication(<?php echo htmlspecialchars(json_encode($app)); ?>)" 
                          class="text-orange-600 hover:text-orange-900 font-semibold bg-orange-50 px-4 py-2 rounded-lg">
                    Review
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="px-6 py-12 text-center text-gray-500">No applications found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<!-- Review Modal -->
<div id="modalBackdrop" class="modal-backdrop" onclick="closeModal()"></div>
<div id="reviewModal" class="modal-box shadow-2xl">
  <div class="flex justify-between items-start mb-4 border-b pb-4">
    <h2 class="text-2xl font-bold text-gray-800">Review Application</h2>
    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
  </div>

  <div class="grid grid-cols-2 gap-6 mb-6">
    <!-- User Information -->
    <div>
      <h3 class="text-lg font-bold text-gray-700 mb-3"><i class="fas fa-user text-orange-500 mr-2"></i> User Info</h3>
      <div class="bg-gray-50 p-4 rounded-lg space-y-2">
        <p class="text-sm"><span class="font-semibold text-gray-600">Name:</span> <span id="m_name"></span></p>
        <p class="text-sm"><span class="font-semibold text-gray-600">Email:</span> <span id="m_email"></span></p>
        <p class="text-sm"><span class="font-semibold text-gray-600">Phone:</span> <span id="m_phone"></span></p>
      </div>
    </div>

    <!-- Property Information -->
    <div>
      <h3 class="text-lg font-bold text-gray-700 mb-3"><i class="fas fa-building text-orange-500 mr-2"></i> Property Info</h3>
      <div class="bg-gray-50 p-4 rounded-lg space-y-2">
        <p class="text-sm"><span class="font-semibold text-gray-600">Condo:</span> <span id="m_condo"></span></p>
        <p class="text-sm"><span class="font-semibold text-gray-600">Branch:</span> <span id="m_branch"></span></p>
        <p class="text-sm"><span class="font-semibold text-gray-600">Address:</span> <span id="m_address"></span></p>
        <p class="text-sm"><span class="font-semibold text-gray-600">Social:</span> <a href="#" id="m_social" target="_blank" class="text-blue-500">Link</a></p>
      </div>
    </div>
  </div>

  <div class="mb-6">
    <h3 class="text-lg font-bold text-gray-700 mb-3"><i class="fas fa-id-card text-orange-500 mr-2"></i> Identity & Documents</h3>
    <div class="grid grid-cols-2 gap-4">
      <div class="border rounded-lg p-3">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Primary ID (<span id="m_id_type"></span>)</p>
        <p class="text-sm mb-2"><span class="font-semibold text-gray-600">No:</span> <span id="m_id_number"></span></p>
        <a href="#" id="m_primary_id" target="_blank" class="text-sm text-blue-600 hover:underline">View Document</a>
      </div>
      <div class="border rounded-lg p-3">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Secondary ID</p>
        <a href="#" id="m_secondary_id" target="_blank" class="text-sm text-blue-600 hover:underline">View Document</a>
      </div>
      <div class="border rounded-lg p-3">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Selfie with ID</p>
        <a href="#" id="m_selfie" target="_blank" class="text-sm text-blue-600 hover:underline">View Document</a>
      </div>
      <div class="border rounded-lg p-3">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Proof of Ownership</p>
        <a href="#" id="m_ownership" target="_blank" class="text-sm text-blue-600 hover:underline">View Document</a>
      </div>
      <div class="border rounded-lg p-3">
        <p class="text-xs text-gray-500 font-bold uppercase mb-1">Utility Bill</p>
        <a href="#" id="m_utility" target="_blank" class="text-sm text-blue-600 hover:underline">View Document</a>
      </div>
    </div>
  </div>

  <div class="mb-6">
    <h3 class="text-lg font-bold text-gray-700 mb-3"><i class="fas fa-money-check text-orange-500 mr-2"></i> Payout Details</h3>
    <div class="bg-gray-50 p-4 rounded-lg space-y-2">
      <p class="text-sm"><span class="font-semibold text-gray-600">Method:</span> <span id="m_payout_method"></span></p>
      <p class="text-sm"><span class="font-semibold text-gray-600">Account Name:</span> <span id="m_acc_name"></span></p>
      <p class="text-sm"><span class="font-semibold text-gray-600">Account Number:</span> <span id="m_acc_no"></span></p>
      <p class="text-sm"><span class="font-semibold text-gray-600">Bank Name:</span> <span id="m_bank_name"></span></p>
    </div>
  </div>

  <div id="decisionArea" class="border-t pt-4">
    <form method="POST">
      <input type="hidden" name="application_id" id="m_app_id">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
        <textarea name="admin_notes" id="m_admin_notes" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 p-3 border" rows="3" placeholder="Leave a note about this decision..."></textarea>
      </div>
      <div class="flex gap-4" id="m_buttons_area">
        <!-- Buttons injected by JS depending on status -->
      </div>
    </form>
  </div>
</div>

<script>
  function viewApplication(app) {
    document.getElementById('modalBackdrop').style.display = 'block';
    document.getElementById('reviewModal').style.display = 'block';
    
    // Fill data
    document.getElementById('m_app_id').value = app.application_id;
    document.getElementById('m_name').textContent = app.raw_full_name;
    document.getElementById('m_email').textContent = app.raw_email;
    document.getElementById('m_phone').textContent = app.raw_phone;

    document.getElementById('m_condo').textContent = app.condo_name;
    document.getElementById('m_branch').textContent = app.branch_name;
    document.getElementById('m_address').textContent = app.complete_address;
    
    if (app.social_media_link) {
        document.getElementById('m_social').href = app.social_media_link;
        document.getElementById('m_social').textContent = "View Profile";
    } else {
        document.getElementById('m_social').removeAttribute('href');
        document.getElementById('m_social').textContent = "N/A";
    }

    document.getElementById('m_id_type').textContent = app.primary_id_type;
    document.getElementById('m_id_number').textContent = app.primary_id_number;

    setupLink('m_primary_id', app.primary_id_path);
    setupLink('m_secondary_id', app.secondary_id_path);
    setupLink('m_selfie', app.selfie_with_id_path);
    setupLink('m_ownership', app.proof_of_ownership_path);
    setupLink('m_utility', app.utility_bill_path);

    document.getElementById('m_payout_method').textContent = app.payout_method;
    document.getElementById('m_acc_name').textContent = app.account_name;
    document.getElementById('m_acc_no').textContent = app.account_number;
    document.getElementById('m_bank_name').textContent = app.bank_name || 'N/A';

    // Fill admin notes
    document.getElementById('m_admin_notes').value = app.admin_notes || '';

    // Handle decision area visibility & buttons
    const btnArea = document.getElementById('m_buttons_area');
    btnArea.innerHTML = '';
    
    if (app.status === 'pending') {
        document.getElementById('decisionArea').style.display = 'block';
        btnArea.innerHTML = `
          <button type="submit" name="action" value="approve" class="flex-1 bg-green-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-700 transition">
            <i class="fas fa-check mr-2"></i> Approve as Host
          </button>
          <button type="submit" name="action" value="reject" class="flex-1 bg-red-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-red-700 transition" onclick="return confirm('Are you sure you want to reject this application?')">
            <i class="fas fa-times mr-2"></i> Reject Application
          </button>
        `;
    } else if (app.status === 'rejected') {
        document.getElementById('decisionArea').style.display = 'block';
        btnArea.innerHTML = `
          <button type="submit" name="action" value="reopen" class="flex-1 bg-yellow-500 text-white font-bold py-3 px-4 rounded-lg hover:bg-yellow-600 transition">
            <i class="fas fa-undo mr-2"></i> Re-open Application
          </button>
          <button type="submit" name="action" value="approve" class="flex-1 bg-green-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-green-700 transition" onclick="return confirm('This application was rejected. Are you sure you want to approve it now?')">
            <i class="fas fa-check mr-2"></i> Force Approve
          </button>
        `;
    } else {
        document.getElementById('decisionArea').style.display = 'none';
    }
  }

  function setupLink(elId, path) {
    const el = document.getElementById(elId);
    if (path && path.length > 0) {
        el.href = path;
        el.style.display = 'inline';
    } else {
        el.style.display = 'none';
    }
  }

  function closeModal() {
    document.getElementById('modalBackdrop').style.display = 'none';
    document.getElementById('reviewModal').style.display = 'none';
  }
</script>

</body>
</html>
