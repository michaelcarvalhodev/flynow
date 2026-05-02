<?php
/**
 * FLYNOW - Configurações de Deduções
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
        $imposto = floatval(str_replace(',', '.', $_POST['imposto'] ?? '12'));
        $reembolso = floatval(str_replace(',', '.', $_POST['reembolso'] ?? '15'));
        $outras = floatval(str_replace(',', '.', $_POST['outras'] ?? '0'));
        $alerta = floatval(str_replace(',', '.', $_POST['alerta'] ?? '25'));

        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'percentual_imposto_default'")->execute([$imposto]);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'percentual_reembolso_default'")->execute([$reembolso]);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'percentual_outras_default'")->execute([$outras]);
        $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'alerta_percentual_folha'")->execute([$alerta]);

        logAction('update', 'configuracoes', null);
        $success = 'Configurações salvas com sucesso!';
    }
}

// Carrega configurações atuais
$imposto = getConfig('percentual_imposto_default', '12');
$reembolso = getConfig('percentual_reembolso_default', '15');
$outras = getConfig('percentual_outras_default', '0');
$alerta = getConfig('alerta_percentual_folha', '25');
?>

<div class="page-header">
    <h1 class="page-title">Configurações</h1>
    <p class="page-subtitle">Gerencie cargos, deduções e regras do sistema</p>
</div>

<!-- Tabs de navegação -->
<div class="flex gap-md mb-lg" style="border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
    <a href="cargos.php" class="btn btn-ghost btn-sm">Cargos</a>
    <a href="deducoes.php" class="btn btn-primary btn-sm">Deduções Padrão</a>
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
        <h3 class="card-title">Percentuais de Dedução Padrão</h3>
    </div>
    <div class="card-body">
        <p class="text-muted mb-lg">
            Estes valores serão usados como padrão ao criar novos fechamentos.
            Cada fechamento pode ter seus próprios percentuais ajustados.
        </p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">% Imposto (padrão)</label>
                    <div class="input-group">
                        <input type="text" name="imposto" class="form-control"
                            value="<?= number_format($imposto, 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                    <p class="form-hint">Percentual de imposto sobre o lucro bruto</p>
                </div>

                <div class="form-group">
                    <label class="form-label">% Reembolso/Chargeback (padrão)</label>
                    <div class="input-group">
                        <input type="text" name="reembolso" class="form-control"
                            value="<?= number_format($reembolso, 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                    <p class="form-hint">Percentual de perdas por reembolso e chargeback</p>
                </div>

                <div class="form-group">
                    <label class="form-label">% Outras Deduções (padrão)</label>
                    <div class="input-group">
                        <input type="text" name="outras" class="form-control"
                            value="<?= number_format($outras, 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                    <p class="form-hint">Outras deduções eventuais</p>
                </div>

                <div class="form-group">
                    <label class="form-label">% Alerta Folha/Lucro</label>
                    <div class="input-group">
                        <input type="text" name="alerta" class="form-control"
                            value="<?= number_format($alerta, 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                    <p class="form-hint">Exibe alerta quando folha excede este % do lucro</p>
                </div>
            </div>

            <div class="flex justify-end mt-lg">
                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Informação sobre ordem das deduções -->
<div class="card mt-lg">
    <div class="card-header">
        <h3 class="card-title">Ordem das Deduções</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">O lucro líquido é calculado aplicando as deduções em sequência:</p>
        <ol style="padding-left: 1.5rem; margin-top: 1rem;">
            <li style="margin-bottom: 0.5rem;"><strong>Lucro Bruto</strong> (input)</li>
            <li style="margin-bottom: 0.5rem;"><strong>(-) Imposto</strong> = Bruto × (1 - %imposto)</li>
            <li style="margin-bottom: 0.5rem;"><strong>(-) Reembolso</strong> = Resultado × (1 - %reembolso)</li>
            <li style="margin-bottom: 0.5rem;"><strong>(-) Outras</strong> = Resultado × (1 - %outras)</li>
            <li><strong>= Lucro Líquido</strong> (base para comissões)</li>
        </ol>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>