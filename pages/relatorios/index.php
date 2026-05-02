<?php
/**
 * FLYNOW - Central de Relatórios
 */

require_once __DIR__ . '/../../includes/functions.php';

$pdo = getDB();

// ========================================
// PROCESSA EXPORTAÇÃO ANTES DE QUALQUER HTML
// ========================================
if (isset($_GET['export'])) {
    $fechamentoId = intval($_GET['fechamento_id'] ?? 0);
    $format = $_GET['format'] ?? 'csv';

    if ($fechamentoId > 0 && $format === 'csv') {
        // Busca dados
        $stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ?");
        $stmt->execute([$fechamentoId]);
        $fechamento = $stmt->fetch();

        if ($fechamento) {
            $stmt = $pdo->prepare("
                SELECT cm.*, c.nome, c.cpf, c.salario_base, cg.nome as cargo_nome
                FROM comissoes cm
                JOIN colaboradores c ON cm.colaborador_id = c.id
                JOIN cargos cg ON c.cargo_id = cg.id
                WHERE cm.fechamento_id = ?
                ORDER BY c.nome
            ");
            $stmt->execute([$fechamentoId]);
            $comissoes = $stmt->fetchAll();

            // HEADERS E DOWNLOAD
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="folha_' . getMesNome($fechamento['mes']) . '_' . $fechamento['ano'] . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

            // Cabeçalho
            fputcsv($output, ['Nome', 'CPF', 'Cargo', 'Salário Base', '% Comissão', 'Valor Comissão', 'Bônus', 'Deduções', 'Total Líquido'], ';');

            foreach ($comissoes as $cm) {
                fputcsv($output, [
                    $cm['nome'],
                    $cm['cpf'] ?? '',
                    $cm['cargo_nome'],
                    number_format($cm['salario_base'], 2, ',', '.'),
                    number_format($cm['percentual_aplicado'], 2, ',', '.') . '%',
                    number_format($cm['valor_comissao'], 2, ',', '.'),
                    number_format($cm['bonus'], 2, ',', '.'),
                    number_format($cm['deducoes'] ?? 0, 2, ',', '.'),
                    number_format($cm['valor_liquido'], 2, ',', '.')
                ], ';');
            }

            // Totais
            fputcsv($output, [], ';');
            fputcsv($output, [
                'TOTAIS',
                '',
                '',
                number_format($fechamento['total_salarios'], 2, ',', '.'),
                '',
                number_format($fechamento['total_comissoes'], 2, ',', '.'),
                number_format($fechamento['total_bonus'], 2, ',', '.'),
                '',
                number_format($fechamento['total_folha'], 2, ',', '.')
            ], ';');

            fclose($output);
            exit; // CRITICAL: Para execução aqui
        }
    }
}

// ========================================
// AGORA SIM CARREGA O HTML
// ========================================
$pageTitle = 'Relatórios';
require_once __DIR__ . '/../../includes/header.php';

// Busca fechamentos para export
$fechamentos = $pdo->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM comissoes WHERE fechamento_id = f.id) as total_colaboradores
    FROM fechamentos f
    WHERE f.status IN ('calculado', 'aprovado', 'pago')
    ORDER BY f.ano DESC, f.mes DESC
")->fetchAll();
?>

<div class="page-header">
    <h1 class="page-title">Central de Relatórios</h1>
    <p class="page-subtitle">Exporte dados de fechamentos</p>
</div>

<div class="grid-2 mb-lg">
    <!-- Exportar Folha Mensal -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                Folha Mensal
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-lg">Exporte a folha de pagamento completa de um mês específico.</p>

            <form method="GET" action="">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="format" value="csv">

                <div class="form-group">
                    <label class="form-label">Selecione o Fechamento</label>
                    <select name="fechamento_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($fechamentos as $f): ?>
                            <option value="<?= $f['id'] ?>">
                                <?= getMesNome($f['mes']) ?>/<?= $f['ano'] ?> -
                                <?= ucfirst($f['status']) ?> (<?= $f['total_colaboradores'] ?> colaboradores)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Exportar CSV
                </button>
            </form>
        </div>
    </div>

    <!-- Resumo Anual -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    style="margin-right: 0.5rem;">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                Resumo Anual
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-lg">Exporte o resumo consolidado de todos os meses do ano.</p>

            <form method="GET" action="">
                <input type="hidden" name="export" value="1">
                <input type="hidden" name="format" value="csv">
                <input type="hidden" name="type" value="annual">

                <div class="form-group">
                    <label class="form-label">Ano</label>
                    <select name="ano" class="form-control">
                        <?php
                        $anos = array_unique(array_column($fechamentos, 'ano'));
                        foreach ($anos as $a):
                        ?>
                            <option value="<?= $a ?>"><?= $a ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-block">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Exportar Resumo
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Últimos Fechamentos -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Fechamentos Disponíveis para Exportação</h3>
    </div>
    <?php if (empty($fechamentos)): ?>
        <div class="card-body text-center" style="padding: 2rem;">
            <p class="text-muted">Nenhum fechamento calculado ainda.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th class="text-right">Lucro Líquido</th>
                        <th class="text-right">Total Folha</th>
                        <th>Colaboradores</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fechamentos as $f): ?>
                        <tr>
                            <td><strong><?= getMesNome($f['mes']) ?>/<?= $f['ano'] ?></strong></td>
                            <td class="text-right"><?= formatMoney($f['lucro_liquido_total']) ?></td>
                            <td class="text-right"><?= formatMoney($f['total_folha']) ?></td>
                            <td><?= $f['total_colaboradores'] ?></td>
                            <td>
                                <?php
                                $statusClass = match ($f['status']) {
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
                                    <a href="?export=1&format=csv&fechamento_id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm"
                                        title="Exportar CSV">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                    </a>
                                    <a href="../fechamento/resumo.php?id=<?= $f['id'] ?>" class="btn btn-ghost btn-sm"
                                        title="Ver Resumo">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
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