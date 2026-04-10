<?php
include_once '../includes/public_session.php';
include_once '../includes/functions.php';
include_once '../includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Database connection
include_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check if user has an application
$query = "SELECT * FROM host_applications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    // If no application, redirect to host register
    header("Location: ../public/be_host.php");
    exit;
}

$application = mysqli_fetch_assoc($result);

// If approved, update session role quietly
if ($application['status'] === 'approved') {
    $_SESSION['role'] = 'host';
}

// Handle Form Resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application']) && $application['status'] === 'rejected') {
    // Property Details
    $condo_name = mysqli_real_escape_string($conn, $_POST['condo_name'] ?? '');
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name'] ?? '');
    $condo_address = mysqli_real_escape_string($conn, $_POST['condo_address'] ?? '');
    $social_media = mysqli_real_escape_string($conn, $_POST['social_media'] ?? '');

    // Identity details
    $primary_id_type = mysqli_real_escape_string($conn, $_POST['primary_id_type'] ?? '');
    $primary_id_number = mysqli_real_escape_string($conn, $_POST['primary_id_number'] ?? '');
    
    // Payout details
    $payout_method = mysqli_real_escape_string($conn, $_POST['payout_method'] ?? '');
    $account_name = mysqli_real_escape_string($conn, $_POST['account_name'] ?? '');
    $account_number = mysqli_real_escape_string($conn, $_POST['account_number'] ?? '');
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');

    $upload_dir = "../uploads/host_applications/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    function handleFileUpload($fileInputName, $upload_dir, $existing_path) {
        if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $new_name = time() . '_' . rand(1000, 9999) . '_' . $fileInputName . '.' . $ext;
                $dest = $upload_dir . $new_name;
                if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $dest)) {
                    // Safe to delete old? Best to keep existing clean or just overwrite variable
                    if ($existing_path && file_exists($existing_path)) {
                        @unlink($existing_path);
                    }
                    return $dest;
                }
            }
        }
        return $existing_path; // Keep existing if no valid fresh upload
    }

    $valid_id1_path = handleFileUpload('valid_id1', $upload_dir, $application['primary_id_path']);
    $valid_id2_path = handleFileUpload('valid_id2', $upload_dir, $application['secondary_id_path']);
    $selfie_path = handleFileUpload('selfie_with_id', $upload_dir, $application['selfie_with_id_path']);
    $ownership_path = handleFileUpload('proof_ownership', $upload_dir, $application['proof_of_ownership_path']);
    $utility_path = handleFileUpload('utility_bill', $upload_dir, $application['utility_bill_path']);
    
    // Update
    $app_id = (int)$application['application_id'];
    $conn->begin_transaction();
    try {
        $update_query = "UPDATE host_applications SET 
            condo_name = '$condo_name', branch_name = '$branch_name', complete_address = '$condo_address', social_media_link = '$social_media',
            primary_id_type = '$primary_id_type', primary_id_number = '$primary_id_number', 
            primary_id_path = '$valid_id1_path', secondary_id_path = '$valid_id2_path', selfie_with_id_path = '$selfie_path',
            proof_of_ownership_path = '$ownership_path', utility_bill_path = '$utility_path', 
            payout_method = '$payout_method', account_name = '$account_name', account_number = '$account_number', bank_name = '$bank_name',
            status = 'pending', reviewed_by = NULL, reviewed_at = NULL 
            WHERE application_id = $app_id";
        
        mysqli_query($conn, $update_query);
        
        // Log resubmission
        $log_action = 'User Resubmitted Application';
        $log_details = "User ID: $user_id updated and resubmitted denied application #$app_id";
        mysqli_query($conn, "INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, details) VALUES ($user_id, '$log_action', 'host_application', $app_id, '$log_details')");

        // Add Notification
        sendNotification($user_id, 'Application Resubmitted', 'Your Host application has been resubmitted and is under review.', 'system', 'system');

        // Email Notification
        @include_once '../includes/email_integration.php';
        if (function_exists('sendHostApplicationReceivedEmail')) {
            @sendHostApplicationReceivedEmail($_SESSION['email'], $_SESSION['fullname']);
        }
        
        $conn->commit();
        
        $message = "Application submitted! 🎉 Your application is now under review. We’ll notify you once it’s done.";
        $application['status'] = 'pending'; // Refresh local state
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating application. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Host Application - BookIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <!-- Enhanced modern Navbar aligning with profile.php -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../public/index.php">
                <i class="fas fa-building me-1 text-warning"></i> BookIT
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item me-2">
                        <a class="nav-link btn btn-outline-warning btn-sm px-3 text-white fw-bold border-warning" href="my_application.php">
                            <i class="fas fa-clipboard-list me-1"></i> My Host Application
                        </a>
                    </li>
                    <li class="nav-item">
                        <div class="nav-link dropdown">
                            <a class="dropdown-toggle d-flex align-items-center text-white text-decoration-none fw-medium" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle fa-lg me-2"></i>
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm rounded-3 border-0 mt-2" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item py-2" href="../modules/notifications.php"><i class="fas fa-bell me-2 text-primary"></i>Notifications</a></li>
                                <li><a class="dropdown-item py-2" href="my_bookings.php"><i class="fas fa-calendar-check me-2 text-success"></i>My Bookings</a></li>
                                <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-cog me-2 text-secondary"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-medium" href="../public/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="max-w-5xl mx-auto px-4 pt-32 pb-16">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Host Application Status</h1>

        <?php if ($message): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl"></i>
                    <span><?php echo $message; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                    <span><?php echo $error; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($application['status'] === 'pending'): ?>
            <!-- Review Details UI -->
            <div class="bg-white rounded-[24px] shadow-sm p-10 text-center border border-gray-200">
                <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6 text-yellow-600 shadow-inner">
                    <i class="fas fa-hourglass-half text-4xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">🕒 Application Under Review</h2>
                <p class="text-gray-500 mb-8 max-w-lg mx-auto">Your Host application is currently being reviewed by our team. Please wait 3–5 business days for the result. We’ll notify you once it’s done.</p>
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 max-w-sm mx-auto text-left flex flex-col gap-2">
                    <div class="text-sm"><span class="font-bold text-gray-700 w-32 inline-block">Date Applied:</span> <span class="text-gray-600"><?php echo date('F j, Y', strtotime($application['created_at'])); ?></span></div>
                    <div class="text-sm"><span class="font-bold text-gray-700 w-32 inline-block">Branch:</span> <span class="text-gray-600"><?php echo htmlspecialchars($application['branch_name']); ?></span></div>
                    <div class="text-sm"><span class="font-bold text-gray-700 w-32 inline-block">Status:</span> <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded font-semibold text-xs">Pending</span></div>
                </div>
            </div>
        <?php elseif ($application['status'] === 'rejected'): ?>
            <!-- Rejected/Edit UI -->
            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-xl mb-8 shadow-sm">
                <div class="flex gap-4">
                    <div class="text-red-500 text-2xl"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h3 class="text-lg font-bold text-red-800 mb-1">❌ Application Not Approved</h3>
                        <p class="text-red-700 mb-3 text-sm">Unfortunately, your application was not approved. You can update your information and resubmit your application.</p>
                        <div class="bg-white p-4 rounded-lg border border-red-100 text-red-900 text-sm shadow-sm flex flex-col">
                            <strong class="mb-1 text-red-950">Reason:</strong>
                            <span class="text-gray-800 whitespace-pre-wrap"><?php echo htmlspecialchars($application['admin_notes']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[24px] shadow-sm p-10 border border-gray-200">
                <div class="mb-8 border-b border-gray-100 pb-6">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Edit Your Application</h3>
                    <p class="text-gray-500 text-sm">💡 Make sure your uploaded documents are clear and valid to avoid delays.</p>
                </div>

                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10" id="resubmitForm">
                    <input type="hidden" name="update_application" value="1">
                    
                    <!-- Property Details -->
                    <div class="space-y-6">
                        <div class="pb-3 border-b border-gray-200">
                            <h4 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas fa-building text-orange-500 mr-2"></i> Property Information
                            </h4>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Condo Name</label>
                            <input type="text" name="condo_name" required value="<?php echo htmlspecialchars($application['condo_name']); ?>"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50 text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Branch Name</label>
                            <input type="text" name="branch_name" required value="<?php echo htmlspecialchars($application['branch_name']); ?>"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50 text-gray-900">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Complete Address</label>
                            <textarea name="condo_address" required rows="2"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50 text-gray-900"><?php echo htmlspecialchars($application['complete_address']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Social Media Link</label>
                            <input type="url" name="social_media" value="<?php echo htmlspecialchars($application['social_media_link']); ?>"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                        </div>
                    </div>

                    <!-- Payout Details -->
                    <div class="space-y-6">
                        <div class="pb-3 border-b border-gray-200">
                            <h4 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas fa-money-check text-orange-500 mr-2"></i> Payout Details
                            </h4>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payout Method</label>
                            <select name="payout_method" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50 text-gray-900">
                                <option value="Bank Transfer" <?php if($application['payout_method']=='Bank Transfer') echo 'selected'; ?>>Bank Transfer</option>
                                <option value="GCash" <?php if($application['payout_method']=='GCash') echo 'selected'; ?>>GCash</option>
                                <option value="Maya" <?php if($application['payout_method']=='Maya') echo 'selected'; ?>>Maya</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Name</label>
                                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($application['bank_name']); ?>"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Account Number</label>
                                <input type="text" name="account_number" required value="<?php echo htmlspecialchars($application['account_number']); ?>"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Account Name</label>
                            <input type="text" name="account_name" required value="<?php echo htmlspecialchars($application['account_name']); ?>"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                        </div>
                    </div>

                    <!-- Documents & Identity -->
                    <div class="col-span-1 md:col-span-2 space-y-6">
                        <div class="pb-3 border-b border-gray-200">
                            <h4 class="text-lg font-bold text-gray-800 flex items-center">
                                <i class="fas fa-id-card text-orange-500 mr-2"></i> Document Updates
                            </h4>
                            <p class="text-xs text-gray-500 mt-1">Leave file inputs empty if you want to keep your previously uploaded documents.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Primary ID Type</label>
                                <select name="primary_id_type" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                                    <option value="Passport" <?php if($application['primary_id_type']=='Passport') echo 'selected'; ?>>Passport</option>
                                    <option value="Driver License" <?php if($application['primary_id_type']=='Driver License') echo 'selected'; ?>>Driver's License</option>
                                    <option value="UMID" <?php if($application['primary_id_type']=='UMID') echo 'selected'; ?>>UMID</option>
                                    <option value="National ID" <?php if($application['primary_id_type']=='National ID') echo 'selected'; ?>>National ID</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">ID Number</label>
                                <input type="text" name="primary_id_number" required value="<?php echo htmlspecialchars($application['primary_id_number']); ?>"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 bg-gray-50">
                            </div>
                        </div>

                        <!-- File uploads -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 pt-4 border-t border-gray-100">
                            <!-- ID 1 -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Primary ID Image</label>
                                <div class="text-xs text-blue-600 hover:underline mb-2"><a href="<?php echo $application['primary_id_path']; ?>" target="_blank">View Currently Saved</a></div>
                                <input type="file" name="valid_id1" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>

                            <!-- ID 2 -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Secondary ID Image</label>
                                <div class="text-xs text-blue-600 hover:underline mb-2"><a href="<?php echo $application['secondary_id_path']; ?>" target="_blank">View Currently Saved</a></div>
                                <input type="file" name="valid_id2" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>

                            <!-- Selfie -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Selfie with ID</label>
                                <div class="text-xs text-blue-600 hover:underline mb-2"><a href="<?php echo $application['selfie_with_id_path']; ?>" target="_blank">View Currently Saved</a></div>
                                <input type="file" name="selfie_with_id" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>

                            <!-- Proof Ownership -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Proof of Ownership</label>
                                <div class="text-xs text-blue-600 hover:underline mb-2"><a href="<?php echo $application['proof_of_ownership_path']; ?>" target="_blank">View Currently Saved</a></div>
                                <input type="file" name="proof_ownership" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>

                            <!-- Utility Bill -->
                            <div class="col-span-1 md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Recent Utility Bill</label>
                                <div class="text-xs text-blue-600 hover:underline mb-2"><a href="<?php echo $application['utility_bill_path']; ?>" target="_blank">View Currently Saved</a></div>
                                <input type="file" name="utility_bill" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            </div>
                        </div>
                    </div>

                    <div class="col-span-1 md:col-span-2 pt-8 border-t border-gray-200 text-right">
                        <button type="submit" id="submitBtn" class="inline-flex items-center justify-center rounded-xl px-10 py-4 text-white font-bold shadow-lg transition bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 transform hover:-translate-y-0.5">
                            <span id="btnText"><i class="fas fa-paper-plane mr-2"></i> Edit & Resubmit Application</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($application['status'] === 'approved'): ?>
            <!-- Approved UI -->
            <div class="bg-white rounded-[24px] shadow-sm p-10 text-center border border-gray-200">
                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6 text-green-600 shadow-inner">
                    <i class="fas fa-check-circle text-5xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">🎉 You’re Now a Host!</h2>
                <p class="text-gray-600 mb-8 max-w-lg mx-auto text-lg">Congratulations! Your Host application has been approved. You can now start listing your property and accept bookings.</p>
                <a href="../host/host_dashboard.php" class="inline-flex items-center justify-center rounded-xl px-10 py-4 text-white font-bold shadow-lg transition bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 transform hover:-translate-y-0.5">
                    Go to Host Dashboard <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Success Modal -->
    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="fixed inset-0 z-50 overflow-y-auto" id="successModal" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeSuccessModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-t-8 border-green-500">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-16 w-16 rounded-full bg-green-100 sm:mx-0 sm:h-12 sm:w-12 mt-1">
                            <i class="fas fa-check text-2xl text-green-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-xl leading-6 font-bold text-gray-900" id="modal-title">
                                ✅ Application Submitted!
                            </h3>
                            <div class="mt-4 space-y-3">
                                <p class="text-sm text-gray-600 font-medium">Thank you for applying as a Host on BookIT! 🎉</p>
                                <p class="text-sm text-gray-600">Your application is now under review. Please allow <strong class="text-gray-800">3–5 business days</strong> for verification.</p>
                                <p class="text-sm text-gray-600">We’ll notify you once your application has been approved or rejected.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-4 sm:px-6 flex justify-center text-center">
                    <button type="button" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-6 py-3 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:text-sm transition-colors" onclick="closeSuccessModal()">
                        View Application Status
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Removes query param for clean reload
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url);
        }
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const resubmitForm = document.getElementById('resubmitForm');
        if (resubmitForm) {
            resubmitForm.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                const btnText = document.getElementById('btnText');
                
                // Disable button and show loading text
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                submitBtn.classList.remove('hover:-translate-y-0.5');
                btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';
            });
        }
    </script>
</body>
</html>
