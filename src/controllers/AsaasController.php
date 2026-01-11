<?php

namespace App\Controllers;

use App\Services\AsaasService;
use App\Models\AsaasModel;
use PDO;
use Exception;

/**
 * Controller de Integração Asaas
 * 
 * Gerencia endpoints da API Asaas
 * 
 * @author Backend Developer
 * @version 1.0.0
 */
class AsaasController
{
    /**
     * Conexão PDO
     * @var PDO
     */
    private $pdo;
    
    /**
     * Modelo Asaas
     * @var AsaasModel
     */
    private $model;
    
    /**
     * Serviço Asaas
     * @var AsaasService
     */
    private $service;
    
    /**
     * Construtor
     * 
     * @param PDO $pdo Conexão com banco de dados
     */
    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo;
        $this->model = new AsaasModel($pdo);
        
        // Obter configuração
        $config = $this->model->getActive();
        if (!$config) {
            throw new Exception('Integração Asaas não configurada');
        }
        
        $this->service = new AsaasService($config['api_key'], $config['ambiente']);
    }
    
    /**
     * Buscar ou criar cliente
     * 
     * @return void
     */
    public function findOrCreateCustomer()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar entrada
            if (empty($input['cliente_id']) || empty($input['cpf_cnpj']) || empty($input['nome'])) {
                throw new Exception('cliente_id, cpf_cnpj e nome são obrigatórios');
            }
            
            $clienteId = $input['cliente_id'];
            $cpfCnpj = preg_replace('/\D/', '', $input['cpf_cnpj']);
            $nome = $input['nome'];
            
            // Verificar se cliente já está mapeado
            $mapped = $this->model->findMappedCustomer($clienteId);
            if ($mapped) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'customer_id' => $mapped['asaas_customer_id'],
                    'message' => 'Cliente já mapeado'
                ]);
                return;
            }
            
            // Buscar cliente no Asaas
            $customer = $this->service->findCustomer($cpfCnpj);
            
            if (!$customer) {
                // Criar cliente
                $customer = $this->service->createCustomer([
                    'name' => $nome,
                    'cpfCnpj' => $cpfCnpj,
                    'mobilePhone' => $input['telefone'] ?? null,
                    'email' => $input['email'] ?? null
                ]);
            }
            
            // Mapear cliente
            $this->model->mapCustomer($clienteId, $customer['id'], $cpfCnpj);
            
            // Log
            $this->model->logOperation('create_customer', 'sucesso', $input, $customer);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'customer_id' => $customer['id'],
                'message' => 'Cliente processado com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->model->logOperation('create_customer', 'erro', $_POST ?? [], null, $e->getMessage());
            
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Criar cobrança
     * 
     * @return void
     */
    public function createPayment()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validar entrada
            $required = ['conta_receber_id', 'tipo_cobranca', 'valor', 'data_vencimento'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Campo obrigatório ausente: {$field}");
                }
            }
            
            $contaReceberId = $input['conta_receber_id'];
            $tipoCobranca = strtoupper($input['tipo_cobranca']);
            $valor = (float) $input['valor'];
            $dataVencimento = $input['data_vencimento'];
            
            // Validar tipo de cobrança
            if (!in_array($tipoCobranca, ['PIX', 'BOLETO'])) {
                throw new Exception('Tipo de cobrança deve ser PIX ou BOLETO');
            }
            
            // Validar valor
            if ($valor <= 0) {
                throw new Exception('Valor deve ser maior que zero');
            }
            
            // Validar data
            if (!$this->isValidDate($dataVencimento)) {
                throw new Exception('Data de vencimento inválida (formato: YYYY-MM-DD)');
            }
            
            // Obter cliente mapeado (usar cliente_id se fornecido)
            $clienteId = $input['cliente_id'] ?? null;
            if ($clienteId) {
                $mapped = $this->model->findMappedCustomer($clienteId);
                if (!$mapped) {
                    throw new Exception('Cliente não mapeado no Asaas');
                }
                $asaasCustomerId = $mapped['asaas_customer_id'];
            } else {
                throw new Exception('cliente_id é obrigatório');
            }
            
            // Criar cobrança
            $payment = $this->service->createPayment([
                'customerId' => $asaasCustomerId,
                'billingType' => $tipoCobranca,
                'value' => $valor,
                'dueDate' => $dataVencimento,
                'description' => $input['descricao'] ?? "Cobrança #{$contaReceberId}",
                'externalReference' => "CONTA_{$contaReceberId}"
            ]);
            
            // Obter dados adicionais
            $additional = [];
            if ($tipoCobranca === 'PIX') {
                $pixData = $this->service->getPixQrCode($payment['id']);
                $additional = $pixData;
            }
            
            // Mapear pagamento
            $this->model->mapPayment(
                $contaReceberId,
                $payment['id'],
                $tipoCobranca,
                $valor,
                $dataVencimento,
                $additional
            );
            
            // Atualizar conta a receber com gateway_asaas_id
            $sql = "UPDATE contas_receber SET gateway_asaas_id = ?, status_asaas = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$payment['id'], 'pending', $contaReceberId]);
            
            // Log
            $this->model->logOperation('create_payment', 'sucesso', $input, $payment);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'payment_id' => $payment['id'],
                'status' => $payment['status'],
                'value' => $payment['value'],
                'dueDate' => $payment['dueDate'],
                'additional' => $additional,
                'message' => 'Cobrança criada com sucesso'
            ]);
            
        } catch (Exception $e) {
            $this->model->logOperation('create_payment', 'erro', $_POST ?? [], null, $e->getMessage());
            
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Obter status de cobrança
     * 
     * @param string $paymentId ID da cobrança
     * @return void
     */
    public function getPaymentStatus($paymentId)
    {
        try {
            if (empty($paymentId)) {
                throw new Exception('ID da cobrança é obrigatório');
            }
            
            $payment = $this->service->getPayment($paymentId);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'payment' => $payment
            ]);
            
        } catch (Exception $e) {
            $this->model->logOperation('get_payment_status', 'erro', ['paymentId' => $paymentId], null, $e->getMessage());
            
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Validar data no formato YYYY-MM-DD
     * 
     * @param string $date
     * @return bool
     */
    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
