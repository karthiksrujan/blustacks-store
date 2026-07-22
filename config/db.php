<?php
/**
 * Database Connection & Global Configuration
 */

// Error reporting configuration (disable in production/Infinity Free if needed)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration (Infinity Free Remote Settings)
define('DB_HOST', 'sql102.infinityfree.com');
define('DB_NAME', 'if0_42431325_blustack_db');
define('DB_USER', 'if0_42431325');
define('DB_PASS', 'Ka14ev0239');
define('DB_CHARSET', 'utf8mb4');

// Security & Site Configurations
define('SITE_NAME', 'blustacksstore');
define('BASE_URL', '/'); // Set to your Infinity Free domain path if subfolder, e.g., '/' or '/store/'
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 104857600); // 100MB in bytes

// Optional Malware Scan config
define('VIRUSTOTAL_API_KEY', ''); // Add VirusTotal API Key here to enable cloud scanning

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // In production, do not expose raw connection errors
    die("Database Connection Failed: Please verify connection details in config/db.php.");
}

// Secure session start
if (session_status() === PHP_SESSION_NONE) {
    // Set secure cookie params (30 minutes lifetime = 1800 seconds)
    $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Session timeout check (30 minutes of inactivity)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    // Redirect to login/home
    if (PHP_SAPI !== 'cli') {
        header("Location: " . BASE_URL . "account.php?timeout=1");
        exit;
    }
}
$_SESSION['LAST_ACTIVITY'] = time();
