<?php
/**
 * Dashboard Profissional - ERP Inlaudo
 * URL: https://erp.inlaudo.com.br/
 * 
 * Dashboard integrado com Bootstrap 5, gráficos e design responsivo
 * VERSÃO 2.0 - Com integração completa ao banco de dados
 * 
 * @author ERP INLAUDO
 * @version 2.0.0
 * @date 2026-01-09
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'src/models/DashboardModel.php';

$pageTitle = 'Dashboard';

try {
    $conn = getConnection();
    $dashboardModel = new DashboardModel($conn);
    
    // ============================================================================
    // BUSCAR ESTATÍSTICAS REAIS DO BANCO DE DADOS
    // ============================================================================
    
    // Total de clientes ativos
    $totalClientes = $dashboardModel->getTotalClientesAtivos();
    
    // Total de leads (novos clientes nos últimos 30 dias)
    $totalLeads = $dashboardModel->getTotalLeads();
    
    // Receita mensal (contas pagas no mês atual)
    $receitaMensal = $dashboardModel->getReceitaMensal();
    
    // Contas a receber pendentes
    $contasReceber = $dashboardModel->getContasReceber();
    
    // Contas a pagar pendentes
    $contasPagar = $dashboardModel->getContasPagar();
    
    // Contas vencidas
    $contasVencidas = $dashboardModel->getContasVencidas();
    
    // Fluxo de Caixa (últimos 10 meses)
    $fluxoDados = $dashboardModel->getFluxoCaixa();
    
    // Contas por Status
    $contasStatus = $dashboardModel->getContasPorStatus();
    
    // Últimas contas a receber
    $ultimasContas = $dashboardModel->getUltimasContasReceber(5);
    
} catch (Exception $e) {
    error_log("Erro ao carregar dashboard: " . $e->getMessage());
    
    // Valores padrão em caso de erro
    $totalClientes = 0;
    $totalLeads = 0;
    $receitaMensal = 0;
    $contasReceber = ['total' => 0, 'valor_total' => 0];
    $contasPagar = ['total' => 0, 'valor_total' => 0];
    $contasVencidas = 0;
    $fluxoDados = [];
    $contasStatus = [];
    $ultimasContas = [];
}

include 'header.php';
?>

<div class="container-fluid">
    
    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <!-- Card: Clientes Ativos -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #1e40af); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Clientes</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo number_format($totalClientes, 0, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Ativos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: Leads Novos -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0891b2, #06b6d4); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Leads</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo number_format($totalLeads, 0, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Novos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: Receita do Mês -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #16a34a, #15803d); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Receita</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: #1f2937;">R$ <?php echo number_format($receitaMensal, 2, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Este mês</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: A Receber -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">A Receber</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo number_format($contasReceber['total'], 0, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #16a34a; font-weight: 600;"><i class="fas fa-arrow-up"></i> Pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: A Pagar -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #dc2626, #b91c1c); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">A Pagar</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo number_format($contasPagar['total'], 0, ',', '.'); ?></div>
                            <div style="font-size: 0.8rem; color: #dc2626; font-weight: 600;"><i class="fas fa-arrow-down"></i> Pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Card: Vencidas -->
        <div class="col-lg-2 col-md-4 col-sm-6 col-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #0891b2, #0e7490); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Vencidas</div>
                            <div style="font-size: 1.75rem; font-weight: 700; color: #1f2937;"><?php echo number_format($contasVencidas, 0, ',', '.'); ?></div>
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
    
    <!-- Últimas Contas a Receber -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0;"><i class="fas fa-history" style="color: #3b82f6; margin-right: 0.5rem;"></i>Últimas Contas a Receber</h5>
                    <a href="contas_receber.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Ver todas ></a>
                </div>
                <div class="card-body">
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Descrição</th>
                                    <th>Valor</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ultimasContas)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">
                                            Nenhuma conta registrada
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ultimasContas as $conta): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($conta['id']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($conta['cliente_nome'] ?? 'N/A', 0, 30)); ?></td>
                                            <td><?php echo htmlspecialchars(substr($conta['descricao'] ?? 'N/A', 0, 40)); ?></td>
                                            <td>R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusText = 'Indefinido';
                                                
                                                switch($conta['status']) {
                                                    case 'pago':
                                                        $statusClass = 'success';
                                                        $statusText = 'Pago';
                                                        break;
                                                    case 'pendente':
                                                        $statusClass = 'warning';
                                                        $statusText = 'Pendente';
                                                        break;
                                                    case 'vencido':
                                                        $statusClass = 'danger';
                                                        $statusText = 'Vencido';
                                                        break;
                                                    case 'cancelado':
                                                        $statusClass = 'secondary';
                                                        $statusText = 'Cancelado';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
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
                        <a href="configuracoes.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Configurações
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
    
    console.log('Fluxo de Caixa:', fluxoDados);
    console.log('Status das Contas:', contasStatusDados);
    
    // Gráfico Fluxo de Caixa
    if (document.getElementById('fluxoCaixaChart')) {
        const ctxFluxo = document.getElementById('fluxoCaixaChart').getContext('2d');
        new Chart(ctxFluxo, {
            type: 'line',
            data: {
                labels: fluxoDados.map(d => d.mes_formatado || 'N/A'),
                datasets: [
                    {
                        label: 'Entradas',
                        data: fluxoDados.map(d => parseFloat(d.entradas) || 0),
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
                        data: fluxoDados.map(d => parseFloat(d.saidas) || 0),
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
        
        const statusMap = {
            'pendente': 'Pendentes',
            'pago': 'Pagas',
            'vencido': 'Vencidas',
            'cancelado': 'Canceladas'
        };
        
        const colorMap = {
            'pendente': '#f59e0b',
            'pago': '#16a34a',
            'vencido': '#dc2626',
            'cancelado': '#64748b'
        };
        
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: contasStatusDados.map(d => statusMap[d.status] || d.status),
                datasets: [{
                    data: contasStatusDados.map(d => parseInt(d.total) || 0),
                    backgroundColor: contasStatusDados.map(d => colorMap[d.status] || '#3b82f6'),
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
