<?php
/**
 * Configuração de Integração - Mercado Pago
 * ERP INLAUDO
 * VERSÃO FINAL ALINHADA COM A TABELA REAL
 */

$pageTitle = 'Integração Mercado Pago';
require_once 'header.php';
require_once 'config.php';

$mensagem = '';
$tipo = '';

function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL);
}

// ===============================
// PROCESSAR FORMULÁRIO
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = getConnection();

        $publicKey     = trim($_POST['public_key'] ?? '');
        $accessToken   = trim($_POST['access_token'] ?? '');
        $webhookUrl    = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');
        $ambiente      = ($_POST['ambiente'] === 'teste') ? 'teste' : 'production';
        $ativo         = isset($_POST['ativo']) ? 1 : 0;
        $qrExpiracao   = (int)($_POST['qr_expiration_time'] ?? 300);
        $autoCapture   = isset($_POST['auto_capture']) ? 1 : 0;

        // ===== VALIDAÇÕES =====
        if (!$publicKey || !$accessToken) {
            throw new Exception('Public Key e Access Token são obrigatórios.');
        }

        if ($webhookUrl && !isValidUrl($webhookUrl)) {
            throw new Exception('URL do webhook inválida.');
        }

        // ===============================
        // SALVAR NA TABELA REAL
        // ===============================
        $stmt = $conn->prepare("
            INSERT INTO integracao_mercadopago
            (
                gateway,
                ativo,
                access_token,
                public_key,
                webhook_url,
                webhook_secret,
                ambiente,
                qr_expiration_time,
                auto_capture,
                datacriacao,
                dataatualizacao
            ) VALUES (
                'mercadopago',
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                ativo = VALUES(ativo),
                access_token = VALUES(access_token),
                public_key = VALUES(public_key),
                webhook_url = VALUES(webhook_url),
                webhook_secret = VALUES(webhook_secret),
                ambiente = VALUES(ambiente),
                qr_expiration_time = VALUES(qr_expiration_time),
                auto_capture = VALUES(auto_capture),
                dataatualizacao = NOW()
        ");

        $stmt->execute([
            $ativo,
            $accessToken,
            $publicKey,
            $webhookUrl,
            $webhookSecret,
            $ambiente,
            $qrExpiracao,
            $autoCapture
        ]);

        $mensagem = 'Configuração salva com sucesso!';
        $tipo = 'sucesso';

    } catch (Exception $e) {
        $mensagem = 'Erro: ' . $e->getMessage();
        $tipo = 'erro';
    }
}

// ===============================
// CARREGAR CONFIGURAÇÃO ATUAL
// ===============================
try {
    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT *
        FROM integracao_mercadopago
        WHERE gateway = 'mercadopago'
        LIMIT 1
    ");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $config = [];
}

// Webhook padrão
$webhookUrlPadrao = 'https://' . $_SERVER['HTTP_HOST'] . '/webhook/webhook_mercadopago.php';
?>

<div class="container-fluid px-4">
    <div class="row">
        <div class="col-12">

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-credit-card"></i> Integração Mercado Pago
                    </h4>
                </div>

                <div class="card-body">

                    <?php if ($mensagem): ?>
                        <div class="alert alert-<?php echo $tipo === 'sucesso' ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($mensagem); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">

                        <!-- ATIVO -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="ativo"
                                <?php echo ($config['ativo'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label"><strong>Integração Ativa</strong></label>
                        </div>

                        <!-- AMBIENTE -->
                        <label class="form-label"><strong>Ambiente</strong></label>
                        <div class="btn-group w-100 mb-4">
                            <input type="radio" class="btn-check" name="ambiente" value="teste"
                                <?php echo ($config['ambiente'] ?? 'production') === 'teste' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-warning">Teste</label>

                            <input type="radio" class="btn-check" name="ambiente" value="production"
                                <?php echo ($config['ambiente'] ?? 'production') === 'production' ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success">Produção</label>
                        </div>

                        <!-- PUBLIC KEY -->
                        <label class="form-label"><strong>Public Key</strong></label>
                        <input type="text" name="public_key" class="form-control mb-3"
                               value="<?php echo htmlspecialchars($config['public_key'] ?? ''); ?>" required>

                        <!-- ACCESS TOKEN -->
                        <label class="form-label"><strong>Access Token</strong></label>
                        <input type="text" name="access_token" class="form-control mb-3"
                               value="<?php echo htmlspecialchars($config['access_token'] ?? ''); ?>" required>

                        <!-- WEBHOOK -->
                        <label class="form-label"><strong>Webhook URL</strong></label>
                        <input type="url" name="webhook_url" class="form-control mb-3"
                               value="<?php echo htmlspecialchars($config['webhook_url'] ?? $webhookUrlPadrao); ?>">

                        <!-- WEBHOOK SECRET -->
                        <label class="form-label"><strong>Webhook Secret</strong></label>
                        <input type="text" name="webhook_secret" class="form-control mb-3"
                               value="<?php echo htmlspecialchars($config['webhook_secret'] ?? ''); ?>">

                        <!-- PIX -->
                        <label class="form-label"><strong>Expiração PIX (segundos)</strong></label>
                        <input type="number" name="qr_expiration_time" class="form-control mb-3"
                               value="<?php echo (int)($config['qr_expiration_time'] ?? 300); ?>">

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="auto_capture"
                                <?php echo ($config['auto_capture'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label">Captura automática</label>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Salvar Configuração
                        </button>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
