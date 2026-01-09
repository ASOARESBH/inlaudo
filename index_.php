<?php
require_once 'config.php';

$pageTitle = 'Dashboard';

// Buscar estatísticas
$conn = getConnection();

// Total de clientes
$stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE tipo_cliente = 'CLIENTE'");
$totalClientes = $stmt->fetch()['total'];

// Total de leads
$stmt = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE tipo_cliente = 'LEAD'");
$totalLeads = $stmt->fetch()['total'];

// Contas a receber pendentes
$stmt = $conn->query("SELECT COUNT(*) as total, SUM(valor) as valor_total FROM contas_receber WHERE status = 'pendente'");
$contasReceber = $stmt->fetch();

// Contas a pagar pendentes
$stmt = $conn->query("SELECT COUNT(*) as total, SUM(valor) as valor_total FROM contas_pagar WHERE status = 'pendente'");
$contasPagar = $stmt->fetch();

// Próximas interações (próximos 7 dias)
$dataLimite = date('Y-m-d H:i:s', strtotime('+7 days'));
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM interacoes WHERE proximo_contato_data <= ? AND proximo_contato_data >= NOW()");
$stmt->execute([$dataLimite]);
$proximasInteracoes = $stmt->fetch()['total'];

// Contas vencidas
$stmt = $conn->query("SELECT COUNT(*) as total FROM contas_receber WHERE status = 'vencido'");
$contasVencidas = $stmt->fetch()['total'];

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Dashboard - Visão Geral</h2>
        </div>
        
        <div class="dashboard-grid">
            <!-- Card Clientes -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h3><?php echo $totalClientes; ?></h3>
                <p>Total de Clientes</p>
            </div>
            
            <!-- Card Leads -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3><?php echo $totalLeads; ?></h3>
                <p>Total de Leads</p>
            </div>
            
            <!-- Card Contas a Receber -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3><?php echo $contasReceber['total'] ?: 0; ?></h3>
                <p>Contas a Receber Pendentes</p>
                <p style="font-size: 1.2rem; margin-top: 0.5rem; font-weight: 600;"><?php echo formatMoeda($contasReceber['valor_total'] ?: 0); ?></p>
            </div>
            
            <!-- Card Contas a Pagar -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <h3><?php echo $contasPagar['total'] ?: 0; ?></h3>
                <p>Contas a Pagar Pendentes</p>
                <p style="font-size: 1.2rem; margin-top: 0.5rem; font-weight: 600;"><?php echo formatMoeda($contasPagar['valor_total'] ?: 0); ?></p>
            </div>
            
            <!-- Card Próximas Interações -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                <h3><?php echo $proximasInteracoes; ?></h3>
                <p>Interações nos Próximos 7 Dias</p>
            </div>
            
            <!-- Card Contas Vencidas -->
            <div class="dashboard-card" style="background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%);">
                <h3><?php echo $contasVencidas; ?></h3>
                <p>Contas Vencidas</p>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2>Acesso Rápido</h2>
        </div>
        <div class="quick-access-grid">
            <a href="clientes.php" class="btn btn-primary">Gerenciar Clientes</a>
            <a href="interacoes.php" class="btn btn-primary">Gerenciar Interações</a>
            <a href="contas_receber.php" class="btn btn-success">Contas a Receber</a>
            <a href="contas_pagar.php" class="btn btn-danger">Contas a Pagar</a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
