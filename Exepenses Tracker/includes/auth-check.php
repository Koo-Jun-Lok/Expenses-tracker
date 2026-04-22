<?php
require_once 'config.php';
require_once 'session-handler.php';

// Check if user is logged in
function isLoggedIn() {
    global $expenseSessionHandler;
    return $expenseSessionHandler->isLoggedIn();
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../ExpenseTracker/login.php');
        exit();
    }
}

// Get current user ID
function getUserId() {
    global $expenseSessionHandler;
    return $expenseSessionHandler->getUserId();
}

// Get user info
function getUserInfo($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>