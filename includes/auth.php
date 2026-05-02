<?php
/**
 * FLYNOW - Autenticação e Autorização
 */

// Configurações seguras de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
require_once __DIR__ . '/config.php';

/**
 * Verifica se o usuário está autenticado
 */
function isAuthenticated()
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Verifica timeout da sessão
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        logout();
        return false;
    }

    // Verifica se o IP ou user-agent mudou (possível sequestro de sessão)
    $currentIP = $_SERVER['REMOTE_ADDR'] ?? '';
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIP) {
        logout();
        return false;
    }

    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUA) {
        logout();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Requer autenticação - redireciona se não autenticado
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        header('Location: ' . SITE_URL . '/index.php?error=session_expired');
        exit;
    }
}

/**
 * Requer perfil de administrador
 */
function requireAdmin()
{
    requireAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Verifica rate limiting para tentativas de login
 * Bloqueia após 5 tentativas em 15 minutos
 */
function checkLoginRateLimit($email)
{
    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $windowMinutes = 15;
    $maxAttempts = 5;

    // Limpa tentativas antigas (PostgreSQL syntax)
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL '1 minute' * ?")
        ->execute([$windowMinutes]);

    // Conta tentativas recentes (por IP ou email) (PostgreSQL syntax)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts FROM login_attempts
        WHERE (ip_address = ? OR email = ?)
        AND attempted_at > NOW() - INTERVAL '1 minute' * ?
    ");
    $stmt->execute([$ip, $email, $windowMinutes]);
    $result = $stmt->fetch();

    return $result['attempts'] < $maxAttempts;
}

/**
 * Registra tentativa de login falha
 */
function recordFailedLogin($email)
{
    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ip, $email]);
}

/**
 * Limpa tentativas de login após sucesso
 */
function clearLoginAttempts($email)
{
    $pdo = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR email = ?");
    $stmt->execute([$ip, $email]);
}

/**
 * Tenta fazer login
 */
function login($email, $senha)
{
    $pdo = getDB();

    // Verifica rate limiting
    if (!checkLoginRateLimit($email)) {
        return ['success' => false, 'error' => 'Muitas tentativas de login. Aguarde 15 minutos.'];
    }

    $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, perfil, ativo FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        recordFailedLogin($email);
        return ['success' => false, 'error' => 'Credenciais inválidas'];
    }

    if (!$user['ativo']) {
        recordFailedLogin($email);
        return ['success' => false, 'error' => 'Usuário desativado'];
    }

    if (!password_verify($senha, $user['senha_hash'])) {
        recordFailedLogin($email);
        return ['success' => false, 'error' => 'Credenciais inválidas'];
    }

    // Limpa tentativas após login bem-sucedido
    clearLoginAttempts($email);

    // Login bem-sucedido - regenera session ID para prevenir session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['nome'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['perfil'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Atualiza último login (PostgreSQL syntax)
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);

    logAction('login', 'usuarios', $user['id']);

    return ['success' => true, 'user' => $user];
}

/**
 * Faz logout
 */
function logout()
{
    if (isset($_SESSION['user_id'])) {
        logAction('logout', 'usuarios', $_SESSION['user_id']);
    }

    session_unset();
    session_destroy();
}

/**
 * Retorna dados do usuário atual
 */
function getCurrentUser()
{
    if (!isAuthenticated()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'nome' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'perfil' => $_SESSION['user_role']
    ];
}

/**
 * Verifica se usuário tem permissão
 */
function hasPermission($permission)
{
    $role = $_SESSION['user_role'] ?? null;

    $permissions = [
        'admin' => ['all'],
        'financeiro' => ['colaboradores', 'fechamento', 'historico', 'relatorios', 'configuracoes']
    ];

    if ($role === 'admin') {
        return true;
    }

    return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
}

/**
 * Verifica se pode aprovar pagamentos (apenas admin)
 */
function canApprovePayments()
{
    return $_SESSION['user_role'] === 'admin';
}