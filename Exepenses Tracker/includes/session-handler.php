<?php
require_once 'config.php';

// Renamed class to avoid conflict with PHP's built-in SessionHandler
class ExpenseSessionHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Login user
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Set cookie for 7 days
            setcookie('expense_user', $user['id'], time() + (7 * 24 * 60 * 60), "/");
            
            return true;
        }
        return false;
    }
    
    // Register new user
    public function register($username, $email, $password, $full_name) {
        // Check if user exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            return false; // User already exists
        }
        
        // Hash password and insert user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$username, $email, $hashed_password, $full_name])) {
            $user_id = $this->pdo->lastInsertId();
            
            // Auto login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            
            return true;
        }
        return false;
    }
    
    // Logout user
    public function logout() {
        session_destroy();
        setcookie('expense_user', '', time() - 3600, "/");
        header('Location: login.php');
        exit();
    }
    
    // Check cookie for auto-login
    public function checkCookie() {
        if (!isset($_SESSION['user_id']) && isset($_COOKIE['expense_user'])) {
            $user_id = $_COOKIE['expense_user'];
            
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
            }
        }
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Get current user ID
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

// Initialize session handler
$expenseSessionHandler = new ExpenseSessionHandler($pdo);
$expenseSessionHandler->checkCookie();
?>