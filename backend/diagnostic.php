<?php
// Diagnostic script to test backend
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$diagnostics = [];

// Test 1: Check if config.php exists and loads
$diagnostics['config_file_exists'] = file_exists(__DIR__ . '/config.php');
if (file_exists(__DIR__ . '/config.php')) {
    @include_once __DIR__ . '/config.php';
    $diagnostics['config_loaded'] = defined('DB_HOST');
}

// Test 2: Check database constants
$diagnostics['db_host'] = defined('DB_HOST') ? DB_HOST : 'NOT DEFINED';
$diagnostics['db_name'] = defined('DB_NAME') ? DB_NAME : 'NOT DEFINED';
$diagnostics['db_user'] = defined('DB_USER') ? DB_USER : 'NOT DEFINED';

// Test 3: Try to connect to database
$diagnostics['database_connection'] = 'Testing...';
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $diagnostics['database_connection'] = 'FAILED: ' . $conn->connect_error;
    } else {
        $diagnostics['database_connection'] = 'SUCCESS';
        
        // Test 4: Check if tables exist
        $result = $conn->query("SHOW TABLES");
        $diagnostics['tables_count'] = $result ? $result->num_rows : 0;
        $diagnostics['tables'] = [];
        
        if ($result) {
            while ($row = $result->fetch_row()) {
                $diagnostics['tables'][] = $row[0];
            }
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    $diagnostics['database_connection'] = 'ERROR: ' . $e->getMessage();
}

// Test 5: Check if email.php exists
$diagnostics['email_file_exists'] = file_exists(__DIR__ . '/email.php');

// Test 6: Check if db.php exists
$diagnostics['db_file_exists'] = file_exists(__DIR__ . '/db.php');

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
