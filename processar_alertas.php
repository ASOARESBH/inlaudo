<?php
/**
 * Script para processar alertas automáticos
 * Este script deve ser executado diariamente via CRON
 * 
 * Adicione ao crontab:
 * 0 9 * * * /usr/bin/php /caminho/para/erp-inlaudo/processar_alertas.php
 */

require_once 'config.php';
require_once 'lib_email.php';

// Log de execução
$logFile = __DIR__ . '/logs/alertas_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMsg($mensagem) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $mensagem\n", FILE_APPEND);
    echo "[$timestamp] $mensagem\n";
}

logMsg("===== INÍCIO DO PROCESSAMENTO DE ALERTAS =====");

try {
    $conn = getConnection();
    
    // 1. ALERTAS DE CONTAS A PAGAR VENCENDO
    logMsg("Processando alertas de contas a pagar...");
    
    $stmt = $conn->query("SELECT * FROM email_templates WHERE codigo = 'conta_pagar_vencendo' AND ativo = TRUE LIMIT 1");
    $templateContaPagar = $stmt->fetch();
    
    if ($templateContaPagar && $templateContaPagar['enviar_automatico']) {
        $diasAntecedencia = $templateContaPagar['dias_antecedencia'];
        $dataLimite = date('Y-m-d', strtotime("+$diasAntecedencia days"));
        
        $sql = "SELECT cp.*, pc.nome as plano_contas_nome
                FROM contas_pagar cp
                LEFT JOIN plano_contas pc ON cp.plano_contas_id = pc.id
                WHERE cp.status = 'pendente'
                AND cp.data_vencimento <= ?
                AND cp.data_vencimento >= CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM alertas_programados ap
                    WHERE ap.referencia_tipo = 'conta_pagar'
                    AND ap.referencia_id = cp.id
                    AND ap.status IN ('enviado', 'pendente')
                    AND DATE(ap.data_programada) = CURDATE()
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$dataLimite]);
        $contasPagar = $stmt->fetchAll();
        
        logMsg("Encontradas " . count($contasPagar) . " contas a pagar para alertar");
        
        foreach ($contasPagar as $conta) {
            $diasRestantes = (strtotime($conta['data_vencimento']) - time()) / 86400;
            $diasRestantes = ceil($diasRestantes);
            
            $variaveis = [
                'descricao' => $conta['descricao'],
                'fornecedor' => $conta['fornecedor'] ?: 'Não informado',
                'valor' => formatMoeda($conta['valor']),
                'data_vencimento' => formatData($conta['data_vencimento']),
                'dias_restantes' => $diasRestantes,
                'plano_contas' => $conta['plano_contas_nome'] ?: 'Não categorizado',
                'link_sistema' => 'https://' . $_SERVER['HTTP_HOST'] . '/contas_pagar.php'
            ];
            
            // Determinar destinatários
            $destinatarios = [];
            if ($templateContaPagar['destinatarios_padrao']) {
                $destinatarios = array_map('trim', explode(',', $templateContaPagar['destinatarios_padrao']));
            }
            
            // Enviar para cada destinatário
            foreach ($destinatarios as $destinatario) {
                $resultado = EmailSender::enviarComTemplate(
                    $templateContaPagar['id'],
                    $destinatario,
                    $variaveis,
                    null,
                    'conta_pagar',
                    $conta['id']
                );
                
                if ($resultado['sucesso']) {
                    logMsg("Alerta enviado para $destinatario - Conta: {$conta['descricao']}");
                    
                    // Registrar alerta programado
                    $conn->prepare("INSERT INTO alertas_programados (template_id, tipo_alerta, referencia_tipo, referencia_id, destinatario_email, data_programada, status, data_envio) VALUES (?, ?, ?, ?, ?, CURDATE(), 'enviado', NOW())")
                         ->execute([$templateContaPagar['id'], 'conta_pagar_vencendo', 'conta_pagar', $conta['id'], $destinatario]);
                } else {
                    logMsg("ERRO ao enviar alerta para $destinatario: " . $resultado['mensagem']);
                }
            }
        }
    }
    
    // 2. ALERTAS DE CONTAS A RECEBER VENCIDAS
    logMsg("Processando alertas de contas a receber...");
    
    $stmt = $conn->query("SELECT * FROM email_templates WHERE codigo = 'conta_receber_vencida' AND ativo = TRUE LIMIT 1");
    $templateContaReceber = $stmt->fetch();
    
    if ($templateContaReceber && $templateContaReceber['enviar_automatico']) {
        $sql = "SELECT cr.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa, c.email, c.celular, c.telefone
                FROM contas_receber cr
                INNER JOIN clientes c ON cr.cliente_id = c.id
                WHERE cr.status = 'pendente'
                AND cr.data_vencimento < CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM alertas_programados ap
                    WHERE ap.referencia_tipo = 'conta_receber'
                    AND ap.referencia_id = cr.id
                    AND ap.status IN ('enviado', 'pendente')
                    AND DATE(ap.data_programada) = CURDATE()
                )";
        
        $stmt = $conn->query($sql);
        $contasReceber = $stmt->fetchAll();
        
        logMsg("Encontradas " . count($contasReceber) . " contas a receber vencidas para alertar");
        
        foreach ($contasReceber as $conta) {
            $diasAtraso = (time() - strtotime($conta['data_vencimento'])) / 86400;
            $diasAtraso = floor($diasAtraso);
            
            $nomeCliente = $conta['tipo_pessoa'] == 'CNPJ' 
                ? ($conta['razao_social'] ?: $conta['nome_fantasia']) 
                : $conta['nome'];
            
            $contatoCliente = $conta['email'] ?: ($conta['celular'] ?: $conta['telefone']);
            
            $variaveis = [
                'cliente' => $nomeCliente,
                'descricao' => $conta['descricao'],
                'valor' => formatMoeda($conta['valor']),
                'data_vencimento' => formatData($conta['data_vencimento']),
                'dias_atraso' => $diasAtraso,
                'contato_cliente' => $contatoCliente,
                'link_sistema' => 'https://' . $_SERVER['HTTP_HOST'] . '/contas_receber.php'
            ];
            
            // Determinar destinatários
            $destinatarios = [];
            if ($templateContaReceber['destinatarios_padrao']) {
                $destinatarios = array_map('trim', explode(',', $templateContaReceber['destinatarios_padrao']));
            }
            
            // Enviar para cada destinatário
            foreach ($destinatarios as $destinatario) {
                $resultado = EmailSender::enviarComTemplate(
                    $templateContaReceber['id'],
                    $destinatario,
                    $variaveis,
                    null,
                    'conta_receber',
                    $conta['id']
                );
                
                if ($resultado['sucesso']) {
                    logMsg("Alerta enviado para $destinatario - Cliente: $nomeCliente");
                    
                    // Registrar alerta programado
                    $conn->prepare("INSERT INTO alertas_programados (template_id, tipo_alerta, referencia_tipo, referencia_id, destinatario_email, data_programada, status, data_envio) VALUES (?, ?, ?, ?, ?, CURDATE(), 'enviado', NOW())")
                         ->execute([$templateContaReceber['id'], 'conta_receber_vencida', 'conta_receber', $conta['id'], $destinatario]);
                } else {
                    logMsg("ERRO ao enviar alerta para $destinatario: " . $resultado['mensagem']);
                }
            }
        }
    }
    
    // 3. ALERTAS DE PRÓXIMAS INTERAÇÕES
    logMsg("Processando alertas de próximas interações...");
    
    $stmt = $conn->query("SELECT * FROM email_templates WHERE codigo = 'interacao_proxima' AND ativo = TRUE LIMIT 1");
    $templateInteracao = $stmt->fetch();
    
    if ($templateInteracao && $templateInteracao['enviar_automatico']) {
        $diasAntecedencia = $templateInteracao['dias_antecedencia'];
        $dataLimite = date('Y-m-d', strtotime("+$diasAntecedencia days"));
        
        $sql = "SELECT i.*, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa, c.email, c.celular, c.telefone
                FROM interacoes i
                INNER JOIN clientes c ON i.cliente_id = c.id
                WHERE DATE(i.proximo_contato_data) <= ?
                AND DATE(i.proximo_contato_data) >= CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM alertas_programados ap
                    WHERE ap.referencia_tipo = 'interacao'
                    AND ap.referencia_id = i.id
                    AND ap.status IN ('enviado', 'pendente')
                    AND DATE(ap.data_programada) = CURDATE()
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$dataLimite]);
        $interacoes = $stmt->fetchAll();
        
        logMsg("Encontradas " . count($interacoes) . " interações próximas para alertar");
        
        foreach ($interacoes as $interacao) {
            $nomeCliente = $interacao['tipo_pessoa'] == 'CNPJ' 
                ? ($interacao['razao_social'] ?: $interacao['nome_fantasia']) 
                : $interacao['nome'];
            
            $contatoCliente = $interacao['email'] ?: ($interacao['celular'] ?: $interacao['telefone']);
            
            $dataHora = formatData($interacao['proximo_contato_data']);
            if ($interacao['proximo_contato_hora']) {
                $dataHora .= ' às ' . date('H:i', strtotime($interacao['proximo_contato_hora']));
            }
            
            $variaveis = [
                'cliente' => $nomeCliente,
                'data_hora' => $dataHora,
                'forma_contato' => ucfirst($interacao['proximo_contato_forma'] ?: 'Não definido'),
                'contato_cliente' => $contatoCliente,
                'historico' => substr($interacao['historico'], 0, 200) . '...',
                'link_sistema' => 'https://' . $_SERVER['HTTP_HOST'] . '/interacoes.php'
            ];
            
            // Determinar destinatários
            $destinatarios = [];
            if ($templateInteracao['destinatarios_padrao']) {
                $destinatarios = array_map('trim', explode(',', $templateInteracao['destinatarios_padrao']));
            }
            
            // Enviar para cada destinatário
            foreach ($destinatarios as $destinatario) {
                $resultado = EmailSender::enviarComTemplate(
                    $templateInteracao['id'],
                    $destinatario,
                    $variaveis,
                    null,
                    'interacao',
                    $interacao['id']
                );
                
                if ($resultado['sucesso']) {
                    logMsg("Alerta enviado para $destinatario - Interação com: $nomeCliente");
                    
                    // Registrar alerta programado
                    $conn->prepare("INSERT INTO alertas_programados (template_id, tipo_alerta, referencia_tipo, referencia_id, destinatario_email, data_programada, status, data_envio) VALUES (?, ?, ?, ?, ?, CURDATE(), 'enviado', NOW())")
                         ->execute([$templateInteracao['id'], 'interacao_proxima', 'interacao', $interacao['id'], $destinatario]);
                } else {
                    logMsg("ERRO ao enviar alerta para $destinatario: " . $resultado['mensagem']);
                }
            }
        }
    }
    
    // 4. PROCESSAR ALERTAS PROGRAMADOS PENDENTES
    logMsg("Processando alertas programados pendentes...");
    
    $sql = "SELECT ap.*, et.* 
            FROM alertas_programados ap
            INNER JOIN email_templates et ON ap.template_id = et.id
            WHERE ap.status = 'pendente'
            AND ap.data_programada <= CURDATE()
            AND ap.tentativas < 3";
    
    $stmt = $conn->query($sql);
    $alertasPendentes = $stmt->fetchAll();
    
    logMsg("Encontrados " . count($alertasPendentes) . " alertas programados pendentes");
    
    foreach ($alertasPendentes as $alerta) {
        // Buscar dados da entidade relacionada
        $variaveis = [];
        
        // Aqui você pode adicionar lógica para buscar dados específicos baseado em referencia_tipo e referencia_id
        // Por enquanto, vamos usar variáveis genéricas
        $variaveis = [
            'descricao' => 'Alerta programado',
            'link_sistema' => 'https://' . $_SERVER['HTTP_HOST']
        ];
        
        $resultado = EmailSender::enviarComTemplate(
            $alerta['template_id'],
            $alerta['destinatario_email'],
            $variaveis,
            null,
            $alerta['referencia_tipo'],
            $alerta['referencia_id']
        );
        
        if ($resultado['sucesso']) {
            logMsg("Alerta programado enviado para {$alerta['destinatario_email']}");
            $conn->prepare("UPDATE alertas_programados SET status = 'enviado', data_envio = NOW() WHERE id = ?")
                 ->execute([$alerta['id']]);
        } else {
            logMsg("ERRO ao enviar alerta programado: " . $resultado['mensagem']);
            $conn->prepare("UPDATE alertas_programados SET status = 'erro', tentativas = tentativas + 1, mensagem_erro = ? WHERE id = ?")
                 ->execute([$resultado['mensagem'], $alerta['id']]);
        }
    }
    
    logMsg("===== FIM DO PROCESSAMENTO DE ALERTAS =====");
    
} catch (Exception $e) {
    logMsg("ERRO FATAL: " . $e->getMessage());
    logMsg($e->getTraceAsString());
}
?>
