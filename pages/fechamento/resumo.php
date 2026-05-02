<?php
/**
 * FLYNOW - Resumo do Fechamento
 */

$pageTitle = 'Resumo do Fechamento';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

// Busca fechamento
$fechamentoId = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ?");
$stmt->execute([$fechamentoId]);
$fechamento = $stmt->fetch();

if (!$fechamento) {
    header('Location: index.php');
    exit;
}

// Busca pagamentos
$stmt = $pdo->prepare("
    SELECT cm.*, c.nome, c.salario_base, cg.nome as cargo_nome, cg.setor
    FROM comissoes cm
    JOIN colaboradores c ON cm.colaborador_id = c.id
    JOIN cargos cg ON c.cargo_id = cg.id
    WHERE cm.fechamento_id = ?
    ORDER BY cg.setor, c.nome
");
$stmt->execute([$fechamentoId]);
$pagamentos = $stmt->fetchAll();

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

    if ($action === 'aprovar' && canApprovePayments()) {
        $stmt = $pdo->prepare("
            UPDATE fechamentos SET status = 'aprovado', data_aprovacao = NOW(), aprovado_por = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $fechamentoId]);
        logAction('aprovar', 'fechamentos', $fechamentoId);
        $success = 'Fechamento aprovado com sucesso!';
        $fechamento['status'] = 'aprovado';
    } elseif ($action === 'pagar' && canApprovePayments()) {
        $stmt = $pdo->prepare("
            UPDATE fechamentos SET status = 'pago', data_pagamento = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([$fechamentoId]);
        logAction('pagar', 'fechamentos', $fechamentoId);
        $success = 'Fechamento marcado como pago!';
        $fechamento['status'] = 'pago';
    } elseif ($action === 'reabrir' && canApprovePayments()) {
        $stmt = $pdo->prepare("
            UPDATE fechamentos SET status = 'calculado', data_aprovacao = NULL, aprovado_por = NULL, data_pagamento = NULL
            WHERE id = ?
        ");
        $stmt->execute([$fechamentoId]);
        logAction('reabrir', 'fechamentos', $fechamentoId);
        $success = 'Fechamento reaberto para edição.';
        $fechamento['status'] = 'calculado';
    } elseif ($action === 'salvar_ajustes' && canApprovePayments()) {
        // Processa ajustes manuais de salário, base comissão, percentual, comissão e bônus
        $pagamentosAjustes = $_POST['pagamentos'] ?? [];
        $totalSalarios = 0;
        $totalComissoes = 0;
        $totalBonus = 0;

        try {
            $pdo->beginTransaction();

            foreach ($pagamentosAjustes as $pagamentoId => $valores) {
                $pagamentoId = intval($pagamentoId);
                $salario = floatval(str_replace(['.', ','], ['', '.'], $valores['salario'] ?? 0));
                $baseComissao = floatval(str_replace(['.', ','], ['', '.'], $valores['base_comissao'] ?? 0));
                $percentual = floatval(str_replace(['.', ','], ['', '.'], $valores['percentual'] ?? 0));
                $comissao = floatval(str_replace(['.', ','], ['', '.'], $valores['comissao'] ?? 0));
                $bonus = floatval(str_replace(['.', ','], ['', '.'], $valores['bonus'] ?? 0));
                $totalPagar = $salario + $comissao + $bonus;

                $stmt = $pdo->prepare("
                    UPDATE comissoes
                    SET valor_comissao = ?, percentual_aplicado = ?,
                        bonus = ?, valor_liquido = ?
                    WHERE id = ? AND fechamento_id = ?
                ");
                $stmt->execute([$comissao, $percentual, $bonus, $totalPagar, $pagamentoId, $fechamentoId]);

                $totalSalarios += $salario;
                $totalComissoes += $comissao;
                $totalBonus += $bonus;
            }

            // Atualiza totais no fechamento
            $totalFolha = $totalSalarios + $totalComissoes + $totalBonus;
            $percentualFolhaLucro = $fechamento['lucro_liquido_total'] > 0
                ? ($totalFolha / $fechamento['lucro_liquido_total']) * 100
                : 0;

            $stmt = $pdo->prepare("
                UPDATE fechamentos
                SET total_salarios = ?, total_comissoes = ?, total_bonus = ?,
                    total_folha = ?, percentual_folha_lucro = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $totalSalarios, $totalComissoes, $totalBonus,
                $totalFolha, $percentualFolhaLucro, $fechamentoId
            ]);

            $pdo->commit();

            logAction('ajuste_manual', 'fechamentos', $fechamentoId, [
                'total_salarios' => $totalSalarios,
                'total_comissoes' => $totalComissoes,
                'total_bonus' => $totalBonus
            ]);

            $success = 'Valores ajustados com sucesso!';

            // Atualiza os dados locais
            $fechamento['total_salarios'] = $totalSalarios;
            $fechamento['total_comissoes'] = $totalComissoes;
            $fechamento['total_bonus'] = $totalBonus;
            $fechamento['total_folha'] = $totalFolha;
            $fechamento['percentual_folha_lucro'] = $percentualFolhaLucro;

            // Recarrega os pagamentos
            $stmt = $pdo->prepare("
                SELECT cm.*, c.nome, c.salario_base, cg.nome as cargo_nome, cg.setor
                FROM comissoes cm
                JOIN colaboradores c ON cm.colaborador_id = c.id
                JOIN cargos cg ON c.cargo_id = cg.id
                WHERE cm.fechamento_id = ?
                ORDER BY cg.setor, c.nome
            ");
            $stmt->execute([$fechamentoId]);
            $pagamentos = $stmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao salvar ajustes: ' . $e->getMessage();
        }
    }
    }
}

// Modo de edição
$modoEdicao = isset($_GET['editar']) && $_GET['editar'] === '1' && in_array($fechamento['status'], ['calculado', 'aprovado']);

// Agrupa por setor
$porSetor = [];
foreach ($pagamentos as $p) {
    $porSetor[$p['setor']][] = $p;
}
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Fechamento -
            <?= getMesNome($fechamento['mes']) ?>/
            <?= $fechamento['ano'] ?>
        </h1>
        <p class="page-subtitle">
            Status:
            <?php
            $statusClass = match ($fechamento['status']) {
                'rascunho' => 'badge-info',
                'calculado' => 'badge-warning',
                'aprovado' => 'badge-success',
                'pago' => 'badge-success',
                default => 'badge-info'
            };
            ?>
            <span class="badge <?= $statusClass ?>">
                <?= ucfirst($fechamento['status']) ?>
            </span>
        </p>
    </div>
    <div class="flex gap-md">
        <?php if (in_array($fechamento['status'], ['calculado', 'aprovado']) && canApprovePayments()): ?>
            <?php if ($modoEdicao): ?>
                <a href="resumo.php?id=<?= $fechamentoId ?>" class="btn btn-secondary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar Edição
                </a>
            <?php else: ?>
                <a href="resumo.php?id=<?= $fechamentoId ?>&editar=1" class="btn btn-warning">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Ajustar Valores Manualmente
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($fechamento['status'] === 'calculado' && !$modoEdicao): ?>
            <a href="index.php?mes=<?= $fechamento['mes'] ?>&ano=<?= $fechamento['ano'] ?>" class="btn btn-secondary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Editar Dados Base
            </a>
        <?php endif; ?>
    </div>
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

<!-- Resumo Geral -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Lucro Bruto Total</div>
        <div class="stat-value">
            <?= formatMoney($fechamento['lucro_bruto_total']) ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Lucro Líquido Total</div>
        <div class="stat-value" style="color: var(--accent-success);">
            <?= formatMoney($fechamento['lucro_liquido_total']) ?>
        </div>
        <div class="stat-change">Após deduções</div>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total Folha</div>
        <div class="stat-value">
            <?= formatMoney($fechamento['total_folha']) ?>
        </div>
        <div class="stat-change <?= $fechamento['percentual_folha_lucro'] <= 20 ? 'positive' : 'negative' ?>">
            <?= formatPercent($fechamento['percentual_folha_lucro']) ?> do lucro
        </div>
    </div>
</div>

<!-- Detalhamento das Deduções -->
<div class="grid-2 mb-lg">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Lucro por Canal</h3>
        </div>
        <div class="card-body">
            <table style="width: 100%;">
                <tr>
                    <td>Meta</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['lucro_bruto_meta']) ?>
                    </td>
                </tr>
                <tr>
                    <td>Google</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['lucro_bruto_google']) ?>
                    </td>
                </tr>
                <tr>
                    <td>Taboola</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['lucro_bruto_taboola']) ?>
                    </td>
                </tr>
                <tr>
                    <td>TikTok</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['lucro_bruto_tiktok']) ?>
                    </td>
                </tr>
                <tr>
                    <td>Backend</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['faturamento_backend'] - $fechamento['custos_backend']) ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Deduções Aplicadas</h3>
        </div>
        <div class="card-body">
            <table style="width: 100%;">
                <tr>
                    <td>(-) Imposto</td>
                    <td class="text-right text-danger">
                        <?= formatPercent($fechamento['percentual_imposto']) ?>
                    </td>
                </tr>
                <tr>
                    <td>(-) Reembolso/Chargeback</td>
                    <td class="text-right text-danger">
                        <?= formatPercent($fechamento['percentual_reembolso']) ?>
                    </td>
                </tr>
                <?php if ($fechamento['percentual_outras'] > 0): ?>
                    <tr>
                        <td>(-) Outras (
                            <?= htmlspecialchars($fechamento['descricao_outras'] ?: 'N/A') ?>)
                        </td>
                        <td class="text-right text-danger">
                            <?= formatPercent($fechamento['percentual_outras']) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr style="border-top: 1px solid var(--border-color);">
                    <td>Terceirizados</td>
                    <td class="text-right">
                        <?= formatMoney($fechamento['custos_terceirizados']) ?>
                    </td>
                </tr>
            </table>

            <?php if ($fechamento['meta_escalonamento_atingida']): ?>
                <div class="alert alert-success mt-md" style="margin-bottom: 0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    Meta de escalonamento atingida! (+0,5% para Copys)
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Composição da Folha -->
<div class="card mb-lg">
    <div class="card-header">
        <h3 class="card-title">Composição da Folha</h3>
    </div>
    <div class="card-body">
        <div class="grid-3">
            <div style="text-align: center; padding: 1rem;">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Salários</div>
                <div style="font-size: 1.5rem; font-weight: 600;">
                    <?= formatMoney($fechamento['total_salarios']) ?>
                </div>
            </div>
            <div style="text-align: center; padding: 1rem;">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Comissões</div>
                <div style="font-size: 1.5rem; font-weight: 600; color: var(--accent-primary);">
                    <?= formatMoney($fechamento['total_comissoes']) ?>
                </div>
            </div>
            <div style="text-align: center; padding: 1rem;">
                <div style="font-size: 0.875rem; color: var(--text-secondary);">Bônus</div>
                <div style="font-size: 1.5rem; font-weight: 600; color: var(--accent-warning);">
                    <?= formatMoney($fechamento['total_bonus']) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detalhamento por Colaborador -->
