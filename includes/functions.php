<?php
/**
 * FLYNOW - Funções de Cálculo de Comissões
 */

require_once __DIR__ . '/config.php';

/**
 * Calcula lucro líquido após deduções
 * @param float $lucroBruto
 * @param float $percentualImposto
 * @param float $percentualReembolso
 * @param float $percentualOutras
 * @return float
 */
function calcularLucroLiquido($lucroBruto, $percentualImposto, $percentualReembolso, $percentualOutras = 0)
{
    $aposImposto = $lucroBruto * (1 - ($percentualImposto / 100));
    $aposReembolso = $aposImposto * (1 - ($percentualReembolso / 100));
    $lucroLiquido = $aposReembolso * (1 - ($percentualOutras / 100));
    return round($lucroLiquido, 2);
}

/**
 * Verifica se atingiu meta de escalonamento
 * @param float $lucroGeralOperacao
 * @return array ['atingiu' => bool, 'incremento' => float]
 */
function verificarEscalonamento($lucroGeralOperacao)
{
    $pdo = getDB();
    $stmt = $pdo->query("SELECT valor_gatilho, incremento_percentual FROM escalonamento WHERE ativo = TRUE LIMIT 1");
    $escalonamento = $stmt->fetch();

    if (!$escalonamento) {
        return ['atingiu' => false, 'incremento' => 0];
    }

    $atingiu = $lucroGeralOperacao >= $escalonamento['valor_gatilho'];
    return [
        'atingiu' => $atingiu,
        'incremento' => $atingiu ? $escalonamento['incremento_percentual'] : 0
    ];
}

/**
 * Obtém percentual de comissão do colaborador
 * @param array $colaborador
 * @param array $cargo
 * @param bool $aplicarEscalonamento
 * @param float $incrementoEscalonamento
 * @return float
 */
function getPercentualComissao($colaborador, $cargo, $aplicarEscalonamento = false, $incrementoEscalonamento = 0)
{
    // Usa percentual do colaborador se definido, senão usa o default do cargo
    $percentual = $colaborador['percentual_comissao'] ?? $cargo['percentual_comissao_default'] ?? 0;

    // Aplica escalonamento se aplicável ao cargo
    if ($aplicarEscalonamento && $cargo['escalonamento_aplicavel']) {
        $percentual += $incrementoEscalonamento;
    }

    return $percentual;
}

/**
 * Calcula comissão de um colaborador
 * @param float $lucroBase - Lucro líquido base para cálculo
 * @param float $percentual - Percentual de comissão
 * @return float
 */
function calcularComissao($lucroBase, $percentual)
{
    return round($lucroBase * ($percentual / 100), 2);
}

/**
 * Calcula todas as comissões de um fechamento
 * @param int $fechamentoId
 * @return array
 */
