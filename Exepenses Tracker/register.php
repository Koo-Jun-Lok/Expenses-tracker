<?php
require_once 'includes/config.php';
require_once 'includes/session-handler.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        if ($expenseSessionHandler->register($username, $email, $password, $full_name)) {
            $success = 'Registration successful! Redirecting...';
            echo '<meta http-equiv="refresh" content="2;url=dashboard.php">';
        } else {
            $error = 'Username or email already exists';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 h-full">
    <div class="mobile-container min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6 rounded-b-3xl shadow-lg">
            <a href="index.php" class="inline-block mb-4">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div class="text-center">
                <h1 class="text-3xl font-bold">Create Account</h1>
                <p class="text-green-100 mt-1">Start your financial journey</p>
            </div>
        </header>

        <!-- Registration Form -->
        <main class="flex-grow p-6">
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
                <form id="registerForm" method="POST" action="">
                    <!-- Full Name -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="full_name">
                            <i class="fas fa-id-card text-green-500 mr-2"></i>Full Name (Optional)
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Enter your full name">
                    </div>

                    <!-- Username -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="username">
                            <i class="fas fa-user text-green-500 mr-2"></i>Username *
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Choose a username"
                               required>
                        <p class="text-xs text-gray-500 mt-1">At least 3 characters</p>
                    </div>

                    <!-- Email -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="email">
                            <i class="fas fa-envelope text-green-500 mr-2"></i>Email Address *
                        </label>
                        <input type="email" 
                               id="email"
                               name="email"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Enter your email"
                               required>
                    </div>

                    <!-- Password -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="password">
                            <i class="fas fa-lock text-green-500 mr-2"></i>Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password"
                                   name="password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Create a password"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2 space-y-1">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-xs mr-2 text-gray-400" id="length-check"></i>
                                <span class="text-xs text-gray-600">At least 6 characters</span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2 font-medium" for="confirm_password">
                            <i class="fas fa-lock text-green-500 mr-2"></i>Confirm Password *
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                   placeholder="Confirm your password"
                                   required>
                            <button type="button" 
                                    class="toggle-password absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-xs mr-2 text-gray-400" id="match-check"></i>
                                <span class="text-xs text-gray-600">Passwords must match</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-green-600 to-blue-600 text-white py-3 rounded-lg font-semibold text-lg shadow-lg hover:shadow-xl transition duration-300">
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </form>
            </div>

            <!-- Login Link -->
            <div class="text-center">
                <p class="text-gray-600">Already have an account?</p>
                <a href="login.php" 
                   class="inline-block mt-2 text-green-600 font-semibold hover:text-green-800">
                    <i class="fas fa-sign-in-alt mr-1"></i>Login Here
                </a>
            </div>
        </main>

        <!-- Footer -->
        <footer class="p-4 text-center text-gray-500 text-sm">
            <p>Your financial data is encrypted and secure</p>
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

        // Password validation
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const lengthCheck = document.getElementById('length-check');
        const matchCheck = document.getElementById('match-check');

        function validatePassword() {
            // Length check
            if (passwordInput.value.length >= 6) {
                lengthCheck.classList.remove('text-gray-400');
                lengthCheck.classList.add('text-green-500');
            } else {
                lengthCheck.classList.remove('text-green-500');
                lengthCheck.classList.add('text-gray-400');
            }

            // Match check
            if (passwordInput.value === confirmInput.value && passwordInput.value.length > 0) {
                matchCheck.classList.remove('text-gray-400');
                matchCheck.classList.add('text-green-500');
            } else {
                matchCheck.classList.remove('text-green-500');
                matchCheck.classList.add('text-gray-400');
            }
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmInput.addEventListener('input', validatePassword);

        // Mobile optimizations
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, button, a').forEach(el => {
                el.style.minHeight = '44px';
            });
        }

        // Auto-focus on first field
        document.getElementById('full_name').focus();
    </script>
</body>
</html>