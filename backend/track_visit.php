<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $pageUrl = isset($data['page_url']) ? trim($data['page_url']) : '';
    $pageTitle = isset($data['page_title']) ? trim($data['page_title']) : '';
    $referrer = isset($data['referrer']) ? trim($data['referrer']) : '';
    $sessionKey = isset($data['session_key']) ? trim($data['session_key']) : '';
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if ($sessionKey === '') {
        $sessionKey = session_id();
    }

    $db->query("CREATE TABLE IF NOT EXISTS website_visits (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        session_key VARCHAR(120),
        page_url VARCHAR(500),
        page_title VARCHAR(255),
        referrer VARCHAR(500),
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_session_key (session_key),
        INDEX idx_visited_at (visited_at)
    )");

    $stmt = $db->prepare("INSERT INTO website_visits (user_id, session_key, page_url, page_title, referrer, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $stmt->bind_param("issssss", $userId, $sessionKey, $pageUrl, $pageTitle, $referrer, $ipAddress, $userAgent);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to track visit']);
}
?>
