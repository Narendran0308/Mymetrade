<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/video_lib.php';

header('Content-Type: text/html; charset=UTF-8');

const ADMIN_USERNAME = 'Myme';
const ADMIN_PASSWORD = '123';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fetch_all_rows($result) {
    $rows = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function scalar_value($db, $sql) {
    $result = $db->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return (int) ($row[0] ?? 0);
}

$db->query("CREATE TABLE IF NOT EXISTS website_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_key VARCHAR(120),
    page_url VARCHAR(500),
    page_title VARCHAR(255),
    referrer VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_key (session_key),
    INDEX idx_visited_at (visited_at)
)");

$db->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'replied') DEFAULT 'new',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
)");

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    header('Location: admin.php');
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['admin_username'] ?? '');
    $password = $_POST['admin_password'] ?? '';

    if (hash_equals(ADMIN_USERNAME, $username) && hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: admin.php');
        exit;
    }

    $loginError = 'Incorrect admin username or password.';
}

$isAuthenticated = !empty($_SESSION['admin_authenticated']);

ensure_course_videos_table($db);
ensure_payment_tables($db);

$videoMessage = '';
$videoError = '';
$memberAccessMessage = '';
$memberAccessError = '';

if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['grant_video_access'])) {
        $memberId = (int) ($_POST['user_id'] ?? 0);
        $planType = trim($_POST['plan_type'] ?? '');
        if (!is_valid_plan_type($planType)) {
            $planType = get_user_pending_plan($db, $memberId) ?: 'lifetime';
        }

        if ($memberId <= 0) {
            $memberAccessError = 'Invalid member selected.';
        } elseif (grant_member_video_access($db, $memberId, $planType)) {
            $memberAccessMessage = 'Video access granted successfully.';
        } else {
            $memberAccessError = 'Could not grant video access.';
        }
    } elseif (!empty($_POST['revoke_video_access'])) {
        $memberId = (int) ($_POST['user_id'] ?? 0);

        if ($memberId <= 0) {
            $memberAccessError = 'Invalid member selected.';
        } elseif (revoke_member_video_access($db, $memberId)) {
            $memberAccessMessage = 'Video access revoked.';
        } else {
            $memberAccessError = 'Could not revoke video access.';
        }
    } elseif (!empty($_POST['delete_video_id'])) {
        $deleteId = (int) $_POST['delete_video_id'];
        $video = fetch_video_by_id($db, $deleteId);

        if ($video) {
            $storageDir = course_video_storage_dir();
            $filePath = $storageDir . DIRECTORY_SEPARATOR . $video['stored_name'];

            if (is_file($filePath)) {
                unlink($filePath);
            }

            $stmt = $db->prepare("DELETE FROM course_videos WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $deleteId);
                $stmt->execute();
                $videoMessage = 'Video deleted successfully.';
            }
        } else {
            $videoError = 'Video not found.';
        }
    } elseif (!empty($_POST['upload_video'])) {
        $title = trim($_POST['video_title'] ?? '');
        $description = trim($_POST['video_description'] ?? '');
        $hasUploadFile = !empty($_FILES['video_file']['name']);

        if ($title === '') {
            $videoError = 'Video title is required.';
        } elseif (empty($_FILES['video_file']['name'])) {
            $videoError = 'Please choose a video file to upload.';
        } elseif ($_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            $videoError = 'Upload failed. Check PHP upload limits (upload_max_filesize / post_max_size).';
        } else {
            $tmpPath = $_FILES['video_file']['tmp_name'];
            $clientMime = $_FILES['video_file']['type'] ?? '';
            $mimeType = detect_video_mime($tmpPath, $clientMime);
            $allowed = allowed_video_mime_types();

            if (!$mimeType || !isset($allowed[$mimeType])) {
                $videoError = 'Unsupported format. Upload MP4, WebM, MOV, or AVI.';
            } else {
                $extension = $allowed[$mimeType];
                $storedName = 'video_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
                $storageDir = course_video_storage_dir();
                $destination = $storageDir . DIRECTORY_SEPARATOR . $storedName;

                if (!move_uploaded_file($tmpPath, $destination)) {
                    $videoError = 'Could not save the uploaded file.';
                } else {
                    $requiredPlan = trim($_POST['required_plan'] ?? 'weekly');
                    if (!is_valid_plan_type($requiredPlan)) {
                        $requiredPlan = 'weekly';
                    }

                    $originalName = $_FILES['video_file']['name'];
                    $fileSize = (int) filesize($destination);
                    $vdocipherForUpload = null;
                    $stmt = $db->prepare(
                        "INSERT INTO course_videos (title, description, vdocipher_video_id, stored_name, original_name, mime_type, file_size, required_plan, is_published)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
                    );

                    if ($stmt) {
                        $stmt->bind_param('ssssssis', $title, $description, $vdocipherForUpload, $storedName, $originalName, $mimeType, $fileSize, $requiredPlan);
                        $stmt->execute();
                        $videoMessage = 'Video uploaded successfully.';
                    } else {
                        unlink($destination);
                        $videoError = 'Database error while saving video details.';
                    }
                }
            }
        }
    }
}

