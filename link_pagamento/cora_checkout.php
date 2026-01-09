<?php
/**
 * Checkout CORA Banking - Boleto Registrado
 * ERP INLAUDO
 * Fluxo: Cliente → Gera boleto → Salva → Abre PDF
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib_boleto_cora_v2.php';

// ======================================================
// VALIDAR SESSÃO DO CLIENTE
// ======================================================
if (
    !isset($_SESSION['usuario_tipo']) ||
    $_SESSION['usuario_tipo'] !== 'cliente'
) {
    die('Acesso não autorizado');
}

$clienteId = $_SESSION['cliente_id'] ?? 0;

if (!$clienteId) {
    die('Cliente não identificado');
}

// ======================================================
// VALIDAR CONTA
// ======================================================
$contaId = isset($_GET['conta_id']) ? (int)$_GET['conta_id'] : 0;

if (!$contaId) {
    die('Conta inválida');
}

$conn = getConnection();

// ======================================================
// BUSCAR CONTA + CLIENTE
// ======================================================
$stmt = $conn->prepare("
    SELECT 
        cr.*,
        c.nome,
        c.razao_social,
        c.email,
        c.cnpj_cpf,
        c.telefone,
        c.logradouro,
        c.numero,
        c.complemento,
        c.bairro,
        c.cidade,
        c.estado,
        c.cep
    FROM contas_receber cr
    INNER JOIN clientes c ON c.id = cr.cliente_id
    WHERE cr.id = ? AND cr.cliente_id = ?
    LIMIT 1
");
$stmt->execute([$contaId, $clienteId]);
$conta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conta) {
    die('Conta não encontrada ou não pertence ao cliente');
}

if ($conta['status'] === 'pago') {
    header('Location: ../cliente_contas_pagar.php?erro=conta_paga');
    exit;
}

// ======================================================
// BUSCAR CONFIGURAÇÃO CORA
// ======================================================
$stmtCfg = $conn->prepare("
    SELECT *
    FROM integracoes
    WHERE tipo = 'cora' AND ativo = 1
    LIMIT 1
");
$stmtCfg->execute();
$config = $stmtCfg->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    die('Configuração CORA não encontrada');
}

$configJson = json_decode($config['configuracoes'], true);

$clientId       = $configJson['client_id'] ?? null;
$ambiente       = $configJson['ambiente'] ?? 'production';
$certificado    = $config['api_key'];
$privateKey     = $config['api_secret'];

if (
    !$clientId ||
    !file_exists($certificado) ||
    !file_exists($privateKey)
) {
    die('Configuração CORA incompleta');
}

// ======================================================
// INSTANCIAR API
// ======================================================
$cora = new CoraAPIv2(
    $clientId,
    $certificado,
    $privateKey,
    $ambiente
);

// ======================================================
// MONTAR DADOS DO CLIENTE
// ======================================================
$documento = preg_replace('/\D/', '', $conta['cnpj_cpf']);

$dadosCliente = [
    'nome' => $conta['nome'] ?: $conta['razao_social'],
    'email' => $conta['email'] ?: 'nao-informado@email.com',
    'documento' => $documento,
    'endereco' => [
        'logradouro' => $conta['logradouro'] ?? 'Não informado',
        'numero' => $conta['numero'] ?? 'S/N',
        'complemento' => $conta['complemento'] ?? '',
        'bairro' => $conta['bairro'] ?? 'Centro',
        'cidade' => $conta['cidade'] ?? 'São Paulo',
        'uf' => $conta['estado'] ?? 'SP',
        'cep' => $conta['cep'] ?? '00000000'
    ]
];

// ======================================================
// MONTAR DADOS DA COBRANÇA
// ======================================================
$dadosCobranca = [
    'codigo_unico'   => 'CR_' . $conta['id'],
    'descricao'      => $conta['descricao'],
    'valor'          => (float)$conta['valor'],
    'data_vencimento'=> $conta['data_vencimento']
];

// ======================================================
// EMITIR BOLETO
// ======================================================
$resultado = $cora->emitirBoleto($dadosCliente, $dadosCobranca);

if (!$resultado['sucesso']) {
    die('Erro ao gerar boleto: ' . $resultado['mensagem']);
}

$boleto = $resultado['dados'];

// ======================================================
// SALVAR NO BANCO
// ======================================================
$stmtUpd = $conn->prepare("
    UPDATE contas_receber SET
        gateway = 'cora',
        gateway_id = ?,
        linha_digitavel = ?,
        codigo_barras = ?,
        url_boleto = ?,
        url_pdf = ?,
        status_gateway = ?,
        data_atualizacao = NOW()
    WHERE id = ?
");

$stmtUpd->execute([
    $boleto['id_cora'],
    $boleto['linha_digitavel'],
    $boleto['codigo_barras'],
    $boleto['url_boleto'],
    $boleto['url_pdf'],
    $boleto['status'],
    $conta['id']
]);

// ======================================================
// REDIRECIONAR PARA O PDF
// ======================================================
header('Location: ' . $boleto['url_pdf']);
exit;
