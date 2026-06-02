<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['login_time'], $_SESSION['signup_pending_details']);

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully.',
]);
