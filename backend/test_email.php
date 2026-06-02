<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email.php';

$testTo = $_GET['to'] ?? '';
if ($testTo === '' || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Add ?to=your@email.com to test SMTP',
    ]);
    exit;
}

global $emailSender;
$sent = $emailSender->sendPassword($testTo, 'TestPass123!', 'SMTP Test');

echo json_encode([
    'success' => $sent,
    'message' => $sent ? 'Email sent' : 'Email failed',
    'error' => $emailSender->getLastError(),
], JSON_PRETTY_PRINT);
