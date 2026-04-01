<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_alert(int $monitorId, string $direction): void {
    $monitor = db_row(
        'SELECT m.*, c.name AS company_name, c.id AS company_id
         FROM monitors m JOIN companies c ON c.id = m.company_id
         WHERE m.id = ?',
        [$monitorId]
    );
    if (!$monitor) return;

    $smtp = db_row('SELECT * FROM smtp_configs WHERE company_id = ?', [$monitor['company_id']]);
    if (!$smtp || empty($smtp['host']) || empty($smtp['to_addr'])) return;

    require_once __DIR__ . '/../vendor/autoload.php';

    $status  = strtoupper($direction);
    $subject = "[monD] {$monitor['name']} is {$status}";
    $body    = "Monitor:  {$monitor['name']}\n"
             . "Company:  {$monitor['company_name']}\n"
             . "Status:   {$status}\n"
             . "Time:     " . date('Y-m-d H:i:s T') . "\n"
             . "Type:     " . strtoupper($monitor['type']) . "\n";

    $port = (int) ($smtp['port'] ?? 587);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->Port       = $port;
        $mail->SMTPAuth   = !empty($smtp['username']);
        $mail->Username   = (string) ($smtp['username'] ?? '');
        $mail->Password   = (string) ($smtp['password'] ?? '');
        $mail->SMTPSecure = $port === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(
            $smtp['from_addr'] ?: $smtp['username'],
            'monD'
        );

        foreach (array_map('trim', explode(',', $smtp['to_addr'])) as $recipient) {
            if ($recipient) $mail->addAddress($recipient);
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
    } catch (Exception) {
        // Silent — alert failure should not break the check cycle
    }
}
