<?php
/**
 * One-time helper for VdoCipher trial setup (run in browser on localhost only).
 * Example: http://localhost/Mymetrades-main%20-%20Copy/backend/setup_vdocipher_trial.php
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/video_lib.php';

header('Content-Type: text/html; charset=UTF-8');

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if ($remote !== '127.0.0.1' && $remote !== '::1') {
    http_response_code(403);
    exit('Run this script only on localhost.');
}

ensure_course_videos_table($db);

$videoId = defined('VDOCIPHER_TRIAL_VIDEO_ID') ? trim((string) VDOCIPHER_TRIAL_VIDEO_ID) : '';
$apiSecret = defined('VDOCIPHER_API_SECRET') ? trim((string) VDOCIPHER_API_SECRET) : '';
$apiBase = defined('VDOCIPHER_API_BASE') ? rtrim((string) VDOCIPHER_API_BASE, '/') : 'https://dev.vdocipher.com/api';

$messages = [];

if ($videoId === '') {
    $messages[] = ['error', 'Set VDOCIPHER_TRIAL_VIDEO_ID in backend/config.php'];
} else {
    $stmt = $db->prepare("SELECT id, title, vdocipher_video_id FROM course_videos WHERE vdocipher_video_id = ? LIMIT 1");
    $stmt->bind_param('s', $videoId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $messages[] = ['ok', 'Lesson already linked (DB id ' . (int) $existing['id'] . '): ' . htmlspecialchars($existing['title'])];
        $lessonDbId = (int) $existing['id'];
    } else {
        $title = 'Trial Lesson (VdoCipher)';
        $description = 'Protected video via VdoCipher trial';
        $storedName = 'vdocipher/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $videoId);
        $originalName = 'VdoCipher: ' . $videoId;
        $mimeType = 'application/x-vdocipher';
        $fileSize = 0;
        $requiredPlan = 'weekly';

        $insert = $db->prepare(
            "INSERT INTO course_videos (title, description, vdocipher_video_id, stored_name, original_name, mime_type, file_size, required_plan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->bind_param('ssssssis', $title, $description, $videoId, $storedName, $originalName, $mimeType, $fileSize, $requiredPlan);

        if ($insert->execute()) {
            $lessonDbId = (int) $insert->insert_id;
            $messages[] = ['ok', 'Created lesson in database (id ' . $lessonDbId . ') with Video ID: ' . htmlspecialchars($videoId)];
        } else {
            $messages[] = ['error', 'Could not insert lesson: ' . htmlspecialchars($db->error)];
            $lessonDbId = 0;
        }
    }
}

if ($apiSecret === '') {
    $messages[] = ['warn', 'VDOCIPHER_API_SECRET is empty. Open VdoCipher dashboard → Settings → API → copy API Secret into backend/config.php'];
} else {
    $payload = vdocipher_otp_request_body('setup-test@mymetrades');
    $url = $apiBase . '/videos/' . rawurlencode($videoId) . '/otp';

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
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if ($httpCode === 200 && !empty($decoded['otp'])) {
        $messages[] = ['ok', 'VdoCipher OTP test OK (trial API). Playback should work on videos.html for paid members.'];
    } else {
        $detail = is_array($decoded) ? json_encode($decoded) : (string) $response;
        $messages[] = ['error', 'OTP test failed (HTTP ' . $httpCode . '). Wrong API secret or video ID? Details: ' . htmlspecialchars($detail)];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>VdoCipher Trial Setup</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; }
        .ok { color: #059669; }
        .warn { color: #d97706; }
        .error { color: #dc2626; }
        li { margin: 10px 0; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>VdoCipher trial setup</h1>
    <ul>
        <?php foreach ($messages as [$type, $text]): ?>
            <li class="<?= htmlspecialchars($type) ?>"><?= $text ?></li>
        <?php endforeach; ?>
    </ul>
    <h2>Next steps</h2>
    <ol>
        <li>In <code>backend/config.php</code>, paste your <strong>API Secret</strong> into <code>VDOCIPHER_API_SECRET</code> (from VdoCipher → Settings → API).</li>
        <li>Reload this page until you see “OTP test OK”.</li>
        <li>Log in as a member with verified payment → open <a href="../videos.html">videos.html</a> and play the lesson.</li>
    </ol>
    <p><strong>Note:</strong> <code>c1224d2c2f3a4832b6bd8146c8541d83</code> is your <em>Video ID</em>, not the API secret.</p>
</body>
</html>
