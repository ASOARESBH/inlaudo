<?php
/**
 * Gerar Pagamento - Portal do Cliente
 * ERP INLAUDO - Versão 7.0
 */

session_start();
require_once 'config.php';
require_once 'lib_mercadopago.php';
require_once 'lib_boleto_cora_v2.php';

// Verificar se é cliente logado
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] != 'cliente') {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado']);
    exit;
}

// Verificar parâmetros
$contratoId = isset($_GET['contrato_id']) ? (int)$_GET['contrato_id'] : 0;
$contaReceberId = isset($_GET['conta_receber_id']) ? (int)$_GET['conta_receber_id'] : 0;

if (!$contratoId && !$contaReceberId) {
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos']);
    exit;
}

try {
    $conn = getConnection();
    
    // Buscar dados do cliente logado
    $stmtUsuario = $conn->prepare("SELECT cliente_id FROM usuarios WHERE id = ?");
    $stmtUsuario->execute([$_SESSION['usuario_id']]);
    $usuario = $stmtUsuario->fetch();
    $clienteId = $usuario['cliente_id'];
    
    // Buscar dados do cliente
    $stmtCliente = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmtCliente->execute([$clienteId]);
    $cliente = $stmtCliente->fetch();
    
    if (!$cliente) {
        echo json_encode(['sucesso' => false, 'erro' => 'Cliente não encontrado']);
        exit;
    }
    
    // Se for pagamento de contrato
    if ($contratoId) {
        // Buscar contrato
        $stmtContrato = $conn->prepare("SELECT * FROM contratos WHERE id = ? AND cliente_id = ?");
        $stmtContrato->execute([$contratoId, $clienteId]);
        $contrato = $stmtContrato->fetch();
        
        if (!$contrato) {
            echo json_encode(['sucesso' => false, 'erro' => 'Contrato não encontrado']);
            exit;
        }
        
        $gateway = $contrato['gateway_pagamento'] ?? 'cora';
        $valor = $contrato['valor_total'];
        $descricao = $contrato['descricao'];
        $dataVencimento = date('Y-m-d', strtotime('+7 days'));
        
    } else {
        // Buscar conta a receber
        $stmtConta = $conn->prepare("
            SELECT cr.*, c.gateway_pagamento 
            FROM contas_receber cr
            LEFT JOIN contratos c ON cr.contrato_id = c.id
            WHERE cr.id = ? AND cr.cliente_id = ?
        ");
        $stmtConta->execute([$contaReceberId, $clienteId]);
        $conta = $stmtConta->fetch();
        
        if (!$conta) {
            echo json_encode(['sucesso' => false, 'erro' => 'Conta não encontrada']);
            exit;
        }
        
        $gateway = $conta['gateway_pagamento'] ?? 'cora';
        $valor = $conta['valor'];
        $descricao = $conta['descricao'];
        $dataVencimento = $conta['data_vencimento'];
    }
    
    // Gerar pagamento conforme gateway
    if ($gateway == 'cora') {
        // Gerar boleto CORA
        $cora = new BoletoCoraV2();
        
        $dadosBoleto = [
            'valor' => $valor,
            'vencimento' => $dataVencimento,
            'pagador' => [
                'nome' => $cliente['razao_social'] ?: $cliente['nome'],
                'documento' => preg_replace('/[^0-9]/', '', $cliente['cnpj_cpf']),
                'endereco' => $cliente['endereco'] ?: '',
                'cidade' => $cliente['cidade'] ?: '',
                'uf' => $cliente['estado'] ?: '',
                'cep' => preg_replace('/[^0-9]/', '', $cliente['cep'] ?: '')
            ],
            'descricao' => $descricao
        ];
        
        $resultado = $cora->emitirBoleto($dadosBoleto);
        
        if ($resultado['sucesso']) {
            // Salvar transação
            $stmtTrans = $conn->prepare("
                INSERT INTO transacoes_pagamento 
                (contrato_id, conta_receber_id, gateway, transaction_id, payment_id, valor, status, 
                 metodo_pagamento, boleto_url, pagador_nome, pagador_email, pagador_documento, 
                 data_vencimento, response_json)
                VALUES (?, ?, 'cora', ?, ?, ?, 'pending', 'boleto', ?, ?, ?, ?, ?, ?)
            ");
            $stmtTrans->execute([
                $contratoId ?: null,
                $contaReceberId ?: null,
                $resultado['boleto_id'],
                $resultado['boleto_id'],
                $valor,
                $resultado['boleto_url'],
                $cliente['razao_social'] ?: $cliente['nome'],
                $cliente['email'],
                preg_replace('/[^0-9]/', '', $cliente['cnpj_cpf']),
                $dataVencimento,
                json_encode($resultado)
            ]);
            
            echo json_encode([
                'sucesso' => true,
                'tipo' => 'boleto',
                'boleto_url' => $resultado['boleto_url'],
                'linha_digitavel' => $resultado['linha_digitavel'] ?? ''
            ]);
            
        } else {
            echo json_encode(['sucesso' => false, 'erro' => $resultado['erro']]);
        }
        
    } elseif ($gateway == 'mercadopago') {
        // Gerar preferência Mercado Pago
        $mp = new MercadoPago();
        
        if (!$mp->estaConfigurado()) {
            echo json_encode(['sucesso' => false, 'erro' => 'Mercado Pago não configurado']);
            exit;
        }
        
        $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
        $referencia = $contratoId ? 'contrato_' . $contratoId : 'conta_' . $contaReceberId;
        
        $dadosPreferencia = [
            'titulo' => $descricao,
            'descricao' => 'Pagamento - ' . ($cliente['razao_social'] ?: $cliente['nome']),
            'valor' => $valor,
            'pagador_nome' => $cliente['razao_social'] ?: $cliente['nome'],
            'pagador_email' => $cliente['email'],
            'pagador_documento' => preg_replace('/[^0-9]/', '', $cliente['cnpj_cpf']),
            'url_sucesso' => $baseUrl . '/cliente_financeiro.php?pagamento=sucesso',
            'url_falha' => $baseUrl . '/cliente_financeiro.php?pagamento=falha',
            'url_pendente' => $baseUrl . '/cliente_financeiro.php?pagamento=pendente',
            'referencia_externa' => $referencia,
            'webhook_url' => $baseUrl . '/webhook_mercadopago.php',
            'data_vencimento' => $dataVencimento
        ];
        
        $resultado = $mp->criarPreferencia($dadosPreferencia);
        
        if ($resultado['sucesso']) {
            // Salvar transação
            $stmtTrans = $conn->prepare("
                INSERT INTO transacoes_pagamento 
                (contrato_id, conta_receber_id, gateway, transaction_id, payment_id, valor, status, 
                 metodo_pagamento, payment_url, pagador_nome, pagador_email, pagador_documento, 
                 data_vencimento, response_json)
                VALUES (?, ?, 'mercadopago', ?, ?, ?, 'pending', 'checkout', ?, ?, ?, ?, ?, ?)
            ");
            $stmtTrans->execute([
                $contratoId ?: null,
                $contaReceberId ?: null,
                $resultado['preference_id'],
                $resultado['preference_id'],
                $valor,
                $resultado['init_point'],
                $cliente['razao_social'] ?: $cliente['nome'],
                $cliente['email'],
                preg_replace('/[^0-9]/', '', $cliente['cnpj_cpf']),
                $dataVencimento,
                json_encode($resultado)
            ]);
            
            // Atualizar contrato com link de pagamento
            if ($contratoId) {
                $stmtUpdate = $conn->prepare("
                    UPDATE contratos 
                    SET link_pagamento = ?, payment_id = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$resultado['init_point'], $resultado['preference_id'], $contratoId]);
            }
            
            echo json_encode([
                'sucesso' => true,
                'tipo' => 'redirect',
                'redirect_url' => $resultado['init_point']
            ]);
            
        } else {
            echo json_encode(['sucesso' => false, 'erro' => $resultado['erro']]);
        }
        
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Gateway não suportado']);
    }
    
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao gerar pagamento: ' . $e->getMessage()]);
}
