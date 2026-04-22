<?php
require_once 'includes/config.php';
require_once 'includes/auth-check.php';

// Redirect to dashboard if logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - Home</title>
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">💰 ExpenseTrack</h1>
                    <p class="text-blue-100 mt-1">Smart Personal Finance Manager</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-wallet text-2xl"></i>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="flex-grow p-6">
            <div class="text-center mb-10">
                <div class="bg-white p-4 rounded-2xl shadow-md inline-block mb-6">
                    <i class="fas fa-chart-line text-5xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-3">Take Control of Your Finances</h2>
                <p class="text-gray-600 mb-8">Track expenses, visualize spending, and save money with our intuitive mobile app.</p>
            </div>

            <!-- Feature Cards -->
            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="bg-white p-4 rounded-xl shadow-md">
                    <div class="text-blue-600 mb-2">
                        <i class="fas fa-receipt text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Track Expenses</h3>
                    <p class="text-sm text-gray-600">Log daily spending easily</p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md">
                    <div class="text-green-600 mb-2">
                        <i class="fas fa-chart-pie text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Visual Reports</h3>
                    <p class="text-sm text-gray-600">See where your money goes</p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md">
                    <div class="text-purple-600 mb-2">
                        <i class="fas fa-camera text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Receipt Scan</h3>
                    <p class="text-sm text-gray-600">Capture receipts on the go</p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md">
                    <div class="text-red-600 mb-2">
                        <i class="fas fa-globe text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Currency Convert</h3>
                    <p class="text-sm text-gray-600">Multi-currency support</p>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="space-y-4">
                <a href="login.php" 
                   class="block bg-gradient-to-r from-blue-600 to-purple-600 text-white text-center py-4 rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition duration-300">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to Your Account
                </a>
                
                <a href="register.php" 
                   class="block bg-white border-2 border-blue-600 text-blue-600 text-center py-4 rounded-xl font-semibold text-lg shadow hover:shadow-md transition duration-300">
                    <i class="fas fa-user-plus mr-2"></i>Create New Account
                </a>
            </div>

            <!-- Stats Preview -->
            <div class="mt-10 bg-white rounded-xl shadow-md p-5">
                <h3 class="font-semibold text-gray-800 mb-3">Start Saving Today!</h3>
                <div class="flex justify-between text-center">
                    <div>
                        <div class="text-2xl font-bold text-blue-600">30%</div>
                        <div class="text-sm text-gray-600">Avg. Savings</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">500+</div>
                        <div class="text-sm text-gray-600">Active Users</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-purple-600">24/7</div>
                        <div class="text-sm text-gray-600">Access</div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="p-4 text-center text-gray-500 text-sm border-t">
            <p>© 2024 ExpenseTrack. All rights reserved.</p>
            <p class="mt-1">Track smart. Save more. Live better.</p>
        </footer>
    </div>

    <script>
        // Mobile-specific optimizations
        if (window.innerWidth <= 768) {
            document.querySelectorAll('a, button').forEach(el => {
                el.style.minHeight = '44px';
                el.style.display = 'flex';
                el.style.alignItems = 'center';
                el.style.justifyContent = 'center';
            });
        }
    </script>
</body>
</html>