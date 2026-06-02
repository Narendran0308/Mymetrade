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

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    // Generate verification password
    $verificationPassword = generatePassword();
    $hashedPassword = password_hash($verificationPassword, PASSWORD_BCRYPT);
    
    // Store in temporary verification table
    $stmt = $db->prepare("INSERT INTO temp_verification (email, password_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $hashedPassword);
        $stmt->execute();
    }

    // Send verification email
    global $emailSender;
    $emailSent = $emailSender->sendPassword($email, $verificationPassword, 'New User');

    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'Verification password sent! Check your inbox (and spam folder).'
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
