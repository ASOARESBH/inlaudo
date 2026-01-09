# Exemplos de Uso - Integração Asaas

## 📚 Índice de Exemplos

1. [JavaScript/Frontend](#javascriptfrontend)
2. [PHP/Backend](#phpbackend)
3. [Casos de Uso](#casos-de-uso)
4. [Tratamento de Erros](#tratamento-de-erros)

---

## JavaScript/Frontend

### Exemplo 1: Criar Cliente e Cobrança PIX

```javascript
// Função para criar cliente e gerar cobrança PIX
async function criarCobrancaPix(clienteId, cpfCnpj, nome, valor, dataVencimento) {
    try {
        // 1. Criar/buscar cliente
        const clienteResponse = await fetch('/api/asaas/customers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cliente_id: clienteId,
                cpf_cnpj: cpfCnpj,
                nome: nome
            })
        });
        
        if (!clienteResponse.ok) {
            throw new Error('Erro ao criar cliente');
        }
        
        const clienteData = await clienteResponse.json();
        console.log('Cliente criado:', clienteData.customer_id);
        
        // 2. Criar cobrança PIX
        const pagamentoResponse = await fetch('/api/asaas/payments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conta_receber_id: contaId,
                tipo_cobranca: 'PIX',
                valor: valor,
                data_vencimento: dataVencimento
            })
        });
        
        if (!pagamentoResponse.ok) {
            throw new Error('Erro ao criar cobrança');
        }
        
        const pagamentoData = await pagamentoResponse.json();
        
        // 3. Exibir QR Code
        if (pagamentoData.additional.encodedImage) {
            const img = document.createElement('img');
            img.src = pagamentoData.additional.encodedImage;
            img.alt = 'QR Code PIX';
            document.getElementById('qrcode-container').appendChild(img);
        }
        
        // 4. Exibir chave copia e cola
        document.getElementById('pix-payload').value = pagamentoData.additional.payload;
        
        return pagamentoData;
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao gerar cobrança: ' + error.message);
    }
}

// Uso
criarCobrancaPix(
    123,                          // clienteId
    '12345678901234',             // cpfCnpj
    'Cliente Teste',              // nome
    150.00,                       // valor
    '2025-02-28'                  // dataVencimento
);
```

### Exemplo 2: Criar Cobrança Boleto

```javascript
async function criarCobrancaBoleto(contaId, valor, dataVencimento) {
    try {
        const response = await fetch('/api/asaas/payments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conta_receber_id: contaId,
                tipo_cobranca: 'BOLETO',
                valor: valor,
                data_vencimento: dataVencimento
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Exibir link do boleto
            const link = document.createElement('a');
            link.href = data.additional.bankSlipUrl;
            link.target = '_blank';
            link.textContent = 'Baixar Boleto';
            link.className = 'btn btn-primary';
            document.getElementById('boleto-container').appendChild(link);
            
            // Exibir linha digitável
            document.getElementById('linha-digitavel').value = 
                data.additional.identificationField;
            
            // Exibir nosso número
            document.getElementById('nosso-numero').value = 
                data.additional.nossoNumero;
        }
        
        return data;
        
    } catch (error) {
        console.error('Erro:', error);
    }
}
```

### Exemplo 3: Monitorar Status de Cobrança

```javascript
async function monitorarCobranca(paymentId, intervalo = 5000) {
    const verificar = async () => {
        try {
            const response = await fetch(`/api/asaas/payments/${paymentId}`);
            const data = await response.json();
            
            if (data.success) {
                const status = data.payment.status;
                
                // Atualizar UI
                document.getElementById('status').textContent = status;
                
                // Se pagamento recebido, parar de verificar
                if (status === 'RECEIVED' || status === 'CONFIRMED') {
                    clearInterval(timer);
                    mostrarMensagemSucesso('Pagamento recebido!');
                }
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
        }
    };
    
    // Verificar a cada intervalo
    const timer = setInterval(verificar, intervalo);
    
    // Verificar imediatamente
    verificar();
}

// Uso
monitorarCobranca('pay_080225913252', 5000);
```

### Exemplo 4: Formulário Completo

```html
<!DOCTYPE html>
<html>
<head>
    <title>Gerar Cobrança</title>
    <style>
        form { max-width: 500px; margin: 50px auto; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #764ba2; }
        .resultado { margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 5px; }
    </style>
</head>
<body>
    <form id="cobrancaForm">
        <h2>Gerar Cobrança</h2>
        
        <div class="form-group">
            <label for="contaId">ID da Conta:</label>
            <input type="number" id="contaId" required>
        </div>
        
        <div class="form-group">
            <label for="valor">Valor (R$):</label>
            <input type="number" id="valor" step="0.01" required>
        </div>
        
        <div class="form-group">
            <label for="dataVencimento">Data de Vencimento:</label>
            <input type="date" id="dataVencimento" required>
        </div>
        
        <div class="form-group">
            <label for="tipoCobranca">Tipo de Cobrança:</label>
            <select id="tipoCobranca" required>
                <option value="PIX">PIX (QR Code)</option>
                <option value="BOLETO">Boleto</option>
            </select>
        </div>
        
        <button type="submit">Gerar Cobrança</button>
    </form>
    
    <div id="resultado" class="resultado" style="display: none;"></div>
    
    <script>
        document.getElementById('cobrancaForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const contaId = document.getElementById('contaId').value;
            const valor = document.getElementById('valor').value;
            const dataVencimento = document.getElementById('dataVencimento').value;
            const tipoCobranca = document.getElementById('tipoCobranca').value;
            
            try {
                const response = await fetch('/api/asaas/payments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conta_receber_id: contaId,
                        tipo_cobranca: tipoCobranca,
                        valor: valor,
                        data_vencimento: dataVencimento
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    let html = `<h3>✓ Cobrança Criada</h3>`;
                    html += `<p><strong>ID:</strong> ${data.payment_id}</p>`;
                    html += `<p><strong>Valor:</strong> R$ ${data.value}</p>`;
                    html += `<p><strong>Vencimento:</strong> ${data.dueDate}</p>`;
                    
                    if (tipoCobranca === 'PIX') {
                        html += `<img src="${data.additional.encodedImage}" alt="QR Code" style="max-width: 300px;">`;
                        html += `<p><strong>Chave PIX:</strong> <input type="text" value="${data.additional.payload}" readonly></p>`;
                    } else {
                        html += `<a href="${data.additional.bankSlipUrl}" target="_blank" class="btn btn-primary">Baixar Boleto</a>`;
                        html += `<p><strong>Linha Digitável:</strong> <input type="text" value="${data.additional.identificationField}" readonly></p>`;
                    }
                    
                    document.getElementById('resultado').innerHTML = html;
                    document.getElementById('resultado').style.display = 'block';
                } else {
                    alert('Erro: ' + data.error);
                }
            } catch (error) {
                alert('Erro: ' + error.message);
            }
        });
    </script>
</body>
</html>
```

---

## PHP/Backend

### Exemplo 1: Criar Cliente

```php
<?php
require_once 'vendor/autoload.php';

use App\Services\AsaasService;

$asaas = new AsaasService();

try {
    $customer = $asaas->createCustomer([
        'name' => 'João Silva',
        'cpfCnpj' => '12345678901234',
        'mobilePhone' => '11999999999',
        'email' => 'joao@example.com'
    ]);
    
    echo "Cliente criado: " . $customer['id'];
    
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

### Exemplo 2: Criar Cobrança

```php
<?php
require_once 'vendor/autoload.php';

use App\Services\AsaasService;
use App\Core\Database;

$asaas = new AsaasService();
$db = Database::getInstance();

try {
    // Buscar cliente Asaas
    $sql = "SELECT asaas_customer_id FROM asaas_clientes WHERE cliente_id = ?";
    $cliente = $db->fetchOne($sql, [123]);
    
    if (!$cliente) {
        throw new \Exception('Cliente não encontrado');
    }
    
    // Criar cobrança
    $payment = $asaas->createPayment([
        'customerId' => $cliente['asaas_customer_id'],
        'billingType' => 'PIX',
        'value' => 150.00,
        'dueDate' => '2025-02-28',
        'description' => 'Fatura #001'
    ]);
    
    // Salvar referência
    $sql = "INSERT INTO asaas_pagamentos (conta_receber_id, asaas_payment_id, tipo_cobranca, valor, data_vencimento) 
            VALUES (?, ?, ?, ?, ?)";
    $db->execute($sql, [456, $payment['id'], 'PIX', 150.00, '2025-02-28']);
    
    echo "Cobrança criada: " . $payment['id'];
    
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

### Exemplo 3: Processar Webhook Manualmente

```php
<?php
require_once 'vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();

// Simular webhook
$event = [
    'id' => 'evt_test_123',
    'event' => 'PAYMENT_RECEIVED',
    'payment' => [
        'id' => 'pay_123',
        'status' => 'RECEIVED',
        'value' => 150.00,
        'netValue' => 147.50
    ]
];

try {
    // Buscar cobrança
    $sql = "SELECT conta_receber_id FROM asaas_pagamentos WHERE asaas_payment_id = ?";
    $mapping = $db->fetchOne($sql, [$event['payment']['id']]);
    
    if ($mapping) {
        // Atualizar conta
        $sql = "UPDATE contas_receber SET status = 'pago', data_pagamento = NOW() WHERE id = ?";
        $db->execute($sql, [$mapping['conta_receber_id']]);
        
        echo "Conta atualizada com sucesso";
    }
    
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

### Exemplo 4: Listar Cobranças Pendentes

```php
<?php
require_once 'vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();

try {
    $sql = "
        SELECT 
            cr.id,
            cr.descricao,
            cr.valor,
            cr.data_vencimento,
            ap.asaas_payment_id,
            ap.tipo_cobranca,
            ap.status_asaas
        FROM contas_receber cr
        LEFT JOIN asaas_pagamentos ap ON cr.id = ap.conta_receber_id
        WHERE cr.status != 'pago'
        AND ap.asaas_payment_id IS NOT NULL
        ORDER BY cr.data_vencimento ASC
    ";
    
    $cobracas = $db->fetchAll($sql);
    
    echo "<table>";
    echo "<tr><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Tipo</th><th>Status</th></tr>";
    
    foreach ($cobracas as $cobranca) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cobranca['descricao']) . "</td>";
        echo "<td>R$ " . number_format($cobranca['valor'], 2, ',', '.') . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($cobranca['data_vencimento'])) . "</td>";
        echo "<td>" . $cobranca['tipo_cobranca'] . "</td>";
        echo "<td>" . $cobranca['status_asaas'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
```

---

## Casos de Uso

### Caso 1: Gerar Cobrança ao Criar Contrato

```php
<?php
// Ao criar novo contrato
$contrato = [
    'cliente_id' => 123,
    'descricao' => 'Contrato de Serviços',
    'valor' => 1000.00,
    'data_vencimento' => date('Y-m-d', strtotime('+30 days'))
];

// 1. Criar conta a receber
$sql = "INSERT INTO contas_receber (cliente_id, descricao, valor, data_vencimento) 
        VALUES (?, ?, ?, ?)";
$db->execute($sql, [$contrato['cliente_id'], $contrato['descricao'], $contrato['valor'], $contrato['data_vencimento']]);
$contaId = $db->lastInsertId();

// 2. Buscar cliente Asaas
$sql = "SELECT asaas_customer_id FROM asaas_clientes WHERE cliente_id = ?";
$cliente = $db->fetchOne($sql, [$contrato['cliente_id']]);

// 3. Criar cobrança no Asaas
$asaas = new AsaasService();
$payment = $asaas->createPayment([
    'customerId' => $cliente['asaas_customer_id'],
    'billingType' => 'PIX',
    'value' => $contrato['valor'],
    'dueDate' => $contrato['data_vencimento']
]);

// 4. Salvar referência
$sql = "INSERT INTO asaas_pagamentos (conta_receber_id, asaas_payment_id, tipo_cobranca, valor, data_vencimento) 
        VALUES (?, ?, ?, ?, ?)";
$db->execute($sql, [$contaId, $payment['id'], 'PIX', $contrato['valor'], $contrato['data_vencimento']]);

echo "Cobrança gerada automaticamente";
?>
```

### Caso 2: Enviar Email com QR Code PIX

```php
<?php
require_once 'vendor/autoload.php';

use App\Services\AsaasService;
use PHPMailer\PHPMailer\PHPMailer;

$asaas = new AsaasService();
$paymentId = 'pay_123';

// Obter dados do pagamento
$payment = $asaas->getPayment($paymentId);
$pixData = $asaas->getPixQrCode($paymentId);

// Preparar email
$mail = new PHPMailer();
$mail->setFrom('noreply@example.com', 'Seu Negócio');
$mail->addAddress('cliente@example.com');
$mail->Subject = 'Sua Cobrança está Pronta';

// HTML do email
$html = "
<h2>Cobrança Gerada</h2>
<p>Valor: R$ " . number_format($payment['value'], 2, ',', '.') . "</p>
<p>Vencimento: " . date('d/m/Y', strtotime($payment['dueDate'])) . "</p>

<h3>Pagar com PIX</h3>
<img src='" . $pixData['encodedImage'] . "' alt='QR Code PIX' style='max-width: 300px;'>
<p>Ou copie e cole:</p>
<p>" . $pixData['payload'] . "</p>
";

$mail->Body = $html;
$mail->isHTML(true);

if ($mail->send()) {
    echo "Email enviado com sucesso";
} else {
    echo "Erro ao enviar email: " . $mail->ErrorInfo;
}
?>
```

### Caso 3: Dashboard com Status de Cobranças

```php
<?php
require_once 'vendor/autoload.php';

use App\Core\Database;

$db = Database::getInstance();

// Estatísticas
$stats = [
    'total' => 0,
    'pago' => 0,
    'pendente' => 0,
    'vencido' => 0,
    'valor_total' => 0,
    'valor_pago' => 0
];

$sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pago,
        SUM(CASE WHEN status != 'pago' AND data_vencimento >= CURDATE() THEN 1 ELSE 0 END) as pendente,
        SUM(CASE WHEN status != 'pago' AND data_vencimento < CURDATE() THEN 1 ELSE 0 END) as vencido,
        SUM(valor) as valor_total,
        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as valor_pago
    FROM contas_receber
    WHERE gateway_asaas_id IS NOT NULL
";

$result = $db->fetchOne($sql);
$stats = array_merge($stats, $result);

// Exibir dashboard
echo "<div class='dashboard'>";
echo "<div class='card'>";
echo "<h3>Total de Cobranças</h3>";
echo "<p class='value'>" . $stats['total'] . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h3>Pago</h3>";
echo "<p class='value success'>" . $stats['pago'] . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h3>Pendente</h3>";
echo "<p class='value warning'>" . $stats['pendente'] . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h3>Vencido</h3>";
echo "<p class='value error'>" . $stats['vencido'] . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h3>Valor Total</h3>";
echo "<p class='value'>R$ " . number_format($stats['valor_total'], 2, ',', '.') . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h3>Valor Pago</h3>";
echo "<p class='value success'>R$ " . number_format($stats['valor_pago'], 2, ',', '.') . "</p>";
echo "</div>";

echo "</div>";
?>
```

---

## Tratamento de Erros

### Exemplo 1: Try-Catch Completo

```php
<?php
try {
    $asaas = new AsaasService();
    
    // Validar configuração
    if (!$asaas->isConfigured()) {
        throw new \Exception('Asaas não está configurado');
    }
    
    // Criar cliente
    $customer = $asaas->createCustomer($data);
    
    // Criar cobrança
    $payment = $asaas->createPayment($paymentData);
    
    // Sucesso
    return ['success' => true, 'payment_id' => $payment['id']];
    
} catch (\Exception $e) {
    // Log do erro
    error_log('[ASAAS] Erro: ' . $e->getMessage());
    
    // Retornar erro
    return [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ];
}
?>
```

### Exemplo 2: Validação de Dados

```php
<?php
function validarDadosCobranca($data) {
    $erros = [];
    
    if (empty($data['conta_receber_id'])) {
        $erros[] = 'ID da conta é obrigatório';
    }
    
    if (empty($data['tipo_cobranca']) || !in_array($data['tipo_cobranca'], ['PIX', 'BOLETO'])) {
        $erros[] = 'Tipo de cobrança inválido';
    }
    
    if (empty($data['valor']) || $data['valor'] <= 0) {
        $erros[] = 'Valor deve ser maior que zero';
    }
    
    if (empty($data['data_vencimento']) || !validarData($data['data_vencimento'])) {
        $erros[] = 'Data de vencimento inválida';
    }
    
    return $erros;
}

function validarData($data) {
    $d = \DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

// Uso
$erros = validarDadosCobranca($_POST);
if (!empty($erros)) {
    return ['success' => false, 'errors' => $erros];
}
?>
```

---

**Versão**: 1.0.0  
**Última Atualização**: Janeiro 2025
