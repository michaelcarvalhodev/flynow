<?php
/**
 * FLYNOW - Formulário de Colaborador
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();
$cargos = getCargos();

$colaborador = null;
$isEdit = false;
$errors = [];
$success = '';

// Gera token CSRF
$csrfToken = generateCSRFToken();

// Carrega colaborador para edição
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
    $stmt->execute([$id]);
    $colaborador = $stmt->fetch();
    $isEdit = (bool) $colaborador;
}

// Processa formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Recarregue a página.';
    } else {
    $nome = sanitize($_POST['nome'] ?? '');
    $cpf = sanitize($_POST['cpf'] ?? '');
    $cargo_id = intval($_POST['cargo_id'] ?? 0);
    $canal = sanitize($_POST['canal'] ?? '') ?: null;
    $salario_base = parseMoney($_POST['salario_base'] ?? '0');
    $percentual_comissao = !empty($_POST['percentual_comissao']) ? floatval(str_replace(',', '.', $_POST['percentual_comissao'])) : null;
    $status = sanitize($_POST['status'] ?? 'ativo');
    $data_admissao = sanitize($_POST['data_admissao'] ?? '') ?: null;
    $observacoes = sanitize($_POST['observacoes'] ?? '');

    // Validação
    if (empty($nome)) {
        $errors[] = 'Nome é obrigatório';
    }
    if ($cargo_id <= 0) {
        $errors[] = 'Cargo é obrigatório';
    }
    if ($salario_base < 0) {
        $errors[] = 'Salário base inválido';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE colaboradores SET
                        nome = ?, cpf = ?, cargo_id = ?, canal = ?, salario_base = ?,
                        percentual_comissao = ?, status = ?, data_admissao = ?, observacoes = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $nome,
                    $cpf,
                    $cargo_id,
                    $canal,
                    $salario_base,
                    $percentual_comissao,
                    $status,
                    $data_admissao,
                    $observacoes,
                    $_GET['id']
                ]);
                logAction('update', 'colaboradores', $_GET['id']);
                $success = 'Colaborador atualizado com sucesso!';

                // Recarrega dados
                $stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $colaborador = $stmt->fetch();
            } else {
                // Insere
                $stmt = $pdo->prepare("
                    INSERT INTO colaboradores (nome, cpf, cargo_id, canal, salario_base, percentual_comissao, status, data_admissao, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nome,
                    $cpf,
                    $cargo_id,
                    $canal,
                    $salario_base,
                    $percentual_comissao,
                    $status,
                    $data_admissao,
                    $observacoes
                ]);
                $newId = $pdo->lastInsertId();
                logAction('create', 'colaboradores', $newId);

                header('Location: index.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Erro ao salvar colaborador: " . $e->getMessage());
            $errors[] = 'Erro ao salvar. Tente novamente.';
        }
    }
    }
}

$pageTitle = $isEdit ? 'Editar Colaborador' : 'Novo Colaborador';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <?= $pageTitle ?>
    </h1>
    <p class="page-subtitle">
        <a href="index.php">Colaboradores</a> /
        <?= $isEdit ? htmlspecialchars($colaborador['nome']) : 'Novo' ?>
    </p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul style="margin: 0; padding-left: 1.5rem;">
            <?php foreach ($errors as $error): ?>
                <li>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="grid-2">
            <div class="form-group">
                <label class="form-label" for="nome">Nome Completo *</label>
                <input type="text" id="nome" name="nome" class="form-control"
                    value="<?= htmlspecialchars($colaborador['nome'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="cpf">CPF</label>
                <input type="text" id="cpf" name="cpf" class="form-control" data-mask="cpf"
                    value="<?= htmlspecialchars($colaborador['cpf'] ?? '') ?>" placeholder="000.000.000-00">
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label" for="cargo_id">Cargo *</label>
                <select id="cargo_id" name="cargo_id" class="form-control" required onchange="updateCargoInfo()">
                    <option value="">Selecione...</option>
                    <?php foreach ($cargos as $cargo): ?>
                        <option value="<?= $cargo['id'] ?>" data-setor="<?= $cargo['setor'] ?>"
                            data-comissao="<?= $cargo['percentual_comissao_default'] ?>"
                            data-comissiona="<?= $cargo['comissiona'] ? '1' : '0' ?>" <?= ($colaborador['cargo_id'] ?? '') == $cargo['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cargo['nome'], ENT_QUOTES, 'UTF-8') ?> (
                            <?= htmlspecialchars(ucfirst($cargo['setor']), ENT_QUOTES, 'UTF-8') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="canal-group"
                style="<?= in_array($colaborador['cargo_id'] ?? '', [3, 4, 5, 6]) ? '' : 'display: none;' ?>">
                <label class="form-label" for="canal">Canal Responsável</label>
                <select id="canal" name="canal" class="form-control">
                    <option value="">Nenhum</option>
                    <option value="meta" <?= ($colaborador['canal'] ?? '') === 'meta' ? 'selected' : '' ?>>Meta</option>
                    <option value="google" <?= ($colaborador['canal'] ?? '') === 'google' ? 'selected' : '' ?>>Google
                    </option>
                    <option value="taboola" <?= ($colaborador['canal'] ?? '') === 'taboola' ? 'selected' : '' ?>>Taboola
                    </option>
                    <option value="tiktok" <?= ($colaborador['canal'] ?? '') === 'tiktok' ? 'selected' : '' ?>>TikTok
                    </option>
                </select>
            </div>
        </div>

        <div class="grid-3">
            <div class="form-group">
                <label class="form-label" for="salario_base">Salário Base *</label>
                <div class="input-group">
                    <span class="input-prefix">R$</span>
                    <input type="text" id="salario_base" name="salario_base" class="form-control with-prefix"
                        value="<?= isset($colaborador['salario_base']) ? number_format($colaborador['salario_base'], 2, ',', '.') : '' ?>"
                        placeholder="0,00" required>
                </div>
            </div>

            <div class="form-group" id="comissao-group">
                <label class="form-label" for="percentual_comissao">% Comissão</label>
                <div class="input-group">
                    <input type="text" id="percentual_comissao" name="percentual_comissao" class="form-control"
                        value="<?= isset($colaborador['percentual_comissao']) ? number_format($colaborador['percentual_comissao'], 2, ',', '.') : '' ?>"
                        placeholder="Usar padrão do cargo">
                    <span
                        style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                </div>
                <p class="form-hint" id="comissao-hint">Deixe vazio para usar o padrão do cargo</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="ativo" <?= ($colaborador['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo
                    </option>
                    <option value="inativo" <?= ($colaborador['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo
                    </option>
                    <option value="em_teste" <?= ($colaborador['status'] ?? '') === 'em_teste' ? 'selected' : '' ?>>Em
                        Teste</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label" for="data_admissao">Data de Admissão</label>
                <input type="date" id="data_admissao" name="data_admissao" class="form-control"
                    value="<?= htmlspecialchars($colaborador['data_admissao'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="observacoes">Observações</label>
            <textarea id="observacoes" name="observacoes" class="form-control" rows="3"
                placeholder="Anotações internas..."><?= htmlspecialchars($colaborador['observacoes'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-end gap-md mt-lg">
            <a href="index.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                <?= $isEdit ? 'Salvar Alterações' : 'Cadastrar' ?>
            </button>
        </div>
    </form>
</div>

<script>
    function updateCargoInfo() {
        const select = document.getElementById('cargo_id');
        const option = select.options[select.selectedIndex];
        const canalGroup = document.getElementById('canal-group');
        const comissaoGroup = document.getElementById('comissao-group');
        const comissaoHint = document.getElementById('comissao-hint');

        if (!option.value) return;

        const setor = option.dataset.setor;
        const comissao = option.dataset.comissao;
        const comissiona = option.dataset.comissiona === '1';

        // Mostra canal apenas para gestores de tráfego
        if (setor === 'trafego' && option.text.includes('Gestor')) {
            canalGroup.style.display = '';
        } else {
            canalGroup.style.display = 'none';
            document.getElementById('canal').value = '';
        }

        // Atualiza hint de comissão
        if (comissiona && comissao) {
            comissaoHint.textContent = `Padrão do cargo: ${parseFloat(comissao).toFixed(1).replace('.', ',')}%`;
        } else if (!comissiona) {
            comissaoHint.textContent = 'Este cargo não comissiona';
        } else {
            comissaoHint.textContent = 'Deixe vazio para usar o padrão do cargo';
        }
    }

    // Atualiza ao carregar
    document.addEventListener('DOMContentLoaded', updateCargoInfo);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>