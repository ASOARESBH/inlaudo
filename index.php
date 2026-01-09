<?php
/**
 * Dashboard Profissional - ERP Inlaudo
 * URL: https://erp.inlaudo.com.br/
 * 
 * Dashboard integrado com Bootstrap 5, gráficos e design responsivo
 * Mantém todas as referências de páginas do sistema
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$pageTitle = 'Dashboard';
$conn = getConnection();

try {
    // ============================================================================
    // BUSCAR ESTATÍSTICAS
    // ============================================================================
    
    // Total de clientes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE tipo_cliente = 'CLIENTE' LIMIT 1");
    $totalClientes = $stmt->fetch()['total'] ?? 0;
    
    // Total de leads
    $stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE tipo_cliente = 'LEAD' LIMIT 1");
    $totalLeads = $stmt->fetch()['total'] ?? 0;
    
    // Receita mensal
    $stmt = $conn->query("SELECT SUM(valor) as total FROM contas_receber WHERE MONTH(data_vencimento) = MONTH(NOW()) AND YEAR(data_vencimento) = YEAR(NOW()) AND status IN ('pendente', 'confirmado') LIMIT 1");
    $receitaMensal = $stmt->fetch()['total'] ?? 0;
    
    // Contas a receber pendentes
    $stmt = $conn->query("SELECT COUNT(*) as total, SUM(valor) as valor_total FROM contas_receber WHERE status IN ('pendente', 'confirmado') LIMIT 1");
    $contasReceber = $stmt->fetch();
    
    // Contas a pagar pendentes
    $stmt = $conn->query("SELECT COUNT(*) as total, SUM(valor) as valor_total FROM contas_pagar WHERE status IN ('pendente', 'confirmado') LIMIT 1");
    $contasPagar = $stmt->fetch();
    
    // Próximas interações (próximos 7 dias)
    $dataLimite = date('Y-m-d H:i:s', strtotime('+7 days'));
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM interacoes WHERE proximo_contato_data <= ? AND proximo_contato_data >= NOW() LIMIT 1");
    $stmt->execute([$dataLimite]);
    $proximasInteracoes = $stmt->fetch()['total'] ?? 0;
    
    // Contas vencidas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM contas_receber WHERE status = 'vencido' LIMIT 1");
    $contasVencidas = $stmt->fetch()['total'] ?? 0;
    
    // Fluxo de Caixa (últimos 10 meses)
    $stmt = $conn->query("
        SELECT DATE_FORMAT(data_vencimento, '%b/%y') as mes, 
               SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas,
               SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas
        FROM fluxo_caixa 
        WHERE data_vencimento >= DATE_SUB(NOW(), INTERVAL 10 MONTH)
        GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')
        ORDER BY data_vencimento
        LIMIT 10
    ");
    $fluxoDados = $stmt->fetchAll() ?? [];
    
    // Contas por Status
    $stmt = $conn->query("SELECT status, COUNT(*) as total FROM contas_receber GROUP BY status LIMIT 10");
    $contasStatus = $stmt->fetchAll() ?? [];
    
    // Últimas interações
    $stmt = $conn->query("
        SELECT c.nome as cliente, i.tipo, i.data_criacao, i.status 
        FROM interacoes i
        JOIN clientes c ON i.cliente_id = c.id
        ORDER BY i.data_criacao DESC LIMIT 5
    ");
    $ultimasInteracoes = $stmt->fetchAll() ?? [];
    
} catch (Exception $e) {
    error_log("Erro ao carregar dashboard: " . $e->getMessage());
    $totalClientes = 0;
    $totalLeads = 0;
    $receitaMensal = 0;
    $contasReceber = ['total' => 0, 'valor_total' => 0];
    $contasPagar = ['total' => 0, 'valor_total' => 0];
    $proximasInteracoes = 0;
    $contasVencidas = 0;
    $fluxoDados = [];
    $contasStatus = [];
    $ultimasInteracoes = [];
}

include 'header.php';
?>

<div class="container-fluid">
    
    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #1e40af); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Clientes</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $totalClientes; ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Ativos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0891b2, #06b6d4); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Leads</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $totalLeads; ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Novos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #16a34a, #15803d); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Receita</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #1f2937;">R$ <?php echo number_format($receitaMensal, 0, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Este mês</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">A Receber</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $contasReceber['total'] ?? 0; ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #dc2626, #b91c1c); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">A Pagar</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $contasPagar['total'] ?? 0; ?></div>
                            <div style="font-size: 0.8rem; color: #dc2626; font-weight: 600;"><i class="fas fa-arrow-down"></i> Pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0891b2, #0e7490); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Vencidas</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo $contasVencidas; ?></div>
                            <div style="font-size: 0.8rem; color: #dc2626; font-weight: 600;"><i class="fas fa-arrow-down"></i> Atenção</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Fluxo de Caixa -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;"><i class="fas fa-chart-area" style="color: #3b82f6; margin-right: 0.5rem;"></i>Fluxo de Caixa</h5>
                </div>
                <div class="card-body">
                    <canvas id="fluxoCaixaChart" height="80"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Contas por Status -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;"><i class="fas fa-pie-chart" style="color: #3b82f6; margin-right: 0.5rem;"></i>Status das Contas</h5>
                </div>
                <div class="card-body" style="display: flex; justify-content: center;">
                    <canvas id="contasStatusChart" style="max-width: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Últimas Interações -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0;"><i class="fas fa-history" style="color: #3b82f6; margin-right: 0.5rem;"></i>Últimas Interações</h5>
                    <a href="interacoes.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Ver todas ></a>
                </div>
                <div class="card-body">
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Tipo</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimasInteracoes)): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #64748b; padding: 2rem;">
                                            Nenhuma interação registrada
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimasInteracoes as $interacao): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($interacao['cliente'] ?? 'N/A', 0, 30)); ?></td>
                                            <td>
                                                <i class="fas fa-<?php echo $interacao['tipo'] === 'ligacao' ? 'phone' : ($interacao['tipo'] === 'email' ? 'envelope' : 'comments'); ?>" style="margin-right: 0.5rem;"></i>
                                                <?php echo ucfirst($interacao['tipo'] ?? 'N/A'); ?>
                                            </td>
                                            <td><?php echo date('d/m H:i', strtotime($interacao['data_criacao'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $interacao['status'] === 'concluida' ? 'success' : ($interacao['status'] === 'pendente' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($interacao['status'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Access -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;"><i class="fas fa-bolt" style="color: #3b82f6; margin-right: 0.5rem;"></i>Acesso Rápido</h5>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                        <a href="clientes.php" class="btn btn-primary">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                        <a href="interacoes.php" class="btn btn-primary">
                            <i class="fas fa-comments"></i> Interações
                        </a>
                        <a href="contas_receber.php" class="btn btn-success">
                            <i class="fas fa-money-bill"></i> A Receber
                        </a>
                        <a href="contas_pagar.php" class="btn btn-danger">
                            <i class="fas fa-credit-card"></i> A Pagar
                        </a>
                        <a href="contratos.php" class="btn btn-info">
                            <i class="fas fa-file-contract"></i> Contratos
                        </a>
                        <a href="relatorios.php" class="btn btn-warning">
                            <i class="fas fa-chart-bar"></i> Relatórios
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Dados do Fluxo de Caixa
    const fluxoDados = <?php echo json_encode($fluxoDados ?? []); ?>;
    const contasStatusDados = <?php echo json_encode($contasStatus ?? []); ?>;
    
    // Gráfico Fluxo de Caixa
    if (document.getElementById('fluxoCaixaChart')) {
        const ctxFluxo = document.getElementById('fluxoCaixaChart').getContext('2d');
        new Chart(ctxFluxo, {
            type: 'line',
            data: {
                labels: fluxoDados.map(d => d.mes),
                datasets: [
                    {
                        label: 'Entradas',
                        data: fluxoDados.map(d => d.entradas || 0),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#16a34a',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Saídas',
                        data: fluxoDados.map(d => d.saidas || 0),
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#dc2626',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { weight: '600', size: 11 },
                            padding: 12,
                            usePointStyle: true
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            },
                            font: { size: 10 }
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 10 }
                        }
                    }
                }
            }
        });
    }
    
    // Gráfico Contas por Status
    if (document.getElementById('contasStatusChart')) {
        const ctxStatus = document.getElementById('contasStatusChart').getContext('2d');
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: contasStatusDados.map(d => {
                    const statusMap = {
                        'pendente': 'Pendentes',
                        'confirmado': 'Confirmadas',
                        'pago': 'Pagas',
                        'cancelado': 'Canceladas',
                        'vencido': 'Vencidas'
                    };
                    return statusMap[d.status] || d.status;
                }),
                datasets: [{
                    data: contasStatusDados.map(d => d.total),
                    backgroundColor: [
                        '#3b82f6',
                        '#16a34a',
                        '#10b981',
                        '#f59e0b',
                        '#dc2626'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { weight: '600', size: 10 },
                            padding: 12,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
</script>

<?php include 'footer.php'; ?>
