<?php
/**
 * Auger — PHPMailer wrapper
 * Loads SMTP config from admin/data/mail-config.json (protected by .htaccess)
 */
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function load_mail_config() {
    $file = __DIR__ . '/../data/mail-config.json';
    if (!file_exists($file)) return null;
    $cfg = json_decode(file_get_contents($file), true);
    return is_array($cfg) ? $cfg : null;
}

function mail_configured() {
    $cfg = load_mail_config();
    return $cfg && !empty($cfg['smtp_host']) && !empty($cfg['smtp_user']) && !empty($cfg['smtp_pass']);
}

function send_mail($to, $subject, $html, $replyTo = null) {
    $cfg = load_mail_config();
    if (!$cfg) return ['success' => false, 'error' => 'Mail config missing'];

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;        // temporary — remove after debugging
    $mail->Debugoutput = 'error_log';
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['smtp_user'];
        $mail->Password   = $cfg['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['smtp_port'] ?? 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($cfg['smtp_user'], $cfg['from_name'] ?? 'Auger Renovations');
        $mail->addAddress($to);
        if ($replyTo) $mail->addReplyTo($replyTo);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags($html);

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}