<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'email.php';

header('Content-Type: application/json');

try {
    // Get JSON data from request
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['email']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        exit;
    }

    $email = trim($data['email']);
    $userPassword = trim($data['password']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    // Check if user already exists
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

    // Verify the password from temp_verification table
    $stmt = $db->prepare("SELECT password_hash FROM temp_verification WHERE email = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        exit;
    }

    $verifRow = $result->fetch_assoc();
    if (!password_verify($userPassword, $verifRow['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }

    // Mark as used
    $stmt = $db->prepare("UPDATE temp_verification SET used = 1 WHERE email = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Hash the user's password for storage
    $hashedPassword = password_hash($userPassword, PASSWORD_BCRYPT);

    // Insert user into database
    $stmt = $db->prepare("INSERT INTO users (email, password, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to create account']);
        exit;
    }
    
    $stmt->bind_param("ss", $email, $hashedPassword);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to create account']);
        exit;
    }

    $userId = (int) $stmt->insert_id;
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['signup_pending_details'] = true;

    echo json_encode([
        'success' => true,
        'message' => '✓ Account created! Enter your details to continue.',
        'needs_details' => true
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

