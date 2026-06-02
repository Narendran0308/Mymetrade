<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login again before submitting details.']);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $phone = trim($data['phone'] ?? '');

    if ($firstName === '' || $lastName === '' || $phone === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill all details.']);
        exit;
    }

    if (strlen($phone) < 8 || strlen($phone) > 20) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number.']);
        exit;
    }

    $userId = (int) $_SESSION['user_id'];
    $stmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }

    $stmt->bind_param("sssi", $firstName, $lastName, $phone, $userId);

    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Unable to save details.']);
        exit;
    }

    $signupComplete = !empty($_SESSION['signup_pending_details']);
    if ($signupComplete) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Details saved successfully.',
        'signup_complete' => $signupComplete
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
