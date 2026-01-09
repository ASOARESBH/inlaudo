<?php
require_once 'config.php';

$pageTitle = 'Boletos Gerados';

// Buscar boletos
$conn = getConnection();
$sql = "SELECT b.*, cr.descricao as conta_descricao, c.nome, c.razao_social, c.nome_fantasia, c.tipo_pessoa
        FROM boletos b
        INNER JOIN contas_receber cr ON b.conta_receber_id = cr.id
        INNER JOIN clientes c ON cr.cliente_id = c.id
        ORDER BY b.data_geracao DESC";

$stmt = $conn->query($sql);
$boletos = $stmt->fetchAll();

// Calcular totais
$totalPendente = 0;
$totalPago = 0;

foreach ($boletos as $boleto) {
    if ($boleto['status'] == 'pendente') $totalPendente += $boleto['valor'];
    if ($boleto['status'] == 'pago') $totalPago += $boleto['valor'];
}

include 'header.php';
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Boletos Gerados</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Pendente</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalPendente); ?></p>
            </div>
            <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 8px;">
                <p style="font-size: 0.875rem; margin-bottom: 0.5rem; opacity: 0.9;">Total Pago</p>
                <p style="font-size: 1.5rem; font-weight: 600;"><?php echo formatMoeda($totalPago); ?></p>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Plataforma</th>
                        <th>Valor</th>
                        <th>Vencimento</th>
                        <th>Status</th>
                        <th>Data Geração</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($boletos)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                Nenhum boleto gerado ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($boletos as $boleto): ?>
                            <tr>
                                <td>
                                    <?php 
                                    echo $boleto['tipo_pessoa'] == 'CNPJ' 
                                        ? ($boleto['razao_social'] ?: $boleto['nome_fantasia']) 
                                        : $boleto['nome']; 
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($boleto['conta_descricao']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $boleto['plataforma'] == 'stripe' ? 'cliente' : 'lead'; ?>">
                                        <?php echo strtoupper($boleto['plataforma']); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;"><?php echo formatMoeda($boleto['valor']); ?></td>
                                <td><?php echo formatData($boleto['data_vencimento']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $boleto['status']; ?>">
                                        <?php echo ucfirst($boleto['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatDataHora($boleto['data_geracao']); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($boleto['url_boleto']): ?>
                                            <a href="<?php echo htmlspecialchars($boleto['url_boleto']); ?>" 
                                               target="_blank" class="btn btn-primary">
                                                Ver Boleto
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($boleto['url_pdf']): ?>
                                            <a href="<?php echo htmlspecialchars($boleto['url_pdf']); ?>" 
                                               target="_blank" class="btn btn-primary">
                                                PDF
                                            </a>
                                        <?php endif; ?>
                                        
                                        <button onclick="mostrarDetalhes(<?php echo $boleto['id']; ?>)" 
                                                class="btn btn-secondary">
                                            Detalhes
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Detalhes do boleto (oculto por padrão) -->
                            <tr id="detalhes_<?php echo $boleto['id']; ?>" style="display: none;">
                                <td colspan="8" style="background: #f9fafb; padding: 1.5rem;">
                                    <h4 style="margin-bottom: 1rem;">Detalhes do Boleto</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <?php if ($boleto['boleto_id']): ?>
                                        <div>
                                            <strong>ID do Boleto:</strong><br>
                                            <code><?php echo htmlspecialchars($boleto['boleto_id']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($boleto['codigo_barras']): ?>
                                        <div>
                                            <strong>Código de Barras:</strong><br>
                                            <code><?php echo htmlspecialchars($boleto['codigo_barras']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($boleto['linha_digitavel']): ?>
                                        <div>
                                            <strong>Linha Digitável:</strong><br>
                                            <code><?php echo htmlspecialchars($boleto['linha_digitavel']); ?></code>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function mostrarDetalhes(boletoId) {
        const detalhes = document.getElementById('detalhes_' + boletoId);
        if (detalhes.style.display === 'none') {
            detalhes.style.display = 'table-row';
        } else {
            detalhes.style.display = 'none';
        }
    }
</script>

<?php include 'footer.php'; ?>