function calcularFechamento($fechamentoId)
{
    $pdo = getDB();

    // Busca dados do fechamento
    $stmt = $pdo->prepare("SELECT * FROM fechamentos WHERE id = ?");
    $stmt->execute([$fechamentoId]);
    $fechamento = $stmt->fetch();

    if (!$fechamento) {
        return ['success' => false, 'error' => 'Fechamento não encontrado'];
    }

    // Calcula lucros líquidos por canal
    $deducoes = [
        'imposto' => $fechamento['percentual_imposto'],
        'reembolso' => $fechamento['percentual_reembolso'],
        'outras' => $fechamento['percentual_outras']
    ];

    $lucroLiqMeta = calcularLucroLiquido($fechamento['lucro_bruto_meta'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);
    $lucroLiqGoogle = calcularLucroLiquido($fechamento['lucro_bruto_google'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);
    $lucroLiqTaboola = calcularLucroLiquido($fechamento['lucro_bruto_taboola'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);
    $lucroLiqTiktok = calcularLucroLiquido($fechamento['lucro_bruto_tiktok'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);

    $lucroLiqTrafego = $lucroLiqMeta + $lucroLiqGoogle + $lucroLiqTaboola + $lucroLiqTiktok;

    // Backend
    $lucroLiqBackend = calcularLucroLiquido(
        $fechamento['faturamento_backend'] - $fechamento['custos_backend'],
        $deducoes['imposto'],
        $deducoes['reembolso'],
        $deducoes['outras']
    );

    // Lucro bruto e líquido total
    $lucroBrutoTotal = $fechamento['lucro_bruto_meta'] + $fechamento['lucro_bruto_google'] +
        $fechamento['lucro_bruto_taboola'] + $fechamento['lucro_bruto_tiktok'] +
        ($fechamento['faturamento_backend'] - $fechamento['custos_backend']);

    $lucroLiqTotal = $lucroLiqTrafego + $lucroLiqBackend;

    // Verifica escalonamento
    $escalonamento = verificarEscalonamento($lucroBrutoTotal);

    // Busca lucros por copy
    $stmt = $pdo->prepare("
        SELECT fcl.*, c.nome, c.cargo_id, c.salario_base, c.percentual_comissao as percentual_customizado
        FROM fechamento_colaborador_lucro fcl
        JOIN colaboradores c ON fcl.colaborador_id = c.id
        WHERE fcl.fechamento_id = ?
    ");
    $stmt->execute([$fechamentoId]);
    $lucrosPorCopy = $stmt->fetchAll();

    $lucroLiqCopysTotal = 0;
    foreach ($lucrosPorCopy as $lc) {
        $lucroLiqCopysTotal += calcularLucroLiquido($lc['lucro_bruto_gerado'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);
    }

    // Busca todos os colaboradores ativos
    $stmt = $pdo->query("
        SELECT c.*, cg.nome as cargo_nome, cg.setor, cg.percentual_comissao_default, 
               cg.comissiona, cg.eh_head, cg.escalonamento_aplicavel
        FROM colaboradores c
        JOIN cargos cg ON c.cargo_id = cg.id
        WHERE c.status = 'ativo'
        ORDER BY cg.setor, c.nome
    ");
    $colaboradores = $stmt->fetchAll();

    // Busca bônus manuais
    $stmt = $pdo->prepare("SELECT * FROM bonus_manuais WHERE fechamento_id = ?");
    $stmt->execute([$fechamentoId]);
    $bonusManuais = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // Calcula pagamentos
    $pagamentos = [];
    $totalSalarios = 0;
    $totalComissoes = 0;
    $totalBonus = 0;

    foreach ($colaboradores as $colab) {
        $salario = $colab['salario_base'];
        $comissao = 0;
        $lucroBase = 0;
        $percentual = 0;

        if ($colab['comissiona']) {
            $percentual = getPercentualComissao(
                $colab,
                ['percentual_comissao_default' => $colab['percentual_comissao_default'], 'escalonamento_aplicavel' => $colab['escalonamento_aplicavel']],
                $escalonamento['atingiu'],
                $escalonamento['incremento']
            );

            // Determina base de cálculo conforme cargo
            if ($colab['eh_head']) {
                if ($colab['setor'] === 'trafego') {
                    $lucroBase = $lucroLiqTrafego;
                } elseif ($colab['setor'] === 'copy') {
                    $lucroBase = $lucroLiqCopysTotal;
                } elseif ($colab['setor'] === 'operacoes') {
                    $lucroBase = $lucroLiqTotal;
                }
            } elseif ($colab['setor'] === 'trafego') {
                // Gestor de tráfego - comissiona sobre seu canal
                switch ($colab['canal']) {
                    case 'meta':
                        $lucroBase = $lucroLiqMeta;
                        break;
                    case 'google':
                        $lucroBase = $lucroLiqGoogle;
                        break;
                    case 'taboola':
                        $lucroBase = $lucroLiqTaboola;
                        break;
                    case 'tiktok':
                        $lucroBase = $lucroLiqTiktok;
                        break;
                }
            } elseif ($colab['setor'] === 'copy') {
                // Copy - busca lucro individual
                foreach ($lucrosPorCopy as $lc) {
                    if ($lc['colaborador_id'] == $colab['id']) {
                        $lucroBase = calcularLucroLiquido($lc['lucro_bruto_gerado'], $deducoes['imposto'], $deducoes['reembolso'], $deducoes['outras']);
                        break;
                    }
                }
            }

            $comissao = calcularComissao($lucroBase, $percentual);
        }

        // Bônus
        $bonus = 0;
        $motivoBonus = '';
        if (isset($bonusManuais[$colab['id']])) {
            foreach ($bonusManuais[$colab['id']] as $b) {
                $bonus += $b['valor'];
                $motivoBonus = $b['motivo'];
            }
        }

        $totalPagar = $salario + $comissao + $bonus;

        $pagamentos[] = [
            'colaborador_id' => $colab['id'],
            'nome' => $colab['nome'],
            'cargo' => $colab['cargo_nome'],
            'setor' => $colab['setor'],
            'salario_base' => $salario,
            'lucro_base_comissao' => $lucroBase,
            'percentual_comissao' => $percentual,
            'valor_comissao' => $comissao,
            'valor_bonus' => $bonus,
            'motivo_bonus' => $motivoBonus,
            'total_pagar' => $totalPagar
        ];

        $totalSalarios += $salario;
        $totalComissoes += $comissao;
        $totalBonus += $bonus;
    }

    $totalFolha = $totalSalarios + $totalComissoes + $totalBonus + $fechamento['custos_terceirizados'];
    $percentualFolhaLucro = $lucroLiqTotal > 0 ? round(($totalFolha / $lucroLiqTotal) * 100, 2) : 0;

    // Atualiza fechamento
    $stmt = $pdo->prepare("
        UPDATE fechamentos SET
            lucro_bruto_total = ?,
            lucro_liquido_total = ?,
            total_salarios = ?,
            total_comissoes = ?,
            total_bonus = ?,
            total_folha = ?,
            percentual_folha_lucro = ?,
            meta_escalonamento_atingida = ?,
            status = 'calculado',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $lucroBrutoTotal,
        $lucroLiqTotal,
        $totalSalarios,
        $totalComissoes,
        $totalBonus,
        $totalFolha,
        $percentualFolhaLucro,
        $escalonamento['atingiu'],
        $fechamentoId
    ]);

    // Remove pagamentos antigos e insere novos
    $pdo->prepare("DELETE FROM pagamentos WHERE fechamento_id = ?")->execute([$fechamentoId]);

    $stmtPag = $pdo->prepare("
        INSERT INTO pagamentos (fechamento_id, colaborador_id, salario_base, lucro_base_comissao, 
                                percentual_comissao, valor_comissao, valor_bonus, motivo_bonus, total_pagar)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($pagamentos as $pag) {
        $stmtPag->execute([
            $fechamentoId,
            $pag['colaborador_id'],
            $pag['salario_base'],
            $pag['lucro_base_comissao'],
            $pag['percentual_comissao'],
            $pag['valor_comissao'],
            $pag['valor_bonus'],
            $pag['motivo_bonus'],
            $pag['total_pagar']
        ]);
    }

    logAction('calcular', 'fechamentos', $fechamentoId);

    return [
        'success' => true,
        'resumo' => [
            'lucro_bruto_total' => $lucroBrutoTotal,
            'lucro_liquido_total' => $lucroLiqTotal,
            'lucro_liquido_trafego' => $lucroLiqTrafego,
            'lucro_liquido_backend' => $lucroLiqBackend,
            'lucro_liquido_meta' => $lucroLiqMeta,
            'lucro_liquido_google' => $lucroLiqGoogle,
            'lucro_liquido_taboola' => $lucroLiqTaboola,
            'lucro_liquido_tiktok' => $lucroLiqTiktok,
            'total_salarios' => $totalSalarios,
            'total_comissoes' => $totalComissoes,
            'total_bonus' => $totalBonus,
            'total_terceirizados' => $fechamento['custos_terceirizados'],
            'total_folha' => $totalFolha,
            'percentual_folha_lucro' => $percentualFolhaLucro,
            'escalonamento_atingido' => $escalonamento['atingiu']
        ],
        'pagamentos' => $pagamentos
    ];
}

/**
 * Obtém lista de cargos
 */
function getCargos()
{
    $pdo = getDB();
    return $pdo->query("SELECT * FROM cargos ORDER BY setor, nome")->fetchAll();
}

/**
 * Obtém colaboradores por setor/cargo
 */
function getColaboradores($filtros = [])
{
    $pdo = getDB();

    $sql = "
        SELECT c.*, cg.nome as cargo_nome, cg.setor
        FROM colaboradores c
        JOIN cargos cg ON c.cargo_id = cg.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($filtros['status'])) {
        $sql .= " AND c.status = ?";
        $params[] = $filtros['status'];
    }

    if (!empty($filtros['setor'])) {
        $sql .= " AND cg.setor = ?";
        $params[] = $filtros['setor'];
    }

    if (!empty($filtros['cargo_id'])) {
        $sql .= " AND c.cargo_id = ?";
        $params[] = $filtros['cargo_id'];
    }

    $sql .= " ORDER BY c.nome";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Obtém colaboradores que comissionam (para fechamento)
 */
function getColaboradoresQueComissionam()
{
    $pdo = getDB();
    return $pdo->query("
        SELECT c.*, cg.nome as cargo_nome, cg.setor, cg.comissiona, cg.eh_head
        FROM colaboradores c
        JOIN cargos cg ON c.cargo_id = cg.id
        WHERE c.status = 'ativo' AND cg.comissiona = TRUE
        ORDER BY cg.setor, c.nome
    ")->fetchAll();
}

/**
 * Obtém copys ativos (para input de lucro)
 */
function getCopysAtivos()
{
    $pdo = getDB();
    return $pdo->query("
        SELECT c.*, cg.nome as cargo_nome
        FROM colaboradores c
        JOIN cargos cg ON c.cargo_id = cg.id
        WHERE c.status = 'ativo' AND cg.setor = 'copy' AND cg.eh_head = FALSE
        ORDER BY c.nome
    ")->fetchAll();
}
