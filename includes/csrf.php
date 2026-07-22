<?php
/**
 * CSRF Protection Helpers
 */

/**
 * Generate a CSRF token if one does not exist in the session.
 * 
 * @return string
 */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden input field with the CSRF token.
 * 
 * @return void
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate a CSRF token from a request.
 * 
 * @param string|null $token Received token. If null, reads from $_POST['csrf_token'] or HTTP Header
 * @return bool
 */
function csrf_validate($token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($token === null) {
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } else {
            return false;
        }
    }
    
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enforce CSRF token verification. Terminate request if invalid.
 * 
 * @return void
 */
function csrf_enforce() {
    if (!csrf_validate()) {
        header('HTTP/1.1 403 Forbidden');
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token.']);
        } else {
            die('Error: Invalid CSRF Token. Request rejected.');
        }
        exit;
    }
}
