<?php
/**
 * Logout Session Handler
 */
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy session on server
session_destroy();

// Set Clear-Site-Data header to purge cookies and cache (leaves storage to preserve theme choice)
header('Clear-Site-Data: "cookies", "cache"');

// Redirect to home page
header("Location: " . BASE_URL);
exit;
