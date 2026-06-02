<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'email.php';

header('Content-Type: application/json');

try {
    // Get JSON data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    $email = trim($data['email']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    // Generate login password
    $loginPassword = generatePassword();
    $hashedPassword = password_hash($loginPassword, PASSWORD_BCRYPT);
    
    // Store in temporary login table
    $stmt = $db->prepare("INSERT INTO temp_login (email, password_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $hashedPassword);
        $stmt->execute();
    }

    // Send login email
    global $emailSender;
    $emailSent = $emailSender->sendPassword($email, $loginPassword, 'Returning User');

    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Login password sent! Check your inbox (and spam folder).'
        ]);
    } else {
        $detail = $emailSender->getLastError();
        echo json_encode([
            'success' => false,
            'message' => 'Could not send email. Check Gmail app password in backend/config.php.',
            'error' => $detail,
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
