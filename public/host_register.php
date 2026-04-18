<?php
include_once "../includes/auth.php";
include_once "../config/constants.php";
include_once "../includes/functions.php";

if (isset($_POST['register'])) {
    // Basic User Information
    $host_name = mysqli_real_escape_string($conn, $_POST['host_name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $password = mysqli_real_escape_string($conn, $_POST['password'] ?? '');
    $confirm_password = mysqli_real_escape_string($conn, $_POST['confirm_password'] ?? '');
    
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

    // Check Agreements
    if (empty($_POST['confirm_true']) || empty($_POST['agree_terms']) || empty($_POST['ack_verify'])) {
        $error = "You must agree to all terms and policies to proceed.";
    }
    // Check if email exists
    else if (mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE email='$email'")) > 0) {
        $error = "Email already registered!";
    } 
    elseif ($password != $confirm_password) {
        $error = "Passwords do not match!";
    } 
    elseif (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters long and include an uppercase letter, lowercase letter, number, and special character.";
    }
    else {
        // File Uploads processing
        $upload_dir = "../uploads/host_applications/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        function uploadFile($fileInputName, $upload_dir) {
            if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                    $new_name = time() . '_' . rand(1000, 9999) . '_' . $fileInputName . '.' . $ext;
                    $dest = $upload_dir . $new_name;
                    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $dest)) {
                        return $dest;
                    }
                }
            }
            return false;
        }

        $valid_id1_path = uploadFile('valid_id1', $upload_dir);
        $valid_id2_path = uploadFile('valid_id2', $upload_dir);
        $selfie_path = uploadFile('selfie_with_id', $upload_dir);
        $ownership_path = uploadFile('proof_ownership', $upload_dir);
        $utility_path = uploadFile('utility_bill', $upload_dir);

        if (!$valid_id1_path || !$valid_id2_path || !$ownership_path || !$utility_path) {
            $error = "Please upload all required valid documents in accepted formats (JPG, PNG, PDF).";
        }
        else {
            // Process DB insertion
            $conn->begin_transaction();
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 1. Insert user as PENDING_HOST first
                $stmt_user = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role, is_active) VALUES (?, ?, ?, ?, 'pending_host', 1)");
                $stmt_user->bind_param("ssss", $host_name, $email, $hashed_password, $phone);
                $stmt_user->execute();
                $new_user_id = $conn->insert_id;

                // 2. Insert host application
                $stmt_app = $conn->prepare("INSERT INTO host_applications (
                    user_id, condo_name, branch_name, complete_address, social_media_link,
                    primary_id_type, primary_id_number, primary_id_path, secondary_id_path, selfie_with_id_path,
                    proof_of_ownership_path, utility_bill_path, payout_method, account_name, account_number, bank_name, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                
                $stmt_app->bind_param("isssssssssssssss", 
                    $new_user_id, $condo_name, $branch_name, $condo_address, $social_media,
                    $primary_id_type, $primary_id_number, $valid_id1_path, $valid_id2_path, $selfie_path,
                    $ownership_path, $utility_path, $payout_method, $account_name, $account_number, $bank_name
                );
                $stmt_app->execute();
                $app_id = $conn->insert_id;

                // Send Notification
                sendNotification($new_user_id, 'Application Submitted', 'Thank you for applying! Your application is under review.', 'system', 'system');

                // Send Email Notification
                @include_once '../includes/email_integration.php';
                if (function_exists('sendHostApplicationReceivedEmail')) {
                    @sendHostApplicationReceivedEmail($email, $host_name);
                }

                $conn->commit();
                
                // Auto Login
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['fullname'] = $host_name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'pending_host'; // Set to pending_host role while under review
                
                header("Location: ../renter/my_application.php?success=1");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Something went wrong saving your application. " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Host Registration - BookIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui']
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-50 via-red-50 to-orange-50 font-sans">
    <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Header with brand gradient -->
            <div class="bg-gradient-to-r from-orange-500 via-red-500 to-orange-600 px-8 pt-10 pb-8 text-center">
                <div class="flex items-center justify-center w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl mb-4 mx-auto">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h1 class="text-4xl font-bold text-white">Become a Host</h1>
                <p class="text-orange-100 text-base mt-2">Register your property and start earning with BookIT</p>
            </div>

            <!-- Form content -->
            <div class="p-8 sm:p-10">

                <!-- Alerts Container -->
            <div id="alertsContainer" class="mb-6 min-h-0">
                <?php if(isset($error)): ?>
                    <div class="rounded-xl bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                        <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if(isset($success)): ?>
                    <div class="rounded-xl bg-green-50 border-l-4 border-green-500 p-4 text-sm text-green-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm">
                        <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Registration Form -->
            <form action="" method="POST" enctype="multipart/form-data" id="registerForm" class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                <input type="hidden" name="register" value="1">
                
                <!-- Column 1: Personal Information & Password -->
                <div class="space-y-6">
                    <div class="pb-3 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <i class="fas fa-user text-orange-500 mr-2"></i> Personal Information
                        </h3>
                    </div>

                    <div>
                        <label for="host_name" class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" id="host_name" name="host_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="Your Full Name" value="<?php echo htmlspecialchars($_POST['host_name'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="email" name="email" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                        <input type="tel" id="phone" name="phone" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="+63 9xxxxxxxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="relative">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" name="password" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 pt-7 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none" onclick="togglePasswordVisibility('password', 'eye-icon-pass')">
                            <svg id="eye-icon-pass" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="relative">
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500 pr-10">
                        <button type="button" class="absolute inset-y-0 right-0 pt-7 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none" onclick="togglePasswordVisibility('confirm_password', 'eye-icon-conf')">
                            <svg id="eye-icon-conf" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>

                    <div id="passwordRequirements" class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                        <p class="text-xs font-semibold text-orange-900 mb-2">Password Requirements:</p>
                        <ul class="text-xs space-y-1 mt-2">
                            <li id="req-length" class="flex items-center text-gray-600 transition-colors duration-200">
                                <svg id="icon-length" class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                At least 8 characters
                            </li>
                            <li id="req-uppercase" class="flex items-center text-gray-600 transition-colors duration-200">
                                <svg id="icon-uppercase" class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One uppercase letter
                            </li>
                            <li id="req-lowercase" class="flex items-center text-gray-600 transition-colors duration-200">
                                <svg id="icon-lowercase" class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One lowercase letter
                            </li>
                            <li id="req-number" class="flex items-center text-gray-600 transition-colors duration-200">
                                <svg id="icon-number" class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One number
                            </li>
                            <li id="req-special" class="flex items-center text-gray-600 transition-colors duration-200">
                                <svg id="icon-special" class="w-3 h-3 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                One special character
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Column 2: Property Information -->
                <div class="space-y-6">
                    <div class="pb-3 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <i class="fas fa-building text-orange-500 mr-2"></i> Property Information
                        </h3>
                    </div>

                    <div>
                        <label for="condo_name" class="block text-sm font-semibold text-gray-700 mb-2">Condo Name</label>
                        <input type="text" id="condo_name" name="condo_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="e.g., Sunset Heights Condo" value="<?php echo htmlspecialchars($_POST['condo_name'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="branch_name" class="block text-sm font-semibold text-gray-700 mb-2">Branch Name</label>
                        <input type="text" id="branch_name" name="branch_name" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="e.g., Makati Branch" value="<?php echo htmlspecialchars($_POST['branch_name'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="condo_address" class="block text-sm font-semibold text-gray-700 mb-2">Complete Address</label>
                        <textarea id="condo_address" name="condo_address" required rows="2"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="Street address, city, province"><?php echo htmlspecialchars($_POST['condo_address'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label for="social_media" class="block text-sm font-semibold text-gray-700 mb-2">Social Media Link (Optional)</label>
                        <input type="url" id="social_media" name="social_media"
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="https://facebook.com/..." value="<?php echo htmlspecialchars($_POST['social_media'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Column 3: Identity Verification -->
                <div class="space-y-6">
                    <div class="pb-3 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <i class="fas fa-id-card text-orange-500 mr-2"></i> Identity Verification
                        </h3>
                    </div>

                    <div>
                        <label for="primary_id_type" class="block text-sm font-semibold text-gray-700 mb-2">Primary ID Type</label>
                        <select name="primary_id_type" id="primary_id_type" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500">
                            <option value="">Select ID Type</option>
                            <option value="Passport" <?php echo (($_POST['primary_id_type'] ?? '') == 'Passport') ? 'selected' : ''; ?>>Passport</option>
                            <option value="Driver License" <?php echo (($_POST['primary_id_type'] ?? '') == 'Driver License') ? 'selected' : ''; ?>>Driver's License</option>
                            <option value="UMID" <?php echo (($_POST['primary_id_type'] ?? '') == 'UMID') ? 'selected' : ''; ?>>UMID</option>
                            <option value="National ID" <?php echo (($_POST['primary_id_type'] ?? '') == 'National ID') ? 'selected' : ''; ?>>National ID</option>
                        </select>
                    </div>

                    <div>
                        <label for="primary_id_number" class="block text-sm font-semibold text-gray-700 mb-2">ID Number</label>
                        <input type="text" id="primary_id_number" name="primary_id_number" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500"
                            placeholder="e.g. XXXX-XXXX-XXXX" value="<?php echo htmlspecialchars($_POST['primary_id_number'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="valid_id1" class="block text-sm font-semibold text-gray-700 mb-2">Upload Primary ID</label>
                        <input type="file" id="valid_id1" name="valid_id1" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>

                    <div>
                        <label for="valid_id2" class="block text-sm font-semibold text-gray-700 mb-2">Upload Secondary ID</label>
                        <input type="file" id="valid_id2" name="valid_id2" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>

                    <div>
                        <label for="selfie_with_id" class="block text-sm font-semibold text-gray-700 mb-2">Selfie with Primary ID (Optional)</label>
                        <input type="file" id="selfie_with_id" name="selfie_with_id" accept=".jpg,.jpeg,.png"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                    </div>
                </div>

                <!-- Column 4: Ownership & Bank Details -->
                <div class="space-y-6">
                    <div class="pb-3 border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <i class="fas fa-file-contract text-orange-500 mr-2"></i> Documents & Payout
                        </h3>
                    </div>

                    <div>
                        <label for="proof_ownership" class="block text-sm font-semibold text-gray-700 mb-2">Proof of Ownership / Lease Contract</label>
                        <input type="file" id="proof_ownership" name="proof_ownership" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>

                    <div>
                        <label for="utility_bill" class="block text-sm font-semibold text-gray-700 mb-2">Recent Utility Bill (Proof of Address)</label>
                        <input type="file" id="utility_bill" name="utility_bill" required accept=".jpg,.jpeg,.png,.pdf"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                    </div>

                    <div class="pt-4 pb-2">
                        <h4 class="text-md font-bold text-gray-600">Payout Details</h4>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label for="payout_method" class="block text-sm font-semibold text-gray-700 mb-2">Method</label>
                            <select name="payout_method" id="payout_method" required class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label for="bank_name" class="block text-sm font-semibold text-gray-700 mb-2">Bank Name</label>
                            <input type="text" id="bank_name" name="bank_name" placeholder="e.g. BDO (If Bank)"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500" value="<?php echo htmlspecialchars($_POST['bank_name'] ?? ''); ?>">
                        </div>
                        <div class="col-span-2">
                            <label for="account_name" class="block text-sm font-semibold text-gray-700 mb-2">Account Name</label>
                            <input type="text" id="account_name" name="account_name" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500" value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>">
                        </div>
                        <div class="col-span-2">
                            <label for="account_number" class="block text-sm font-semibold text-gray-700 mb-2">Account Number</label>
                            <input type="text" id="account_number" name="account_number" required
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 focus:ring-2 focus:ring-orange-500" value="<?php echo htmlspecialchars($_POST['account_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Terms and Agreement Checkboxes (Spans both columns) -->
                <div class="col-span-1 md:col-span-2 bg-gray-50 p-6 rounded-xl border border-gray-200 mt-4 space-y-4">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" name="confirm_true" value="1" required class="mt-1 mr-3 w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                        <span class="text-sm text-gray-700">Confirm all information is true and accurate</span>
                    </label>
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" id="terms" name="agree_terms" value="1" required class="mt-1 mr-3 w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                        <span class="text-sm text-gray-700">Agree to <a href="#" onclick="openModal('termsModal'); return false;" class="text-orange-600 hover:underline">Host Policies and Terms of Service</a></span>
                    </label>
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" name="ack_verify" value="1" required class="mt-1 mr-3 w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                        <span class="text-sm text-gray-700">Acknowledge that application is subject to verification and approval</span>
                    </label>
                </div>

            </form>

            <!-- Submit Button (Full Width) -->
            <div class="mt-8 pt-6 border-t border-gray-300">
                <button type="submit" form="registerForm" id="submitBtn"
                    class="w-full inline-flex items-center justify-center rounded-lg px-6 py-3 text-white font-semibold shadow-lg hover:shadow-xl transition bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600">
                    <span id="btnText" class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Submit Application
                    </span>
                </button>
            </div>

            <!-- Links -->
            <div class="mt-6 pt-6 border-t border-gray-300 text-center space-y-3">
                <p class="text-sm text-gray-600">Already have an account? 
                    <a href="login.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Sign In</a>
                </p>
                <p class="text-sm text-gray-600">Looking to book a unit? 
                    <a href="register.php" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline transition">Register as Renter</a>
                </p>
            </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Host Policies and Terms of Service</h3>
                <button onclick="closeModal('termsModal')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-4 text-sm text-gray-600">
                <h4 class="font-bold text-gray-900">1. Verification and Acceptance</h4>
                <p>All host applications are subject to mandatory verification by administrators. Submission of this form does not guarantee acceptance.</p>
                
                <h4 class="font-bold text-gray-900">2. Revenue Sharing</h4>
                <p>By listing properties on BookIT, you agree to our platform revenue sharing structure (10% Admin Booking Fee, 90% Host Return).</p>
                
                <h4 class="font-bold text-gray-900">3. Accurate Representation</h4>
                <p>Hosts must provide accurate unit descriptions, locations, and pricing. Fraudulent listings will result in irreversible bans and potential legal pursuit.</p>

                <h4 class="font-bold text-gray-900">4. Misconduct</h4>
                <p>Any circumvention of the system, offensive dialogue within reviews/messages, or falsifying uploaded certificates will immediately suspend your payout release pipeline.</p>
            </div>
            <div class="p-6 border-t border-gray-200 bg-gray-50 rounded-b-xl flex justify-end">
                <button onclick="document.getElementById('terms').checked = true; closeModal('termsModal');" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded-lg font-semibold transition">I Accept</button>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('termsModal');
            if (event.target === modal) {
                closeModal('termsModal');
            }
        }
        // Password visibility toggle
        function togglePasswordVisibility(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-4.803m5.596-3.856a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
            } else {
                field.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
            }
        }

        // Password strength validator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function validatePassword(password) {
            const checks = {
                'req-length': password.length >= 8,
                'req-uppercase': /[A-Z]/.test(password),
                'req-lowercase': /[a-z]/.test(password),
                'req-number': /\d/.test(password),
                'req-special': /[\W_]/.test(password)
            };

            for (const [id, valid] of Object.entries(checks)) {
                const element = document.getElementById(id);
                const icon = document.getElementById('icon-' + id.split('-')[1]);
                if (valid) {
                    element.classList.remove('text-gray-600');
                    element.classList.add('text-green-600');
                    icon.classList.remove('text-gray-400');
                    icon.classList.add('text-green-500');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>';
                } else {
                    element.classList.add('text-gray-600');
                    element.classList.remove('text-green-600');
                    icon.classList.add('text-gray-400');
                    icon.classList.remove('text-green-500');
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>';
                }
            }
        }

        passwordInput.addEventListener('focus', function() {
            // Show requirements on focus for better UX
        });

        passwordInput.addEventListener('input', function() {
            validatePassword(this.value);
        });

        function showErrorIndicator(message) {
            const alertsContainer = document.getElementById('alertsContainer');
            alertsContainer.innerHTML = `
                <div class="rounded-xl bg-red-50 border-l-4 border-red-500 p-4 text-sm text-red-700 flex items-start animate-in fade-in slide-in-from-top shadow-sm mb-4">
                    <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span>${message}</span>
                </div>
            `;
            // Scroll to the top so user sees the warning
            alertsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const email = document.getElementById('email').value;

            // Clear previous alerts
            document.getElementById('alertsContainer').innerHTML = '';

            if (!/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(password)) {
                validatePassword(password);
                showErrorIndicator('Password does not meet all requirements.');
                passwordInput.focus();
                return;
            }

            if (password !== confirmPassword) {
                showErrorIndicator('Passwords do not match.');
                confirmPasswordInput.focus();
                return;
            }

            // Disable button
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';

            // Check email uniqueness via AJAX to prevent losing file uploads on reload
            try {
                const response = await fetch('check_email.php?email=' + encodeURIComponent(email));
                const data = await response.json();
                if (data.exists) {
                    showErrorIndicator('Email already registered! Please use a different email.');
                    document.getElementById('email').focus();
                    
                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btnText.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg> Submit Application';
                    return;
                }
            } catch (err) {
                console.error('Email validation error:', err);
                showErrorIndicator('An error occurred checking validation. Please try again.');
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                btnText.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg> Submit Application';
                return;
            }
            
            this.submit();
        });
    </script>

</body>
</html>