<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

ensure_course_videos_table($db);
ensure_payment_tables($db);

if (!is_member_authenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'reason' => 'login_required',
        'message' => 'Please log in with your email to view videos.',
        'membership' => null,
    ]);
    exit;
}

$access = video_access_status($db);

if (!$access['allowed']) {
    $statusCode = $access['reason'] === 'login_required' ? 401 : 403;
    $message = $access['reason'] === 'login_required'
        ? 'Please log in with your email to view videos.'
        : 'Videos unlock after payment is verified by admin. If you already paid, contact support with your payment screenshot.';

    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'reason' => $access['reason'],
        'message' => $message,
        'membership' => $access['membership'],
    ]);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$membership = $access['membership'] ?? get_member_access_summary($db, $userId);
$videos = [];

if (is_admin_authenticated()) {
    $result = $db->query(
        "SELECT id, title, description, vdocipher_video_id, original_name, file_size, is_published, required_plan, uploaded_at
         FROM course_videos ORDER BY uploaded_at DESC"
    );

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['plan_label'] = plan_label($row['required_plan'] ?? 'weekly');
            $videos[] = $row;
        }
    }
} else {
    $videos = get_accessible_videos_for_user($db, $userId);
    // Generate short-lived per-session tokens for local (non-VdoCipher) videos
    if (!isset($_SESSION['video_tokens']) || !is_array($_SESSION['video_tokens'])) {
        $_SESSION['video_tokens'] = [];
    }
    foreach ($videos as &$v) {
        // create a token valid for 12 hours
        $token = bin2hex(random_bytes(12));
        $_SESSION['video_tokens'][(int)$v['id']] = [
            'token' => $token,
            'expires' => time() + 43200,
        ];
        $v['stream_token'] = $token;
    }
    unset($v);
}

echo json_encode([
    'success' => true,
    'videos' => $videos,
    'membership' => $membership,
    'viewer' => [
        'email' => $_SESSION['user_email'] ?? 'Admin',
        'is_admin' => is_admin_authenticated(),
    ],
]);
