<?php
/**
 * FLYNOW - Configurações de Cargos
 */

$pageTitle = 'Configurações';
require_once __DIR__ . '/../../includes/header.php';
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

        if ($action === 'update_cargo') {
            $cargoId = intval($_POST['cargo_id'] ?? 0);
            $percentual = floatval(str_replace(',', '.', $_POST['percentual'] ?? '0'));

            $stmt = $pdo->prepare("UPDATE cargos SET percentual_comissao_default = ? WHERE id = ?");
            $stmt->execute([$percentual, $cargoId]);
            logAction('update', 'cargos', $cargoId);
            $success = 'Cargo atualizado com sucesso!';
        }
    }
}

$cargos = getCargos();
?>

<div class="page-header">
    <h1 class="page-title">Configurações</h1>
    <p class="page-subtitle">Gerencie cargos, deduções e regras do sistema</p>
</div>

<!-- Tabs de navegação -->
<div class="flex gap-md mb-lg" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
    <a href="cargos.php" class="btn btn-primary btn-sm">Cargos</a>
    <a href="deducoes.php" class="btn btn-ghost btn-sm">Deduções Padrão</a>
    <a href="escalonamento.php" class="btn btn-ghost btn-sm">Escalonamento</a>
    <?php if (canApprovePayments()): ?>
        <a href="usuarios.php" class="btn btn-ghost btn-sm">Usuários</a>
    <?php endif; ?>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Cargos e Percentuais de Comissão</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Cargo</th>
                    <th>Setor</th>
                    <th>Comissiona</th>
                    <th>Head</th>
                    <th>Escalonamento</th>
                    <th>% Comissão Padrão</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cargos as $cargo): ?>
                    <tr>
                        <td><strong>
                                <?= htmlspecialchars($cargo['nome']) ?>
                            </strong></td>
                        <td><span class="badge badge-info">
                                <?= ucfirst($cargo['setor']) ?>
                            </span></td>
                        <td>
                            <?= $cargo['comissiona'] ? '<span class="text-success">Sim</span>' : '<span class="text-muted">Não</span>' ?>
                        </td>
                        <td>
                            <?= $cargo['eh_head'] ? '<span class="text-success">Sim</span>' : '-' ?>
                        </td>
                        <td>
                            <?= $cargo['escalonamento_aplicavel'] ? '<span class="text-success">Sim</span>' : '-' ?>
                        </td>
                        <td>
                            <?= $cargo['percentual_comissao_default'] ? formatPercent($cargo['percentual_comissao_default']) : '-' ?>
                        </td>
                        <td>
                            <?php if ($cargo['comissiona']): ?>
                                <button type="button" class="btn btn-ghost btn-sm"
                                    onclick="openEditModal(<?= $cargo['id'] ?>, '<?= htmlspecialchars($cargo['nome']) ?>', <?= $cargo['percentual_comissao_default'] ?? 0 ?>)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editar Percentual</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="update_cargo">
            <input type="hidden" name="cargo_id" id="edit-cargo-id">

            <div class="modal-body">
                <p class="mb-lg"><strong id="edit-cargo-nome"></strong></p>

                <div class="form-group">
                    <label class="form-label">% Comissão Padrão</label>
                    <div class="input-group">
                        <input type="text" name="percentual" id="edit-percentual" class="form-control"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-modal')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, nome, percentual) {
        document.getElementById('edit-cargo-id').value = id;
        document.getElementById('edit-cargo-nome').textContent = nome;
        document.getElementById('edit-percentual').value = percentual.toFixed(2).replace('.', ',');
        openModal('edit-modal');
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>