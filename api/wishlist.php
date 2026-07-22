<?php
/**
 * AJAX Wishlist Toggle API
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// Check auth
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please sign in to manage your wishlist.']);
    exit;
}

// Enforce CSRF validation
csrf_enforce();

$user_id = get_logged_in_user_id();
$app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;

if ($app_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid application ID.']);
    exit;
}

try {
    // Check if already in wishlist
    $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND app_id = ?");
    $stmt->execute([$user_id, $app_id]);
    $wishlist_item = $stmt->fetch();

    if ($wishlist_item) {
        // Remove from wishlist
        $delete_stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND app_id = ?");
        $delete_stmt->execute([$user_id, $app_id]);
        echo json_encode(['status' => 'removed']);
    } else {
        // Add to wishlist
        $insert_stmt = $pdo->prepare("INSERT INTO wishlist (user_id, app_id) VALUES (?, ?)");
        $insert_stmt->execute([$user_id, $app_id]);
        echo json_encode(['status' => 'added']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database operation failed.']);
}
