<?php
session_start();
require_once 'config.php';
require_once 'funcoes_auxiliares.php';
require_once 'fpdf/fpdf.php';

// Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $tipo = $_GET['tipo'] ?? '';
    $dataInicial = $_GET['data_inicial'] ?? '';
    $dataFinal = $_GET['data_final'] ?? '';

    if (empty($tipo)) {
        throw new Exception('Tipo de relatório não informado');
    }

    // Gerar dados do relatório
    $dados = gerarDadosRelatorio($conn, $tipo, $dataInicial, $dataFinal);

    // Criar PDF
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // Cabeçalho
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, utf8_decode('ERP INLAUDO'), 0, 1, 'C');
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, utf8_decode($dados['titulo']), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_decode('Período: ' . formatarData($dataInicial) . ' a ' . formatarData($dataFinal)), 0, 1, 'C');
    $pdf->Cell(0, 6, utf8_decode('Gerado em: ' . date('d/m/Y H:i:s')), 0, 1, 'C');
    $pdf->Ln(5);

    // Estatísticas
    if (!empty($dados['stats'])) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, utf8_decode('Resumo'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        
        foreach ($dados['stats'] as $label => $value) {
            $pdf->Cell(70, 6, utf8_decode($label . ':'), 0, 0, 'L');
            $pdf->Cell(0, 6, utf8_decode($value), 0, 1, 'L');
        }
        $pdf->Ln(5);
    }

    // Tabela de dados
    if (!empty($dados['dados'])) {
        // Determinar largura das colunas baseado no tipo de relatório
        $larguras = getLargurasColunas($tipo, count($dados['colunas']));

        // Cabeçalho da tabela
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(102, 126, 234);
        $pdf->SetTextColor(255, 255, 255);
        
        foreach ($dados['colunas'] as $index => $coluna) {
            $pdf->Cell($larguras[$index], 7, utf8_decode($coluna), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Dados da tabela
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;

        foreach ($dados['dados'] as $row) {
            $pdf->SetFillColor(248, 249, 250);
            
            $values = array_values($row);
            foreach ($values as $index => $value) {
                $pdf->Cell($larguras[$index], 6, utf8_decode($value), 1, 0, 'L', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;

            // Verificar se precisa adicionar nova página
            if ($pdf->GetY() > 180) {
                $pdf->AddPage();
                
                // Repetir cabeçalho
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(102, 126, 234);
                $pdf->SetTextColor(255, 255, 255);
                
                foreach ($dados['colunas'] as $index => $coluna) {
                    $pdf->Cell($larguras[$index], 7, utf8_decode($coluna), 1, 0, 'C', true);
                }
                $pdf->Ln();
                
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
            }
        }
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 10, utf8_decode('Nenhum registro encontrado para os filtros selecionados.'), 0, 1, 'C');
    }

    // Rodapé
    $pdf->SetY(-15);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, utf8_decode('Página ' . $pdf->PageNo()), 0, 0, 'C');

    // Output
    $nomeArquivo = 'relatorio_' . $tipo . '_' . date('Y-m-d_His') . '.pdf';
    $pdf->Output('D', $nomeArquivo);

} catch (Exception $e) {
    die('Erro ao gerar PDF: ' . $e->getMessage());
}

/**
 * Gerar dados do relatório
 */
function gerarDadosRelatorio($conn, $tipo, $dataInicial, $dataFinal) {
    // Simular POST para reutilizar funções do gerar_relatorio.php
    $_POST['tipo_relatorio'] = $tipo;
    $_POST['data_inicial'] = $dataInicial;
    $_POST['data_final'] = $dataFinal;

    switch ($tipo) {
        case 'clientes':
            return gerarRelatorioClientes($conn, $dataInicial, $dataFinal);

        case 'contratos':
            return gerarRelatorioContratos($conn, $dataInicial, $dataFinal);

        case 'contas_pagar':
            return gerarRelatorioContasPagar($conn, $dataInicial, $dataFinal);

        case 'contas_vencer':
            return gerarRelatorioContasVencer($conn, $dataInicial, $dataFinal);

        default:
            throw new Exception('Tipo de relatório inválido');
    }
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

    $dadosFormatados = [];
    $totalClientes = count($dados);
    $totalContratos = 0;
    $valorTotal = 0;

    foreach ($dados as $row) {
        $totalContratos += $row['total_contratos'];
        $valorTotal += $row['valor_total_contratos'];

        $dadosFormatados[] = [
            $row['id'],
            $row['nome_razao_social'],
            formatarCpfCnpj($row['cpf_cnpj']),
            $row['email'],
            formatarTelefone($row['telefone']),
            $row['cidade'] . '/' . $row['estado'],
            formatarData($row['data_cadastro']),
            $row['total_contratos']
        ];
    }

    return [
        'titulo' => 'Relatório de Clientes',
        'colunas' => ['ID', 'Nome/Razão Social', 'CPF/CNPJ', 'E-mail', 'Telefone', 'Cidade/UF', 'Cadastro', 'Contratos'],
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
                co.descricao,
                co.valor_mensal,
                co.data_inicio,
                co.data_fim,
                co.status
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
            $row['id'],
            $row['numero_contrato'],
            $row['cliente'],
            $row['descricao'],
            formatarMoeda($row['valor_mensal']),
            formatarData($row['data_inicio']),
            $row['data_fim'] ? formatarData($row['data_fim']) : 'Indeterminado',
            ucfirst($row['status'])
        ];
    }

    return [
        'titulo' => 'Relatório de Contratos',
        'colunas' => ['ID', 'Nº Contrato', 'Cliente', 'Descrição', 'Valor Mensal', 'Início', 'Fim', 'Status'],
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
                cp.data_vencimento,
                cp.data_pagamento,
                cp.status
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

    $dadosFormatados = [];
    $totalContas = count($dados);
    $totalPago = 0;
    $totalPendente = 0;
    $contasPagas = 0;

    foreach ($dados as $row) {
        if ($row['status'] === 'pago') {
            $contasPagas++;
            $totalPago += $row['valor'];
        } else {
            $totalPendente += $row['valor'];
        }

        $dadosFormatados[] = [
            $row['id'],
            $row['cliente'],
            $row['numero_contrato'],
            $row['descricao'],
            formatarMoeda($row['valor']),
            formatarData($row['data_vencimento']),
            $row['data_pagamento'] ? formatarData($row['data_pagamento']) : '-',
            ucfirst($row['status'])
        ];
    }

    return [
        'titulo' => 'Relatório de Contas a Pagar',
        'colunas' => ['ID', 'Cliente', 'Contrato', 'Descrição', 'Valor', 'Vencimento', 'Pagamento', 'Status'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Contas' => $totalContas,
            'Contas Pagas' => $contasPagas,
            'Total Pago' => formatarMoeda($totalPago),
            'Total Pendente' => formatarMoeda($totalPendente)
        ]
    ];
}

/**
 * Relatório de Contas a Vencer
 */
function gerarRelatorioContasVencer($conn, $dataInicial, $dataFinal) {
    $hoje = date('Y-m-d');
    
    if (empty($dataInicial)) {
        $dataInicial = $hoje;
    }
    if (empty($dataFinal)) {
        $dataFinal = date('Y-m-d', strtotime('+30 days'));
    }

    $sql = "SELECT 
                cr.id,
                c.nome_razao_social as cliente,
                co.numero_contrato,
                cr.descricao,
                cr.valor,
                cr.data_vencimento,
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

    $dadosFormatados = [];
    $totalContas = count($dados);
    $valorTotal = 0;

    foreach ($dados as $row) {
        $valorTotal += $row['valor'];
        $diasVencer = $row['dias_para_vencer'];

        if ($diasVencer <= 0) {
            $statusVencimento = 'Vencido';
        } else {
            $statusVencimento = 'Vence em ' . $diasVencer . ' dia(s)';
        }

        $dadosFormatados[] = [
            $row['id'],
            $row['cliente'],
            $row['numero_contrato'],
            $row['descricao'],
            formatarMoeda($row['valor']),
            formatarData($row['data_vencimento']),
            $statusVencimento
        ];
    }

    return [
        'titulo' => 'Relatório de Contas a Vencer',
        'colunas' => ['ID', 'Cliente', 'Contrato', 'Descrição', 'Valor', 'Vencimento', 'Status'],
        'dados' => $dadosFormatados,
        'stats' => [
            'Total de Contas' => $totalContas,
            'Valor Total' => formatarMoeda($valorTotal)
        ]
    ];
}

/**
 * Definir larguras das colunas por tipo de relatório
 */
function getLargurasColunas($tipo, $numColunas) {
    switch ($tipo) {
        case 'clientes':
            return [10, 50, 30, 45, 25, 30, 22, 15]; // Total: 227mm (A4 landscape = 277mm)
        
        case 'contratos':
            return [10, 25, 50, 40, 25, 22, 22, 20]; // Total: 214mm
        
        case 'contas_pagar':
            return [10, 45, 25, 40, 25, 22, 22, 20]; // Total: 209mm
        
        case 'contas_vencer':
            return [10, 50, 25, 45, 25, 22, 30]; // Total: 207mm
        
        default:
            // Distribuir igualmente
            $larguraTotal = 270;
            $largura = floor($larguraTotal / $numColunas);
            return array_fill(0, $numColunas, $largura);
    }
}
