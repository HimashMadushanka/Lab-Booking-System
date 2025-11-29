<?php
// send_email.php
// Usage: require 'send_email.php'; send_email($to, $subject, $html_body, $plain_text_body = '');

function send_email($to, $subject, $html_body, $plain_text_body = '') {
    // Config - change these to match your SMTP provider if using SMTP
    $config = [
        'use_smtp' => true,            // set to true to use SMTP; set false to force PHP mail()
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_user' => 'your-smtp-username@example.com',
        'smtp_pass' => 'your-smtp-password',
        'smtp_secure' => 'tls',        // 'ssl' or 'tls' or ''
        'from_email' => 'no-reply@example.com',
        'from_name' => 'Lab Booking System',
        'reply_to' => 'support@example.com'
    ];

    // If PHPMailer available (recommended)
    if ($config['use_smtp'] && file_exists(__DIR__ . '/vendor/autoload.php')) {
        // Use PHPMailer via Composer autoload
        require_once __DIR__ . '/vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['smtp_user'];
            $mail->Password = $config['smtp_pass'];
            $mail->SMTPSecure = $config['smtp_secure'];
            $mail->Port = $config['smtp_port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);
            if (!empty($config['reply_to'])) $mail->addReplyTo($config['reply_to']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html_body;
            $mail->AltBody = $plain_text_body ?: strip_tags($html_body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer send failed: " . $mail->ErrorInfo);
            // fallback to PHP mail() below
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['from_name']} <{$config['from_email']}>\r\n";
    if (!empty($config['reply_to'])) {
        $headers .= "Reply-To: {$config['reply_to']}\r\n";
    }

    return mail($to, $subject, $html_body, $headers);
}
