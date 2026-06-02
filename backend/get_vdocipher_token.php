<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');

ensure_course_videos_table($db);
ensure_payment_tables($db);

$internalId = (int) ($_GET['id'] ?? 0);
if ($internalId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid video id']);
    exit;
}

$access = video_access_status($db);
if (!$access['allowed']) {
    $status = $access['reason'] === 'login_required' ? 401 : 403;
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$video = fetch_video_by_id($db, $internalId);
if (!$video || (int) $video['is_published'] !== 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Video not found']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!is_admin_authenticated() && !user_can_stream_video($db, $userId, $video)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'This video is not included in your current plan.']);
    exit;
}

$vdoVideoId = video_vdocipher_id($video);
if ($vdoVideoId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'This lesson has no VdoCipher Video ID. Add it in Admin → Course Videos.',
    ]);
    exit;
}

$apiSecret = defined('VDOCIPHER_API_SECRET') ? trim((string) VDOCIPHER_API_SECRET) : '';
if ($apiSecret === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Set VDOCIPHER_API_SECRET in backend/config.php (from VdoCipher dashboard → API).',
    ]);
    exit;
}

$viewerEmail = trim((string) ($_SESSION['user_email'] ?? 'Member'));
$payload = vdocipher_otp_request_body($viewerEmail);

$apiBase = defined('VDOCIPHER_API_BASE') ? rtrim((string) VDOCIPHER_API_BASE, '/') : 'https://www.vdocipher.com/api';
$url = $apiBase . '/videos/' . rawurlencode($vdoVideoId) . '/otp';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Apisecret ' . $apiSecret,
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Could not reach VdoCipher.', 'details' => $curlError]);
    exit;
}

$decoded = json_decode($response, true);
if ($httpCode !== 200 || !is_array($decoded) || empty($decoded['otp'])) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'VdoCipher OTP request failed. Check API secret and Video ID.',
        'details' => is_array($decoded) ? ($decoded['message'] ?? $decoded) : $response,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'otp' => $decoded['otp'],
    'playbackInfo' => $decoded['playbackInfo'] ?? '',
]);
