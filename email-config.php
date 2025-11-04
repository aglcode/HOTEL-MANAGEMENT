<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendBookingEmailSMTP($email, $guestName, $token, $bookingDetails) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // or your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';
        $mail->Password   = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@gitarraapartelle.com', 'Gitarra Apartelle');
        $mail->addAddress($email, $guestName);
        $mail->addReplyTo('info@gitarraapartelle.com', 'Gitarra Apartelle');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "🏨 Booking Confirmation - Gitarra Apartelle (Token: $token)";
        $mail->Body    = $message; // Use the HTML message from above
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: {$mail->ErrorInfo}");
        return false;
    }
}
?>