<?php
require_once 'includes/config.php';
require_once 'includes/session-handler.php';

// Logout user
$expenseSessionHandler->logout();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out...</title>
    <script>
        // Clear all localStorage data
        localStorage.clear();
        // Clear sessionStorage
        sessionStorage.clear();
        // Redirect to login page
        window.location.href = 'login.php';
    </script>
</head>
<body>
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h2>Logging you out...</h2>
        <div class="spinner" style="
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        "></div>
        <p>Please wait while we secure your session.</p>
        
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </div>
</body>
</html>