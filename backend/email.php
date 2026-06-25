<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $host = 'smtp.gmail.com';
    private $port = 587;
    private $email;
    private $password;
    private $lastError = '';

    public function __construct() {
        $this->email = GMAIL_EMAIL;
        // Gmail app passwords are shown with spaces but must be used without them
        $this->password = preg_replace('/\s+/', '', (string) GMAIL_PASSWORD);
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function sendPassword($recipientEmail, $password, $userName = '') {
        $subject = 'Your ' . APP_NAME . ' Login Password';
        
        $htmlBody = "
        <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        background-color: #f4f4f4;
                    }
                    .container {
                        background-color: #ffffff;
                        margin: 20px auto;
                        padding: 30px;
                        border-radius: 10px;
                        max-width: 600px;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    }
                    .header {
                        color: #00f5a0;
                        border-bottom: 3px solid #00f5a0;
                        padding-bottom: 20px;
                        margin-bottom: 20px;
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 28px;
                    }
                    .content {
                        color: #333;
                        line-height: 1.6;
                    }
                    .password-box {
                        background-color: #f0f0f0;
                        padding: 15px;
                        border-left: 4px solid #00f5a0;
                        margin: 20px 0;
                        font-size: 18px;
                        font-weight: bold;
                        font-family: 'Courier New', monospace;
                        color: #00f5a0;
                        word-break: break-all;
                    }
                    .footer {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #ddd;
                        color: #666;
                        font-size: 12px;
                    }
                    .warning {
                        background-color: #fff3cd;
                        border: 1px solid #ffc107;
                        color: #856404;
                        padding: 12px;
                        border-radius: 4px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Welcome to " . APP_NAME . "</h1>
                    </div>
                    <div class='content'>
                        <p>Hello " . htmlspecialchars($userName) . ",</p>
                        <p>Your temporary login password has been generated:</p>
                        
                        <div class='password-box'>
                            " . htmlspecialchars($password) . "
                        </div>
                        
                        <div class='warning'>
                            <strong>Security Note:</strong> Do not share this password with anyone. Each login generates a new password.
                        </div>
                        
                        <p>Use this password to complete your login or signup process on our platform.</p>
                        
                        <p style='color: #666; font-size: 14px;'>
                            If you did not request this password, please ignore this email or contact our support team.
                        </p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                        <p>This is an automated email. Please do not reply.</p>
                    </div>
                </div>
            </body>
        </html>
        ";

        return $this->sendEmail($recipientEmail, $subject, $htmlBody);
    }

    private function sendEmail($to, $subject, $htmlBody) {
        // Using PHP mail function with configuration
        // For production, use PHPMailer library
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">" . "\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

        // Try to send email using mail function
        // Note: This may not work with Gmail without SMTP configuration
        // For production, use PHPMailer with SMTP
        
        return $this->sendViaPhpMailer($to, $subject, $htmlBody);
    }

    private function sendViaPhpMailer($to, $subject, $htmlBody) {
        $this->lastError = '';

        if ($this->email === '' || $this->password === '') {
            $this->lastError = 'Gmail SMTP credentials are not configured in backend/config.php';
            error_log($this->lastError);
            return false;
        }

        try {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $this->email;
            $mail->Password = $this->password;
            $mail->Timeout = 30;

            // XAMPP on Windows often lacks CA bundle for TLS
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];

            $mail->setFrom($this->email, APP_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->msgHTML($htmlBody);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer debug [$level]: $str");
            };

            if ($mail->send()) {
                return true;
            }

            $this->lastError = $mail->ErrorInfo ?: 'Unknown mail error';
            error_log('Email send failed: ' . $this->lastError);
            return false;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('PHPMailer Error: ' . $this->lastError);
            return false;
        }
    }
}

$emailSender = new EmailSender();
?>
