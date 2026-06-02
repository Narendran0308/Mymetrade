<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!is_member_authenticated() && !is_admin_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

$userId = is_admin_authenticated() ? 0 : (int) ($_SESSION['user_id'] ?? 0);
$membership = get_member_access_summary($db, $userId);

echo json_encode([
    'success' => true,
    'membership' => $membership,
]);
