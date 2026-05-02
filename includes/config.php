<?php
/**
 * FLYNOW - Configuração do Sistema
 * Conexão PDO com PostgreSQL (Supabase) e constantes globais
 */

// ============================================
// CARREGA ARQUIVO .env SE EXISTIR
// ============================================
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
}

// ============================================
// CONFIGURAÇÕES DO BANCO DE DADOS
// ============================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');

// ============================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================
define('SITE_NAME', 'Flynow - Gestão de Comissões');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');
define('BASE_PATH', getenv('BASE_PATH') ?: '');
define('SESSION_TIMEOUT', 1800);

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('America/Sao_Paulo');

// ============================================
// CONEXÃO PDO (PostgreSQL)
// ============================================
function getDB()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
        } catch (PDOException $e) {
            error_log("Erro de conexão DB: " . $e->getMessage());
            die("Erro de conexão com o banco de dados. Contate o administrador.");
        }
    }

    return $pdo;
}

// ============================================
// FUNÇÕES DE UTILIDADE
// ============================================

function formatMoney($value)
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatPercent($value)
{
    return number_format($value, 1, ',', '.') . '%';
}

function parseMoney($value)
{
    $value = str_replace(['R$', ' ', '.'], '', $value);
    $value = str_replace(',', '.', $value);
    return floatval($value);
}

function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function logAction($acao, $tabela, $registroId = null, $dadosAnteriores = null, $dadosNovos = null)
{
    $pdo = getDB();
    $usuarioId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO logs (usuario_id, acao, tabela, registro_id, dados_anteriores, dados_novos, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $usuarioId,
        $acao,
        $tabela,
        $registroId,
        $dadosAnteriores ? json_encode($dadosAnteriores) : null,
        $dadosNovos ? json_encode($dadosNovos) : null,
        $ip
    ]);
}

function getConfig($chave, $default = null)
{
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $result = $stmt->fetch();
    return $result ? $result['valor'] : $default;
}

function getMesNome($mes)
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    return $meses[$mes] ?? '';
}
