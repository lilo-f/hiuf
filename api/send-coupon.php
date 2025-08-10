<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Inclua os arquivos necessários do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

// Definir o caminho para o arquivo que armazenará os e-mails
$emailsFile = 'emails_used.txt';

// Apenas aceita requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['email']) || !isset($data['coupon']) || !isset($data['discount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$coupon = htmlspecialchars($data['coupon']);
$discount = htmlspecialchars($data['discount']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Endereço de e-mail inválido.']);
    exit;
}

// ----------------------------------------------------
// Lógica para verificar se o e-mail já foi usado
// ----------------------------------------------------
if (file_exists($emailsFile)) {
    $emails_usados = file($emailsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (in_array($email, $emails_usados)) {
        http_response_code(409); // Código de status para Conflito
        echo json_encode(['success' => false, 'message' => 'Este e-mail já foi usado para resgatar um cupom.']);
        exit;
    }
}
// ----------------------------------------------------

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_OFF; // Mude para DEBUG_SERVER para depuração, se necessário
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ravenstudio.ink@gmail.com';
    $mail->Password = 'siyv uxdh krvf mnna'; // SUA SENHA DE APP DE 16 DÍGITOS
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('ravenstudio.ink@gmail.com', 'Raven Studio');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "Seu Cupom de Desconto Raven Studio!";
    $mail->Body = "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Seu Cupom de Desconto</title>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
            .header { text-align: center; border-bottom: 2px solid #6a0dad; padding-bottom: 10px; }
            .content { padding: 20px 0; text-align: center; }
            .coupon-code { background-color: #6a0dad; color: #fff; padding: 15px 30px; font-size: 24px; font-weight: bold; display: inline-block; border-radius: 5px; margin: 20px 0; letter-spacing: 2px; }
            .footer { text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eee; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Raven Studio</h1>
            </div>
            <div class='content'>
                <h2>Parabéns!</h2>
                <p>Você ganhou um cupom de desconto de <b>{$discount}%</b>.</p>
                <p>Use o código abaixo para resgatar seu desconto:</p>
                <div class='coupon-code'>{$coupon}</div>
                <p>Aproveite!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date("Y") . " Raven Studio. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>";

    $mail->send();

    // Se o e-mail foi enviado com sucesso, adicione o e-mail ao arquivo
    file_put_contents($emailsFile, $email . PHP_EOL, FILE_APPEND);
    
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Seu cupom foi enviado para o seu e-mail com sucesso!']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Falha ao enviar o e-mail. Erro: {$mail->ErrorInfo}"]);
}
?>