if ($isAuthenticated) {
    $stats = [
        'members' => scalar_value($db, "SELECT COUNT(*) FROM users"),
        'visits' => scalar_value($db, "SELECT COUNT(*) FROM website_visits"),
        'unique_visitors' => scalar_value($db, "SELECT COUNT(DISTINCT COALESCE(NULLIF(session_key, ''), ip_address)) FROM website_visits"),
        'contacts' => scalar_value($db, "SELECT COUNT(*) FROM contact_messages"),
        'new_contacts' => scalar_value($db, "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
        'logins_today' => scalar_value($db, "SELECT COUNT(*) FROM login_history WHERE DATE(login_time) = CURDATE()"),
        'visits_today' => scalar_value($db, "SELECT COUNT(*) FROM website_visits WHERE DATE(visited_at) = CURDATE()"),
        'contacts_today' => scalar_value($db, "SELECT COUNT(*) FROM contact_messages WHERE DATE(created_at) = CURDATE()"),
        'paid_members' => scalar_value($db, "SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status = 'active' AND (end_date IS NULL OR end_date > NOW())")
    ];

    // Load latest contact messages (include id so we can mark them as read when viewed)
    $recentContacts = fetch_all_rows($db->query("SELECT id, name, email, message, status, ip_address, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 10"));

    // If any of the loaded messages are still 'new', mark them as 'read' since admin is viewing them
    $newIds = [];
    foreach ($recentContacts as $c) {
        if (isset($c['status']) && $c['status'] === 'new') {
            $newIds[] = (int) $c['id'];
        }
    }
    if (!empty($newIds)) {
        $idsList = implode(',', $newIds);
        $db->query("UPDATE contact_messages SET status = 'read' WHERE id IN ($idsList)");
        // reflect the change in the local array so the UI shows 'read'
        foreach ($recentContacts as &$c) {
            if (in_array((int) $c['id'], $newIds, true)) {
                $c['status'] = 'read';
            }
        }
        unset($c);
        // refresh the new contacts count shown in the dashboard
        if (isset($stats) && is_array($stats)) {
            $stats['new_contacts'] = scalar_value($db, "SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
        }
    }
    $recentVisits = fetch_all_rows($db->query("SELECT page_title, page_url, referrer, ip_address, user_agent, visited_at FROM website_visits ORDER BY visited_at DESC LIMIT 12"));
    $recentMembers = fetch_all_rows($db->query(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.status, u.created_at, u.last_login, u.pending_plan,
            s.plan_type AS active_plan, s.end_date AS active_end_date, s.status AS sub_status,
            EXISTS(
                SELECT 1 FROM subscriptions s2
                WHERE s2.user_id = u.id AND s2.status = 'active'
                AND (s2.end_date IS NULL OR s2.end_date > NOW())
            ) AS has_video_access
         FROM users u
         LEFT JOIN subscriptions s ON s.id = (
            SELECT id FROM subscriptions
            WHERE user_id = u.id AND status = 'active'
            AND (end_date IS NULL OR end_date > NOW())
            ORDER BY id DESC LIMIT 1
         )
         ORDER BY u.created_at DESC
         LIMIT 25"
    ));
    $recentLogins = fetch_all_rows($db->query("SELECT email, ip_address, user_agent, login_time FROM login_history ORDER BY login_time DESC LIMIT 10"));
    $popularPages = fetch_all_rows($db->query("SELECT page_url, COUNT(*) AS total FROM website_visits GROUP BY page_url ORDER BY total DESC LIMIT 6"));
    $courseVideos = fetch_all_rows($db->query("SELECT id, title, description, vdocipher_video_id, original_name, file_size, is_published, required_plan, uploaded_at FROM course_videos ORDER BY uploaded_at DESC"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mymetrades Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            color-scheme: dark;
            --bg-primary: #0a0a0a;
            --bg-secondary: #121212;
            --bg-tertiary: #1a1a1a;
            --bg-page: #000000;
            --heading-color: #ffffff;
            --text-primary: #e8e8e8;
            --text-secondary: #a3a3a3;
            --text-muted: #737373;
            --border-color: rgba(255, 107, 0, 0.18);
            --nav-bg: rgba(18, 18, 18, 0.94);
            --card-bg: #141414;
            --input-bg: #1a1a1a;
            --accent-color: #ff6b00;
            --accent-hover: #ff8533;
            --accent-soft: rgba(255, 107, 0, 0.14);
            --accent-glow: rgba(255, 107, 0, 0.45);
            --accent-gradient: linear-gradient(135deg, #ff6b00 0%, #ff8c33 50%, #ffb347 100%);
            --banner-gradient: linear-gradient(90deg, #cc5500 0%, #ff6b00 45%, #ff9500 100%);
            --btn-text: #0a0a0a;
            --shadow-card: 0 4px 28px rgba(0, 0, 0, 0.55);
            --shadow-card-hover: 0 16px 40px rgba(255, 107, 0, 0.2);
            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --success-bg: rgba(34, 197, 94, 0.12);
            --success-text: #4ade80;
            --danger-bg: rgba(239, 68, 68, 0.12);
            --danger-text: #f87171;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg-page);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: "Poppins", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .admin-nav {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 18px clamp(18px, 4vw, 56px);
            background: var(--nav-bg);
            border-bottom: 1px solid var(--border-color);
            backdrop-filter: blur(16px) saturate(1.4);
            -webkit-backdrop-filter: blur(16px) saturate(1.4);
            box-shadow: var(--shadow-card);
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
        }

        .brand span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .nav-actions a {
            padding: 10px 14px;
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-actions a:hover {
            background: var(--accent-soft);
            border-color: rgba(37, 99, 235, 0.25);
        }

        .nav-actions .logout {
            background: var(--accent-gradient);
            color: var(--btn-text);
            font-weight: 700;
            border-color: transparent;
            box-shadow: 0 4px 14px var(--accent-glow);
        }

        .page {
            width: min(1320px, calc(100% - 32px));
            margin: 0 auto;
            padding: 42px 0 70px;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-bottom: 28px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(34px, 5vw, 64px);
            line-height: 1;
        }

        .hero p {
            margin: 12px 0 0;
            color: var(--text-secondary);
            max-width: 640px;
            line-height: 1.6;
        }

        .today-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            min-width: min(420px, 100%);
        }

        .today-item {
            padding: 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--card-bg);
            text-align: center;
            box-shadow: var(--shadow-card);
        }

        .today-item strong {
            display: block;
            color: var(--accent-color);
            font-size: 24px;
        }

        .today-item span {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card,
        .panel,
        .login-card {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--card-bg);
            box-shadow: var(--shadow-card);
        }

        .metric-card {
            padding: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .metric-card p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .metric-card strong {
            display: block;
            margin-top: 8px;
            font-size: 34px;
            line-height: 1;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(360px, 0.75fr);
            gap: 18px;
        }

        .panel {
            overflow: hidden;
            margin-bottom: 18px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 18px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .panel-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .panel-header span {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th,
        td {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.07);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            color: var(--accent-color);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        td {
            color: var(--text-primary);
        }

        .muted,
        .clip {
            color: var(--text-muted);
        }

        .clip {
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-cell {
            max-width: 420px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .status {
            display: inline-flex;
            padding: 5px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent-hover);
            font-size: 12px;
            font-weight: 700;
        }

        .page-list {
            padding: 4px 20px 18px;
        }

        .page-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.07);
        }

        .page-row:last-child {
            border-bottom: 0;
        }

        .page-row p {
            margin: 0;
            max-width: 360px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .page-row strong {
            color: var(--accent-color);
        }

        .login-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .login-card {
            width: min(440px, 100%);
            padding: 36px;
        }

        .login-card h1 {
            margin: 0 0 10px;
            font-size: 34px;
            color: var(--heading-color);
        }

        .login-card p {
            margin: 0 0 24px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .login-card label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .login-card input {
            width: 100%;
            padding: 14px 16px;
                border: 1px solid var(--border-color);
            border-radius: 8px;
                background: var(--input-bg);
                color: var(--text-primary);
            font: inherit;
        }

        .login-card input + label {
            margin-top: 14px;
        }

        .login-card button,
        .btn-primary {
            width: 100%;
            margin-top: 16px;
            padding: 14px;
            border: 0;
            border-radius: var(--radius-sm);
            background: var(--accent-gradient);
            color: var(--btn-text);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 18px var(--accent-glow);
        }

        .btn-primary {
            width: auto;
            margin-top: 0;
        }

        .login-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 12px;
            padding: 13px;
                border: 1px solid var(--border-color);
            border-radius: 8px;
                background: var(--bg-primary);
                color: var(--text-primary);
            font-weight: 700;
        }

        .login-back:hover,
        .login-card button:hover {
            filter: brightness(1.08);
        }

        .error,
        .alert-error {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .alert-success {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            background: var(--success-bg);
            color: var(--success-text);
        }

        .video-upload-form {
            padding: 20px;
            display: grid;
            gap: 14px;
        }

        .video-upload-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--heading-color);
        }

        .video-upload-form input[type="text"],
        .video-upload-form textarea,
        .video-upload-form input[type="file"] {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background: var(--input-bg);
            color: var(--text-primary);
            font: inherit;
        }

        .video-upload-form textarea {
            min-height: 90px;
            resize: vertical;
        }

        .video-note {
            margin: 0;
            padding: 0 20px 18px;
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.55;
        }

        .video-note strong {
            color: var(--heading-color);
        }

        .btn-danger {
            padding: 8px 12px;
            border: 0;
            border-radius: var(--radius-sm);
            background: var(--danger-bg);
            color: var(--danger-text);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-success {
            padding: 8px 12px;
            border: 0;
            border-radius: var(--radius-sm);
            background: var(--success-bg);
            color: var(--success-text);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .access-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .access-badge.paid {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .access-badge.unpaid {
            background: #fff7ed;
            color: #c2410c;
        }

        .member-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .member-actions select {
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font: inherit;
            background: var(--input-bg);
        }

        .btn-link {
            color: var(--accent-color);
            font-weight: 600;
        }

        @media (max-width: 1100px) {
            .hero,
            .dashboard-grid {
                grid-template-columns: 1fr;
                display: block;
            }

            .today-strip {
                margin-top: 20px;
            }

            .metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .admin-nav,
            .hero {
                align-items: flex-start;
                flex-direction: column;
            }

            .metric-grid,
            .today-strip {
                grid-template-columns: 1fr;
            }

            .nav-actions {
                width: 100%;
            }

            .nav-actions a {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<?php if (!$isAuthenticated): ?>
    <main class="login-shell">
        <form class="login-card" method="post">
            <p style="margin:0 0 8px;font-weight:800;font-size:14px;color:var(--accent-color);">MyMeTrades</p>
            <h1>Admin Login</h1>
            <p>Sign in to manage members, uploads, visits, contacts, and course videos.</p>
            <label for="admin_username">Username</label>
            <input type="text" id="admin_username" name="admin_username" required autofocus>
            <label for="admin_password">Password</label>
            <input type="password" id="admin_password" name="admin_password" required>
            <button type="submit">Open Dashboard</button>
            <a href="index.html" class="login-back">Back to Website</a>
            <?php if ($loginError): ?>
                <div class="error"><?= e($loginError) ?></div>
            <?php endif; ?>
        </form>
    </main>
<?php else: ?>
    <nav class="admin-nav">
        <a class="brand" href="admin.php">MyMe<span>Trades</span> Admin</a>
        <div class="nav-actions">
            <a href="#videos">Videos</a>
            <a href="#contacts">Contacts</a>
            <a href="#visits">Visits</a>
            <a href="#members">Members</a>
            <a href="videos.html" target="_blank" rel="noopener">Preview Library</a>
            <a href="index.html">Website</a>
            <a class="logout" href="admin.php?logout=1">Logout</a>
            <form id="reset-dashboard-form" method="post" style="display:inline;margin-left:8px;">
                <input type="hidden" name="_reset_dashboard" value="1">
                <button type="button" id="reset-dashboard-button" class="btn-danger" style="font-size:13px;padding:6px 10px;">Reset Dashboard</button>
            </form>
        </div>
    </nav>

    <main class="page">
        <section class="hero">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Track website visits, contact form messages, registered members, and recent login activity from one place.</p>
            </div>
            <div class="today-strip">
                <div class="today-item">
                    <strong><?= e($stats['visits_today']) ?></strong>
                    <span>Visits Today</span>
                </div>
                <div class="today-item">
                    <strong><?= e($stats['contacts_today']) ?></strong>
                    <span>Contacts Today</span>
                </div>
                <div class="today-item">
                    <strong><?= e($stats['logins_today']) ?></strong>
                    <span>Logins Today</span>
                </div>
            </div>
        </section>

        <section class="metric-grid" aria-label="Statistics">
            <div class="metric-card">
                <p>Total Members</p>
                <strong><?= e($stats['members']) ?></strong>
            </div>
            <div class="metric-card">
                <p>Total Visits</p>
                <strong><?= e($stats['visits']) ?></strong>
            </div>
            <div class="metric-card">
                <p>Unique Visitors</p>
                <strong><?= e($stats['unique_visitors']) ?></strong>
            </div>
            <div class="metric-card">
                <p>Contact Messages</p>
                <strong><?= e($stats['contacts']) ?></strong>
            </div>
            <div class="metric-card">
                <p>New Contacts</p>
                <strong><?= e($stats['new_contacts']) ?></strong>
            </div>
            <div class="metric-card">
                <p>Paid Video Access</p>
                <strong><?= e($stats['paid_members']) ?></strong>
            </div>
        </section>

        <!-- Admin preview modal/player -->
        <div id="admin-preview" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);z-index:9999;"> 
            <div style="width:min(980px,95%);background:#0f172a;padding:12px;border-radius:8px;"> 
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"> 
                    <strong style="color:#fff;">Preview</strong>
                    <button id="admin-preview-close" type="button" style="background:transparent;border:0;color:#fff;font-size:18px;">✕</button>
                </div>
                <video id="admin-preview-player" controls style="width:100%;height:auto;background:#000;display:block;"></video>
            </div>
        </div>

        <section class="panel" id="videos">
            <div class="panel-header">
                <h2>Course Videos</h2>
                <span><?= e(count($courseVideos ?? [])) ?> uploaded</span>
            </div>
            <p class="video-note">
                Upload videos directly from your local disk (protected playback).
                Members only see videos after payment is verified in the <a class="btn-link" href="#members">Members &amp; Payment Access</a> section.
            </p>
            <?php if ($videoMessage): ?>
                <div class="alert-success video-upload-form"><?= e($videoMessage) ?></div>
            <?php endif; ?>
            <?php if ($videoError): ?>
                <div class="alert-error video-upload-form"><?= e($videoError) ?></div>
            <?php endif; ?>
            <form class="video-upload-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="upload_video" value="1">
                <div>
                    <label for="video_title">Video title</label>
                    <input type="text" id="video_title" name="video_title" required placeholder="e.g. Day 1 — Market Structure">
                </div>
                <div>
                    <label for="video_description">Description (optional)</label>
                    <textarea id="video_description" name="video_description" placeholder="Short summary for members"></textarea>
                </div>
                <!-- <div>
                    <label for="required_plan">Minimum plan required</label>
                    <select id="required_plan" name="required_plan" required>
                        <option value="weekly">20-Day Session only</option>
                        <option value="monthly">40-Day Session (also for 20-day users)</option>
                        <option value="lifetime">Lifetime (all paid members)</option>
                    </select>
                </div> -->
                <div>
                    <label for="video_file">Local video file</label>
                    <input type="file" id="video_file" name="video_file" accept="video/mp4,video/webm,video/quicktime,video/x-msvideo" required>
                </div>
                <button type="submit" class="btn-primary">Save Lesson</button>
            </form>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Plan</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courseVideos)): ?>
                            <tr><td colspan="7" class="muted">No videos uploaded yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($courseVideos as $video): ?>
                            <tr>
                                <td>
                                    <strong><?= e($video['title']) ?></strong>
                                    <?php if (!empty($video['description'])): ?>
                                        <div class="muted"><?= e($video['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status"><?= e(plan_label($video['required_plan'] ?? 'weekly')) ?></span></td>
                                <td class="clip"><?= e($video['original_name']) ?></td>
                                <td><?= e(round(((int) $video['file_size']) / (1024 * 1024), 1)) ?> MB</td>
                                <td class="muted"><?= e($video['uploaded_at']) ?></td>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <button type="button" class="btn-secondary preview-btn" data-id="<?= e($video['id']) ?>">Preview</button>
                                        <form method="post" onsubmit="return confirm('Delete this video permanently?');" style="margin:0;">
                                            <input type="hidden" name="delete_video_id" value="<?= e($video['id']) ?>">
                                            <button type="submit" class="btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="dashboard-grid">
            <div>
                <section class="panel" id="contacts">
                    <div class="panel-header">
                        <h2>Contact Form Messages</h2>
                        <span>Latest 10</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentContacts)): ?>
                                    <tr><td colspan="5" class="muted">No contact messages yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentContacts as $contact): ?>
                                    <tr>
                                        <td><?= e($contact['name']) ?></td>
                                        <td><?= e($contact['email']) ?></td>
                                        <td class="message-cell"><?= e($contact['message']) ?></td>
                                        <td><span class="status"><?= e($contact['status']) ?></span></td>
                                        <td class="muted"><?= e($contact['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel" id="visits">
                    <div class="panel-header">
                        <h2>Recent Website Visits</h2>
                        <span>Latest 12</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th>IP</th>
                                    <th>Referrer</th>
                                    <th>Visited</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentVisits)): ?>
                                    <tr><td colspan="4" class="muted">No visits tracked yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentVisits as $visit): ?>
                                    <tr>
                                        <td>
                                            <?= e($visit['page_title'] ?: 'Website page') ?>
                                            <div class="clip"><?= e($visit['page_url']) ?></div>
                                        </td>
                                        <td><?= e($visit['ip_address']) ?></td>
                                        <td class="clip"><?= e($visit['referrer'] ?: 'Direct') ?></td>
                                        <td class="muted"><?= e($visit['visited_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <aside>
                <section class="panel">
                    <div class="panel-header">
                        <h2>Popular Pages</h2>
                        <span>By visits</span>
                    </div>
                    <div class="page-list">
                        <?php if (empty($popularPages)): ?>
                            <p class="muted">No page data yet.</p>
                        <?php endif; ?>
                        <?php foreach ($popularPages as $page): ?>
                            <div class="page-row">
                                <p><?= e($page['page_url'] ?: 'Unknown page') ?></p>
                                <strong><?= e($page['total']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel" id="members">
                    <div class="panel-header">
                        <h2>Members &amp; Payment Access</h2>
                        <span>Verify payment → grant plan</span>
                    </div>
                    <p class="video-note">After the user pays (QR on payment page), confirm payment and grant the correct plan: <strong>20 days</strong>, <strong>40 days</strong>, or <strong>Lifetime</strong>. Their pending selection is shown if they chose a plan at checkout.</p>
                    <?php if ($memberAccessMessage): ?>
                        <div class="alert-success video-upload-form"><?= e($memberAccessMessage) ?></div>
                    <?php endif; ?>
                    <?php if ($memberAccessError): ?>
                        <div class="alert-error video-upload-form"><?= e($memberAccessError) ?></div>
                    <?php endif; ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Plan / Expiry</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentMembers)): ?>
                                    <tr><td colspan="4" class="muted">No members yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentMembers as $member): ?>
                                    <?php
                                        $hasAccess = !empty($member['has_video_access']);
                                        $pendingPlan = is_valid_plan_type($member['pending_plan'] ?? '') ? $member['pending_plan'] : null;
                                        $activePlan = $member['active_plan'] ?? null;
                                        $daysLeft = null;
                                        if ($hasAccess && !empty($member['active_end_date'])) {
                                            $daysLeft = max(0, (int) ceil((strtotime($member['active_end_date']) - time()) / 86400));
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <?= e($member['email']) ?>
                                            <div class="muted"><?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: 'No profile details') ?></div>
                                            <div class="muted">Joined <?= e($member['created_at']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($hasAccess && $activePlan): ?>
                                                <strong><?= e(plan_label($activePlan)) ?></strong>
                                                <?php if ($activePlan === 'lifetime'): ?>
                                                    <div class="muted">Never expires</div>
                                                <?php elseif ($daysLeft !== null): ?>
                                                    <div class="muted"><?= e($daysLeft) ?> day(s) left</div>
                                                    <div class="muted">Until <?= e(date('d M Y', strtotime($member['active_end_date']))) ?></div>
                                                <?php endif; ?>
                                            <?php elseif ($pendingPlan): ?>
                                                <div class="muted">Selected: <?= e(plan_label($pendingPlan)) ?></div>
                                                <div class="muted">Awaiting payment verify</div>
                                            <?php else: ?>
                                                <span class="muted">No plan selected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="access-badge <?= $hasAccess ? 'paid' : 'unpaid' ?>">
                                                <?= $hasAccess ? 'Active' : 'Not paid' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($hasAccess): ?>
                                                <form method="post" class="member-actions" onsubmit="return confirm('Revoke video access for this member?');">
                                                    <input type="hidden" name="revoke_video_access" value="1">
                                                    <input type="hidden" name="user_id" value="<?= e($member['id']) ?>">
                                                    <button type="submit" class="btn-danger">Revoke</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" class="member-actions">
                                                    <input type="hidden" name="grant_video_access" value="1">
                                                    <input type="hidden" name="user_id" value="<?= e($member['id']) ?>">
                                                    <select name="plan_type" aria-label="Plan type">
                                                        <option value="weekly" <?= $pendingPlan === 'weekly' ? 'selected' : '' ?>>20-Day (12K)</option>
                                                        <option value="monthly" <?= $pendingPlan === 'monthly' ? 'selected' : '' ?>>40-Day (49K)</option>
                                                        <option value="lifetime" <?= $pendingPlan === 'lifetime' ? 'selected' : '' ?>>Lifetime (99K)</option>
                                                    </select>
                                                    <button type="submit" class="btn-success">Verify &amp; Grant</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Login Activity</h2>
                        <span>Latest 10</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>IP</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentLogins)): ?>
                                    <tr><td colspan="3" class="muted">No login history yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentLogins as $login): ?>
                                    <tr>
                                        <td><?= e($login['email']) ?></td>
                                        <td><?= e($login['ip_address']) ?></td>
                                        <td class="muted"><?= e($login['login_time']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </aside>
        </div>
    </main>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('reset-dashboard-button');
    if (!btn) return;

    btn.addEventListener('click', function(){
        if (!confirm('This will wipe recent dashboard data (messages, visits, login logs, email logs). Are you sure?')) return;
        btn.disabled = true;
        btn.textContent = 'Resetting...';

        var formData = new FormData();
        formData.append('confirm', 'yes');

        fetch('backend/reset_dashboard.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function(res){
            return res.text().then(function(text) {
                if (!res.ok) {
                    throw new Error(res.status + ' ' + res.statusText + ': ' + text);
                }
                if (!text) {
                    throw new Error('Empty response from server');
                }
                try {
                    return JSON.parse(text);
                } catch (parseError) {
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        }).then(function(json){
            if (json && json.success) {
                var msg = 'Dashboard reset completed.';
                if (json.cleared && json.cleared.length > 0) {
                    msg += '\nCleared tables: ' + json.cleared.join(', ');
                }
                alert(msg + '\nThe page will reload.');
                window.location.reload();
            } else {
                var errorMsg = 'Reset failed: ' + (json && json.message ? json.message : 'Unknown error');
                if (json && json.errors && json.errors.length > 0) {
                    errorMsg += '\n\nErrors:\n' + json.errors.join('\n');
                }
                if (json && json.cleared && json.cleared.length > 0) {
                    errorMsg += '\n\nPartially cleared: ' + json.cleared.join(', ');
                }
                alert(errorMsg);
                btn.disabled = false;
                btn.textContent = 'Reset Dashboard';
            }
        }).catch(function(err){
            alert('Reset failed: ' + (err.message || err));
            btn.disabled = false;
            btn.textContent = 'Reset Dashboard';
        });
    });
});
</script>

</body>
    <script>
        (function(){
            function $(sel, ctx){ return (ctx||document).querySelector(sel); }
            function $all(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

            const previewModal = $('#admin-preview');
            const previewPlayer = $('#admin-preview-player');
            const previewClose = $('#admin-preview-close');

            $all('.preview-btn').forEach(btn => {
                btn.addEventListener('click', function(){
                    const id = this.dataset.id;
                    if (!id) return;
                    // stream via protected endpoint (admin session must be active)
                    previewPlayer.pause();
                    previewPlayer.removeAttribute('src');
                    previewPlayer.src = 'backend/stream_video.php?id=' + encodeURIComponent(id);
                    previewModal.style.display = 'flex';
                    previewPlayer.load();
                    previewPlayer.play().catch(()=>{});
                });
            });

            previewClose.addEventListener('click', function(){
                previewPlayer.pause();
                previewPlayer.removeAttribute('src');
                previewPlayer.load();
                previewModal.style.display = 'none';
            });

            // close on background click
            previewModal.addEventListener('click', function(e){
                if (e.target === previewModal) previewClose.click();
            });
        })();
    </script>
</html>
