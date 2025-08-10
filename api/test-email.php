<?php
// Script de teste simplificado para envio de e-mail

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ravenstudio.ink@gmail.com';
    $mail->Password = 'siyv uxdh krvf mnna'; // USE A SENHA DE APP AQUI
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('ravenstudio.ink@gmail.com', 'Raven Studio');
    $mail->addAddress('larafabianribeiro@icloud.com'); // Substitua pelo seu e-mail

    $mail->isHTML(true);
    $mail->Subject = "Teste de E-mail PHPMailer";
    $mail->Body = "Olá! Este é um e-mail de teste enviado com sucesso.";

    $mail->send();
    echo 'E-mail de teste enviado com sucesso!';
} catch (Exception $e) {
    echo "Erro ao enviar e-mail: {$mail->ErrorInfo}";
}
?>