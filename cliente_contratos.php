<?php
/**
 * Portal do Cliente - Meus Contratos
 * ERP INLAUDO - Versão 7.3
 */

require_once 'verifica_sessao_cliente.php';
require_once 'config.php';

$conn = getConnection();

// Buscar contratos do cliente
$stmt = $conn->prepare("
    SELECT c.*,
           DATEDIFF(c.data_fim, c.data_inicio) as dias_contrato
    FROM contratos c
    WHERE c.cliente_id = ?
    ORDER BY 
        CASE c.status
            WHEN 'ativo' THEN 1
            WHEN 'suspenso' THEN 2
            WHEN 'cancelado' THEN 3
            WHEN 'finalizado' THEN 4
        END,
        c.data_inicio DESC
");
$stmt->execute([$cliente_id]);
$contratos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Contratos - Portal do Cliente</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        h1 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #64748b;
            margin-bottom: 2rem;
        }
        
        .contracts-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .contract-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .contract-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        
        .contract-header {
            padding: 1.5rem;
            border-left: 4px solid;
            display: flex;
            justify-content: space-between;
            align-items: start;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .contract-header.ativo { border-left-color: #10b981; background: #f0fdf4; }
        .contract-header.suspenso { border-left-color: #f59e0b; background: #fffbeb; }
        .contract-header.cancelado { border-left-color: #ef4444; background: #fef2f2; }
        .contract-header.finalizado { border-left-color: #64748b; background: #f8fafc; }
        
        .contract-title {
            flex: 1;
        }
        
        .contract-title h3 {
            color: #1e293b;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .contract-title p {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .contract-status {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .contract-status.ativo { background: #d1fae5; color: #065f46; }
        .contract-status.suspenso { background: #fef3c7; color: #92400e; }
        .contract-status.cancelado { background: #fee2e2; color: #991b1b; }
        .contract-status.finalizado { background: #e2e8f0; color: #475569; }
        
        .contract-body {
            padding: 1.5rem;
        }
        
        .contract-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .info-value.large {
            font-size: 1.5rem;
            color: #10b981;
        }
        
        .contract-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #475569;
        }
        
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        
        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty-state h2 {
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .contract-info-grid {
                grid-template-columns: 1fr;
            }
            
            .contract-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="portal_cliente.php" class="btn-back">← Voltar</a>
                <div>
                    <h2>Meus Contratos</h2>
                </div>
            </div>
            <div>
                <span><?php echo htmlspecialchars($cliente_nome); ?></span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <h1>📄 Meus Contratos</h1>
        <p class="subtitle">Visualize todos os seus contratos, detalhes e documentos</p>
        
        <?php if (empty($contratos)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h2>Nenhum contrato encontrado</h2>
                <p>Você ainda não possui contratos cadastrados.</p>
            </div>
        <?php else: ?>
            <div class="contracts-grid">
                <?php foreach ($contratos as $contrato): ?>
                    <div class="contract-card">
                        <div class="contract-header <?php echo $contrato['status']; ?>">
                            <div class="contract-title">
                                <h3><?php echo htmlspecialchars($contrato['descricao']); ?></h3>
                                <p>Contrato #<?php echo $contrato['id']; ?></p>
                            </div>
                            <div class="contract-status <?php echo $contrato['status']; ?>">
                                <?php
                                $statusLabels = [
                                    'ativo' => '✓ Ativo',
                                    'suspenso' => '⏸ Suspenso',
                                    'cancelado' => '✗ Cancelado',
                                    'finalizado' => '✓ Finalizado'
                                ];
                                echo $statusLabels[$contrato['status']] ?? ucfirst($contrato['status']);
                                ?>
                            </div>
                        </div>
                        
                        <div class="contract-body">
                            <div class="contract-info-grid">
                                <div class="info-item">
                                    <span class="info-label">Valor Total</span>
                                    <span class="info-value large"><?php echo formatMoeda($contrato['valor_total']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Forma de Pagamento</span>
                                    <span class="info-value">
                                        <?php
                                        $formas = [
                                            'boleto' => '📄 Boleto',
                                            'cartao_credito' => '💳 Cartão de Crédito',
                                            'pix' => '🔲 PIX',
                                            'transferencia' => '🏦 Transferência'
                                        ];
                                        echo $formas[$contrato['forma_pagamento']] ?? ucfirst(str_replace('_', ' ', $contrato['forma_pagamento']));
                                        ?>
                                    </span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Parcelas</span>
                                    <span class="info-value"><?php echo $contrato['parcelas']; ?>x</span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Período</span>
                                    <span class="info-value">
                                        <?php echo date('d/m/Y', strtotime($contrato['data_inicio'])); ?> até 
                                        <?php echo date('d/m/Y', strtotime($contrato['data_fim'])); ?>
                                    </span>
                                </div>
                                
                                <?php if ($contrato['recorrencia'] && $contrato['recorrencia'] != 'unico'): ?>
                                <div class="info-item">
                                    <span class="info-label">Recorrência</span>
                                    <span class="info-value">
                                        <?php
                                        $recorrencias = [
                                            'mensal' => 'Mensal',
                                            'trimestral' => 'Trimestral',
                                            'semestral' => 'Semestral',
                                            'anual' => 'Anual'
                                        ];
                                        echo $recorrencias[$contrato['recorrencia']] ?? ucfirst($contrato['recorrencia']);
                                        ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($contrato['observacoes']): ?>
                                <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                                    <div class="info-label">Observações</div>
                                    <p style="color: #475569; margin-top: 0.5rem; line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($contrato['observacoes'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="contract-actions">
                                <?php if (!empty($contrato['arquivo_contrato'])): ?>
                                    <a href="<?php echo htmlspecialchars($contrato['arquivo_contrato']); ?>" 
                                       target="_blank" 
                                       class="btn btn-primary">
                                        📄 Visualizar Contrato
                                    </a>
                                    <a href="<?php echo htmlspecialchars($contrato['arquivo_contrato']); ?>" 
                                       download 
                                       class="btn btn-secondary">
                                        ⬇️ Baixar PDF
                                    </a>
                                <?php else: ?>
                                    <span style="color: #64748b; font-size: 0.9rem;">
                                        📎 Contrato não disponível para download
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
