<?php
/**
 * Admin Categories Manager Action Handler (Create / Delete)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Gate access
require_admin();

$admin_id = get_logged_in_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "admin/dashboard.php?tab=categories");
    exit;
}

// CSRF check
csrf_enforce();

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $slug = isset($_POST['slug']) ? trim($_POST['slug']) : '';
        $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';

        // Clean slug
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $slug)));

        if (empty($name) || empty($slug) || empty($icon)) {
            die("Error: Please fill in all category fields.");
        }

        // Verify slug is unique
        $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $check->execute([$slug]);
        if ($check->fetch()) {
            die("Error: A category with this slug already exists.");
        }

        // Insert category
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon) VALUES (?, ?, ?)");
        $stmt->execute([$name, $slug, $icon]);
        $cat_id = $pdo->lastInsertId();

        // Audit Log
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, app_id, timestamp) VALUES (?, ?, NULL, NOW())");
        $log_stmt->execute([$admin_id, "Created category: " . $name]);

        header("Location: " . BASE_URL . "admin/dashboard.php?tab=categories&success=1");
        exit;

    } elseif ($action === 'delete') {
        $cat_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($cat_id <= 0) {
            die("Error: Invalid category ID.");
        }

        // Get category name
        $name_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $name_stmt->execute([$cat_id]);
        $cat_name = $name_stmt->fetchColumn();

        if (!$cat_name) {
            die("Error: Category not found.");
        }

        // Check if apps exist in this category
        $check_apps = $pdo->prepare("SELECT COUNT(*) FROM apps WHERE category_id = ?");
        $check_apps->execute([$cat_id]);
        $apps_count = (int)$check_apps->fetchColumn();

        if ($apps_count > 0) {
            // Cannot delete due to restriction
            die("Error: Cannot delete category. " . $apps_count . " applications are currently assigned to this category. Reassign or delete them first.");
        }

        // Delete row
        $del_stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $del_stmt->execute([$cat_id]);

        // Audit Log
        $log_stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, app_id, timestamp) VALUES (?, ?, NULL, NOW())");
        $log_stmt->execute([$admin_id, "Deleted category: " . $cat_name]);

        header("Location: " . BASE_URL . "admin/dashboard.php?tab=categories&deleted=1");
        exit;
    }
} catch (PDOException $e) {
    die("Database error managing categories: " . $e->getMessage());
}
