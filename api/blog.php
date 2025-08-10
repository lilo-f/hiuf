
<?php
// blog.php - API para o sistema de blog
// Configuração de erro detalhada (apenas para desenvolvimento)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Log de erros
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents('php_errors.log', 
        date('Y-m-d H:i:s') . " - [$errno] $errstr in $errfile on line $errline\n", 
        FILE_APPEND
    );
});

// Log de exceções
set_exception_handler(function($e) {
    file_put_contents('php_exceptions.log', 
        date('Y-m-d H:i:s') . " - " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", 
        FILE_APPEND
    );
    sendError("Erro interno do servidor", 500);
});
// Configurações iniciais
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configuração de CORS
$allowedOrigins = [
    "http://localhost",
    "http://127.0.0.1",
    "http://127.0.0.1:5500",
    "http://127.0.0.1:5501",
    "http://127.0.0.1:5502"
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
}

header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");

// Responder imediatamente para requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Funções auxiliares
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

function getAuthorizationToken() {
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        return null;
    }
    
    $authHeader = $headers['Authorization'];
    
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return null;
    }
    
    return $matches[1];
}

function validateUser($conn, $userId) {
    $stmt = $conn->prepare("SELECT id, isAdmin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Conexão com o banco de dados
require_once 'conexao.php';

try {
    // Verificar se a conexão foi estabelecida
    if (!$conn) {
        sendError("Erro na conexão com o banco de dados", 500);
    }

    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError("Dados JSON inválidos");
    }

    if (empty($input['action'])) {
        sendError("Ação não especificada");
    }

    // Processar ações
    switch ($input['action']) {
        case 'get_posts':
            // Parâmetros de paginação
            $page = isset($input['page']) ? (int)$input['page'] : 1;
            $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
            
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 50) $limit = 10;
            
            $offset = ($page - 1) * $limit;

            // Consulta para obter posts
            $stmt = $conn->prepare("
                SELECT 
                    p.id, 
                    p.user_id, 
                    p.content, 
                    p.image, 
                    p.created_at,
                    u.first_name, 
                    u.last_name, 
                    u.avatar,
                    (SELECT COUNT(*) FROM blog_likes WHERE post_id = p.id) AS likes_count,
                    (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id) AS comments_count
                FROM blog_posts p
                JOIN users u ON p.user_id = u.id
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verificar se há mais posts
            $stmt = $conn->prepare("SELECT COUNT(*) FROM blog_posts");
            $stmt->execute();
            $totalPosts = $stmt->fetchColumn();
            $hasMore = ($totalPosts > ($offset + $limit));
            
            sendResponse([
                'success' => true,
                'posts' => $posts,
                'hasMore' => $hasMore,
                'total' => $totalPosts
            ]);
            break;

case 'create_post':
    // Verificação robusta de autenticação
    $userId = getAuthorizationToken();
    if (!$userId) {
        sendError("Token de autenticação não fornecido", 401);
    }

    // Verificar se usuário é admin
    $stmt = $conn->prepare("SELECT isAdmin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['isAdmin']) {
        sendError("Apenas administradores podem criar posts", 403);
    }

    // Validação dos dados
    if (!isset($input['content']) && !isset($input['image'])) {
        sendError("O post deve conter texto ou imagem");
    }

    $content = isset($input['content']) ? trim($input['content']) : null;
    $image = isset($input['image']) ? trim($input['image']) : null;

    // Validação adicional
    if ($content === "" && $image === null) {
        sendError("O post deve conter texto ou imagem");
    }

    if ($content && mb_strlen($content) > 2000) {
        sendError("O conteúdo do post não pode exceder 2000 caracteres");
    }

    if ($image && !filter_var($image, FILTER_VALIDATE_URL) && !str_starts_with($image, 'data:image')) {
        sendError("Formato de imagem inválido");
    }

    // Inserir no banco de dados
    try {
        $stmt = $conn->prepare("
            INSERT INTO blog_posts (user_id, content, image)
            VALUES (:user_id, :content, :image)
        ");
        
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':image', $image);
        
        if (!$stmt->execute()) {
            sendError("Erro ao criar post no banco de dados", 500);
        }

        // Obter dados completos do post criado
        $postId = $conn->lastInsertId();
        
        $stmt = $conn->prepare("
            SELECT 
                p.id, 
                p.user_id, 
                p.content, 
                p.image, 
                p.created_at,
                u.first_name, 
                u.last_name, 
                u.avatar,
                0 AS likes_count,
                0 AS comments_count
            FROM blog_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Post criado com sucesso',
            'post' => $post
        ]);

    } catch (PDOException $e) {
        // Verificar se é erro de duplicação
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            sendError("Este post já existe", 400);
        }
        sendError("Erro no banco de dados: " . $e->getMessage(), 500);
    }
    break;

case 'toggle_like':
    // Verificação robusta de autenticação
    $userId = getAuthorizationToken();
    if (!$userId) {
        sendError("Token de autenticação não fornecido", 401);
    }

    // Validar post_id
    if (empty($input['post_id'])) {
        sendError("ID do post não fornecido");
    }

    $postId = $input['post_id'];

    // Verificar se o post existe
    $stmt = $conn->prepare("SELECT 1 FROM blog_posts WHERE id = ?");
    $stmt->execute([$postId]);
    
    if ($stmt->rowCount() === 0) {
        sendError("Post não encontrado", 404);
    }

    // Iniciar transação
    $conn->beginTransaction();

    try {
        // Verificar se o like já existe (usando a coluna correta)
        $stmt = $conn->prepare("SELECT 1 FROM blog_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            // Remover like
            $stmt = $conn->prepare("DELETE FROM blog_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $userId]);
            $action = 'removed';
        } else {
            // Adicionar like (assumindo que a tabela tem colunas post_id e user_id)
            $stmt = $conn->prepare("INSERT INTO blog_likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$postId, $userId]);
            $action = 'added';
        }

        // Obter nova contagem de likes
        $stmt = $conn->prepare("SELECT COUNT(*) FROM blog_likes WHERE post_id = ?");
        $stmt->execute([$postId]);
        $likesCount = (int)$stmt->fetchColumn();

        $conn->commit();

        sendResponse([
            'success' => true,
            'action' => $action,
            'likesCount' => $likesCount
        ]);

    } catch (PDOException $e) {
        $conn->rollBack();
        sendError("Erro no banco de dados: " . $e->getMessage(), 500);
    }
    break;

        case 'add_comment':
            // Verificar autenticação
            $userId = getAuthorizationToken();
            
            if (!$userId) {
                sendError("Token de autenticação não fornecido", 401);
            }
            
            // Validar dados
            if (empty($input['post_id'])) {
                sendError("ID do post não fornecido");
            }
            
            if (empty($input['content'])) {
                sendError("Conteúdo do comentário não fornecido");
            }
            
            $postId = $input['post_id'];
            $content = trim($input['content']);
            
            // Validar tamanho do comentário
            if (mb_strlen($content) > 500) {
                sendError("O comentário não pode exceder 500 caracteres");
            }
            
            // Verificar se o post existe
            $stmt = $conn->prepare("SELECT id FROM blog_posts WHERE id = ?");
            $stmt->execute([$postId]);
            
            if ($stmt->rowCount() === 0) {
                sendError("Post não encontrado", 404);
            }
            
            // Inserir comentário
            $stmt = $conn->prepare("
                INSERT INTO blog_comments (post_id, user_id, content)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([$postId, $userId, $content]);
            
            // Obter dados completos do comentário
            $commentId = $conn->lastInsertId();
            
            $stmt = $conn->prepare("
                SELECT 
                    c.id,
                    c.post_id,
                    c.user_id,
                    c.content,
                    c.created_at,
                    u.first_name,
                    u.last_name,
                    u.avatar
                FROM blog_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ");
            
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'message' => 'Comentário adicionado com sucesso',
                'comment' => $comment
            ]);
            break;

        case 'get_comments':
            // Validar post_id
            if (empty($input['post_id'])) {
                sendError("ID do post não fornecido");
            }
            
            $postId = $input['post_id'];
            
            // Obter comentários
            $stmt = $conn->prepare("
                SELECT 
                    c.id,
                    c.post_id,
                    c.user_id,
                    c.content,
                    c.created_at,
                    u.first_name,
                    u.last_name,
                    u.avatar
                FROM blog_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ?
                ORDER BY c.created_at DESC
            ");
            
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'comments' => $comments
            ]);
            break;

        default:
            sendError("Ação não reconhecida");
    }
} catch (PDOException $e) {
    sendError("Erro no banco de dados: " . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError("Erro interno: " . $e->getMessage(), 500);
}

