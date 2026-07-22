<?php
/**
 * AJAX Search Autocomplete API
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Search only approved apps
    $stmt = $pdo->prepare("
        SELECT id, name, short_desc, icon_url, average_rating 
        FROM apps 
        WHERE status = 'approved' AND is_published = 1 
          AND (name LIKE :term OR description LIKE :term OR short_desc LIKE :term)
        ORDER BY download_count DESC
        LIMIT 6
    ");
    
    $stmt->execute(['term' => '%' . $query . '%']);
    $apps = $stmt->fetchAll();
    
    $results = [];
    foreach ($apps as $app) {
        $results[] = [
            'id' => $app['id'],
            'name' => esc($app['name']),
            'short_desc' => esc($app['short_desc']),
            'icon_url' => get_app_icon_url($app),
            'average_rating' => $app['average_rating']
        ];
    }
    
    echo json_encode($results);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed.']);
}
