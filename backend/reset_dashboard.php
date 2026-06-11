<?php
session_start();

// Simple check for admin authentication
if (empty($_SESSION['admin_authenticated'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Check confirmation
$confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
if ($confirm !== 'yes') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Confirmation missing']);
    exit;
}

// Include database connection
require_once __DIR__ . '/db.php';

// Check if $db exists
if (!isset($db) || !$db) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

// Tables to clear
$tablesToClear = [
    'contact_messages',
    'website_visits',
    'login_history',
    'email_logs'
];

$cleared = [];
$errors = [];

foreach ($tablesToClear as $table) {
    // Check if table exists
    $result = $db->query("SHOW TABLES LIKE '$table'");
    
    if ($result && $result->num_rows > 0) {
        // Try to clear the table
        if ($db->query("DELETE FROM `$table`")) {
            // Try to reset auto increment (not critical if it fails)
            $db->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            $cleared[] = $table;
        } else {
            $errors[] = "Failed to clear $table: " . $db->error;
        }
    }
}

// Return response
if (count($errors) > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Some operations failed',
        'cleared' => $cleared,
        'errors' => $errors
    ]);
} else if (count($cleared) > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard reset completed',
        'cleared' => $cleared
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'No tables to clear (they may not exist yet)',
        'cleared' => []
    ]);
}
