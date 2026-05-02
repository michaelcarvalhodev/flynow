<?php
/**
 * FLYNOW - Login
 * Página de autenticação do sistema
 */

require_once __DIR__ . '/includes/auth.php';

// Se já autenticado, redireciona
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Gera token CSRF para o formulário
$csrfToken = generateCSRFToken();

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $error = 'Preencha todos os campos';
        } else {
            $result = login($email, $senha);
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                // Mensagem genérica para evitar enumeração de usuários
                $error = 'Credenciais inválidas';
            }
        }
    }
}

// Verifica mensagens de erro na URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'session_expired':
            $error = 'Sua sessão expirou. Faça login novamente.';
            break;
        case 'access_denied':
            $error = 'Acesso negado.';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login -
        <?= SITE_NAME ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= time() ?>">
</head>

<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="login-logo">F</div>
                <h1 class="login-title">Flynow</h1>
                <p class="login-subtitle">Sistema de Gestão de Comissões</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <span>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label class="form-label" for="email">E-mail</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                            </path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input type="email" id="email" name="email" class="form-control" placeholder="seu@email.com"
                            required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="senha">Senha</label>
                    <div class="input-group">
                        <svg class="input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••"
                            required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block mt-lg">
                    Entrar
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <p class="text-center text-muted mt-lg" style="font-size: 0.75rem;">
                ©
                <?= date('Y') ?> Flynow. Todos os direitos reservados.
            </p>
        </div>
    </div>
</body>

</html>