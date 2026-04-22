<?php
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$show_form = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($full_name) || empty($username) || empty($email) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Verify user identity by checking all three fields
        $stmt = $pdo->prepare("SELECT id FROM users WHERE full_name = ? AND username = ? AND email = ?");
        $stmt->execute([$full_name, $username, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($update_stmt->execute([$hashed_password, $user['id']])) {
                $success = 'Password reset successful! You can now login with your new password.';
                $show_form = false;
                
                // Optional: Send email notification
                // sendPasswordResetEmail($email, $full_name);
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'User information does not match our records. Please verify your details.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
        .card-hover { transition: transform 0.2s, box-shadow 0.2s; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 h-full">
    <div class="mobile-container min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-b-3xl shadow-lg">
            <a href="login.php" class="inline-block mb-4">
                <i class="fas fa-arrow-left mr-2"></i> Back to Login
            </a>
            <div class="text-center">
                <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-shield text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold">Reset Password</h1>
                <p class="text-blue-100 mt-1">Verify your identity to reset password</p>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow p-6">
            <?php if ($error): ?>
            <div id="errorMessage" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center message-slide-down">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <!-- Success Message -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 text-center card-hover">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-3xl text-green-500"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-3">Success!</h2>
                <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($success); ?></p>
                <p class="text-gray-500 text-sm mb-6">You can now login with your new password.</p>
                
                <div class="space-y-3">
                    <a href="login.php" 
                       class="block bg-gradient-to-r from-green-500 to-green-600 text-white py-3 rounded-lg font-semibold text-lg shadow-md hover:shadow-lg transition duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                    </a>
                    <a href="index.php" 
                       class="block border-2 border-blue-600 text-blue-600 py-3 rounded-lg font-semibold text-lg hover:bg-blue-50 transition duration-300">
                        <i class="fas fa-home mr-2"></i> Back to Home
                    </a>
                </div>
                
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <h3 class="font-semibold text-gray-700 mb-2">Security Tips:</h3>
                    <ul class="text-sm text-gray-600 text-left space-y-1">
                        <li><i class="fas fa-shield-alt text-green-500 mr-2"></i> Don't share your password with anyone</li>
                        <li><i class="fas fa-sync-alt text-blue-500 mr-2"></i> Change your password regularly</li>
                        <li><i class="fas fa-lock text-purple-500 mr-2"></i> Use a unique password for each account</li>
                    </ul>
                </div>
            </div>
            
            <?php elseif ($show_form): ?>
            <!-- Reset Password Form -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 card-hover">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Verify Your Identity</h2>
                    <p class="text-gray-600">Please provide your account details for verification</p>
                </div>
                
                <form id="resetForm" method="POST" action="" class="space-y-5">
                    <!-- Full Name -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="full_name">
                            <i class="fas fa-user text-blue-500 mr-2"></i> Full Name *
                        </label>
                        <input type="text" 
                               id="full_name"
                               name="full_name"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your full name as registered"
                               required
                               autofocus>
                    </div>
                    
                    <!-- Username -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="username">
                            <i class="fas fa-at text-blue-500 mr-2"></i> Username *
                        </label>
                        <input type="text" 
                               id="username"
                               name="username"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your username"
                               required>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="email">
                            <i class="fas fa-envelope text-blue-500 mr-2"></i> Email Address *
                        </label>
                        <input type="email" 
                               id="email"
                               name="email"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your registered email"
                               required>
                    </div>
                    
                    <!-- New Password -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium" for="new_password">
                            <i class="fas fa-key text-green-500 mr-2"></i> New Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="new_password"
                                   name="new_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Enter new password (min 6 characters)"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div class="flex items-center">
                                <div id="lengthCheck" class="w-2 h-2 bg-gray-300 rounded-full mr-2"></div>
                                <span class="text-xs text-gray-600">6+ characters</span>
                            </div>
                            <div class="flex items-center">
                                <div id="strengthCheck" class="w-2 h-2 bg-gray-300 rounded-full mr-2"></div>
                                <span class="text-xs text-gray-600">Strong</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
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
                        <div id="matchIndicator" class="text-sm mt-1 hidden">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>
                            <span class="text-green-600">Passwords match</span>
                        </div>
                    </div>
                    
                    <!-- Security Verification -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-800 mb-2 flex items-center">
                            <i class="fas fa-shield-alt mr-2"></i> Security Verification
                        </h3>
                        <p class="text-sm text-blue-700 mb-3">
                            We need to verify your identity before resetting your password. 
                            All three identification fields must match our records.
                        </p>
                        <div class="flex items-center text-sm text-blue-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            <span>If you don't remember your details, please contact support.</span>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" id="resetBtn"
                            class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        <i class="fas fa-save mr-2"></i> Reset Password
                    </button>
                </form>
            </div>
            
            <!-- Help Section -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-3 flex items-center">
                    <i class="fas fa-question-circle text-blue-500 mr-2"></i> Need Help?
                </h3>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="bg-blue-100 text-blue-600 p-2 rounded-lg mr-3">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700">Verification Failed?</h4>
                            <p class="text-sm text-gray-600">Make sure you're entering the exact details you used during registration.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-green-100 text-green-600 p-2 rounded-lg mr-3">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700">Password Tips</h4>
                            <p class="text-sm text-gray-600">Use a mix of letters, numbers, and symbols for a stronger password.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="bg-purple-100 text-purple-600 p-2 rounded-lg mr-3">
                            <i class="fas fa-history"></i>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-700">Remember Password?</h4>
                            <p class="text-sm text-gray-600">
                                <a href="login.php" class="text-blue-600 hover:text-blue-800">Try logging in again</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="p-4 text-center text-gray-500 text-sm border-t">
            <p><i class="fas fa-user-shield text-blue-500 mr-1"></i> Secure Password Reset System</p>
            <p class="mt-1">© 2024 Expense Tracker | All rights reserved</p>
        </footer>
    </div>

    <script>
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
        
        // Form validation
        const resetForm = document.getElementById('resetForm');
        const resetBtn = document.getElementById('resetBtn');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const lengthCheck = document.getElementById('lengthCheck');
        const strengthCheck = document.getElementById('strengthCheck');
        const matchIndicator = document.getElementById('matchIndicator');
        
        // Check all form fields
        function validateForm() {
            const fullName = document.getElementById('full_name').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Check if all fields are filled
            const allFilled = fullName && username && email && newPassword && confirmPassword;
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const validEmail = emailRegex.test(email);
            
            // Check password strength
            const passwordValid = newPassword.length >= 6;
            
            // Check password match
            const passwordsMatch = newPassword === confirmPassword && newPassword.length > 0;
            
            // Update password strength indicators
            if (newPassword.length >= 6) {
                lengthCheck.classList.remove('bg-gray-300');
                lengthCheck.classList.add('bg-green-500');
            } else {
                lengthCheck.classList.remove('bg-green-500');
                lengthCheck.classList.add('bg-gray-300');
            }
            
            const strength = checkPasswordStrength(newPassword);
            if (strength >= 3) {
                strengthCheck.classList.remove('bg-gray-300');
                strengthCheck.classList.add('bg-green-500');
            } else if (strength >= 2) {
                strengthCheck.classList.remove('bg-gray-300');
                strengthCheck.classList.add('bg-yellow-500');
            } else {
                strengthCheck.classList.remove('bg-green-500', 'bg-yellow-500');
                strengthCheck.classList.add('bg-gray-300');
            }
            
            // Show/hide password match indicator
            if (confirmPassword.length > 0) {
                if (passwordsMatch) {
                    matchIndicator.classList.remove('hidden');
                } else {
                    matchIndicator.classList.add('hidden');
                }
            } else {
                matchIndicator.classList.add('hidden');
            }
            
            // Enable/disable submit button
            if (allFilled && validEmail && passwordValid && passwordsMatch) {
                resetBtn.disabled = false;
                resetBtn.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
            } else {
                resetBtn.disabled = true;
                resetBtn.classList.add('disabled:opacity-50', 'disabled:cursor-not-allowed');
            }
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            return strength;
        }
        
        // Add event listeners to all form inputs
        const formInputs = resetForm.querySelectorAll('input');
        formInputs.forEach(input => {
            input.addEventListener('input', validateForm);
            input.addEventListener('blur', validateForm);
        });
        
        // Initial validation
        validateForm();
        
        // Auto-dismiss error message after 5 seconds
        const errorMessage = document.getElementById('errorMessage');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0';
                errorMessage.style.transform = 'translateY(-10px)';
                errorMessage.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    if (errorMessage.parentNode) {
                        errorMessage.parentNode.removeChild(errorMessage);
                    }
                }, 300);
            }, 5000);
        }
        
        // Mobile optimizations
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, button, a').forEach(el => {
                el.style.minHeight = '44px';
            });
        }
        
        // Form submission loading state
        if (resetForm) {
            resetForm.addEventListener('submit', function() {
                resetBtn.disabled = true;
                resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Resetting Password...';
            });
        }
    </script>
</body>
</html>