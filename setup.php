<?php
/**
 * FLYNOW - Script de Configuração Inicial
 * Execute este script uma única vez para configurar o sistema
 * IMPORTANTE: Delete este arquivo após a configuração!
 */

// Bloqueia acesso se já foi configurado
$lockFile = __DIR__ . '/.setup_complete';
if (file_exists($lockFile)) {
    die('Sistema já configurado. Delete este arquivo por segurança.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Processa formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Testa conexão com banco
        $host = $_POST['db_host'] ?? 'localhost';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';

        try {
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Verifica se o banco existe, se não, cria
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");

            // Salva configurações em sessão
            session_start();
            $_SESSION['setup_db'] = [
                'host' => $host,
                'name' => $name,
                'user' => $user,
                'pass' => $pass
            ];

            header('Location: setup.php?step=2');
            exit;
        } catch (PDOException $e) {
            $error = 'Erro de conexão: ' . $e->getMessage();
        }
    } elseif ($step == 2) {
        session_start();
        $dbConfig = $_SESSION['setup_db'] ?? null;

        if (!$dbConfig) {
            header('Location: setup.php?step=1');
            exit;
        }

        try {
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Drop tabelas existentes na ordem correta (respeitando FKs)
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $dropTables = [
                'logs',
                'login_attempts',
                'bonus_manuais',
                'pagamentos',
                'fechamento_colaborador_lucro',
                'fechamentos',
                'escalonamento',
                'colaboradores',
                'cargos',
                'usuarios',
                'configuracoes'
            ];
            foreach ($dropTables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            // Executa schema
            $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
            $pdo->exec($schema);

            // Executa seed (sem o usuário admin)
            $seed = file_get_contents(__DIR__ . '/sql/seed.sql');
            // Remove a linha do usuário admin do seed
            $seed = preg_replace("/INSERT INTO usuarios.*?;/s", "", $seed);
            $pdo->exec($seed);

            header('Location: setup.php?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Erro ao criar tabelas: ' . $e->getMessage();
        }
    } elseif ($step == 3) {
        session_start();
        $dbConfig = $_SESSION['setup_db'] ?? null;

        if (!$dbConfig) {
            header('Location: setup.php?step=1');
            exit;
        }

        $adminNome = trim($_POST['admin_nome'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminSenha = $_POST['admin_senha'] ?? '';
        $adminSenhaConfirm = $_POST['admin_senha_confirm'] ?? '';

        // Validações
        if (empty($adminNome) || empty($adminEmail) || empty($adminSenha)) {
            $error = 'Preencha todos os campos.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'E-mail inválido.';
        } elseif (strlen($adminSenha) < 8) {
            $error = 'A senha deve ter no mínimo 8 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $adminSenha)) {
            $error = 'A senha deve ter pelo menos uma letra maiúscula.';
        } elseif (!preg_match('/[a-z]/', $adminSenha)) {
            $error = 'A senha deve ter pelo menos uma letra minúscula.';
        } elseif (!preg_match('/[0-9]/', $adminSenha)) {
            $error = 'A senha deve ter pelo menos um número.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $adminSenha)) {
            $error = 'A senha deve ter pelo menos um caractere especial.';
        } elseif ($adminSenha !== $adminSenhaConfirm) {
            $error = 'As senhas não conferem.';
        } else {
            try {
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                $senhaHash = password_hash($adminSenha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, 'admin', TRUE)");
                $stmt->execute([$adminNome, $adminEmail, $senhaHash]);

                // Cria arquivo .env
                $envContent = "# Configurações do Banco de Dados FLYNOW\n";
                $envContent .= "DB_HOST={$dbConfig['host']}\n";
                $envContent .= "DB_NAME={$dbConfig['name']}\n";
                $envContent .= "DB_USER={$dbConfig['user']}\n";
                $envContent .= "DB_PASS={$dbConfig['pass']}\n";

                file_put_contents(__DIR__ . '/.env', $envContent);

                // Cria arquivo de lock
                file_put_contents($lockFile, date('Y-m-d H:i:s'));

                // Limpa sessão
                session_destroy();

                header('Location: setup.php?step=4');
                exit;
            } catch (PDOException $e) {
                $error = 'Erro ao criar usuário: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Flynow</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .setup-container {
            background: #111;
            border: 1px solid #333;
            max-width: 500px;
            width: 100%;
            padding: 2rem;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #333;
        }
        .setup-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #F5A623, #FF6B00);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .steps {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .step-item {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            background: #222;
            border: 2px solid #333;
        }
        .step-item.active {
            background: #F5A623;
            border-color: #F5A623;
            color: #000;
        }
        .step-item.done {
            background: #22c55e;
            border-color: #22c55e;
            color: #fff;
        }
        .form-group { margin-bottom: 1.25rem; }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #999;
        }
        input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: #1a1a1a;
            border: 1px solid #333;
            color: #fff;
            font-size: 1rem;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #F5A623;
        }
        button {
            width: 100%;
            padding: 0.875rem;
            background: #F5A623;
            border: none;
            color: #000;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        button:hover { background: #e09000; }
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
        .alert-success { background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e; }
        .alert-warning { background: rgba(245, 166, 35, 0.1); border: 1px solid #F5A623; color: #F5A623; }
        .hint { font-size: 0.75rem; color: #666; margin-top: 0.25rem; }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }
        a.btn {
            display: block;
            text-align: center;
            padding: 0.875rem;
            background: #F5A623;
            color: #000;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="setup-logo">F</div>
            <h1>Flynow Setup</h1>
            <p style="color: #666;">Configuração inicial do sistema</p>
        </div>

        <div class="steps">
            <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">1</div>
            <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">2</div>
            <div class="step-item <?= $step >= 3 ? ($step > 3 ? 'done' : 'active') : '' ?>">3</div>
            <div class="step-item <?= $step >= 4 ? 'done' : '' ?>">4</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <h2 style="margin-bottom: 1rem;">Banco de Dados</h2>
            <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.875rem;">
                Configure a conexão com o MySQL
            </p>

            <form method="POST">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Nome do Banco</label>
                    <input type="text" name="db_name" value="flynow_gestao" required>
                    <p class="hint">Será criado se não existir</p>
                </div>
                <div class="form-group">
                    <label>Usuário</label>
                    <input type="text" name="db_user" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="db_pass">
                </div>
                <button type="submit">Testar Conexão</button>
            </form>

        <?php elseif ($step == 2): ?>
            <h2 style="margin-bottom: 1rem;">Criando Tabelas</h2>
            <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.875rem;">
                O sistema irá criar todas as tabelas necessárias
            </p>

            <div class="alert alert-warning">
                <strong>Atenção:</strong> Se já existirem tabelas com os mesmos nomes, elas serão sobrescritas.
            </div>

            <form method="POST">
                <button type="submit">Criar Tabelas</button>
            </form>

        <?php elseif ($step == 3): ?>
            <h2 style="margin-bottom: 1rem;">Usuário Administrador</h2>
            <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.875rem;">
                Crie o primeiro usuário do sistema
            </p>

            <form method="POST">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="admin_nome" required>
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="admin_senha" required minlength="8">
                    <p class="hint">Mínimo 8 caracteres, com maiúscula, minúscula, número e especial</p>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha</label>
                    <input type="password" name="admin_senha_confirm" required>
                </div>
                <button type="submit">Criar Usuário</button>
            </form>

        <?php elseif ($step == 4): ?>
            <div class="success-icon">✓</div>
            <h2 style="text-align: center; margin-bottom: 1rem;">Configuração Concluída!</h2>
            <p style="color: #666; text-align: center; margin-bottom: 1.5rem; font-size: 0.875rem;">
                O sistema foi configurado com sucesso.
            </p>

            <div class="alert alert-warning">
                <strong>IMPORTANTE:</strong> Delete o arquivo <code>setup.php</code> por segurança!
            </div>

            <a href="index.php" class="btn">Acessar o Sistema</a>
        <?php endif; ?>
    </div>
</body>
</html>
