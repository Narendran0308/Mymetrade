<?php

function plan_catalog() {
    return [
        'weekly' => [
            'label' => '20-Day Batch',
            'short_label' => '20 days',
            'duration_days' => 20,
            'amount' => 12000,
            'price_short' => '12K',
            'price_display' => '₹12,000',
            'currency' => 'INR',
            'subtitle' => '20 days live market access + starter lessons',
            'rank' => 1,
            'highlights' => [
                'Daily live market session for 20 days',
                'Focus: F&O and Forex, including Nifty and Bank Nifty',
                'Entry, exit and stop-loss levels shared for valid setups',
                'Our private community access after payment',
                'No cancellation, no refund',
            ],
        ],
        'monthly' => [
            'label' => 'Monthly Mentorship',
            'short_label' => '40 days',
            'duration_days' => 40,
            'amount' => 49000,
            'price_short' => '49K',
            'price_display' => '₹49,000',
            'currency' => 'INR',
            'subtitle' => '40 days live access + deeper course lessons',
            'rank' => 2,
            'highlights' => [
                'Daily live market session for 40 days',
                'Focus: F&O, Forex, Nifty, Bank Nifty and Gold',
                'Mentorship support for doubts, reviews and execution discipline',
                'Full access to our structured trading course',
                'Our private community access after payment',
                'Refer refund policy',
            ],
        ],
        'lifetime' => [
            'label' => 'Lifetime Mentorship',
            'short_label' => 'Lifetime',
            'duration_days' => null,
            'amount' => 99000,
            'price_short' => '99K',
            'price_display' => '₹99,000',
            'currency' => 'INR',
            'subtitle' => 'One-time payment for long-term learning',
            'rank' => 3,
            'badge' => 'Best Value',
            'badge_note' => 'Includes 1:1 Coaching',
            'highlights' => [
                'Daily live market session access for lifetime members',
                'Focus: F&O, Forex, Nifty, Bank Nifty and Gold',
                'Full access to the complete trading course',
                '1-on-1 coaching',
                'Our private community access after payment',
                'Refer refund policy',
            ],
        ],
    ];
}

function plan_catalog_for_client() {
    $plans = [];
    foreach (plan_catalog() as $type => $plan) {
        $plans[] = array_merge(['type' => $type], $plan);
    }
    return $plans;
}

function plan_rank($planType) {
    $catalog = plan_catalog();
    return $catalog[$planType]['rank'] ?? 0;
}

function plan_label($planType) {
    $catalog = plan_catalog();
    return $catalog[$planType]['label'] ?? ucfirst((string) $planType);
}

function is_valid_plan_type($planType) {
    return isset(plan_catalog()[$planType]);
}

