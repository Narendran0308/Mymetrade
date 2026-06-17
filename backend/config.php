<?php

// Database Configuration

define('DB_HOST', 'localhost:3307');

define('DB_USER', 'root');  // Change if different

define('DB_PASS', '');      // Enter your MySQL password

define('DB_NAME', 'mymetrades_db');



// Gmail Configuration

define('GMAIL_EMAIL', 'narendrans0308@gmail.com');  // Your Gmail address

define('GMAIL_PASSWORD', 'ttay kbwg qyqn kujh');  // Gmail App Password (NOT regular password)



// Email Configuration

define('MAIL_FROM', 'noreply@mymetrades.com');

define('MAIL_FROM_NAME', 'Mymetrades');



// App Configuration

define('APP_NAME', 'Mymetrades');

define('APP_URL', 'http://localhost/Mymetrades-main/');



// VdoCipher TRIAL: use dev API. Dashboard → Settings → copy "API Secret" (not the video ID).

define('VDOCIPHER_API_SECRET', 'awVsqDPHQJzjFpUiEcTadFbBv4f3Ur0EEcZeSnRzY6C70ktH9Z9WD1iNia2NhiWY');

define('VDOCIPHER_API_BASE', 'https://dev.vdocipher.com/api');

// Your uploaded video ID from VdoCipher dashboard (Videos → click video → copy ID)

define('VDOCIPHER_TRIAL_VIDEO_ID', 'c1224d2c2f3a4832b6bd8146c8541d83');



// Password Configuration

define('PASSWORD_LENGTH', 12);



// Function to generate random password

function generatePassword($length = PASSWORD_LENGTH) {

    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';

    $password = '';

    for ($i = 0; $i < $length; $i++) {

        $password .= $characters[rand(0, strlen($characters) - 1)];

    }

    return $password;

}



// CORS Headers

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type');

header('Content-Type: application/json');



// Handle preflight requests (CLI-safe)

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    exit(0);

}

