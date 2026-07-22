<?php
/**
 * Developer App Deletion Handler
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Gate access
require_developer();

$user_id = get_logged_in_user_id();
$user_role = get_logged_in_user_role();

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($app_id <= 0) {
    die("Error: Invalid app ID.");
}

try {
    // 1. Fetch app details
    $stmt = $pdo->prepare("SELECT * FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app = $stmt->fetch();

    if (!$app) {
        die("Error: App not found.");
    }

    // Verify ownership (only developer owner or admin can delete)
    if ($app['developer_id'] != $user_id && $user_role !== 'admin') {
        die("Error: Unauthorized deletion attempt.");
    }

    // 2. Delete associated physical files
    // A. Icon
    $iconPath = UPLOAD_DIR . '/icons/' . $app['icon_url'];
    if (!empty($app['icon_url']) && !filter_var($app['icon_url'], FILTER_VALIDATE_URL) && file_exists($iconPath)) {
        @unlink($iconPath);
    }

    // B. Banner
    $bannerPath = UPLOAD_DIR . '/banners/' . $app['banner_url'];
    if (!empty($app['banner_url']) && !filter_var($app['banner_url'], FILTER_VALIDATE_URL) && file_exists($bannerPath)) {
        @unlink($bannerPath);
    }

    // C. App file
    $appFilePath = UPLOAD_DIR . '/apps/' . $app['file_path_or_url'];
    if (!empty($app['file_path_or_url']) && !filter_var($app['file_path_or_url'], FILTER_VALIDATE_URL) && file_exists($appFilePath)) {
        @unlink($appFilePath);
    }

    // D. Screenshots
    $ss_stmt = $pdo->prepare("SELECT image_url FROM screenshots WHERE app_id = ?");
    $ss_stmt->execute([$app_id]);
    $screenshots = $ss_stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($screenshots as $img) {
        $ssPath = UPLOAD_DIR . '/screenshots/' . $img;
        if (!empty($img) && !filter_var($img, FILTER_VALIDATE_URL) && file_exists($ssPath)) {
            @unlink($ssPath);
        }
    }

    // 3. Delete App Row (Cascade constraints handles database tables automatically)
    $del_stmt = $pdo->prepare("DELETE FROM apps WHERE id = ?");
    $del_stmt->execute([$app_id]);

    // If admin is deleting from admin dashboard, redirect there
    if ($user_role === 'admin' && isset($_GET['from_admin'])) {
        header("Location: " . BASE_URL . "admin/dashboard.php?tab=approved&deleted=1");
    } else {
        header("Location: " . BASE_URL . "developer/dashboard.php?tab=my_apps&deleted=1");
    }
    exit;

} catch (PDOException $e) {
    die("Database error during deletion: " . $e->getMessage());
}