function ensure_course_videos_table($db) {
    $db->query("CREATE TABLE IF NOT EXISTS course_videos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        stored_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        required_plan ENUM('weekly', 'monthly', 'lifetime') NOT NULL DEFAULT 'weekly',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_published (is_published),
        INDEX idx_required_plan (required_plan),
        INDEX idx_uploaded_at (uploaded_at)
    )");

    $column = $db->query("SHOW COLUMNS FROM course_videos LIKE 'required_plan'");
    if ($column && $column->num_rows === 0) {
        $db->query("ALTER TABLE course_videos
            ADD COLUMN required_plan ENUM('weekly', 'monthly', 'lifetime') NOT NULL DEFAULT 'weekly' AFTER is_published");
    }

    $vdocipherColumn = $db->query("SHOW COLUMNS FROM course_videos LIKE 'vdocipher_video_id'");
    if ($vdocipherColumn && $vdocipherColumn->num_rows === 0) {
        $db->query("ALTER TABLE course_videos
            ADD COLUMN vdocipher_video_id VARCHAR(64) NULL DEFAULT NULL AFTER description");
    }
}

function video_vdocipher_id($video) {
    if (!is_array($video)) {
        return '';
    }

    return trim($video['vdocipher_video_id'] ?? $video['vdocipher_key'] ?? '');
}

function video_uses_vdocipher($video) {
    return video_vdocipher_id($video) !== '';
}

/**
 * Build OTP POST body for VdoCipher.
 * annotate must be a JSON *string* whose parsed value is an array of watermark objects.
 */
function vdocipher_otp_request_body($watermarkText = '', $ttlSeconds = 300) {
    $body = ['ttl' => max(60, (int) $ttlSeconds)];

    $label = trim((string) $watermarkText);
    if ($label !== '') {
        $body['annotate'] = json_encode([
            [
                'type' => 'rtext',
                'text' => $label,
                'alpha' => '0.55',
                'color' => '0xFFFFFF',
                'size' => '14',
                'interval' => '5000',
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    return json_encode($body);
}

function ensure_payment_tables($db) {
    $db->query("CREATE TABLE IF NOT EXISTS subscriptions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        plan_type ENUM('weekly', 'monthly', 'lifetime') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
        currency VARCHAR(3) DEFAULT 'INR',
        status ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
        start_date TIMESTAMP NULL,
        end_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_plan_type (plan_type)
    )");

    $db->query("CREATE TABLE IF NOT EXISTS transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        subscription_id INT NULL,
        amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        payment_method VARCHAR(50),
        transaction_id VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    )");

    $pendingPlanColumn = $db->query("SHOW COLUMNS FROM users LIKE 'pending_plan'");
    if ($pendingPlanColumn && $pendingPlanColumn->num_rows === 0) {
        $db->query("ALTER TABLE users ADD COLUMN pending_plan ENUM('weekly', 'monthly', 'lifetime') NULL DEFAULT NULL");
    }

    $db->query("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending'");
}

function course_video_storage_dir() {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'videos';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = dirname($dir) . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
    }

    $videosHtaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($videosHtaccess)) {
        file_put_contents($videosHtaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
    }

    return $dir;
}

function is_admin_authenticated() {
    return !empty($_SESSION['admin_authenticated']);
}

function is_member_authenticated() {
    return !empty($_SESSION['user_id']);
}

function save_user_pending_plan($db, $userId, $planType) {
    $userId = (int) $userId;

    if ($userId <= 0 || !is_valid_plan_type($planType)) {
        return false;
    }

    ensure_payment_tables($db);

    $stmt = $db->prepare("UPDATE users SET pending_plan = ? WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $planType, $userId);

    return $stmt->execute();
}

function get_user_pending_plan($db, $userId) {
    $userId = (int) $userId;

    if ($userId <= 0) {
        return null;
    }

    ensure_payment_tables($db);

    $stmt = $db->prepare("SELECT pending_plan FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $plan = $row['pending_plan'] ?? null;

    return is_valid_plan_type($plan) ? $plan : null;
}

function expire_overdue_subscriptions($db, $userId = null) {
    ensure_payment_tables($db);

    if ($userId !== null) {
        $userId = (int) $userId;
        $stmt = $db->prepare(
            "UPDATE subscriptions SET status = 'expired'
             WHERE user_id = ? AND status = 'active' AND end_date IS NOT NULL AND end_date <= NOW()"
        );
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
        }
        return;
    }

    $db->query(
        "UPDATE subscriptions SET status = 'expired'
         WHERE status = 'active' AND end_date IS NOT NULL AND end_date <= NOW()"
    );
}

function get_user_active_subscription($db, $userId) {
    $userId = (int) $userId;

    if ($userId <= 0) {
        return null;
    }

    ensure_payment_tables($db);
    expire_overdue_subscriptions($db, $userId);

    $stmt = $db->prepare(
        "SELECT id, user_id, plan_type, amount, status, start_date, end_date, created_at
         FROM subscriptions
         WHERE user_id = ? AND status = 'active'
         AND (end_date IS NULL OR end_date > NOW())
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        return null;
    }

    return enrich_subscription_row($row);
}

function enrich_subscription_row($row) {
    $planType = $row['plan_type'] ?? '';
    $catalog = plan_catalog()[$planType] ?? null;

    $row['plan_label'] = $catalog['label'] ?? plan_label($planType);
    $row['plan_rank'] = $catalog['rank'] ?? 0;
    $row['is_lifetime'] = $planType === 'lifetime' || empty($row['end_date']);

    if ($row['is_lifetime']) {
        $row['days_remaining'] = null;
        $row['expires_label'] = 'Never expires';
    } elseif (!empty($row['end_date'])) {
        $endTimestamp = strtotime($row['end_date']);
        $row['days_remaining'] = max(0, (int) ceil(($endTimestamp - time()) / 86400));
        $row['expires_label'] = date('d M Y', $endTimestamp);
    } else {
        $row['days_remaining'] = 0;
        $row['expires_label'] = 'Expired';
    }

    return $row;
}

function user_has_paid_access($db, $userId) {
    return get_user_active_subscription($db, $userId) !== null;
}

function get_member_access_summary($db, $userId) {
    $userId = (int) $userId;
    ensure_payment_tables($db);

    $email = $_SESSION['user_email'] ?? '';
    if ($userId > 0) {
        $stmt = $db->prepare("SELECT email, pending_plan FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $email = $row['email'] ?? $email;
                $pendingPlan = is_valid_plan_type($row['pending_plan'] ?? '') ? $row['pending_plan'] : null;
            }
        }
    }

    $subscription = get_user_active_subscription($db, $userId);

    if ($subscription) {
        return [
            'email' => $email,
            'has_access' => true,
            'payment_status' => 'active',
            'plan_type' => $subscription['plan_type'],
            'plan_label' => $subscription['plan_label'],
            'plan_rank' => $subscription['plan_rank'],
            'start_date' => $subscription['start_date'],
            'end_date' => $subscription['end_date'],
            'days_remaining' => $subscription['days_remaining'],
            'expires_label' => $subscription['expires_label'],
            'is_lifetime' => $subscription['is_lifetime'],
            'pending_plan' => $pendingPlan ?? null,
            'pending_plan_label' => isset($pendingPlan) ? plan_label($pendingPlan) : null,
        ];
    }

    $pendingPlan = $pendingPlan ?? get_user_pending_plan($db, $userId);

    return [
        'email' => $email,
        'has_access' => false,
        'payment_status' => $pendingPlan ? 'awaiting_verification' : 'none',
        'plan_type' => null,
        'plan_label' => null,
        'plan_rank' => 0,
        'start_date' => null,
        'end_date' => null,
        'days_remaining' => 0,
        'expires_label' => null,
        'is_lifetime' => false,
        'pending_plan' => $pendingPlan,
        'pending_plan_label' => $pendingPlan ? plan_label($pendingPlan) : null,
    ];
}

function user_can_access_video_plan($userPlanType, $videoPlanType) {
    $userRank = plan_rank($userPlanType);
    $videoRank = plan_rank($videoPlanType);

    return $userRank > 0 && $videoRank > 0 && $userRank >= $videoRank;
}

function video_access_status($db) {
    if (is_admin_authenticated()) {
        return ['allowed' => true, 'reason' => null, 'membership' => null];
    }

    if (!is_member_authenticated()) {
        return ['allowed' => false, 'reason' => 'login_required', 'membership' => null];
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $membership = get_member_access_summary($db, $userId);

    if (!$membership['has_access']) {
        return [
            'allowed' => false,
            'reason' => 'payment_required',
            'membership' => $membership,
        ];
    }

    return ['allowed' => true, 'reason' => null, 'membership' => $membership];
}

function can_access_videos($db) {
    return video_access_status($db)['allowed'];
}

function get_accessible_videos_for_user($db, $userId, $includeUnpublished = false) {
    ensure_course_videos_table($db);

    $subscription = get_user_active_subscription($db, $userId);
    if (!$subscription) {
        return [];
    }

    $sql = $includeUnpublished
        ? "SELECT id, title, description, vdocipher_video_id, original_name, file_size, is_published, required_plan, uploaded_at FROM course_videos ORDER BY uploaded_at DESC"
        : "SELECT id, title, description, vdocipher_video_id, original_name, file_size, required_plan, uploaded_at FROM course_videos WHERE is_published = 1 ORDER BY uploaded_at DESC";

    $result = $db->query($sql);
    $videos = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (user_can_access_video_plan($subscription['plan_type'], $row['required_plan'])) {
                $row['plan_label'] = plan_label($row['required_plan']);
                $videos[] = $row;
            }
        }
    }

    return $videos;
}

function user_can_stream_video($db, $userId, $video) {
    if (is_admin_authenticated()) {
        return true;
    }

    $subscription = get_user_active_subscription($db, $userId);
    if (!$subscription || empty($video['required_plan'])) {
        return false;
    }

    return user_can_access_video_plan($subscription['plan_type'], $video['required_plan']);
}

function grant_member_video_access($db, $userId, $planType = 'lifetime', $amount = 0) {
    $userId = (int) $userId;

    if ($userId <= 0 || !is_valid_plan_type($planType)) {
        return false;
    }

    ensure_payment_tables($db);
    $catalog = plan_catalog()[$planType];

    $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status IN ('active', 'pending')");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    $endDate = null;
    if (!empty($catalog['duration_days'])) {
        $endDate = date('Y-m-d H:i:s', strtotime('+' . (int) $catalog['duration_days'] . ' days'));
    }

    $amount = (float) ($amount > 0 ? $amount : $catalog['amount']);

    if ($endDate === null) {
        $stmt = $db->prepare(
            "INSERT INTO subscriptions (user_id, plan_type, amount, status, start_date, end_date)
             VALUES (?, ?, ?, 'active', NOW(), NULL)"
        );
    } else {
        $stmt = $db->prepare(
            "INSERT INTO subscriptions (user_id, plan_type, amount, status, start_date, end_date)
             VALUES (?, ?, ?, 'active', NOW(), ?)"
        );
    }

    if (!$stmt) {
        return false;
    }

    if ($endDate === null) {
        $stmt->bind_param('isd', $userId, $planType, $amount);
    } else {
        $stmt->bind_param('isds', $userId, $planType, $amount, $endDate);
    }

    if (!$stmt->execute()) {
        return false;
    }

    $subscriptionId = (int) $db->lastInsertId();
    $txn = $db->prepare(
        "INSERT INTO transactions (user_id, subscription_id, amount, status, payment_method, transaction_id)
         VALUES (?, ?, ?, 'completed', 'manual_verified', ?)"
    );

    if ($txn) {
        $manualId = 'admin_grant_' . $subscriptionId . '_' . time();
        $txn->bind_param('iids', $userId, $subscriptionId, $amount, $manualId);
        $txn->execute();
    }

    $clear = $db->prepare("UPDATE users SET pending_plan = NULL WHERE id = ?");
    if ($clear) {
        $clear->bind_param('i', $userId);
        $clear->execute();
    }

    return true;
}

function revoke_member_video_access($db, $userId) {
    $userId = (int) $userId;

    if ($userId <= 0) {
        return false;
    }

    ensure_payment_tables($db);

    $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);

    return $stmt->execute();
}

function allowed_video_mime_types() {
    return [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
    ];
}

function detect_video_mime($filePath, $clientMime = '') {
    $allowed = allowed_video_mime_types();

    if ($clientMime && isset($allowed[$clientMime])) {
        return $clientMime;
    }

    if (function_exists('mime_content_type')) {
        $detected = mime_content_type($filePath);
        if ($detected && isset($allowed[$detected])) {
            return $detected;
        }
    }

    return '';
}

function fetch_video_by_id($db, $videoId) {
    $stmt = $db->prepare("SELECT * FROM course_videos WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $videoId);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc() ?: null;
}

function stream_video_file($absolutePath, $mimeType, $downloadName = 'video') {
    if (!is_readable($absolutePath)) {
        http_response_code(404);
        exit('Video not found');
    }

    $fileSize = filesize($absolutePath);
    $start = 0;
    $end = $fileSize - 1;
    $status = 200;

    header('Content-Type: ' . $mimeType);
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadName) . '"');

    if (isset($_SERVER['HTTP_RANGE'])) {
        if (!preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }

        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }

        if ($start > $end || $start >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        $status = 206;
    }

    $length = $end - $start + 1;
    http_response_code($status);
    header("Content-Range: bytes $start-$end/$fileSize");
    header('Content-Length: ' . $length);

    $handle = fopen($absolutePath, 'rb');
    if (!$handle) {
        http_response_code(500);
        exit('Unable to open video');
    }

    fseek($handle, $start);

    $bytesLeft = $length;
    while ($bytesLeft > 0 && !feof($handle)) {
        $chunk = fread($handle, min(8192, $bytesLeft));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $bytesLeft -= strlen($chunk);
        flush();
    }

    fclose($handle);
    exit;
}
