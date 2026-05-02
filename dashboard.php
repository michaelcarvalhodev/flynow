<?php
/**
 * FLYNOW - Dashboard Principal
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

// Dados do dashboard
$pdo = getDB();

// Contagem de colaboradores ativos
$stmtColab = $pdo->query("SELECT COUNT(*) as total FROM colaboradores WHERE status = 'ativo'");
$totalColaboradores = $stmtColab->fetch()['total'];

// Último fechamento
$stmtUltimo = $pdo->query("
    SELECT * FROM fechamentos 
    WHERE status IN ('calculado', 'aprovado', 'pago') 
    ORDER BY ano DESC, mes DESC 
    LIMIT 1
");
$ultimoFechamento = $stmtUltimo->fetch();

// Fechamentos do ano atual
$anoAtual = date('Y');
$stmtAno = $pdo->prepare("
    SELECT mes, lucro_liquido_total, total_folha, percentual_folha_lucro, status
    FROM fechamentos 
    WHERE ano = ?
    ORDER BY mes
");
$stmtAno->execute([$anoAtual]);
$fechamentosAno = $stmtAno->fetchAll();

// Verifica se precisa fechar mês anterior
$mesAnterior = date('n') - 1;
$anoAnterior = $anoAtual;
if ($mesAnterior == 0) {
    $mesAnterior = 12;
    $anoAnterior--;
}

$stmtPendente = $pdo->prepare("SELECT id, status FROM fechamentos WHERE mes = ? AND ano = ?");
$stmtPendente->execute([$mesAnterior, $anoAnterior]);
$fechamentoPendente = $stmtPendente->fetch();

$alertaFechamento = false;
if (!$fechamentoPendente || $fechamentoPendente['status'] === 'rascunho') {
    $alertaFechamento = true;
}
?>

<?php if ($alertaFechamento): ?>
    <div class="alert alert-warning">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <div>
            <strong>Atenção!</strong> O fechamento de
            <?= getMesNome($mesAnterior) ?>/
            <?= $anoAnterior ?> está pendente.
            <a href="<?= SITE_URL ?>/pages/fechamento/index.php?mes=<?= $mesAnterior ?>&ano=<?= $anoAnterior ?>"
                class="btn btn-sm btn-primary" style="margin-left: 1rem;">
                Iniciar Fechamento
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Lucro Líquido (Último Mês)</div>
        <div class="stat-value">
            <?= $ultimoFechamento ? formatMoney($ultimoFechamento['lucro_liquido_total']) : 'R$ 0,00' ?>
        </div>
        <?php if ($ultimoFechamento): ?>
            <div class="stat-change">
                <?= getMesNome($ultimoFechamento['mes']) ?>/
                <?= $ultimoFechamento['ano'] ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="stat-label">Total Folha (Último Mês)</div>
        <div class="stat-value">
            <?= $ultimoFechamento ? formatMoney($ultimoFechamento['total_folha']) : 'R$ 0,00' ?>
        </div>
        <?php if ($ultimoFechamento): ?>
            <div class="stat-change <?= $ultimoFechamento['percentual_folha_lucro'] <= 20 ? 'positive' : 'negative' ?>">
                <?= formatPercent($ultimoFechamento['percentual_folha_lucro']) ?> do lucro
            </div>
        <?php endif; ?>
    </div>

    <div class="stat-card success">
        <div class="stat-label">Colaboradores Ativos</div>
        <div class="stat-value">
            <?= $totalColaboradores ?>
        </div>
        <div class="stat-change">cadastrados no sistema</div>
    </div>
</div>

<!-- Evolução Anual -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Evolução
            <?= $anoAtual ?>
        </h3>
        <a href="<?= SITE_URL ?>/pages/historico/index.php" class="btn btn-secondary btn-sm">
            Ver Histórico Completo
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($fechamentosAno)): ?>
            <div class="text-center" style="padding: 3rem;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <p class="text-muted mt-md">Nenhum fechamento realizado em
                    <?= $anoAtual ?>
                </p>
                <a href="<?= SITE_URL ?>/pages/fechamento/index.php" class="btn btn-primary mt-md">
                    Iniciar Primeiro Fechamento
                </a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Mês</th>
                            <th>Lucro Líquido</th>
                            <th>Total Folha</th>
                            <th>% Folha/Lucro</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fechamentosAno as $f): ?>
                            <tr>
                                <td><strong>
                                        <?= getMesNome($f['mes']) ?>
                                    </strong></td>
                                <td>
                                    <?= formatMoney($f['lucro_liquido_total']) ?>
                                </td>
                                <td>
                                    <?= formatMoney($f['total_folha']) ?>
                                </td>
                                <td>
                                    <span class="<?= $f['percentual_folha_lucro'] <= 20 ? 'text-success' : 'text-warning' ?>">
                                        <?= formatPercent($f['percentual_folha_lucro']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match ($f['status']) {
                                        'rascunho' => 'badge-info',
                                        'calculado' => 'badge-warning',
                                        'aprovado' => 'badge-success',
                                        'pago' => 'badge-success',
                                        default => 'badge-info'
                                    };
                                    $statusLabel = match ($f['status']) {
                                        'rascunho' => 'Rascunho',
                                        'calculado' => 'Calculado',
                                        'aprovado' => 'Aprovado',
                                        'pago' => 'Pago',
                                        default => $f['status']
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= $statusLabel ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid-3 mt-lg">
    <a href="<?= SITE_URL ?>/pages/colaboradores/form.php" class="card" style="text-decoration: none;">
        <div class="flex items-center gap-md">
            <div
                style="width: 48px; height: 48px; background: var(--gradient-primary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
            </div>
            <div>
                <h4>Novo Colaborador</h4>
                <p class="text-muted" style="font-size: 0.875rem;">Cadastrar novo membro</p>
            </div>
        </div>
    </a>

    <a href="<?= SITE_URL ?>/pages/fechamento/index.php" class="card" style="text-decoration: none;">
        <div class="flex items-center gap-md">
            <div
                style="width: 48px; height: 48px; background: var(--gradient-success); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path
                        d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                </svg>
            </div>
            <div>
                <h4>Fechamento</h4>
                <p class="text-muted" style="font-size: 0.875rem;">Calcular comissões</p>
            </div>
        </div>
    </a>

    <a href="<?= SITE_URL ?>/pages/relatorios/index.php" class="card" style="text-decoration: none;">
        <div class="flex items-center gap-md">
            <div
                style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
            </div>
            <div>
                <h4>Relatórios</h4>
                <p class="text-muted" style="font-size: 0.875rem;">Exportar dados</p>
            </div>
        </div>
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>