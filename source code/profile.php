<?php
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';
require_once 'includes/currency-helper.php';

requireLogin();

// Get current user info
$user_id = getUserId();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$stats_stmt = $pdo->prepare("SELECT 
    COUNT(*) as total_expenses,
    SUM(amount) as total_spent,
    MIN(expense_date) as first_expense,
    MAX(expense_date) as last_expense
    FROM expenses WHERE user_id = ?");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
$update_error = '';
$update_success = '';

// Check for success message from session (if redirected)
if (isset($_SESSION['profile_update_success'])) {
    $update_success = $_SESSION['profile_update_success'];
    unset($_SESSION['profile_update_success']); // Clear after displaying
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    
    // Validation flags
    $is_valid = true;
    $changes_detected = false;
    
    // Get current user data
    $current_user_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $current_user_stmt->execute([$user_id]);
    $current_user = $current_user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check 1: Are there any changes?
    if ($full_name === $current_user['full_name'] && $email === $current_user['email']) {
        $update_error = 'You haven\'t made any changes to your profile.';
        $is_valid = false;
    } else {
        $changes_detected = true;
        
        // Check 2: Full name validation
        if (empty($full_name)) {
            $update_error = 'Full name is required';
            $is_valid = false;
        } elseif (strlen($full_name) < 2) {
            $update_error = 'Full name must be at least 2 characters';
            $is_valid = false;
        }
        
        // Check 3: Email validation
        if (empty($email)) {
            $update_error = 'Email address is required';
            $is_valid = false;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $update_error = 'Please enter a valid email address';
            $is_valid = false;
        }
    }
    
    // If changes detected and validation passed
    if ($changes_detected && $is_valid) {
        // Check if email already exists for another user
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$email, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $update_error = 'This email is already registered to another account. Please use a different email.';
        } else {
            // Update the profile
            $update_stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
            if ($update_stmt->execute([$full_name, $email, $user_id])) {
                // Store success message in session
                $_SESSION['profile_update_success'] = 'Profile updated successfully!';
                
                // Update session variables
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                // Redirect to same page (Post-Redirect-Get pattern)
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit(); // IMPORTANT: Stop execution after redirect
                
            } else {
                $update_error = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Handle password change
$password_error = '';
$password_success = '';

// Check for password success message from session
if (isset($_SESSION['password_change_success'])) {
    $password_success = $_SESSION['password_change_success'];
    unset($_SESSION['password_change_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $password_error = 'Current password is incorrect';
    } elseif (strlen($new_password) < 6) {
        $password_error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $password_error = 'New passwords do not match';
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($password_stmt->execute([$hashed_password, $user_id])) {
            // Store success message in session
            $_SESSION['password_change_success'] = 'Password changed successfully!';
            
            // Redirect to same page
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $password_error = 'Failed to change password';
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    // In a real app, you would mark as deleted instead of actually deleting
    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($delete_stmt->execute([$user_id])) {
        session_destroy();
        setcookie('expense_user', '', time() - 3600, "/");
        header('Location: index.php');
        exit();
    }
}

// Format date
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('d M Y', strtotime($date));
}

?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
        .card-hover:hover { transform: translateY(-2px); }
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); }
        .slide-up { animation: slideUp 0.3s ease-out; }

        
        
        /* Message animations */
        .message-slide-down {
            animation: slideDown 0.3s ease-out;
        }
        .message-slide-up {
            animation: slideUp 0.3s ease-out forwards;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-20px); opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-50 h-full">
    <div class="mobile-container min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-gray-800 to-gray-900 text-white p-6 rounded-b-3xl shadow-lg">
            <div class="flex items-center justify-between mb-6">
                <a href="dashboard.php" class="flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <div class="text-right">
                    <div class="text-xs opacity-80">Member Since</div>
                    <div class="text-sm"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
            
            <div class="text-center">
                <div class="w-24 h-24 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-4xl font-bold mx-auto mb-4 shadow-xl">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h1>
                <p class="text-gray-300 mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="mt-3 inline-block bg-gray-700/50 px-3 py-1 rounded-full text-sm">
                    <i class="fas fa-user-check mr-1"></i> Verified User
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow p-6 pb-24">
            <!-- Messages Container -->
            <div id="messagesContainer"></div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl p-4 shadow-md card-hover transition-all duration-200">
                    <div class="flex items-center mb-2">
                        <div class="bg-blue-100 text-blue-600 p-2 rounded-lg mr-3">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800"><?php echo $stats['total_expenses'] ?? 0; ?></div>
                            <div class="text-xs text-gray-600">Expenses & Income</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-md card-hover transition-all duration-200">
                    <div class="flex items-center mb-2">
                        <div class="bg-green-100 text-green-600 p-2 rounded-lg mr-3">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-800">
                        <?php 
                        $amount_myr = $stats['total_spent'] ?? 0;
                        $config = getCurrencyConfig();
                        $converted_amount = $amount_myr * $config['rate'];
                        
                        // Display in selected currency with abbreviation for large numbers
                        if ($converted_amount >= 1000000) {
                            echo $config['code'] . ' ' . number_format($converted_amount / 1000000, 1) . 'M';
                        } elseif ($converted_amount >= 1000) {
                            echo $config['code'] . ' ' . number_format($converted_amount / 1000, 1) . 'K';
                        } else {
                            echo $config['code'] . ' ' . number_format($converted_amount, 2);
                        }
                        ?>
                        </div>
                            <div class="text-xs text-gray-600">Cash Flow</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Information -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 slide-up">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-user-circle text-blue-500 mr-3"></i> Profile Information
                </h2>
                
                <form id="profileForm" method="POST" action="" class="space-y-4">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="username">
                            <i class="fas fa-user text-gray-500 mr-2"></i> Username
                        </label>
                        <input type="text" 
                               id="username"
                               value="<?php echo htmlspecialchars($user['username']); ?>"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg"
                               disabled>
                        <p class="text-xs text-gray-500 mt-1">Username cannot be changed</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="full_name">
                            <i class="fas fa-id-card text-blue-500 mr-2"></i> Full Name *
                        </label>
                        <input type="text" 
                               id="full_name"
                               name="full_name"
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your full name"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="email">
                            <i class="fas fa-envelope text-blue-500 mr-2"></i> Email Address *
                        </label>
                        <input type="email" 
                               id="email"
                               name="email"
                               value="<?php echo htmlspecialchars($user['email']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your email"
                               required>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">
                            <i class="fas fa-calendar-alt text-blue-500 mr-2"></i> Member Since
                        </label>
                        <input type="text" 
                               value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg"
                               disabled>
                    </div>
                    
                    <button type="submit" id="updateProfileBtn"
                            class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 rounded-lg font-semibold text-lg shadow-md hover:shadow-lg transition duration-300">
                        <i class="fas fa-save mr-2"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 slide-up" style="animation-delay: 0.1s">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-lock text-green-500 mr-3"></i> Change Password
                </h2>
                
                <form id="passwordForm" method="POST" action="" class="space-y-4">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="current_password">
                            <i class="fas fa-key text-gray-500 mr-2"></i> Current Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="current_password"
                                   name="current_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Enter current password"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="new_password">
                            <i class="fas fa-key text-green-500 mr-2"></i> New Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="new_password"
                                   name="new_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Enter new password"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Must be at least 6 characters</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="confirm_password">
                            <i class="fas fa-key text-green-500 mr-2"></i> Confirm New Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Confirm new password"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" id="changePasswordBtn"
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-3 rounded-lg font-semibold text-lg shadow-md hover:shadow-lg transition duration-300">
                        <i class="fas fa-lock mr-2"></i> Change Password
                    </button>
                </form>
            </div>

            <!-- Account Settings -->
            <div class="bg-white rounded-2xl shadow-lg p-6 slide-up" style="animation-delay: 0.2s">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-cog text-gray-600 mr-3"></i> Account Settings
                </h2>
                
                <div class="space-y-3">
                    <!-- Logout -->
                    <a href="logout.php"
                       class="flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition duration-200">
                        <div class="flex items-center">
                            <div class="bg-blue-100 text-blue-600 p-2 rounded-lg mr-3">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-medium text-blue-800">Logout</div>
                                <div class="text-sm text-blue-600">Sign out of your account</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-blue-400"></i>
                    </a>
                    
                    <!-- Delete Account (Danger Zone) -->
                    <button type="button" 
                            onclick="showDeleteModal()"
                            class="w-full flex items-center justify-between p-4 bg-red-50 hover:bg-red-100 rounded-lg transition duration-200 mt-6">
                        <div class="flex items-center">
                            <div class="bg-red-100 text-red-600 p-2 rounded-lg mr-3">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-medium text-red-800">Delete Account</div>
                                <div class="text-sm text-red-600">Permanently delete your account</div>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-red-400"></i>
                    </button>
                </div>
            </div>
        </main>

        <!-- Include Footer -->
        <?php include 'includes/footer.php'; ?>
    </div>

    <!-- Delete Account Modal -->
    <div id="deleteModal" class="fixed inset-0 modal-overlay hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 max-w-sm w-full slide-up">
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">Delete Account?</h3>
                <p class="text-gray-600 mb-4">This action cannot be undone. All your data will be permanently deleted.</p>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="delete_account" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2 font-medium" for="confirm_delete">
                        Type "DELETE" to confirm
                    </label>
                    <input type="text" 
                           id="confirm_delete"
                           name="confirm_delete"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                           placeholder="Type DELETE"
                           required>
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" 
                            onclick="hideDeleteModal()"
                            class="flex-1 bg-gray-100 text-gray-800 py-3 rounded-lg font-medium">
                        Cancel
                    </button>
                    <button type="submit"
                            id="deleteButton"
                            class="flex-1 bg-gradient-to-r from-red-500 to-red-600 text-white py-3 rounded-lg font-medium opacity-50"
                            disabled>
                        Delete Account
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show messages from PHP
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messagesContainer');
            
            // Function to create and show message
            function showMessage(type, message) {
                const messageId = 'message-' + Date.now();
                const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
                const bgColor = type === 'success' ? 'green' : 'red';
                const textColor = type === 'success' ? 'green' : 'red';
                
                const messageDiv = document.createElement('div');
                messageDiv.id = messageId;
                messageDiv.className = `message-slide-down bg-${bgColor}-50 border-l-4 border-${bgColor}-400 p-4 mb-3`;
                messageDiv.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas ${icon} text-${textColor}-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-${textColor}-700">${message}</p>
                            </div>
                        </div>
                        <button type="button" onclick="dismissMessage('${messageId}')" 
                                class="text-${textColor}-400 hover:text-${textColor}-500 ml-4">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                messagesContainer.prepend(messageDiv);
                
                // Auto-dismiss after 3 seconds
                setTimeout(() => {
                    dismissMessage(messageId);
                }, 3000);
            }
            
            // Show PHP messages if they exist
            <?php if ($update_success): ?>
                showMessage('success', '<?php echo addslashes($update_success); ?>');
            <?php endif; ?>
            
            <?php if ($update_error): ?>
                showMessage('error', '<?php echo addslashes($update_error); ?>');
            <?php endif; ?>
            
            <?php if ($password_success): ?>
                showMessage('success', '<?php echo addslashes($password_success); ?>');
            <?php endif; ?>
            
            <?php if ($password_error): ?>
                showMessage('error', '<?php echo addslashes($password_error); ?>');
            <?php endif; ?>
            
            // Dismiss message function
            window.dismissMessage = function(messageId) {
                const messageElement = document.getElementById(messageId);
                if (messageElement) {
                    messageElement.classList.remove('message-slide-down');
                    messageElement.classList.add('message-slide-up');
                    setTimeout(() => {
                        if (messageElement.parentNode) {
                            messageElement.parentNode.removeChild(messageElement);
                        }
                    }, 300);
                }
            };
            
            // Form submission handlers
            const profileForm = document.getElementById('profileForm');
            const passwordForm = document.getElementById('passwordForm');
            
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const submitBtn = document.getElementById('updateProfileBtn');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
                });
            }
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const submitBtn = document.getElementById('changePasswordBtn');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Changing...';
                });
            }
        });

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Delete account modal
        function showDeleteModal() {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Confirm delete typing
        const confirmDeleteInput = document.getElementById('confirm_delete');
        const deleteButton = document.getElementById('deleteButton');

        if (confirmDeleteInput && deleteButton) {
            confirmDeleteInput.addEventListener('input', function() {
                if (this.value.toUpperCase() === 'DELETE') {
                    deleteButton.disabled = false;
                    deleteButton.classList.remove('opacity-50');
                } else {
                    deleteButton.disabled = true;
                    deleteButton.classList.add('opacity-50');
                }
            });
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });


        // Mobile optimizations
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, button, a').forEach(el => {
                el.style.minHeight = '44px';
            });
        }

        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                
                if (password.length > 0 && password.length < 6) {
                    this.classList.add('border-yellow-500');
                    this.classList.remove('border-green-500', 'border-red-500');
                } else if (strength >= 3) {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-yellow-500', 'border-red-500');
                }
            });
        }

        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }
    </script>
</body>
</html>