<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==============================================
// CONFIGURAÇÕES INICIAIS E INCLUSÕES
// ==============================================

require_once 'utils.php';
require_once 'conexao.php'; 

ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', true);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
session_start();

// CONFIGURAÇÕES DO GOOGLE
const CLIENT_ID = '970818424404-j46cnm3j91kv14ba5srfdmsjcn0bfir2.apps.googleusercontent.com';
const CLIENT_SECRET = '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';
const REDIRECT_URI = 'http://localhost/trabalhofinal/finalmente/api/google-auth.php'; 

// ==============================================
// LÓGICA DE AUTENTICAÇÃO DO GOOGLE
// ==============================================

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $token_exchange_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $code,
        'client_id' => CLIENT_ID,
        'client_secret' => CLIENT_SECRET,
        'redirect_uri' => REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_exchange_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    $token_response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($token_response['access_token'])) {
        $access_token = $token_response['access_token'];

        $userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userinfo_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token
        ]);
        $user_info = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($user_info['email'])) {
            $email = sanitizeInput($user_info['email']);
            $firstName = sanitizeInput($user_info['given_name']);
            $lastName = sanitizeInput($user_info['family_name']);
            $avatar = sanitizeInput($user_info['picture']);

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                $_SESSION['user_id'] = $existing_user['id'];
                $_SESSION['is_admin'] = $existing_user['isAdmin'];
                header('Location: http://localhost/trabalhofinal/finalmente/pages/user.html');
                exit;
            } else {
                // Usuário não existe, cria uma nova conta com CPF e telefone nulos
                $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, avatar, cpf, phone) VALUES (?, ?, ?, ?, NULL, NULL)");
                $stmt->execute([$firstName, $lastName, $email, $avatar]);
                
                $new_user_id = $conn->lastInsertId();
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['is_admin'] = 0;
                header('Location: http://localhost/trabalhofinal/finalmente/pages/user.html');
                exit;
            }
        }
    }
}

header('Location: http://localhost/trabalhofinal/finalmente/pages/login.html');
exit;
?>