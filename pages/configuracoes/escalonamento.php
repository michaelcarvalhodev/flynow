<?php
/**
 * FLYNOW - Configurações de Escalonamento
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
        $valorGatilho = parseMoney($_POST['valor_gatilho'] ?? '1000000');
        $incremento = floatval(str_replace(',', '.', $_POST['incremento'] ?? '0.5'));
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        $pdo->prepare("
            UPDATE escalonamento SET valor_gatilho = ?, incremento_percentual = ?, ativo = ? WHERE id = 1
        ")->execute([$valorGatilho, $incremento, $ativo]);

        logAction('update', 'escalonamento', 1);
        $success = 'Regra de escalonamento atualizada!';
    }
}

// Carrega regra atual
$stmt = $pdo->query("SELECT * FROM escalonamento WHERE id = 1");
$escalonamento = $stmt->fetch();
?>

<div class="page-header">
    <h1 class="page-title">Configurações</h1>
    <p class="page-subtitle">Gerencie cargos, deduções e regras do sistema</p>
</div>

<!-- Tabs de navegação -->
<div class="flex gap-md mb-lg" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
    <a href="cargos.php" class="btn btn-ghost btn-sm">Cargos</a>
    <a href="deducoes.php" class="btn btn-ghost btn-sm">Deduções Padrão</a>
    <a href="escalonamento.php" class="btn btn-primary btn-sm">Escalonamento</a>
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
        <h3 class="card-title">Regra de Escalonamento de Comissão</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-lg">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
                <strong>Como funciona:</strong> Quando o lucro bruto TOTAL da operação atinge o valor gatilho,
                os cargos marcados com "Escalonamento Aplicável" (Copy de Ads, Copy de Oferta) recebem um incremento
                no percentual de comissão.
            </div>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Valor Gatilho</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="valor_gatilho" class="form-control with-prefix"
                            value="<?= number_format($escalonamento['valor_gatilho'], 2, ',', '.') ?>">
                    </div>
                    <p class="form-hint">Lucro bruto total para ativar</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Incremento na Comissão</label>
                    <div class="input-group">
                        <input type="text" name="incremento" class="form-control"
                            value="<?= number_format($escalonamento['incremento_percentual'], 2, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                    <p class="form-hint">Adicionado ao % base</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div style="padding: 1rem 0;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="ativo" value="1" <?= $escalonamento['ativo'] ? 'checked' : '' ?>
                            style="width: 20px; height: 20px;">
                            <span>Regra ativa</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-lg">
                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Salvar Configuração
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Exemplo -->
<div class="card mt-lg">
    <div class="card-header">
        <h3 class="card-title">Exemplo de Cálculo</h3>
    </div>
    <div class="card-body">
        <p class="mb-md">Com as configurações atuais:</p>
        <ul style="padding-left: 1.5rem;">
            <li style="margin-bottom: 0.5rem;">
                Se lucro bruto total da operação = <strong>
                    <?= formatMoney($escalonamento['valor_gatilho'] - 1) ?>
                </strong><br>
                <span class="text-muted">Copy de Ads recebe: 2,5% de comissão (padrão)</span>
            </li>
            <li>
                Se lucro bruto total da operação = <strong>
                    <?= formatMoney($escalonamento['valor_gatilho']) ?>
                </strong> ou mais<br>
                <span class="text-success">Copy de Ads recebe:
                    <?= number_format(2.5 + $escalonamento['incremento_percentual'], 1, ',', '.') ?>% de comissão (+
                    <?= number_format($escalonamento['incremento_percentual'], 1, ',', '.') ?>%)
                </span>
            </li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>