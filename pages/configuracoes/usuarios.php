<?php
/**
 * FLYNOW - Gerenciamento de Usuários
 */

$pageTitle = 'Configurações';
require_once __DIR__ . '/../../includes/header.php';
requireAdmin();
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

// Gera token CSRF
$csrfToken = generateCSRFToken();

// Processa ações
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $action = sanitize($_POST['action'] ?? '');

    if ($action === 'create') {
        $nome = sanitize($_POST['nome'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $perfil = sanitize($_POST['perfil'] ?? 'financeiro');

        if (empty($nome) || empty($email) || empty($senha)) {
            $errors[] = 'Preencha todos os campos';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido';
        } elseif (strlen($senha) < 8) {
            $errors[] = 'A senha deve ter no mínimo 8 caracteres';
        } elseif (!preg_match('/[A-Z]/', $senha)) {
            $errors[] = 'A senha deve ter pelo menos uma letra maiúscula';
        } elseif (!preg_match('/[a-z]/', $senha)) {
            $errors[] = 'A senha deve ter pelo menos uma letra minúscula';
        } elseif (!preg_match('/[0-9]/', $senha)) {
            $errors[] = 'A senha deve ter pelo menos um número';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            $errors[] = 'A senha deve ter pelo menos um caractere especial';
        } else {
            // Verifica se email já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Este e-mail já está cadastrado';
            } else {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senhaHash, $perfil]);
                logAction('create', 'usuarios', $pdo->lastInsertId());
                $success = 'Usuário criado com sucesso!';
            }
        }
    } elseif ($action === 'toggle') {
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId != $_SESSION['user_id']) { // Não pode desativar a si mesmo
            $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = ?")->execute([$userId]);
            logAction('toggle', 'usuarios', $userId);
            $success = 'Status do usuário alterado!';
        }
    }
    }
}

// Busca usuários
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome")->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title">Configurações</h1>
    <p class="page-subtitle">Gerencie cargos, deduções e regras do sistema</p>
</div>

<!-- Tabs de navegação -->
<div class="flex gap-md mb-lg" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
    <a href="cargos.php" class="btn btn-ghost btn-sm">Cargos</a>
    <a href="deducoes.php" class="btn btn-ghost btn-sm">Deduções Padrão</a>
    <a href="escalonamento.php" class="btn btn-ghost btn-sm">Escalonamento</a>
    <a href="usuarios.php" class="btn btn-primary btn-sm">Usuários</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p>
                <?= $error ?>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Novo Usuário -->
<div class="card mb-lg">
    <div class="card-header">
        <h3 class="card-title">Novo Usuário</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create">

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" class="form-control" required minlength="8">
                    <p class="form-hint">Min. 8 caracteres, com maiúscula, minúscula, número e especial</p>
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Perfil</label>
                    <select name="perfil" class="form-control">
                        <option value="financeiro">Financeiro</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        Criar Usuário
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Usuários -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Usuários Cadastrados</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Perfil</th>
                    <th>Último Login</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>
                            <strong>
                                <?= htmlspecialchars($u['nome']) ?>
                            </strong>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span class="text-muted">(você)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['email']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $u['perfil'] === 'admin' ? 'badge-success' : 'badge-info' ?>">
                                <?= ucfirst($u['perfil']) ?>
                            </span>
                        </td>
                        <td class="text-muted">
                            <?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'Nunca' ?>
                        </td>
                        <td>
                            <span class="badge <?= $u['ativo'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm"
                                        title="<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                        <?php if ($u['ativo']): ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <path
                                                    d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                                                </path>
                                                <line x1="1" y1="1" x2="23" y2="23"></line>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>