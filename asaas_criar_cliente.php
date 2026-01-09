<?php
/**
 * Asaas - Criar/Buscar Cliente
 * 
 * Processa a criação ou busca de cliente no Asaas
 */

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'src/services/AsaasService.php';
require_once 'src/models/AsaasModel.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $cpf_cnpj = $_POST['cpf_cnpj'] ?? '';
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($cpf_cnpj) || empty($nome)) {
        throw new Exception('CPF/CNPJ e Nome são obrigatórios');
    }
    
    // Conectar ao banco
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obter configuração Asaas
    $sql = "SELECT * FROM integracao_asaas WHERE id = 1 AND ativo = 1 LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        throw new Exception('Integração Asaas não está configurada');
    }
    
    // Inicializar serviço
    $asaas = new AsaasService($config['api_key'], $config['ambiente']);
    $model = new AsaasModel($pdo);
    
    // Buscar cliente local
    $sql = "SELECT id FROM clientes WHERE cpf_cnpj = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cpf_cnpj]);
    $cliente_local = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente_local) {
        throw new Exception('Cliente não encontrado no sistema local');
    }
    
    $cliente_id = $cliente_local['id'];
    
    // Verificar se cliente já existe no Asaas
    $sql = "SELECT asaas_customer_id FROM asaas_clientes WHERE cliente_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cliente_id]);
    $cliente_asaas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente_asaas) {
        $response['success'] = true;
        $response['message'] = 'Cliente já existe no Asaas';
        $response['data'] = [
            'customer_id' => $cliente_asaas['asaas_customer_id'],
            'status' => 'existente'
        ];
        
        // Registrar log
        $model->registrarLog(
            'BUSCAR_CLIENTE',
            'sucesso',
            ['cliente_id' => $cliente_id],
            ['customer_id' => $cliente_asaas['asaas_customer_id']]
        );
    } else {
        // Criar cliente no Asaas
        $dados_cliente = [
            'name' => $nome,
            'cpfCnpj' => preg_replace('/\D/', '', $cpf_cnpj),
            'email' => $email ?: null,
            'notificationDisabled' => false
        ];
        
        $resultado = $asaas->criarCliente($dados_cliente);
        
        if (!$resultado['success']) {
            throw new Exception($resultado['message']);
        }
        
        $customer_id = $resultado['data']['id'];
        
        // Salvar mapeamento
        $sql = "INSERT INTO asaas_clientes (cliente_id, asaas_customer_id, cpf_cnpj, data_criacao) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cliente_id, $customer_id, $cpf_cnpj]);
        
        $response['success'] = true;
        $response['message'] = 'Cliente criado com sucesso no Asaas';
        $response['data'] = [
            'customer_id' => $customer_id,
            'status' => 'criado'
        ];
        
        // Registrar log
        $model->registrarLog(
            'CRIAR_CLIENTE',
            'sucesso',
            $dados_cliente,
            $resultado['data']
        );
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Registrar erro
    if (isset($model)) {
        $model->registrarLog(
            'CRIAR_CLIENTE',
            'erro',
            $_POST ?? [],
            ['erro' => $e->getMessage()]
        );
    }
}

// Retornar resposta
header('Content-Type: application/json');
echo json_encode($response);
?>
