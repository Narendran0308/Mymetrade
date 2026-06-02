<?php
require_once __DIR__ . '/video_lib.php';

header('Content-Type: application/json; charset=UTF-8');

echo json_encode([
    'success' => true,
    'plans' => plan_catalog_for_client(),
    'note' => 'All plans include daily live trading, 24/7 support, and full access to our proven systems.',
]);
