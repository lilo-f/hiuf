<?php
// ==============================================
// CONFIGURAÇÕES INICIAIS
// ==============================================
// Linha que deve ser adicionada
session_start();
// Linha que deve ser adicionada para simular um login
// Remova este bloco de código depois que terminar de testar
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // ID do usuário que será usado para o carrinho
}
// Linhas para exibir erros PHP (mantenha essas linhas)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_reporting(0);
if (ob_get_length()) ob_clean();

$allowedOrigins = [
    "http://127.0.0.1:5500",
    "http://127.0.0.1:5501", 
    "http://127.0.0.1:5502",
    "http://localhost"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


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


function sendError($message, $code = 400) {
    http_response_code($code);
    die(json_encode(['success' => false, 'message' => $message]));
}

// Inclui as funções auxiliares
require_once 'utils.php';

// Conexão com o banco de dados
require_once 'conexao.php';

// NOVO CÓDIGO: Lidar com requisição GET para buscar dados da sessão
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_user_session') {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            unset($user['password']);
            $user['isAdmin'] = (bool)($user['isAdmin'] ?? false);
            http_response_code(200);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Nenhum usuário logado']);
    }
    exit();
}

// Lógica original para requisições POST
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados JSON inválidos']);
    exit();
}

if (empty($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetro "action" não especificado']);
    exit();
}

try {
    $response = [];
    
    switch ($input['action']) {
        case 'register':
            $requiredFields = ['firstName', 'lastName', 'email', 'phone', 'cpf', 'password'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("O campo {$field} é obrigatório");
                }
            }

            $firstName = sanitizeInput($input['firstName']);
            $lastName = sanitizeInput($input['lastName']);
            $email = sanitizeInput($input['email']);
            $phone = preg_replace('/[^0-9]/', '', $input['phone']);
            $cpf = preg_replace('/[^0-9]/', '', $input['cpf']);
            $password = $input['password'];

            if (!validateEmail($email)) {
                throw new Exception("E-mail inválido");
            }
            if (!validateCPF($cpf)) {
                throw new Exception("CPF inválido");
            }
            if (strlen($password) < 6) {
                throw new Exception("A senha deve ter pelo menos 6 caracteres");
            }

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR cpf = ?");
            $stmt->execute([$email, $cpf]);
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("E-mail ou CPF já cadastrado");
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, cpf, password) VALUES (?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$firstName, $lastName, $email, $phone, $cpf, $passwordHash]);

            if (!$success || $stmt->rowCount() === 0) {
                throw new Exception("Erro ao registrar usuário no banco de dados");
            }

            $userId = $conn->lastInsertId();
            $response = ['success' => true, 'message' => 'Usuário registrado com sucesso', 'userId' => $userId];
            http_response_code(201);
            echo json_encode($response);
            exit;
        case 'login':
            if (empty($input['email']) || empty($input['password'])) {
                throw new Exception("E-mail e senha são obrigatórios");
            }
            $email = sanitizeInput($input['email']);
            $password = $input['password'];

            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Credenciais inválidas");
            }

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!password_verify($password, $user['password'])) {
                throw new Exception("Credenciais inválidas");
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['isAdmin'];
            error_log("Login realizado - ID: {$user['id']}, Admin: {$user['isAdmin']}");

            unset($user['password']);
            
            if (!empty($user['avatar'])) {
                $user['avatarUrl'] = $user['avatar'];
            } else {
                $user['avatar'] = null;
                $user['avatarUrl'] = null;
            }
            
            $user['points'] = $user['loyalty_points'] ?? 0;
            $user['isAdmin'] = (bool)($user['isAdmin'] ?? false);
            
$response = [
    'success' => true,
    'message' => 'Login realizado com sucesso',
    'user' => [
        'id' => $user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'avatar' => $user['avatar'],
        'isAdmin' => (bool)$user['isAdmin'],
        'points' => $user['loyalty_points'] ?? 0,
        'loyalty_points' => $user['loyalty_points'] ?? 0
    ]
];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['is_admin'] = $user['isAdmin'];
            break;

            case 'get_user_data':
    if (empty($input['userId'])) {
        throw new Exception("ID do usuário não fornecido");
    }

    // Verifica se o usuário logado é o mesmo que está sendo consultado
    if ($_SESSION['user_id'] != $input['userId']) {
        throw new Exception("Não autorizado a ver estes dados");
    }

    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, avatar, loyalty_points FROM users WHERE id = ?");
    $stmt->execute([$input['userId']]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("Usuário não encontrado");
    }

    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'points' => $user['loyalty_points'],
            'loyalty_points' => $user['loyalty_points']
        ]
    ];
    break;

            case 'delete_account':
    if (empty($input['userId'])) {
        throw new Exception("ID do usuário não fornecido");
    }

    // Verifica se o usuário logado é o mesmo que está sendo deletado
    if ($_SESSION['user_id'] != $input['userId']) {
        throw new Exception("Não autorizado a deletar esta conta");
    }

    // Inicia uma transação para garantir que todas as operações sejam completadas
    $conn->beginTransaction();

    try {
        // Primeiro deleta os dados relacionados (agendamentos, etc)
        $stmt = $conn->prepare("DELETE FROM appointments WHERE user_id = ?");
        $stmt->execute([$input['userId']]);

        // Depois deleta o usuário
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$input['userId']]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Usuário não encontrado");
        }

        // Se tudo ocorrer bem, confirma a transação
        $conn->commit();

        // Destrói a sessão
        session_destroy();

        $response = [
            'success' => true,
            'message' => 'Conta deletada com sucesso'
        ];
    } catch (Exception $e) {
        // Em caso de erro, reverte a transação
        $conn->rollBack();
        throw $e;
    }
    break;
      case 'add_to_cart':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para adicionar produtos ao carrinho.");
        }

        $productId = $input['product_id'] ?? null;
        $quantity = $input['quantity'] ?? 1;
        $userId = $_SESSION['user_id'];

        if (empty($productId) || !is_numeric($productId) || $quantity <= 0) {
            throw new Exception("Dados do produto inválidos.");
        }

        $stmt = $conn->prepare("SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingItem) {
            $newQuantity = $existingItem['quantity'] + $quantity;
            $updateStmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $updateStmt->execute([$newQuantity, $userId, $productId]);
            $response = ['success' => true, 'message' => "Quantidade do produto atualizada no carrinho."];
        } else {
            $insertStmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $productId, $quantity]);
            $response = ['success' => true, 'message' => "Produto adicionado ao carrinho."];
        }
        break;
    
