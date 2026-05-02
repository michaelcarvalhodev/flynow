<?php
/**
 * FLYNOW - Lista de Colaboradores
 */

$pageTitle = 'Colaboradores';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Filtros - sanitiza inputs
$filtros = [
    'status' => sanitize($_GET['status'] ?? ''),
    'setor' => sanitize($_GET['setor'] ?? ''),
    'cargo_id' => intval($_GET['cargo_id'] ?? 0) ?: ''
];

$colaboradores = getColaboradores($filtros);
$cargos = getCargos();
?>

<div class="page-header flex justify-between items-center">
    <div>
        <h1 class="page-title">Colaboradores</h1>
        <p class="page-subtitle">Gerencie a equipe e suas configurações</p>
    </div>
    <a href="form.php" class="btn btn-primary">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Novo Colaborador
    </a>
</div>

<!-- Filtros -->
<div class="card mb-lg">
    <form method="GET" class="flex gap-md items-center" style="flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
            <select name="status" class="form-control" style="padding: 0.5rem;">
                <option value="">Todos os status</option>
                <option value="ativo" <?= $filtros['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                <option value="inativo" <?= $filtros['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                <option value="em_teste" <?= $filtros['status'] === 'em_teste' ? 'selected' : '' ?>>Em Teste</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
            <select name="setor" class="form-control" style="padding: 0.5rem;">
                <option value="">Todos os setores</option>
                <option value="trafego" <?= $filtros['setor'] === 'trafego' ? 'selected' : '' ?>>Tráfego</option>
                <option value="copy" <?= $filtros['setor'] === 'copy' ? 'selected' : '' ?>>Copy</option>
                <option value="operacoes" <?= $filtros['setor'] === 'operacoes' ? 'selected' : '' ?>>Operações</option>
                <option value="backend" <?= $filtros['setor'] === 'backend' ? 'selected' : '' ?>>Backend</option>
                <option value="outro" <?= $filtros['setor'] === 'outro' ? 'selected' : '' ?>>Outro</option>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <select name="cargo_id" class="form-control" style="padding: 0.5rem;">
                <option value="">Todos os cargos</option>
                <?php foreach ($cargos as $cargo): ?>
                    <option value="<?= $cargo['id'] ?>" <?= $filtros['cargo_id'] == $cargo['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cargo['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>

        <?php if (!empty($filtros['status']) || !empty($filtros['setor']) || !empty($filtros['cargo_id'])): ?>
            <a href="index.php" class="btn btn-ghost btn-sm">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<!-- Lista -->
<div class="card">
    <?php if (empty($colaboradores)): ?>
        <div class="text-center" style="padding: 3rem;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <p class="text-muted mt-md">Nenhum colaborador encontrado</p>
            <a href="form.php" class="btn btn-primary mt-md">Cadastrar Primeiro Colaborador</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Cargo</th>
                        <th>Setor</th>
                        <th>Canal</th>
                        <th>Salário Base</th>
                        <th>% Comissão</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colaboradores as $colab): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($colab['nome']) ?>
                                </strong>
                                <?php if ($colab['cpf']): ?>
                                    <br><small class="text-muted">
                                        <?= htmlspecialchars($colab['cpf'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($colab['cargo_nome']) ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?= ucfirst($colab['setor']) ?>
                                </span>
                            </td>
                            <td>
                                <?= $colab['canal'] ? ucfirst($colab['canal']) : '-' ?>
                            </td>
                            <td>
                                <?= formatMoney($colab['salario_base']) ?>
                            </td>
                            <td>
                                <?= $colab['percentual_comissao'] ? formatPercent($colab['percentual_comissao']) : 'Padrão' ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match ($colab['status']) {
                                    'ativo' => 'badge-success',
                                    'inativo' => 'badge-danger',
                                    'em_teste' => 'badge-warning',
                                    default => 'badge-info'
                                };
                                $statusLabel = match ($colab['status']) {
                                    'ativo' => 'Ativo',
                                    'inativo' => 'Inativo',
                                    'em_teste' => 'Em Teste',
                                    default => $colab['status']
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-sm">
                                    <a href="form.php?id=<?= $colab['id'] ?>" class="btn btn-ghost btn-sm" title="Editar">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div
            style="padding: 1rem; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.875rem;">
            Total:
            <?= count($colaboradores) ?> colaborador(es)
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>