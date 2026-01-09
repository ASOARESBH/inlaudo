<?php
/**
 * Script de Teste da Integração CORA API v2
 * 
 * Este script testa a integração completa com a API CORA
 * incluindo autenticação mTLS e emissão de boleto
 */

require_once 'config.php';
require_once 'lib_boleto_cora_v2.php';

// Configurações
$clientId = 'int-6f2u3vpjglGsZ8nev37Wm7';
$certificadoPath = __DIR__ . '/certs/certificate.pem';
$privateKeyPath = __DIR__ . '/certs/private-key.key';
$ambiente = 'production'; // ou 'stage' para testes

echo "<h1>Teste de Integração CORA API v2</h1>";
echo "<hr>";

// Verificar se os arquivos existem
echo "<h2>1. Verificação de Arquivos</h2>";
echo "<p><strong>Certificado:</strong> " . ($certificadoPath) . "</p>";
echo "<p>Existe: " . (file_exists($certificadoPath) ? '✅ SIM' : '❌ NÃO') . "</p>";

echo "<p><strong>Chave Privada:</strong> " . ($privateKeyPath) . "</p>";
echo "<p>Existe: " . (file_exists($privateKeyPath) ? '✅ SIM' : '❌ NÃO') . "</p>";

if (!file_exists($certificadoPath) || !file_exists($privateKeyPath)) {
    die("<p style='color: red;'><strong>ERRO:</strong> Certificados não encontrados. Configure os certificados primeiro.</p>");
}

echo "<hr>";

// Criar instância da API
echo "<h2>2. Criação da Instância da API</h2>";
try {
    $cora = new CoraAPIv2($clientId, $certificadoPath, $privateKeyPath, $ambiente);
    echo "<p>✅ Instância criada com sucesso</p>";
    echo "<p><strong>Client ID:</strong> " . htmlspecialchars($clientId) . "</p>";
    echo "<p><strong>Ambiente:</strong> " . htmlspecialchars($ambiente) . "</p>";
} catch (Exception $e) {
    die("<p style='color: red;'><strong>ERRO:</strong> " . $e->getMessage() . "</p>");
}

echo "<hr>";

// Testar conexão
echo "<h2>3. Teste de Conexão</h2>";
$resultadoConexao = $cora->testarConexao();

if ($resultadoConexao['sucesso']) {
    echo "<p style='color: green;'>✅ " . htmlspecialchars($resultadoConexao['mensagem']) . "</p>";
} else {
    echo "<p style='color: red;'>❌ " . htmlspecialchars($resultadoConexao['mensagem']) . "</p>";
    echo "<p><em>Verifique as credenciais e certificados.</em></p>";
}

echo "<hr>";

// Listar boletos
echo "<h2>4. Listar Boletos (Primeiros 5)</h2>";
$resultadoLista = $cora->listarBoletos(0, 5);

