<?php
/**
 * Admin Moderation Actions Handler (Approve / Reject)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Gate access
require_admin();

$admin_id = get_logged_in_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "admin/dashboard.php");
    exit;
}

// CSRF enforce
csrf_enforce();

$action = isset($_POST['action']) ? $_POST['action'] : '';
$app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

if ($app_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    die("Error: Invalid moderation request parameters.");
}

try {
    // Verify app exists
    $stmt = $pdo->prepare("SELECT name, status FROM apps WHERE id = ?");
    $stmt->execute([$app_id]);
    $app_name = $stmt->fetchColumn();

    if (!$app_name) {
        die("Error: Application not found.");
    }

    if ($action === 'approve') {
        // Update app status to approved
        $up_stmt = $pdo->prepare("UPDATE apps SET status = 'approved', updated_at = NOW() WHERE id = ?");
        $up_stmt->execute([$app_id]);

        // Audit Log
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, app_id, timestamp) VALUES (?, ?, ?, NOW())");
        $log_stmt->execute([$admin_id, "Approved application: " . $app_name, $app_id]);

        header("Location: " . BASE_URL . "admin/dashboard.php?tab=pending&success=1");
        exit;

    } elseif ($action === 'reject') {
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        if (empty($reason)) {
            die("Error: Rejection reason is required.");
        }

        // Update app status to rejected
        $up_stmt = $pdo->prepare("UPDATE apps SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $up_stmt->execute([$app_id]);

        // Audit Log (record reason)
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, app_id, timestamp) VALUES (?, ?, ?, NOW())");
        $log_stmt->execute([$admin_id, "Rejected application: " . $app_name . " (Reason: " . $reason . ")", $app_id]);

        header("Location: " . BASE_URL . "admin/dashboard.php?tab=pending&rejected=1");
        exit;
    }

} catch (PDOException $e) {
    die("Database error during moderation action: " . $e->getMessage());
}
