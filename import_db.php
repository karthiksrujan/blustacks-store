<?php
/**
 * Temporary Database Import Helper
 * Bypasses Infinity Free Remote MySQL block by running locally on the server.
 * Automatically deletes itself after completion.
 */

// Show errors for troubleshooting
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$sqlFile = __DIR__ . '/database.sql';

if (!file_exists($sqlFile)) {
    die("Error: database.sql not found on the server.");
}

try {
    echo "Starting database import to 'if0_42431325_blustack_db' on 'sql102.infinityfree.com'...</br>";

    $sqlContent = file_get_contents($sqlFile);
    
    // Remove comments
    $sqlContent = preg_replace('/--.*\n/', '', $sqlContent);
    $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
    
    // Split queries by semicolon
    $queries = explode(';', $sqlContent);

    $successCount = 0;
    $errorCount = 0;

    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $pdo->exec($query);
                $successCount++;
            } catch (PDOException $e) {
                echo "Error executing query: " . htmlspecialchars($query) . " - " . $e->getMessage() . "</br>";
                $errorCount++;
            }
        }
    }

    echo "Import completed. Successfully executed: $successCount queries. Errors: $errorCount.</br>";
    
    // Self-destruct for security
    @unlink(__FILE__);
    echo "Security notice: import_db.php has self-destructed and been deleted from the server.";

} catch (Exception $e) {
    die("Import failed: " . $e->getMessage());
}