if ($resultadoLista['sucesso']) {
    echo "<p style='color: green;'>✅ Boletos listados com sucesso</p>";
    echo "<pre>" . json_encode($resultadoLista['dados'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Erro ao listar boletos: " . htmlspecialchars($resultadoLista['mensagem']) . "</p>";
    if (isset($resultadoLista['dados'])) {
        echo "<pre>" . json_encode($resultadoLista['dados'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
}

echo "<hr>";

// Teste de emissão de boleto (COMENTADO - descomente para testar emissão real)
echo "<h2>5. Teste de Emissão de Boleto</h2>";
echo "<p><em>⚠️ Teste de emissão comentado. Descomente o código abaixo para testar emissão real.</em></p>";

/*
// Dados do cliente
$dadosCliente = [
    'nome' => 'Fulano da Silva Teste',
    'email' => 'fulano.teste@email.com',
    'documento' => '34052649000178', // CNPJ de teste
    'endereco' => [
        'logradouro' => 'Rua Gomes de Carvalho',
        'numero' => '1629',
        'bairro' => 'Vila Olímpia',
        'cidade' => 'São Paulo',
        'uf' => 'SP',
        'cep' => '04547005',
        'complemento' => 'Sala 100'
    ]
];

// Dados da cobrança
$dadosCobranca = [
    'codigo_unico' => 'TESTE-' . time(),
    'descricao' => 'Teste de Emissão de Boleto via API v2',
    'valor' => 150.00,
    'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
    'multa' => [
        'percentual' => 2.0 // 2% de multa
    ],
    'juros' => [
        'percentual_mes' => 1.0 // 1% ao mês
    ]
];

echo "<h3>Dados do Cliente:</h3>";
echo "<pre>" . json_encode($dadosCliente, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

echo "<h3>Dados da Cobrança:</h3>";
echo "<pre>" . json_encode($dadosCobranca, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Emitir boleto
$resultadoBoleto = $cora->emitirBoleto($dadosCliente, $dadosCobranca);

if ($resultadoBoleto['sucesso']) {
    echo "<p style='color: green;'>✅ Boleto emitido com sucesso!</p>";
    echo "<h3>Dados do Boleto:</h3>";
    echo "<ul>";
    echo "<li><strong>ID CORA:</strong> " . htmlspecialchars($resultadoBoleto['dados']['id_cora']) . "</li>";
    echo "<li><strong>Status:</strong> " . htmlspecialchars($resultadoBoleto['dados']['status']) . "</li>";
    echo "<li><strong>Linha Digitável:</strong> " . htmlspecialchars($resultadoBoleto['dados']['linha_digitavel']) . "</li>";
    echo "<li><strong>Valor:</strong> R$ " . number_format($resultadoBoleto['dados']['valor_total'], 2, ',', '.') . "</li>";
    echo "<li><strong>Vencimento:</strong> " . date('d/m/Y', strtotime($dadosCobranca['data_vencimento'])) . "</li>";
    if ($resultadoBoleto['dados']['url_pdf']) {
        echo "<li><strong>PDF:</strong> <a href='" . htmlspecialchars($resultadoBoleto['dados']['url_pdf']) . "' target='_blank'>Visualizar Boleto</a></li>";
    }
    if ($resultadoBoleto['dados']['qr_code_pix']) {
        echo "<li><strong>Pix:</strong> Disponível</li>";
    }
    echo "</ul>";
    
    echo "<h3>Resposta Completa:</h3>";
    echo "<pre>" . json_encode($resultadoBoleto['dados'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Erro ao emitir boleto: " . htmlspecialchars($resultadoBoleto['mensagem']) . "</p>";
    if (isset($resultadoBoleto['dados'])) {
        echo "<pre>" . json_encode($resultadoBoleto['dados'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
}
*/

echo "<hr>";

// Verificar logs
echo "<h2>6. Logs de Integração</h2>";
echo "<p>Verifique os logs detalhados em: <a href='logs_integracao.php'>Logs de Integração</a></p>";

echo "<hr>";
echo "<p><strong>Teste concluído!</strong></p>";
echo "<p><a href='integracoes_boleto.php'>← Voltar para Configurações</a></p>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
    line-height: 1.6;
}

h1 {
    color: #2563eb;
    border-bottom: 3px solid #2563eb;
    padding-bottom: 0.5rem;
}

h2 {
    color: #1e40af;
    margin-top: 2rem;
}

h3 {
    color: #1e3a8a;
}

pre {
    background: #f3f4f6;
    padding: 1rem;
    border-radius: 4px;
    overflow-x: auto;
    border-left: 4px solid #2563eb;
}

hr {
    border: none;
    border-top: 1px solid #e5e7eb;
    margin: 2rem 0;
}

a {
    color: #2563eb;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

ul {
    background: #f9fafb;
    padding: 1rem 1rem 1rem 2rem;
    border-radius: 4px;
}
</style>
