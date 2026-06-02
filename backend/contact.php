<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if ($name === '' || $email === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $db->query("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new', 'read', 'replied') DEFAULT 'new',
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    )");

    $stmt = $db->prepare("INSERT INTO contact_messages (user_id, name, email, message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }

    $stmt->bind_param("isssss", $userId, $name, $email, $message, $ipAddress, $userAgent);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Thanks. Your message has been submitted.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to submit your message right now.']);
}
?>