<div class="card mb-lg">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Detalhamento por Colaborador</h3>
        <?php if ($modoEdicao): ?>
            <span class="badge badge-warning">Modo Edição Ativo</span>
        <?php endif; ?>
    </div>
    <?php if ($modoEdicao): ?>
    <form method="POST" id="formAjustes">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="salvar_ajustes">
    <?php endif; ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Cargo</th>
                    <th>Setor</th>
                    <th class="text-right">Salário</th>
                    <th class="text-right">% Com.</th>
                    <th class="text-right">Comissão</th>
                    <th class="text-right">Bônus</th>
                    <th class="text-right"><strong>Total</strong></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagamentos as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($p['cargo_nome']) ?></td>
                        <td><span class="badge badge-info"><?= ucfirst($p['setor']) ?></span></td>
                        <td class="text-right">
                            <?php if ($modoEdicao): ?>
                                <input type="text" name="pagamentos[<?= $p['id'] ?>][salario]"
                                    value="<?= number_format($p['salario_base'] ?? 0, 2, ',', '.') ?>"
                                    class="form-control input-money text-right" style="width: 120px;"
                                    data-row="<?= $p['id'] ?>" data-field="salario">
                            <?php else: ?>
                                <?= formatMoney($p['salario_base'] ?? 0) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($modoEdicao): ?>
                                <input type="text" name="pagamentos[<?= $p['id'] ?>][percentual]"
                                    value="<?= number_format($p['percentual_aplicado'] ?? 0, 2, ',', '.') ?>"
                                    class="form-control input-percent text-right" style="width: 80px;"
                                    data-row="<?= $p['id'] ?>" data-field="percentual">
                            <?php else: ?>
                                <?= ($p['percentual_aplicado'] ?? 0) > 0 ? formatPercent($p['percentual_aplicado']) : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right" style="color: var(--accent-primary);">
                            <?php if ($modoEdicao): ?>
                                <span class="comissao-calculada" data-row="<?= $p['id'] ?>">
                                    <?= formatMoney($p['valor_comissao'] ?? 0) ?>
                                </span>
                                <input type="hidden" name="pagamentos[<?= $p['id'] ?>][comissao]"
                                    value="<?= number_format($p['valor_comissao'] ?? 0, 2, ',', '.') ?>"
                                    class="input-comissao-hidden"
                                    data-row="<?= $p['id'] ?>">
                            <?php else: ?>
                                <?= ($p['valor_comissao'] ?? 0) > 0 ? formatMoney($p['valor_comissao']) : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right" style="color: var(--accent-warning);">
                            <?php if ($modoEdicao): ?>
                                <input type="text" name="pagamentos[<?= $p['id'] ?>][bonus]"
                                    value="<?= number_format($p['bonus'] ?? 0, 2, ',', '.') ?>"
                                    class="form-control input-money text-right" style="width: 120px;"
                                    data-row="<?= $p['id'] ?>" data-field="bonus">
                            <?php else: ?>
                                <?= ($p['bonus'] ?? 0) > 0 ? formatMoney($p['bonus']) : '-' ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <strong class="total-row" data-row="<?= $p['id'] ?>">
                                <?= formatMoney($p['valor_liquido'] ?? 0) ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: var(--bg-tertiary);">
                    <td colspan="3"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong id="total-salarios">
                            <?= formatMoney($fechamento['total_salarios']) ?>
                        </strong></td>
                    <td></td>
                    <td class="text-right"><strong id="total-comissoes">
                            <?= formatMoney($fechamento['total_comissoes']) ?>
                        </strong></td>
                    <td class="text-right"><strong id="total-bonus">
                            <?= formatMoney($fechamento['total_bonus']) ?>
                        </strong></td>
                    <td class="text-right" style="font-size: 1.1rem;"><strong id="total-geral">
                            <?= formatMoney($fechamento['total_salarios'] + $fechamento['total_comissoes'] + $fechamento['total_bonus']) ?>
                        </strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if ($modoEdicao): ?>

        <div class="card-body" style="border-top: 1px solid var(--border-color);">
            <div class="flex justify-between items-center">
                <p class="text-muted" style="margin: 0;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; vertical-align: middle;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    Edite a Base Comissão e o % para recalcular a comissão automaticamente. Salário e Bônus também podem ser ajustados.
                </p>
                <button type="submit" class="btn btn-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Salvar Ajustes
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php if ($modoEdicao): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputsMoney = document.querySelectorAll('.input-money');
    const inputsBase = document.querySelectorAll('.input-base');
    const inputsPercent = document.querySelectorAll('.input-percent');

    // Formata valor para moeda brasileira
    function formatMoney(value) {
        return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Converte string formatada para número
    function parseMoneyToNumber(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Recalcula a comissão de uma linha específica
    function recalcularComissaoLinha(rowId) {
        const baseInput = document.querySelector(`.input-base[data-row="${rowId}"]`);
        const percentInput = document.querySelector(`.input-percent[data-row="${rowId}"]`);
        const comissaoSpan = document.querySelector(`.comissao-calculada[data-row="${rowId}"]`);
        const comissaoHidden = document.querySelector(`.input-comissao-hidden[data-row="${rowId}"]`);

        if (baseInput && percentInput && comissaoSpan && comissaoHidden) {
            const base = parseMoneyToNumber(baseInput.value);
            const percentual = parseMoneyToNumber(percentInput.value);
            const comissao = base * (percentual / 100);

            comissaoSpan.textContent = 'R$ ' + formatMoney(comissao);
            comissaoHidden.value = formatMoney(comissao);
        }
    }

    // Recalcula totais da linha e gerais
    function recalcularTotais() {
        let totalSalarios = 0;
        let totalComissoes = 0;
        let totalBonus = 0;

        // Coleta todos os IDs de linha únicos
        const rowIds = new Set();
        inputsMoney.forEach(input => rowIds.add(input.dataset.row));
        inputsBase.forEach(input => rowIds.add(input.dataset.row));

        // Calcula total de cada linha e soma totais gerais
        rowIds.forEach(rowId => {
            const salarioInput = document.querySelector(`.input-money[data-row="${rowId}"][data-field="salario"]`);
            const bonusInput = document.querySelector(`.input-money[data-row="${rowId}"][data-field="bonus"]`);
            const comissaoHidden = document.querySelector(`.input-comissao-hidden[data-row="${rowId}"]`);

            const salario = salarioInput ? parseMoneyToNumber(salarioInput.value) : 0;
            const bonus = bonusInput ? parseMoneyToNumber(bonusInput.value) : 0;
            const comissao = comissaoHidden ? parseMoneyToNumber(comissaoHidden.value) : 0;
            const totalLinha = salario + comissao + bonus;

            totalSalarios += salario;
            totalComissoes += comissao;
            totalBonus += bonus;

            // Atualiza total da linha
            const totalCell = document.querySelector(`.total-row[data-row="${rowId}"]`);
            if (totalCell) {
                totalCell.textContent = 'R$ ' + formatMoney(totalLinha);
            }
        });

        // Atualiza totais gerais
        document.getElementById('total-salarios').textContent = 'R$ ' + formatMoney(totalSalarios);
        document.getElementById('total-comissoes').textContent = 'R$ ' + formatMoney(totalComissoes);
        document.getElementById('total-bonus').textContent = 'R$ ' + formatMoney(totalBonus);
        document.getElementById('total-geral').textContent = 'R$ ' + formatMoney(totalSalarios + totalComissoes + totalBonus);
    }

    // Adiciona evento nos inputs de base comissão e percentual
    inputsBase.forEach(input => {
        input.addEventListener('input', function() {
            recalcularComissaoLinha(this.dataset.row);
            recalcularTotais();
        });

        input.addEventListener('blur', function() {
            const value = parseMoneyToNumber(this.value);
            this.value = formatMoney(value);
        });
    });

    inputsPercent.forEach(input => {
        input.addEventListener('input', function() {
            recalcularComissaoLinha(this.dataset.row);
            recalcularTotais();
        });

        input.addEventListener('blur', function() {
            const value = parseMoneyToNumber(this.value);
            this.value = formatMoney(value);
        });
    });

    // Adiciona evento em cada input de dinheiro (salário e bônus)
    inputsMoney.forEach(input => {
        input.addEventListener('input', function() {
            recalcularTotais();
        });

        input.addEventListener('blur', function() {
            // Formata o valor ao sair do campo
            const value = parseMoneyToNumber(this.value);
            this.value = formatMoney(value);
        });
    });
});
</script>
<?php endif; ?>

