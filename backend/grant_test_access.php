<?php
/**
 * Grant Test Access to Videos
 * FOR DEVELOPMENT/TESTING PURPOSES ONLY
 * Removes this in production
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in first to access this feature.',
    ]);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$planType = $_POST['plan_type'] ?? 'weekly'; // Default to weekly

// Validate plan type
if (!is_valid_plan_type($planType)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid plan type.',
    ]);
    exit;
}

// Get plan info
$catalog = plan_catalog();
$planInfo = $catalog[$planType] ?? null;

if (!$planInfo) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Plan not found.',
    ]);
    exit;
}

try {
    ensure_payment_tables($db);

    // Check if user already has an active subscription
    $existing = $db->query("SELECT id FROM subscriptions WHERE user_id = $userId AND status = 'active' LIMIT 1");
    
    if ($existing && $existing->num_rows > 0) {
        // Remove old subscription
        $db->query("UPDATE subscriptions SET status = 'expired' WHERE user_id = $userId AND status = 'active'");
    }

    // Calculate dates
    $startDate = date('Y-m-d H:i:s');
    $endDate = null;

    if ($planInfo['duration_days'] !== null) {
        $endDate = date('Y-m-d H:i:s', strtotime("+{$planInfo['duration_days']} days"));
    }

    // Create test subscription
    $stmt = $db->prepare(
        "INSERT INTO subscriptions (user_id, plan_type, amount, currency, status, start_date, end_date, created_at)
         VALUES (?, ?, ?, 'INR', 'active', ?, ?, NOW())"
    );

    if (!$stmt) {
        throw new Exception("Database error: " . $db->connection->error);
    }

    $amount = $planInfo['amount'];
    $stmt->bind_param('isiss', $userId, $planType, $amount, $startDate, $endDate);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Failed to create test subscription.");
    }

    // Mark in session that this is a test access
    $_SESSION['test_access'] = true;
    $_SESSION['test_access_plan'] = $planType;

    echo json_encode([
        'success' => true,
        'message' => "Test access granted for {$planInfo['label']} ({$planInfo['duration_days']} days)",
        'plan_type' => $planType,
        'plan_label' => $planInfo['label'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating test access: ' . $e->getMessage(),
    ]);
}
