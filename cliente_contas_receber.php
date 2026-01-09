<?php
/**
 * Portal do Cliente - Contas a Receber
 * Exibe todas as contas a receber do cliente (de contratos e avulsas)
 * ERP INLAUDO
 */

require_once 'verifica_sessao_cliente.php';
require_once 'config.php';

$conn = getConnection();

// Buscar todas as contas a receber do cliente
$stmt = $conn->prepare("
    SELECT 
        cr.*,
        c.nome as contrato_nome,
        c.descricao as contrato_descricao
    FROM contas_receber cr
    LEFT JOIN contratos c ON cr.contrato_id = c.id
    WHERE cr.cliente_id = ?
    ORDER BY cr.data_vencimento DESC, cr.id DESC
");
$stmt->execute([$cliente_id]);
$contas = $stmt->fetchAll();

// Buscar dados do cliente
$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$cliente_id]);
$cliente = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Contas - Portal do Cliente</title>
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
        
        .header-left h1 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
        }
        
        .header-left p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .btn-voltar {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-voltar:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #374151;
        }
        
        .filter-group select {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .contas-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .conta-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .conta-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .conta-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .conta-info {
            flex: 1;
        }
        
        .conta-descricao {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .conta-origem {
            font-size: 0.875rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge-pendente {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-pago {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-vencido {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .conta-body {
            padding: 1.5rem;
        }
        
        .conta-detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detalhe-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .detalhe-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .detalhe-valor {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .valor-destaque {
            font-size: 1.5rem;
            color: #10b981;
        }
        
        .anexos-section {
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }
        
        .anexos-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .anexos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .anexo-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .anexo-item:hover {
            background: #f3f4f6;
            border-color: #10b981;
            transform: translateX(4px);
        }
        
        .anexo-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .anexo-info {
            flex: 1;
            min-width: 0;
        }
        
        .anexo-nome {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .anexo-tamanho {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .acoes-conta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
        }
        
        .btn-primary:hover {
            background: #059669;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .no-contas {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .no-contas-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .no-contas-text {
            font-size: 1.25rem;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .conta-detalhes {
                grid-template-columns: 1fr;
            }
            
            .anexos-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <h1>💰 Minhas Contas</h1>
                <p>Bem-vindo(a), <?php echo htmlspecialchars($cliente['nome'] ?: $cliente['razao_social']); ?></p>
            </div>
            <a href="portal_cliente.php" class="btn-voltar">← Voltar ao Portal</a>
        </div>
    </div>
    
    <div class="container">
        <h2 class="page-title">Contas a Receber</h2>
        <p class="page-subtitle">Visualize todas as suas contas, de contratos e avulsas, com anexos disponíveis para download</p>
        
        <div class="filters">
            <div class="filter-group">
                <label>Status:</label>
                <select id="filtroStatus" onchange="filtrarContas()">
                    <option value="">Todos</option>
                    <option value="pendente">Pendentes</option>
                    <option value="pago">Pagas</option>
                    <option value="vencido">Vencidas</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Origem:</label>
                <select id="filtroOrigem" onchange="filtrarContas()">
                    <option value="">Todas</option>
                    <option value="contrato">De Contratos</option>
                    <option value="avulsa">Avulsas</option>
                </select>
            </div>
        </div>
        
        <?php if (count($contas) > 0): ?>
        <div class="contas-grid" id="contasGrid">
            <?php foreach ($contas as $conta): 
                // Determinar status
                $status = $conta['status'];
                if ($status == 'pendente' && strtotime($conta['data_vencimento']) < time()) {
                    $status = 'vencido';
                }
                
                // Determinar origem
                $origem = $conta['contrato_id'] ? 'contrato' : 'avulsa';
                
                // Buscar anexos
                $stmtAnexos = $conn->prepare("SELECT * FROM contas_receber_anexos WHERE conta_receber_id = ? ORDER BY data_upload DESC");
                $stmtAnexos->execute([$conta['id']]);
                $anexos = $stmtAnexos->fetchAll();
            ?>
            <div class="conta-card" data-status="<?php echo $status; ?>" data-origem="<?php echo $origem; ?>">
                <div class="conta-header">
                    <div class="conta-info">
                        <div class="conta-descricao">
                            <?php echo htmlspecialchars($conta['descricao']); ?>
                        </div>
                        <div class="conta-origem">
                            <?php if ($conta['contrato_id']): ?>
                                📄 De Contrato: <?php echo htmlspecialchars($conta['contrato_nome']); ?>
                            <?php else: ?>
                                📋 Conta Avulsa
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge badge-<?php echo $status; ?>">
                        <?php 
                        if ($status == 'pendente') echo '⏳ Pendente';
                        elseif ($status == 'pago') echo '✅ Pago';
                        elseif ($status == 'vencido') echo '⚠️ Vencido';
                        ?>
                    </span>
                </div>
                
                <div class="conta-body">
                    <div class="conta-detalhes">
                        <div class="detalhe-item">
                            <span class="detalhe-label">Valor</span>
                            <span class="detalhe-valor valor-destaque">
                                <?php echo formatarMoeda($conta['valor']); ?>
                            </span>
                        </div>
                        
                        <div class="detalhe-item">
                            <span class="detalhe-label">Vencimento</span>
                            <span class="detalhe-valor">
                                <?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?>
                            </span>
                        </div>
                        
                        <div class="detalhe-item">
                            <span class="detalhe-label">Forma de Pagamento</span>
                            <span class="detalhe-valor">
                                <?php echo ucfirst($conta['forma_pagamento']); ?>
                            </span>
                        </div>
                        
                        <?php if ($conta['recorrencia'] > 1): ?>
                        <div class="detalhe-item">
                            <span class="detalhe-label">Parcela</span>
                            <span class="detalhe-valor">
                                <?php echo $conta['parcela_atual']; ?>/<?php echo $conta['recorrencia']; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($anexos) > 0): ?>
                    <div class="anexos-section">
                        <div class="anexos-title">
                            📎 Anexos (<?php echo count($anexos); ?>)
                        </div>
                        <div class="anexos-grid">
                            <?php foreach ($anexos as $anexo): 
                                $ext = strtolower(pathinfo($anexo['nome_arquivo'], PATHINFO_EXTENSION));
                                $icon = '📎';
                                if ($ext == 'pdf') $icon = '📄';
                                elseif (in_array($ext, ['doc', 'docx'])) $icon = '📝';
                                elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '📊';
                                elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) $icon = '🖼️';
                            ?>
                            <a href="<?php echo $anexo['caminho_arquivo']; ?>" target="_blank" class="anexo-item" title="Clique para abrir em nova aba">
                                <span class="anexo-icon"><?php echo $icon; ?></span>
                                <div class="anexo-info">
                                    <div class="anexo-nome"><?php echo htmlspecialchars($anexo['nome_original']); ?></div>
                                    <div class="anexo-tamanho"><?php echo number_format($anexo['tamanho_arquivo'] / 1024, 2); ?> KB</div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conta['status'] == 'pendente' && $conta['forma_pagamento'] == 'boleto'): ?>
                    <div class="acoes-conta" style="margin-top: 1.5rem;">
                        <a href="gerar_link_pagamento.php?conta_id=<?php echo $conta['id']; ?>&gateway=cora&origem=cliente" 
                           class="btn btn-primary" target="_blank">
                            💳 Gerar Boleto
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-contas">
            <div class="no-contas-icon">📭</div>
            <p class="no-contas-text">Você não possui contas cadastradas no momento.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function filtrarContas() {
            const filtroStatus = document.getElementById('filtroStatus').value;
            const filtroOrigem = document.getElementById('filtroOrigem').value;
            const cards = document.querySelectorAll('.conta-card');
            
            cards.forEach(card => {
                const status = card.getAttribute('data-status');
                const origem = card.getAttribute('data-origem');
                
                let mostrar = true;
                
                if (filtroStatus && status !== filtroStatus) {
                    mostrar = false;
                }
                
                if (filtroOrigem && origem !== filtroOrigem) {
                    mostrar = false;
                }
                
                card.style.display = mostrar ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>
