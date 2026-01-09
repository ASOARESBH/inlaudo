<?php
session_start();
require_once 'config.php';
require_once 'funcoes_auxiliares.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

header('Content-Type: application/json');

try {
    $tipo = $_POST['tipo_relatorio'] ?? '';
    $dataInicial = $_POST['data_inicial'] ?? '';
    $dataFinal = $_POST['data_final'] ?? '';

    if (empty($tipo)) {
        throw new Exception('Tipo de relatório não informado');
    }

    // Validar datas
    if (!empty($dataInicial) && !DateTime::createFromFormat('Y-m-d', $dataInicial)) {
        throw new Exception('Data inicial inválida');
    }

    if (!empty($dataFinal) && !DateTime::createFromFormat('Y-m-d', $dataFinal)) {
        throw new Exception('Data final inválida');
    }

    $response = [];

    switch ($tipo) {
        case 'clientes':
            $response = gerarRelatorioClientes($conn, $dataInicial, $dataFinal);
            break;

        case 'contratos':
            $response = gerarRelatorioContratos($conn, $dataInicial, $dataFinal);
            break;

        case 'contas_pagar':
            $response = gerarRelatorioContasPagar($conn, $dataInicial, $dataFinal);
            break;

        case 'contas_vencer':
            $response = gerarRelatorioContasVencer($conn, $dataInicial, $dataFinal);
            break;

        default:
            throw new Exception('Tipo de relatório inválido');
    }

    $response['success'] = true;
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Relatório de Clientes
 */
function gerarRelatorioClientes($conn, $dataInicial, $dataFinal) {
    $sql = "SELECT 
                c.id,
                c.nome_razao_social,
                c.cpf_cnpj,
                c.email,
                c.telefone,
                c.cidade,
                c.estado,
                c.data_cadastro,
                COUNT(DISTINCT co.id) as total_contratos,
                COALESCE(SUM(co.valor_mensal), 0) as valor_total_contratos
            FROM clientes c
            LEFT JOIN contratos co ON c.id = co.cliente_id";

    $params = [];
    $where = [];

    if (!empty($dataInicial) && !empty($dataFinal)) {
        $where[] = "c.data_cadastro BETWEEN ? AND ?";
        $params[] = $dataInicial;
        $params[] = $dataFinal;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " GROUP BY c.id ORDER BY c.nome_razao_social ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados
    $dadosFormatados = [];
    $totalClientes = count($dados);
    $totalContratos = 0;
    $valorTotal = 0;

    foreach ($dados as $row) {
        $totalContratos += $row['total_contratos'];
        $valorTotal += $row['valor_total_contratos'];

        $dadosFormatados[] = [
            'ID' => $row['id'],
            'Nome/Razão Social' => $row['nome_razao_social'],
            'CPF/CNPJ' => formatarCpfCnpj($row['cpf_cnpj']),
            'E-mail' => $row['email'],
            'Telefone' => formatarTelefone($row['telefone']),
            'Cidade/UF' => $row['cidade'] . '/' . $row['estado'],
            'Data Cadastro' => formatarData($row['data_cadastro']),
            'Contratos' => $row['total_contratos'],
            'Valor Total' => formatarMoeda($row['valor_total_contratos'])
        ];
    }

    return [
        'titulo' => 'Relatório de Clientes',
        'colunas' => ['ID', 'Nome/Razão Social', 'CPF/CNPJ', 'E-mail', 'Telefone', 'Cidade/UF', 'Data Cadastro', 'Contratos', 'Valor Total'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Clientes' => $totalClientes,
            'Total de Contratos' => $totalContratos,
            'Valor Total' => formatarMoeda($valorTotal)
        ]
    ];
}

/**
 * Relatório de Contratos
 */
function gerarRelatorioContratos($conn, $dataInicial, $dataFinal) {
    $sql = "SELECT 
                co.id,
                co.numero_contrato,
                c.nome_razao_social as cliente,
                c.cpf_cnpj,
                co.descricao,
                co.valor_mensal,
                co.data_inicio,
                co.data_fim,
                co.status,
                co.data_criacao
            FROM contratos co
            INNER JOIN clientes c ON co.cliente_id = c.id";

    $params = [];
    $where = [];

    if (!empty($dataInicial) && !empty($dataFinal)) {
        $where[] = "co.data_criacao BETWEEN ? AND ?";
        $params[] = $dataInicial;
        $params[] = $dataFinal;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY co.data_criacao DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados
    $dadosFormatados = [];
    $totalContratos = count($dados);
    $contratosAtivos = 0;
    $valorTotal = 0;

    foreach ($dados as $row) {
        if ($row['status'] === 'ativo') {
            $contratosAtivos++;
        }
        $valorTotal += $row['valor_mensal'];

        $dadosFormatados[] = [
            'ID' => $row['id'],
            'Nº Contrato' => $row['numero_contrato'],
            'Cliente' => $row['cliente'],
            'CPF/CNPJ' => formatarCpfCnpj($row['cpf_cnpj']),
            'Descrição' => $row['descricao'],
            'Valor Mensal' => formatarMoeda($row['valor_mensal']),
            'Data Início' => formatarData($row['data_inicio']),
            'Data Fim' => $row['data_fim'] ? formatarData($row['data_fim']) : 'Indeterminado',
            'Status' => ucfirst($row['status']),
            'Data Criação' => formatarData($row['data_criacao'])
        ];
    }

    return [
        'titulo' => 'Relatório de Contratos',
        'colunas' => ['ID', 'Nº Contrato', 'Cliente', 'CPF/CNPJ', 'Descrição', 'Valor Mensal', 'Data Início', 'Data Fim', 'Status', 'Data Criação'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Contratos' => $totalContratos,
            'Contratos Ativos' => $contratosAtivos,
            'Valor Mensal Total' => formatarMoeda($valorTotal)
        ]
    ];
}

/**
 * Relatório de Contas a Pagar
 */
function gerarRelatorioContasPagar($conn, $dataInicial, $dataFinal) {
    $sql = "SELECT 
                cp.id,
                c.nome_razao_social as cliente,
                co.numero_contrato,
                cp.descricao,
                cp.valor,
                cp.valor_pago,
                cp.data_vencimento,
                cp.data_pagamento,
                cp.status,
                cp.gateway,
                cp.payment_id
            FROM contas_pagar cp
            INNER JOIN contratos co ON cp.contrato_id = co.id
            INNER JOIN clientes c ON co.cliente_id = c.id";

    $params = [];
    $where = [];

    if (!empty($dataInicial) && !empty($dataFinal)) {
        $where[] = "cp.data_vencimento BETWEEN ? AND ?";
        $params[] = $dataInicial;
        $params[] = $dataFinal;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY cp.data_vencimento ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados
    $dadosFormatados = [];
    $totalContas = count($dados);
    $totalPago = 0;
    $totalPendente = 0;
    $totalVencido = 0;
    $contasPagas = 0;
    $contasPendentes = 0;
    $contasVencidas = 0;

    $hoje = date('Y-m-d');

    foreach ($dados as $row) {
        $status = $row['status'];
        
        if ($status === 'pago') {
            $contasPagas++;
            $totalPago += $row['valor_pago'];
        } else {
            if ($row['data_vencimento'] < $hoje) {
                $contasVencidas++;
                $totalVencido += $row['valor'];
            } else {
                $contasPendentes++;
                $totalPendente += $row['valor'];
            }
        }

        $dadosFormatados[] = [
            'ID' => $row['id'],
            'Cliente' => $row['cliente'],
            'Contrato' => $row['numero_contrato'],
            'Descrição' => $row['descricao'],
            'Valor' => formatarMoeda($row['valor']),
            'Valor Pago' => $row['valor_pago'] ? formatarMoeda($row['valor_pago']) : '-',
            'Vencimento' => formatarData($row['data_vencimento']),
            'Data Pagamento' => $row['data_pagamento'] ? formatarData($row['data_pagamento']) : '-',
            'Status' => ucfirst($status),
            'Gateway' => $row['gateway'] ? ucfirst($row['gateway']) : '-'
        ];
    }

    return [
        'titulo' => 'Relatório de Contas a Pagar',
        'colunas' => ['ID', 'Cliente', 'Contrato', 'Descrição', 'Valor', 'Valor Pago', 'Vencimento', 'Data Pagamento', 'Status', 'Gateway'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Contas' => $totalContas,
            'Pagas' => $contasPagas,
            'Pendentes' => $contasPendentes,
            'Vencidas' => $contasVencidas,
            'Total Pago' => formatarMoeda($totalPago),
            'Total Pendente' => formatarMoeda($totalPendente),
            'Total Vencido' => formatarMoeda($totalVencido)
        ]
    ];
}

/**
 * Relatório de Contas a Vencer
 */
function gerarRelatorioContasVencer($conn, $dataInicial, $dataFinal) {
    $hoje = date('Y-m-d');
    
    // Se não informou datas, usa próximos 30 dias
    if (empty($dataInicial)) {
        $dataInicial = $hoje;
    }
    if (empty($dataFinal)) {
        $dataFinal = date('Y-m-d', strtotime('+30 days'));
    }

    $sql = "SELECT 
                cr.id,
                c.nome_razao_social as cliente,
                c.email,
                c.telefone,
                co.numero_contrato,
                cr.descricao,
                cr.valor,
                cr.data_vencimento,
                cr.status,
                DATEDIFF(cr.data_vencimento, ?) as dias_para_vencer
            FROM contas_receber cr
            INNER JOIN contratos co ON cr.contrato_id = co.id
            INNER JOIN clientes c ON co.cliente_id = c.id
            WHERE cr.status != 'pago'
            AND cr.data_vencimento BETWEEN ? AND ?
            ORDER BY cr.data_vencimento ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$hoje, $dataInicial, $dataFinal]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar dados
    $dadosFormatados = [];
    $totalContas = count($dados);
    $valorTotal = 0;
    $vencendoHoje = 0;
    $vencendo7dias = 0;
    $vencendo30dias = 0;

    foreach ($dados as $row) {
        $valorTotal += $row['valor'];
        $diasVencer = $row['dias_para_vencer'];

        if ($diasVencer <= 0) {
            $vencendoHoje++;
            $statusVencimento = '🔴 Vencido';
        } elseif ($diasVencer <= 7) {
            $vencendo7dias++;
            $statusVencimento = '🟡 Vence em ' . $diasVencer . ' dia(s)';
        } elseif ($diasVencer <= 30) {
            $vencendo30dias++;
            $statusVencimento = '🟢 Vence em ' . $diasVencer . ' dia(s)';
        } else {
            $statusVencimento = '⚪ Vence em ' . $diasVencer . ' dia(s)';
        }

        $dadosFormatados[] = [
            'ID' => $row['id'],
            'Cliente' => $row['cliente'],
            'E-mail' => $row['email'],
            'Telefone' => formatarTelefone($row['telefone']),
            'Contrato' => $row['numero_contrato'],
            'Descrição' => $row['descricao'],
            'Valor' => formatarMoeda($row['valor']),
            'Vencimento' => formatarData($row['data_vencimento']),
            'Status' => $statusVencimento
        ];
    }

    return [
        'titulo' => 'Relatório de Contas a Vencer',
        'colunas' => ['ID', 'Cliente', 'E-mail', 'Telefone', 'Contrato', 'Descrição', 'Valor', 'Vencimento', 'Status'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Contas' => $totalContas,
            'Vencidas' => $vencendoHoje,
            'Vencem em 7 dias' => $vencendo7dias,
            'Vencem em 30 dias' => $vencendo30dias,
            'Valor Total' => formatarMoeda($valorTotal)
        ]
    ];
}
