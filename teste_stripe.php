<?php
/**
 * Script de Teste da Integração Stripe
 * Este arquivo testa a comunicação com a API do Stripe
 */

require_once 'config.php';
require_once 'lib_stripe_faturamento.php';
require_once 'lib_logs.php';

$pageTitle = 'Teste de Integração Stripe';

$resultados = [];
$erros = [];

// Processar teste
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Teste 1: Verificar configuração
        $resultados[] = ['teste' => 'Verificar Configuração', 'status' => 'iniciando'];
        
        $conn = getConnection();
        $stmt = $conn->query("SELECT * FROM integracoes WHERE tipo = 'stripe'");
        $config = $stmt->fetch();
        
        if (!$config) {
            $erros[] = "Configuração Stripe não encontrada no banco de dados";
            $resultados[count($resultados)-1]['status'] = 'erro';
        } elseif (!$config['ativo']) {
            $erros[] = "Integração Stripe não está ativa";
            $resultados[count($resultados)-1]['status'] = 'erro';
        } elseif (empty($config['api_secret'])) {
            $erros[] = "Secret Key não configurada";
            $resultados[count($resultados)-1]['status'] = 'erro';
        } else {
            $resultados[count($resultados)-1]['status'] = 'sucesso';
            $resultados[count($resultados)-1]['detalhes'] = "Configuração OK - Secret Key: " . substr($config['api_secret'], 0, 10) . "...";
        }
        
        // Teste 2: Testar conexão com API
        if (empty($erros)) {
            $resultados[] = ['teste' => 'Testar Conexão com API Stripe', 'status' => 'iniciando'];
            
            try {
                $stripe = new StripeFaturamento();
                
                // Fazer uma chamada simples para testar
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/customers?limit=1');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $config['api_secret']
                ]);
                
                $startTime = microtime(true);
                $response = curl_exec($ch);
                $tempoResposta = microtime(true) - $startTime;
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $responseData = json_decode($response, true);
                
                if ($httpCode == 200) {
                    $resultados[count($resultados)-1]['status'] = 'sucesso';
                    $resultados[count($resultados)-1]['detalhes'] = "Conexão OK - Tempo: " . number_format($tempoResposta, 3) . "s - HTTP: $httpCode";
                    
                    LogIntegracao::sucesso(
                        'stripe',
                        'teste_conexao',
                        'Teste de conexão bem-sucedido',
                        null,
                        $responseData,
                        $httpCode,
                        $tempoResposta
                    );
                } else {
                    $erros[] = "Erro na API: HTTP $httpCode - " . ($responseData['error']['message'] ?? 'Erro desconhecido');
                    $resultados[count($resultados)-1]['status'] = 'erro';
                    $resultados[count($resultados)-1]['detalhes'] = "HTTP: $httpCode";
                    
                    LogIntegracao::erro(
                        'stripe',
                        'teste_conexao',
                        'Erro ao testar conexão: HTTP ' . $httpCode,
                        null,
                        $responseData,
                        $httpCode,
                        $tempoResposta
                    );
                }
                
            } catch (Exception $e) {
                $erros[] = "Exceção ao testar API: " . $e->getMessage();
                $resultados[count($resultados)-1]['status'] = 'erro';
                $resultados[count($resultados)-1]['detalhes'] = $e->getMessage();
            }
        }
        
        // Teste 3: Testar criação de customer (se houver clientes)
        if (empty($erros)) {
            $resultados[] = ['teste' => 'Testar Criação de Customer', 'status' => 'iniciando'];
            
            $stmtCliente = $conn->query("SELECT * FROM clientes WHERE tipo_cliente = 'CLIENTE' LIMIT 1");
            $clienteTeste = $stmtCliente->fetch();
            
            if ($clienteTeste) {
                try {
                    $stripe = new StripeFaturamento();
                    
                    // Remover customer_id existente para forçar criação
                    $clienteTeste['stripe_customer_id'] = null;
                    
                    $startTime = microtime(true);
                    $customerId = $stripe->criarOuObterCustomer($clienteTeste);
                    $tempoResposta = microtime(true) - $startTime;
                    
                    $resultados[count($resultados)-1]['status'] = 'sucesso';
                    $resultados[count($resultados)-1]['detalhes'] = "Customer criado: $customerId - Tempo: " . number_format($tempoResposta, 3) . "s";
                    
                } catch (Exception $e) {
                    $erros[] = "Erro ao criar customer: " . $e->getMessage();
                    $resultados[count($resultados)-1]['status'] = 'erro';
                    $resultados[count($resultados)-1]['detalhes'] = $e->getMessage();
                }
            } else {
                $resultados[count($resultados)-1]['status'] = 'pulado';
                $resultados[count($resultados)-1]['detalhes'] = "Nenhum cliente disponível para teste";
            }
        }
        
        // Teste 4: Verificar logs
        $resultados[] = ['teste' => 'Verificar Sistema de Logs', 'status' => 'iniciando'];
        
        $totalLogs = LogIntegracao::contar(['tipo' => 'stripe']);
        $resultados[count($resultados)-1]['status'] = 'sucesso';
        $resultados[count($resultados)-1]['detalhes'] = "Total de logs Stripe: $totalLogs";
        
    } catch (Exception $e) {
        $erros[] = "Erro geral: " . $e->getMessage();
    }
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Teste de Integração Stripe</h2>
        </div>
        
        <div style="background: #eff6ff; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #2563eb;">
            <h3 style="color: #1e40af; margin-bottom: 0.5rem;">Sobre este Teste</h3>
            <p style="margin-bottom: 0.5rem;">
                Este script testa a comunicação com a API do Stripe para garantir que tudo está funcionando corretamente.
            </p>
            <p style="margin-bottom: 0;">
                <strong>Testes realizados:</strong>
            </p>
            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                <li>Verificação de configuração no banco de dados</li>
                <li>Teste de conexão com API Stripe</li>
                <li>Teste de criação de customer</li>
                <li>Verificação do sistema de logs</li>
            </ul>
        </div>
        
        <?php if (!empty($erros)): ?>
            <div class="alert alert-error">
                <strong>Erros Encontrados:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($erros as $erro): ?>
                        <li><?php echo htmlspecialchars($erro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($resultados)): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin-bottom: 1rem;">Resultados dos Testes</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Teste</th>
                            <th>Status</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $resultado): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resultado['teste']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'cancelado';
                                    if ($resultado['status'] == 'sucesso') $badgeClass = 'pago';
                                    if ($resultado['status'] == 'erro') $badgeClass = 'vencido';
                                    if ($resultado['status'] == 'pulado') $badgeClass = 'pendente';
                                    ?>
                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($resultado['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo isset($resultado['detalhes']) ? htmlspecialchars($resultado['detalhes']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Executar Testes</button>
                <a href="logs_integracao.php" class="btn btn-secondary">Ver Logs Completos</a>
                <a href="integracoes_boleto.php" class="btn btn-secondary">Configurar Stripe</a>
            </div>
        </form>
        
        <?php if (!empty($resultados)): ?>
            <div style="margin-top: 2rem; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                <h3 style="margin-bottom: 1rem;">Próximos Passos</h3>
                
                <?php if (empty($erros)): ?>
                    <div style="color: #059669; margin-bottom: 1rem;">
                        <strong>✓ Todos os testes passaram!</strong>
                    </div>
                    <p>A integração com Stripe está funcionando perfeitamente. Você pode:</p>
                    <ul style="margin-left: 1.5rem;">
                        <li>Criar contas a receber e gerar faturas automaticamente</li>
                        <li>Acessar <strong>Faturamento > Faturas Stripe</strong> para ver as faturas criadas</li>
                        <li>Acessar <strong>Integrações > Logs de Integração</strong> para monitorar todas as chamadas de API</li>
                    </ul>
                <?php else: ?>
                    <div style="color: #dc2626; margin-bottom: 1rem;">
                        <strong>✗ Alguns testes falharam</strong>
                    </div>
                    <p>Verifique os erros acima e:</p>
                    <ul style="margin-left: 1.5rem;">
                        <li>Acesse <strong>Integrações > Boleto (CORA/Stripe)</strong> para configurar as credenciais</li>
                        <li>Certifique-se de que a Secret Key está correta</li>
                        <li>Verifique se a integração está marcada como "Ativa"</li>
                        <li>Consulte os logs em <strong>Integrações > Logs de Integração</strong> para mais detalhes</li>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
