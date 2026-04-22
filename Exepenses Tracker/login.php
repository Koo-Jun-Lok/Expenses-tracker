<?php
require_once 'includes/config.php';
require_once 'includes/session-handler.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        if ($expenseSessionHandler->login($username, $password)) {
            if ($remember) {
                setcookie('expense_user', $_SESSION['user_id'], time() + (30 * 24 * 60 * 60), "/");
            }
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 h-full">
    <div class="mobile-container min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-b-3xl shadow-lg">
            <a href="index.php" class="inline-block mb-4">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <div class="text-center">
                <h1 class="text-3xl font-bold">Welcome Back!</h1>
                <p class="text-blue-100 mt-1">Sign in to continue tracking</p>
            </div>
        </header>

        <!-- Login Form -->
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
                <form id="loginForm" method="POST" action="">
                    <!-- Username/Email -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="username">
                            <i class="fas fa-user text-blue-500 mr-2"></i>Username or Email
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter username or email"
                               required>
                    </div>

                    <!-- Password -->
                    <div class="mb-5">
                        <label class="block text-gray-700 mb-2 font-medium" for="password">
                            <i class="fas fa-lock text-blue-500 mr-2"></i>Password
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="password"
                                   name="password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter your password"
                                   required>
                            <button type="button" 
                                    id="togglePassword"
                                    class="absolute right-3 top-3 text-gray-500">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex justify-between items-center mb-6">
                        <label class="flex items-center">
                        </label>
                        <a href="reset-password.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            Forgot Password?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-semibold text-lg shadow-lg hover:shadow-xl transition duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </button>
                </form>
            </div>


            <!-- Register Link -->
            <div class="text-center">
                <p class="text-gray-600">Don't have an account?</p>
                <a href="register.php" 
                   class="inline-block mt-2 text-blue-600 font-semibold hover:text-blue-800">
                    <i class="fas fa-user-plus mr-1"></i>Create Account
                </a>
            </div>
        </main>

        <!-- Footer -->
        <footer class="p-4 text-center text-gray-500 text-sm">
            <p>Secure login with SSL encryption</p>
            <p class="mt-1"><i class="fas fa-shield-alt text-green-500"></i> Your data is protected</p>
        </footer>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Mobile optimizations
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, button, a').forEach(el => {
                el.style.minHeight = '44px';
            });
        }

        // Auto-focus on username field
        document.getElementById('username').focus();
    </script>
</body>
</html>