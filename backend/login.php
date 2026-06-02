<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';
require_once 'email.php';
require_once 'video_lib.php';

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

    $user = $result->fetch_assoc();
    $userId = $user['id'];

    // Verify the password from temp_login table
    $stmt = $db->prepare("SELECT password_hash FROM temp_login WHERE email = ? AND expires_at > NOW() AND used = 0 ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired password']);
        exit;
    }

    $loginRow = $result->fetch_assoc();
    if (!password_verify($userPassword, $loginRow['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        exit;
    }

    // Mark as used
    $stmt = $db->prepare("UPDATE temp_login SET used = 1 WHERE email = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Log successful login for admin statistics
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = $db->prepare("INSERT INTO login_history (user_id, email, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $userId, $email, $ipAddress, $userAgent);
        $stmt->execute();
    }

    // Create session
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_email'] = $email;
    $_SESSION['login_time'] = time();

    ensure_payment_tables($db);
    $membership = get_member_access_summary($db, $userId);
    $wantsCourseVideos = isset($data['next']) && $data['next'] === 'course-videos';

    if ($membership['has_access']) {
        $redirect = 'videos.html';
    } elseif ($wantsCourseVideos) {
        $redirect = 'payment.html?from=course-videos';
    } else {
        $redirect = 'terms.html';
    }

    echo json_encode([
        'success' => true,
        'message' => '✓ Login successful!',
        'user_id' => $userId,
        'redirect' => $redirect,
        'membership' => $membership
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
