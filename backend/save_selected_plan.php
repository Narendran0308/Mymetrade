<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');

if (!is_member_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$planType = trim($data['plan_type'] ?? '');

if (!is_valid_plan_type($planType)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid plan selected.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (!save_user_pending_plan($db, $userId, $planType)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save plan selection.']);
    exit;
}

$catalog = plan_catalog()[$planType];

echo json_encode([
    'success' => true,
    'message' => 'Plan saved. Complete payment and wait for admin verification.',
    'plan' => [
        'type' => $planType,
        'label' => $catalog['label'],
        'amount' => $catalog['amount'],
        'duration_days' => $catalog['duration_days'],
    ],
]);
