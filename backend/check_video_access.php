<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ensure_payment_tables($db);

if (!is_member_authenticated()) {
    http_response_code(401);
    echo json_encode([
        'allowed' => false,
        'reason' => 'login_required',
    ]);
    exit;
}

$access = video_access_status($db);

echo json_encode([
    'allowed' => (bool) ($access['allowed'] ?? false),
    'reason' => $access['reason'] ?? null,
]);