case 'get_cart_items':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para ver o carrinho.");
        }
    
        $userId = $_SESSION['user_id'];
    
        // Conecta ao banco de dados e busca os itens do carrinho com os detalhes do produto
        $stmt = $conn->prepare("
            SELECT 
                ci.id, 
                ci.quantity, 
                ci.product_id, 
                p.name, 
                p.price, 
                p.image_url 
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.user_id = ?
        ");
        $stmt->execute([$userId]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $response = [
            'success' => true,
            'cart_items' => $cartItems
        ];
        break;
        case 'update_quantity':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para atualizar o carrinho.");
        }
        $cartItemId = $input['cart_item_id'] ?? null;
        $quantity = $input['quantity'] ?? null;
        $userId = $_SESSION['user_id'];
    
        if (empty($cartItemId) || !is_numeric($cartItemId) || empty($quantity) || !is_numeric($quantity)) {
            throw new Exception("Dados de atualização inválidos.");
        }
    
        if ($quantity > 0) {
            $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cartItemId, $userId]);
            $response = ['success' => true, 'message' => "Quantidade do item atualizada."];
        } else {
            // Se a quantidade for 0 ou menos, remove o item
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartItemId, $userId]);
            $response = ['success' => true, 'message' => "Item removido do carrinho."];
        }
        break;

    case 'remove_item':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para remover itens do carrinho.");
        }
        $cartItemId = $input['cart_item_id'] ?? null;
        $userId = $_SESSION['user_id'];
    
        if (empty($cartItemId) || !is_numeric($cartItemId)) {
            throw new Exception("Dados de remoção inválidos.");
        }
    
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartItemId, $userId]);
    
        $response = ['success' => true, 'message' => "Item removido do carrinho."];
        break;
        case 'add_to_favorites':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para favoritar produtos.");
        }
        $productId = $input['product_id'] ?? null;
        $userId = $_SESSION['user_id'];
    
        if (empty($productId) || !is_numeric($productId)) {
            throw new Exception("ID do produto inválido.");
        }
    
        // Verifica se o produto já foi favoritado para evitar duplicatas
        $stmt = $conn->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        if ($stmt->fetchColumn() > 0) {
            $response = ['success' => false, 'message' => "Produto já está nos favoritos."];
            break;
        }
    
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        $response = ['success' => true, 'message' => "Produto adicionado aos favoritos."];
        break;

    case 'remove_from_favorites':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para remover favoritos.");
        }
        $productId = $input['product_id'] ?? null;
        $userId = $_SESSION['user_id'];
    
        if (empty($productId) || !is_numeric($productId)) {
            throw new Exception("ID do produto inválido.");
        }
    
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $response = ['success' => true, 'message' => "Produto removido dos favoritos."];
        break;

    case 'get_favorites':
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Você precisa estar logado para ver os favoritos.");
        }
        $userId = $_SESSION['user_id'];
    
        $stmt = $conn->prepare("
            SELECT p.*
            FROM favorites f
            JOIN products p ON f.product_id = p.id
            WHERE f.user_id = ?
        ");
        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $response = [
            'success' => true,
            'favorites' => $favorites
        ];
        break;

    case 'get_user_appointments':
    if (empty($input['userId'])) {
        throw new Exception("ID do usuário não fornecido");
    }

    // Verifica se o usuário logado é o mesmo que está sendo consultado
    if ($_SESSION['user_id'] != $input['userId']) {
        throw new Exception("Não autorizado a ver estes agendamentos");
    }

    $stmt = $conn->prepare("
        SELECT a.*, u.first_name as artist_name, u.avatar as artist_image 
        FROM appointments a
        LEFT JOIN users u ON a.artist_id = u.id
        WHERE a.user_id = ?
        ORDER BY a.preferred_date1, a.preferred_time1
    ");
    $stmt->execute([$input['userId']]);
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'appointments' => $appointments
    ];
    break;
        default:
            throw new Exception("Ação inválida");
    }

    http_response_code(200);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}

$conn = null;