<!-- Ações -->
<div class="card">
    <div class="card-body">
        <form method="POST" class="flex justify-between items-center">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="text-muted">
                <?php if ($fechamento['status'] === 'aprovado'): ?>
                    Aprovado em
                    <?= date('d/m/Y H:i', strtotime($fechamento['data_aprovacao'])) ?>
                <?php elseif ($fechamento['status'] === 'pago'): ?>
                    Pago em
                    <?= date('d/m/Y', strtotime($fechamento['data_pagamento'])) ?>
                <?php endif; ?>
            </div>

            <div class="flex gap-md">
                <?php if ($fechamento['status'] === 'calculado' && canApprovePayments()): ?>
                    <button type="submit" name="action" value="aprovar" class="btn btn-success">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                        Aprovar para Pagamento
                    </button>
                <?php endif; ?>

                <?php if ($fechamento['status'] === 'aprovado' && canApprovePayments()): ?>
                    <button type="submit" name="action" value="reabrir" class="btn btn-secondary">
                        Reabrir
                    </button>
                    <button type="submit" name="action" value="pagar" class="btn btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                        Marcar como Pago
                    </button>
                <?php endif; ?>

                <?php if ($fechamento['status'] === 'pago' && canApprovePayments()): ?>
                    <button type="submit" name="action" value="reabrir" class="btn btn-secondary">
                        Reabrir Fechamento
                    </button>
                <?php endif; ?>

                <a href="../relatorios/index.php?fechamento_id=<?= $fechamentoId ?>" class="btn btn-secondary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Exportar
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>