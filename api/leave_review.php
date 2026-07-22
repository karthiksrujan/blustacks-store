<?php
/**
 * Review Submission API Handler
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

// Form submission requires POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL);
    exit;
}

// Check login
if (!is_logged_in()) {
    die("Error: Login required to submit reviews.");
}

// Validate CSRF token
csrf_enforce();

$user_id = get_logged_in_user_id();
$app_id = isset($_POST['app_id']) ? (int)$_POST['app_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Basic validations
if ($app_id <= 0 || $rating < 1 || $rating > 5 || empty($review_text)) {
    header("Location: " . BASE_URL . "app.php?id=" . $app_id . "&error=invalid_inputs");
    exit;
}

// Enforce review length limit
if (mb_strlen($review_text) > 500) {
    $review_text = mb_substr($review_text, 0, 500);
}

try {
    // 1. Verify user has actually downloaded the app
    if (!has_downloaded($pdo, $user_id, $app_id)) {
        header("Location: " . BASE_URL . "app.php?id=" . $app_id . "&error=not_downloaded");
        exit;
    }

    // 2. Check if user already reviewed
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE app_id = ? AND user_id = ?");
    $stmt->execute([$app_id, $user_id]);
    if ($stmt->fetch()) {
        header("Location: " . BASE_URL . "app.php?id=" . $app_id . "&error=already_reviewed");
        exit;
    }

    // 3. Insert review
    $insert_stmt = $pdo->prepare("
        INSERT INTO reviews (app_id, user_id, rating, review_text, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $insert_stmt->execute([$app_id, $user_id, $rating, $review_text]);

    // 4. Recalculate average rating of the app
    $avg_stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE app_id = ?");
    $avg_stmt->execute([$app_id]);
    $new_avg = (float)$avg_stmt->fetchColumn();

    $update_stmt = $pdo->prepare("UPDATE apps SET average_rating = ? WHERE id = ?");
    $update_stmt->execute([$new_avg, $app_id]);

    // Redirect back to app detail page reviews tab
    header("Location: " . BASE_URL . "app.php?id=" . $app_id . "#reviews");
    exit;

} catch (PDOException $e) {
    die("Database error during review submission: " . $e->getMessage());
}
