<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

echo json_encode(['test' => 'Backend is working']);
?>
