<?php
/**
 * File Download Tracker and File Server
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($app_id <= 0) {
    die("Error: Invalid app ID.");
}

try {
    // 1. Fetch app details
    $stmt = $pdo->prepare("SELECT id, name, file_path_or_url, price, status, developer_id, is_published FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();

    if (!$app) {
        die("Error: App not found.");
    }

    // Only allow download of approved and published apps (unless developer owner / admin)
    if ($app['status'] !== 'approved' || $app['is_published'] == 0) {
        $user_id = get_logged_in_user_id();
        $user_role = get_logged_in_user_role();
        
        if (!$user_id || ($user_id != $app['developer_id'] && $user_role !== 'admin')) {
            if ($app['status'] !== 'approved') {
                die("Error: This app is pending review and cannot be downloaded.");
            } else {
                die("Error: This app is not published yet and cannot be downloaded.");
            }
        }
    }

    // 2. Track download history (if logged in)
    $user_id = get_logged_in_user_id();
    if ($user_id) {
        $log_stmt = $pdo->prepare("INSERT INTO downloads (app_id, user_id, downloaded_at) VALUES (?, ?, NOW())");
        $log_stmt->execute([$app_id, $user_id]);
    }

    // 3. Increment download count in apps table
    $inc_stmt = $pdo->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = ?");
    $inc_stmt->execute([$app_id]);

    // 4. Serve file or Redirect
    $file = $app['file_path_or_url'];

    // Case A: External URL
    if (filter_var($file, FILTER_VALIDATE_URL)) {
        header("Location: " . $file);
        exit;
    }

    // Case B: Local File
    $filePath = UPLOAD_DIR . '/apps/' . $file;

    if (!file_exists($filePath)) {
        // Fallback for demo: if local file is missing, show details or redirect to external mock
        die("
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <title>Download Pending</title>
                <script src='https://cdn.tailwindcss.com'></script>
            </head>
            <body class='bg-gray-100 flex items-center justify-center h-screen dark:bg-gray-900'>
                <div class='bg-white p-8 rounded-lg shadow-md max-w-md w-full text-center dark:bg-gray-800 border dark:border-slate-800'>
                    <div class='w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 dark:bg-blue-950/50 dark:text-blue-400'>
                        <i class='fa-solid fa-cloud-arrow-down text-2xl'></i>
                    </div>
                    <h1 class='text-xl font-bold text-slate-900 dark:text-white mb-2'>Simulated Download Successful</h1>
                    <p class='text-sm text-slate-500 dark:text-slate-400 mb-6'>
                        The application <strong>" . esc($app['name']) . "</strong> has been tracked successfully. Since this is a demo environment, the physical file binary was simulated.
                    </p>
                    <a href='" . BASE_URL . "app.php?id=" . $app_id . "' class='inline-block bg-primary-600 text-white px-6 py-2 rounded-full hover:bg-primary-700 transition'>Return to App Page</a>
                </div>
            </body>
            </html>
        ");
        exit;
    }

    // Secure local file streaming
    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
