<?php
/**
 * FLYNOW - Wizard de Fechamento Mensal
 */

$pageTitle = 'Fechamento Mensal';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

// Determina mês/ano do fechamento
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

// Se for mês atual, usa mês anterior
if ($mes == date('n') && $ano == date('Y')) {
    $mes--;
    if ($mes == 0) {
        $mes = 12;
        $ano--;
    }
}

// Busca ou cria fechamento
$stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE mes = ? AND ano = ?");
$stmt->execute([$mes, $ano]);
$fechamento = $stmt->fetch();

if (!$fechamento) {
    // Cria novo fechamento em rascunho
    $stmt = $pdo->prepare("
        INSERT INTO fechamentos (mes, ano, percentual_imposto, percentual_reembolso, percentual_outras)
        VALUES (?, ?, ?, ?, ?)
    ");
    $defaultImposto = getConfig('percentual_imposto_default', 12);
    $defaultReembolso = getConfig('percentual_reembolso_default', 15);
    $defaultOutras = getConfig('percentual_outras_default', 0);

    $stmt->execute([$mes, $ano, $defaultImposto, $defaultReembolso, $defaultOutras]);
    $fechamentoId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ?");
    $stmt->execute([$fechamentoId]);
    $fechamento = $stmt->fetch();
} else {
    $fechamentoId = $fechamento['id'];
}

// Busca copys ativos
$copys = getCopysAtivos();

// Busca lucros já cadastrados por copy
$stmt = $pdo->prepare("SELECT colaborador_id, lucro_gerado FROM lucro_colaborador WHERE fechamento_id = ?");
$stmt->execute([$fechamentoId]);
$lucrosPorCopy = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Busca bônus já cadastrados
$stmt = $pdo->prepare("SELECT * FROM bonus_manuais WHERE fechamento_id = ?");
$stmt->execute([$fechamentoId]);
$bonusManuais = $stmt->fetchAll();

// Busca colaboradores para bônus
$colaboradores = getColaboradores(['status' => 'ativo']);

// Gera token CSRF
$csrfToken = generateCSRFToken();

// Processa salvamento
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $action = sanitize($_POST['action'] ?? 'save');

    try {
        // Salva dados do fechamento
        $stmt = $pdo->prepare("
            UPDATE fechamentos SET
                lucro_bruto_meta = ?,
                lucro_bruto_google = ?,
                lucro_bruto_taboola = ?,
                lucro_bruto_tiktok = ?,
                faturamento_backend = ?,
                custos_backend = ?,
                custos_terceirizados = ?,
                percentual_imposto = ?,
                percentual_reembolso = ?,
                percentual_outras = ?,
                descricao_outras = ?
            WHERE id = ?
        ");
        $stmt->execute([
            parseMoney($_POST['lucro_bruto_meta'] ?? '0'),
            parseMoney($_POST['lucro_bruto_google'] ?? '0'),
            parseMoney($_POST['lucro_bruto_taboola'] ?? '0'),
            parseMoney($_POST['lucro_bruto_tiktok'] ?? '0'),
            parseMoney($_POST['faturamento_backend'] ?? '0'),
            parseMoney($_POST['custos_backend'] ?? '0'),
            parseMoney($_POST['custos_terceirizados'] ?? '0'),
            floatval(str_replace(',', '.', $_POST['percentual_imposto'] ?? '12')),
            floatval(str_replace(',', '.', $_POST['percentual_reembolso'] ?? '15')),
            floatval(str_replace(',', '.', $_POST['percentual_outras'] ?? '0')),
            sanitize($_POST['descricao_outras'] ?? ''),
            $fechamentoId
        ]);

        // Salva lucros por copy
        $pdo->prepare("DELETE FROM lucro_colaborador WHERE fechamento_id = ?")->execute([$fechamentoId]);

        if (!empty($_POST['lucro_copy'])) {
            $stmtCopy = $pdo->prepare("
                INSERT INTO lucro_colaborador (fechamento_id, colaborador_id, lucro_gerado)
                VALUES (?, ?, ?)
            ");
            foreach ($_POST['lucro_copy'] as $colabId => $valor) {
                if ($valor) {
                    $stmtCopy->execute([$fechamentoId, $colabId, parseMoney($valor)]);
                }
            }
        }

        // Salva bônus manuais
        $pdo->prepare("DELETE FROM bonus_manuais WHERE fechamento_id = ?")->execute([$fechamentoId]);

        if (!empty($_POST['bonus_colaborador'])) {
            $stmtBonus = $pdo->prepare("
                INSERT INTO bonus_manuais (fechamento_id, colaborador_id, valor, motivo)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($_POST['bonus_colaborador'] as $i => $colabId) {
                $valor = parseMoney($_POST['bonus_valor'][$i] ?? '0');
                if ($colabId && $valor > 0) {
                    $stmtBonus->execute([$fechamentoId, $colabId, $valor, sanitize($_POST['bonus_motivo'][$i] ?? '')]);
                }
            }
        }

        if ($action === 'calculate') {
            // Calcula comissões
            $resultado = calcularFechamento($fechamentoId);
            if ($resultado['success']) {
                header('Location: resumo.php?id=' . $fechamentoId);
                exit;
            } else {
                $errors[] = $resultado['error'];
            }
        } else {
            $success = 'Dados salvos com sucesso!';

            // Recarrega dados
            $stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ?");
            $stmt->execute([$fechamentoId]);
            $fechamento = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT colaborador_id, lucro_gerado FROM lucro_colaborador WHERE fechamento_id = ?");
            $stmt->execute([$fechamentoId]);
            $lucrosPorCopy = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmt = $pdo->prepare("SELECT * FROM bonus_manuais WHERE fechamento_id = ?");
            $stmt->execute([$fechamentoId]);
            $bonusManuais = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Erro ao salvar fechamento: " . $e->getMessage());
        $errors[] = 'Erro ao salvar. Tente novamente.';
    }
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Fechamento -
        <?= getMesNome($mes) ?>/
        <?= $ano ?>
    </h1>
    <p class="page-subtitle">
        Status: <span class="badge <?= $fechamento['status'] === 'rascunho' ? 'badge-info' : 'badge-success' ?>">
            <?= ucfirst($fechamento['status']) ?>
        </span>
    </p>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<!-- Wizard Steps -->
<div class="wizard-steps mb-lg">
    <div class="wizard-step active">
        <div class="wizard-step-number">1</div>
        <span class="wizard-step-label">Lucro Tráfego</span>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-number">2</div>
        <span class="wizard-step-label">Lucro Copy</span>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-number">3</div>
        <span class="wizard-step-label">Backend</span>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-number">4</div>
        <span class="wizard-step-label">Deduções</span>
    </div>
    <div class="wizard-step">
        <div class="wizard-step-number">5</div>
        <span class="wizard-step-label">Bônus</span>
    </div>
</div>

<form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <!-- Passo 1: Lucro por Canal -->
    <div class="card mb-lg">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Passo 1 - Lucro Bruto por Canal de Tráfego
            </h3>
        </div>
        <div class="card-body">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Meta (Facebook/Instagram)</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="lucro_bruto_meta" class="form-control with-prefix"
                            value="<?= $fechamento['lucro_bruto_meta'] > 0 ? number_format($fechamento['lucro_bruto_meta'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Google Ads</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="lucro_bruto_google" class="form-control with-prefix"
                            value="<?= $fechamento['lucro_bruto_google'] > 0 ? number_format($fechamento['lucro_bruto_google'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Taboola</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="lucro_bruto_taboola" class="form-control with-prefix"
                            value="<?= $fechamento['lucro_bruto_taboola'] > 0 ? number_format($fechamento['lucro_bruto_taboola'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">TikTok Ads</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="lucro_bruto_tiktok" class="form-control with-prefix"
                            value="<?= $fechamento['lucro_bruto_tiktok'] > 0 ? number_format($fechamento['lucro_bruto_tiktok'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Passo 2: Lucro por Copy -->
    <div class="card mb-lg">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                </svg>
                Passo 2 - Lucro Bruto por Copy
            </h3>
        </div>
        <div class="card-body">
            <?php if (empty($copys)): ?>
                <div class="alert alert-info">
                    Nenhum copy cadastrado. <a href="../colaboradores/form.php">Cadastrar colaborador</a>
                </div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($copys as $copy): ?>
                        <div class="form-group">
                            <label class="form-label">
                                <?= htmlspecialchars($copy['nome']) ?> (
                                <?= $copy['cargo_nome'] ?>)
                            </label>
                            <div class="input-group">
                                <span class="input-prefix">R$</span>
                                <input type="text" name="lucro_copy[<?= $copy['id'] ?>]" class="form-control with-prefix"
                                    value="<?= isset($lucrosPorCopy[$copy['id']]) ? number_format($lucrosPorCopy[$copy['id']], 2, ',', '.') : '' ?>"
                                    placeholder="0,00">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Passo 3: Backend -->
    <div class="card mb-lg">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
                Passo 3 - Backend e Terceirizados
            </h3>
        </div>
        <div class="card-body">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Faturamento Backend</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="faturamento_backend" class="form-control with-prefix"
                            value="<?= $fechamento['faturamento_backend'] > 0 ? number_format($fechamento['faturamento_backend'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Custos Backend</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="custos_backend" class="form-control with-prefix"
                            value="<?= $fechamento['custos_backend'] > 0 ? number_format($fechamento['custos_backend'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Terceirizados (Total)</label>
                    <div class="input-group">
                        <span class="input-prefix">R$</span>
                        <input type="text" name="custos_terceirizados" class="form-control with-prefix"
                            value="<?= $fechamento['custos_terceirizados'] > 0 ? number_format($fechamento['custos_terceirizados'], 2, ',', '.') : '' ?>"
                            placeholder="0,00">
                    </div>
                    <p class="form-hint">Matheus, Paytcall, etc.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Passo 4: Deduções -->
    <div class="card mb-lg">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
                Passo 4 - Percentuais de Dedução
            </h3>
        </div>
        <div class="card-body">
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">% Imposto</label>
                    <div class="input-group">
                        <input type="text" name="percentual_imposto" class="form-control"
                            value="<?= number_format($fechamento['percentual_imposto'], 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">% Reembolso/Chargeback</label>
                    <div class="input-group">
                        <input type="text" name="percentual_reembolso" class="form-control"
                            value="<?= number_format($fechamento['percentual_reembolso'], 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">% Outras Deduções</label>
                    <div class="input-group">
                        <input type="text" name="percentual_outras" class="form-control"
                            value="<?= number_format($fechamento['percentual_outras'], 1, ',', '.') ?>"
                            style="text-align: right; padding-right: 2.5rem;">
                        <span
                            style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);">%</span>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Descrição das Outras Deduções</label>
                <input type="text" name="descricao_outras" class="form-control"
                    value="<?= htmlspecialchars($fechamento['descricao_outras'] ?? '') ?>"
                    placeholder="Descreva se houver outras deduções...">
            </div>
        </div>
    </div>

    <!-- Passo 5: Bônus Manuais -->
    <div class="card mb-lg">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <polygon
                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2">
                    </polygon>
                </svg>
                Passo 5 - Bônus Manuais
            </h3>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addBonus()">
                + Adicionar Bônus
            </button>
        </div>
        <div class="card-body" id="bonus-container">
            <?php if (empty($bonusManuais)): ?>
                <p class="text-muted text-center" id="no-bonus">Nenhum bônus adicionado</p>
            <?php else: ?>
                <?php foreach ($bonusManuais as $i => $bonus): ?>
                    <div class="grid-3 bonus-row" style="align-items: end; margin-bottom: 1rem;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Colaborador</label>
                            <select name="bonus_colaborador[]" class="form-control">
                                <option value="">Selecione...</option>
                                <?php foreach ($colaboradores as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $bonus['colaborador_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Valor</label>
                            <div class="input-group">
                                <span class="input-prefix">R$</span>
                                <input type="text" name="bonus_valor[]" class="form-control with-prefix"
                                    value="<?= number_format($bonus['valor'], 2, ',', '.') ?>">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Motivo</label>
                            <div class="flex gap-sm">
                                <input type="text" name="bonus_motivo[]" class="form-control"
                                    value="<?= htmlspecialchars($bonus['motivo'] ?? '') ?>" placeholder="Motivo...">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeBonus(this)">×</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ações -->
    <div class="flex justify-end gap-md">
        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" name="action" value="save" class="btn btn-secondary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Salvar Rascunho
        </button>
        <button type="submit" name="action" value="calculate" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
                <rect x="9" y="9" width="6" height="6"></rect>
                <line x1="9" y1="1" x2="9" y2="4"></line>
                <line x1="15" y1="1" x2="15" y2="4"></line>
                <line x1="9" y1="20" x2="9" y2="23"></line>
                <line x1="15" y1="20" x2="15" y2="23"></line>
            </svg>
            Calcular Comissões
        </button>
    </div>
</form>

<template id="bonus-template">
    <div class="grid-3 bonus-row" style="align-items: end; margin-bottom: 1rem;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Colaborador</label>
            <select name="bonus_colaborador[]" class="form-control">
                <option value="">Selecione...</option>
                <?php foreach ($colaboradores as $c): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Valor</label>
            <div class="input-group">
                <span class="input-prefix">R$</span>
                <input type="text" name="bonus_valor[]" class="form-control with-prefix" placeholder="0,00">
            </div>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Motivo</label>
            <div class="flex gap-sm">
                <input type="text" name="bonus_motivo[]" class="form-control" placeholder="Motivo...">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeBonus(this)">×</button>
            </div>
        </div>
    </div>
</template>

<script>
    function addBonus() {
        const container = document.getElementById('bonus-container');
        const template = document.getElementById('bonus-template');
        const noBonus = document.getElementById('no-bonus');

        if (noBonus) noBonus.remove();

        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    }

    function removeBonus(btn) {
        btn.closest('.bonus-row').remove();

        const container = document.getElementById('bonus-container');
        if (container.querySelectorAll('.bonus-row').length === 0) {
            container.innerHTML = '<p class="text-muted text-center" id="no-bonus">Nenhum bônus adicionado</p>';
        }
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>