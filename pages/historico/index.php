<?php
/**
 * FLYNOW - Histórico de Fechamentos
 */

$pageTitle = 'Histórico';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

// Ano selecionado
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

// Anos disponíveis
$anos = $pdo->query("SELECT DISTINCT ano FROM fechamentos ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($anos)) {
    $anos = [date('Y')];
}

// Fechamentos do ano
$stmt = $pdo->prepare("
    SELECT * FROM fechamentos 
    WHERE ano = ? 
    ORDER BY mes DESC
");
$stmt->execute([$ano]);
$fechamentos = $stmt->fetchAll();

// Totais do ano
$stmt = $pdo->prepare("
    SELECT 
        SUM(lucro_liquido_total) as total_lucro,
        SUM(total_folha) as total_folha,
        AVG(percentual_folha_lucro) as media_percentual
    FROM fechamentos 
    WHERE ano = ? AND status IN ('calculado', 'aprovado', 'pago')
");
$stmt->execute([$ano]);
$totaisAno = $stmt->fetch();
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Histórico de Fechamentos</h1>
        <p class="page-subtitle">Visualize todos os fechamentos realizados</p>
    </div>

    <form method="GET" class="flex gap-md items-center">
        <select name="ano" class="form-control" onchange="this.form.submit()" style="width: auto;">
            <?php foreach ($anos as $a): ?>
                <option value="<?= $a ?>" <?= $a == $ano ? 'selected' : '' ?>>
                    <?= $a ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Resumo do Ano -->
<?php if ($totaisAno['total_lucro']): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Lucro Líquido
                <?= $ano ?>
            </div>
            <div class="stat-value">
                <?= formatMoney($totaisAno['total_lucro']) ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Folha
                <?= $ano ?>
            </div>
            <div class="stat-value">
                <?= formatMoney($totaisAno['total_folha']) ?>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-label">Média % Folha/Lucro</div>
            <div class="stat-value">
                <?= $totaisAno['media_percentual'] ? formatPercent($totaisAno['media_percentual']) : 'N/A' ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Lista de Fechamentos -->
<div class="card">
    <?php if (empty($fechamentos)): ?>
        <div class="text-center" style="padding: 3rem;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <p class="text-muted mt-md">Nenhum fechamento encontrado em
                <?= $ano ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th class="text-right">Lucro Bruto</th>
                        <th class="text-right">Lucro Líquido</th>
                        <th class="text-right">Total Folha</th>
                        <th class="text-right">% Folha/Lucro</th>
                        <th>Escalonamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fechamentos as $f): ?>
                        <tr>
                            <td><strong>
                                    <?= getMesNome($f['mes']) ?>
                                </strong></td>
                            <td class="text-right">
                                <?= formatMoney($f['lucro_bruto_total']) ?>
                            </td>
                            <td class="text-right" style="color: var(--accent-success);">
                                <?= formatMoney($f['lucro_liquido_total']) ?>
                            </td>
                            <td class="text-right">
                                <?= formatMoney($f['total_folha']) ?>
                            </td>
                            <td class="text-right">
                                <span class="<?= $f['percentual_folha_lucro'] <= 20 ? 'text-success' : 'text-warning' ?>">
                                    <?= formatPercent($f['percentual_folha_lucro'] ?? 0) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($f['meta_escalonamento_atingida']): ?>
                                    <span class="badge badge-success">Atingida</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match ($f['status']) {
                                    'rascunho' => 'badge-info',
                                    'calculado' => 'badge-warning',
                                    'aprovado' => 'badge-success',
                                    'pago' => 'badge-success',
                                    default => 'badge-info'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>">
                                    <?= ucfirst($f['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-sm">
                                    <?php if ($f['status'] === 'rascunho'): ?>
                                        <a href="../fechamento/index.php?mes=<?= $f['mes'] ?>&ano=<?= $f['ano'] ?>"
                                            class="btn btn-ghost btn-sm" title="Continuar">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <a href="../fechamento/resumo.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm"
                                            title="Ver Resumo">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>