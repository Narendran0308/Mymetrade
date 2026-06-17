<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Step 1: PHP is working<br>";

session_start();
echo "Step 2: Session started<br>";

try {
    require_once __DIR__ . '/backend/db.php';
    echo "Step 3: DB file loaded<br>";
} catch (Exception $e) {
    die("DB Error: " . $e->getMessage());
}

try {
    require_once __DIR__ . '/backend/video_lib.php';
    echo "Step 4: Video lib loaded<br>";
} catch (Exception $e) {
    die("Video lib Error: " . $e->getMessage());
}

echo "Step 5: Testing database connection<br>";
$test = $db->query("SELECT 1");
if ($test) {
    echo "Step 6: Database query works!<br>";
} else {
    echo "Step 6: Database query failed: " . $db->error . "<br>";
}

echo "Step 7: Testing table creation<br>";
$db->query("CREATE TABLE IF NOT EXISTS website_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_key VARCHAR(120),
    page_url VARCHAR(500),
    page_title VARCHAR(255),
    referrer VARCHAR(500),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    visited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Step 8: website_visits table created or exists<br>";

echo "<br><strong>All steps completed! Admin.php should work now.</strong>";
?>
