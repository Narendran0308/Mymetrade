<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

ensure_course_videos_table($db);
ensure_payment_tables($db);

$access = video_access_status($db);

if (!$access['allowed']) {
    http_response_code($access['reason'] === 'login_required' ? 401 : 403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit($access['reason'] === 'login_required' ? 'Login required' : 'Payment verification required');
}

$videoId = (int) ($_GET['id'] ?? 0);
if ($videoId <= 0) {
    http_response_code(400);
    exit('Invalid video');
}

$video = fetch_video_by_id($db, $videoId);
if (!$video || ((int) $video['is_published'] !== 1 && !is_admin_authenticated())) {
    http_response_code(404);
    exit('Video not found');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!is_admin_authenticated() && !user_can_stream_video($db, $userId, $video)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('This video is not included in your current plan.');
}

$storageDir = course_video_storage_dir();
$absolutePath = $storageDir . DIRECTORY_SEPARATOR . $video['stored_name'];

if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('Video file missing');
}

// validate short-lived per-session token to prevent direct linking
$token = $_GET['t'] ?? '';
$valid = false;
if (is_admin_authenticated()) {
    $valid = true;
} elseif (!empty($token) && !empty($_SESSION['video_tokens']) && is_array($_SESSION['video_tokens'])) {
    $entry = $_SESSION['video_tokens'][(int)$videoId] ?? null;
    if ($entry && is_array($entry) && hash_equals($entry['token'] ?? '', $token) && (int)($entry['expires'] ?? 0) >= time()) {
        $valid = true;
    }
}

if (!$valid) {
    // Log debug info to help diagnose token issues
    try {
        $log = [
            'time' => date('c'),
            'session_id' => session_id(),
            'video_id' => $videoId,
            'provided_token' => $token,
            'session_video_tokens' => isset($_SESSION['video_tokens']) ? ($_SESSION['video_tokens'][(int)$videoId] ?? $_SESSION['video_tokens']) : null,
            'now' => time(),
        ];
        @file_put_contents(__DIR__ . '/stream_debug.log', json_encode($log, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // ignore logging failures
    }

    http_response_code(403);
    exit('Invalid or expired stream token');
}

session_write_close();
stream_video_file($absolutePath, $video['mime_type'], $video['original_name']);
