<?php
/**
 * Portal do Cliente - Helpdesk (Interações)
 */

session_start();
require_once 'config.php';
require_once 'header_cliente.php';

$conn = getConnection();
$cliente_id = $_SESSION['cliente_id'];

$mensagem = '';
$erro = '';

// Processar nova interação
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nova_interacao'])) {
    $data_hora = $_POST['data_hora'] ?? '';
    $historico = trim($_POST['historico'] ?? '');
    $forma_contato = $_POST['forma_contato'] ?? '';
    $proximo_contato_data = $_POST['proximo_contato_data'] ?? null;
    $proximo_contato_forma = $_POST['proximo_contato_forma'] ?? null;
    
    if (empty($data_hora) || empty($historico) || empty($forma_contato)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO interacoes (cliente_id, data_hora, historico, forma_contato, proximo_contato, proximo_contato_forma)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $cliente_id,
                $data_hora,
                $historico,
                $forma_contato,
                !empty($proximo_contato_data) ? $proximo_contato_data : null,
                !empty($proximo_contato_forma) ? $proximo_contato_forma : null
            ]);
            
            $mensagem = 'Interação registrada com sucesso!';
            
            // Limpar campos
            $_POST = [];
            
        } catch (Exception $e) {
            $erro = 'Erro ao registrar interação. Tente novamente.';
        }
    }
}

// Buscar interações do cliente
$stmt = $conn->prepare("
    SELECT * FROM interacoes 
    WHERE cliente_id = ? 
    ORDER BY data_hora DESC
");
$stmt->execute([$cliente_id]);
$interacoes = $stmt->fetchAll();
?>

<h1 style="color: #1e293b; margin: 0 0 25px 0;">Helpdesk - Suporte e Atendimento</h1>

<?php if ($mensagem): ?>
    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #10b981;">
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #dc2626;">
        <?php echo htmlspecialchars($erro); ?>
    </div>
<?php endif; ?>

<!-- Formulário Nova Interação -->
<div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 1.3rem;">Abrir Nova Solicitação</h2>
    
    <form method="POST">
        <input type="hidden" name="nova_interacao" value="1">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div>
                <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Data e Hora *</label>
                <input type="datetime-local" name="data_hora" value="<?php echo date('Y-m-d\TH:i'); ?>" required
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
            </div>
            
            <div>
                <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Forma de Contato *</label>
                <select name="forma_contato" required
                        style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                    <option value="">Selecione...</option>
                    <option value="telefone">📞 Telefone</option>
                    <option value="email">📧 E-mail</option>
                    <option value="presencial">👤 Presencial</option>
                    <option value="whatsapp">💬 WhatsApp</option>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Descrição do Problema / Solicitação *</label>
            <textarea name="historico" rows="6" required placeholder="Descreva sua solicitação ou problema..."
                      style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; resize: vertical;"></textarea>
        </div>
        
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3 style="color: #1e293b; margin: 0 0 15px 0; font-size: 1.1rem;">Agendar Próximo Contato (Opcional)</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Data e Hora</label>
                    <input type="datetime-local" name="proximo_contato_data"
                           style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                </div>
                
                <div>
                    <label style="display: block; color: #64748b; font-weight: 600; margin-bottom: 8px;">Forma de Contato</label>
                    <select name="proximo_contato_forma"
                            style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem;">
                        <option value="">Selecione...</option>
                        <option value="telefone">📞 Telefone</option>
                        <option value="email">📧 E-mail</option>
                        <option value="presencial">👤 Presencial</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                    </select>
                </div>
            </div>
        </div>
        
        <button type="submit" style="padding: 14px 32px; background: #10b981; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
            Enviar Solicitação
        </button>
    </form>
</div>

<!-- Histórico de Interações -->
<h2 style="color: #1e293b; margin: 0 0 20px 0; font-size: 1.3rem;">Histórico de Atendimentos</h2>

<?php if (empty($interacoes)): ?>
    <div style="background: #f8fafc; padding: 40px; border-radius: 12px; text-align: center; color: #64748b;">
        <p style="font-size: 1.2rem; margin: 0;">Nenhum atendimento registrado.</p>
        <p style="margin: 10px 0 0 0;">Suas solicitações aparecerão aqui.</p>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($interacoes as $interacao): ?>
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid #3b82f6;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 5px 0;">Data e Hora do Atendimento</p>
                        <p style="color: #1e293b; font-weight: 700; font-size: 1.1rem; margin: 0;"><?php echo formatarDataHora($interacao['data_hora']); ?></p>
                    </div>
                    <div>
                        <?php 
                            $formas_icon = ['telefone' => '📞', 'email' => '📧', 'presencial' => '👤', 'whatsapp' => '💬'];
                            $formas_label = ['telefone' => 'Telefone', 'email' => 'E-mail', 'presencial' => 'Presencial', 'whatsapp' => 'WhatsApp'];
                        ?>
                        <span style="background: #dbeafe; color: #1e40af; padding: 8px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 600;">
                            <?php echo $formas_icon[$interacao['forma_contato']] ?? ''; ?> 
                            <?php echo $formas_label[$interacao['forma_contato']] ?? $interacao['forma_contato']; ?>
                        </span>
                    </div>
                </div>
                
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <p style="color: #64748b; font-size: 0.85rem; margin: 0 0 8px 0; font-weight: 600;">Descrição:</p>
                    <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($interacao['historico']); ?></p>
                </div>
                
                <?php if (!empty($interacao['proximo_contato'])): ?>
                    <div style="background: #fef3c7; padding: 15px; border-radius: 8px; border-left: 3px solid #f59e0b;">
                        <p style="color: #92400e; font-size: 0.85rem; margin: 0 0 5px 0; font-weight: 600;">⏰ Próximo Contato Agendado:</p>
                        <p style="color: #92400e; margin: 0;">
                            <?php echo formatarDataHora($interacao['proximo_contato']); ?> - 
                            <?php echo $formas_icon[$interacao['proximo_contato_forma']] ?? ''; ?>
                            <?php echo $formas_label[$interacao['proximo_contato_forma']] ?? $interacao['proximo_contato_forma']; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once 'footer_cliente.php'; ?>
