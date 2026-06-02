<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_authenticated'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$confirm = $_POST['confirm'] ?? '';
if ($confirm !== 'yes') {
    echo json_encode(['success' => false, 'message' => 'Confirmation missing']);
    exit;
}

// Tables to clear. Preserve course_videos, users and uploaded files.
$tablesToClear = [
    'contact_messages',
    'website_visits',
    'login_history',
    'email_logs'
];

try {
    $db->begin_transaction();

    foreach ($tablesToClear as $t) {
        // Use DELETE to stay safe with foreign keys
        $db->query("DELETE FROM `" . $db->real_escape_string($t) . "`");
        // Reset auto-increment
        $db->query("ALTER TABLE `" . $db->real_escape_string($t) . "` AUTO_INCREMENT = 1");
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Dashboard reset completed.']);
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to reset dashboard']);
}
