<?php
/**
 * Authentication and Authorization Helpers
 */

/**
 * Check if the user is authenticated.
 * 
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get the current logged-in user ID.
 * 
 * @return int|null
 */
function get_logged_in_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get the current logged-in username.
 * 
 * @return string|null
 */
function get_logged_in_username() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get the current logged-in user role.
 * 
 * @return string|null ('user', 'developer', 'admin')
 */
function get_logged_in_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Require the user to be logged in. Redirect to account.php if not.
 * 
 * @return void
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "account.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Require a specific role or set of roles.
 * 
 * @param array|string $allowedRoles Single role string or array of allowed roles
 * @return void
 */
function require_role($allowedRoles) {
    require_login();
    
    $userRole = get_logged_in_user_role();
    $allowed = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    
    if (!in_array($userRole, $allowed)) {
        header('HTTP/1.1 403 Forbidden');
        // Render simple 403 page
        die('
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Access Denied</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-gray-100 flex items-center justify-center h-screen dark:bg-gray-900">
                <div class="bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center dark:bg-gray-800">
                    <h1 class="text-3xl font-bold text-red-600 mb-4 dark:text-red-500">403 Forbidden</h1>
                    <p class="text-gray-600 mb-6 dark:text-gray-300">You do not have permission to access this page.</p>
                    <a href="' . BASE_URL . '" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">Go to Homepage</a>
                </div>
            </body>
            </html>
        ');
        exit;
    }
}

/**
 * Gate helper for admin dashboard access.
 * 
 * @return void
 */
function require_admin() {
    require_role('admin');
}

/**
 * Gate helper for developer dashboard access.
 * 
 * @return void
 */
function require_developer() {
    // Admins can also act as developers if needed
    require_role(['developer', 'admin']);
}
