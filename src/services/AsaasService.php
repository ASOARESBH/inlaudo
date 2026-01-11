<?php
/**
 * Serviço de Integração Asaas
 * 
 * Classe para gerenciar todas as operações com a API Asaas v3
 * Suporta Sandbox e Production
 */

class AsaasService {
    
    private $api_key;
    private $ambiente;
    private $base_url;
    private $timeout = 30;
    private $pdo;
    
    // URLs base
    const SANDBOX_URL = 'https://sandbox.asaas.com/api/v3';
    const PRODUCTION_URL = 'https://api.asaas.com/v3';
    
    /**
     * Construtor
     */
    public function __construct($pdo, $api_key = null, $ambiente = 'sandbox') {
        $this->pdo = $pdo;
        $this->api_key = $api_key;
        $this->ambiente = $ambiente;
        $this->base_url = $ambiente === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }
    
    /**
     * Fazer requisição à API
     */
    private function request($metodo, $endpoint, $dados = null) {
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'User-Agent: InlaudoERP/1.0'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($dados) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
            }
        } elseif ($metodo === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($dados) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
            }
        } elseif ($metodo === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception('Erro cURL: ' . $curl_error);
        }
        
        $resultado = json_decode($response, true);
        
        if ($http_code >= 400) {
            $erro = $resultado['errors'][0]['detail'] ?? 'Erro desconhecido';
            throw new Exception('Erro Asaas (' . $http_code . '): ' . $erro);
        }
        
        return $resultado;
    }
    
    /**
     * Criar ou buscar cliente
     */
    public function criarOuBuscarCliente($cpf_cnpj, $nome, $email, $telefone = null) {
        try {
            // Buscar cliente existente
            $cliente = $this->buscarClientePorCpfCnpj($cpf_cnpj);
            
            if ($cliente) {
                return $cliente;
            }
            
            // Criar novo cliente
            $dados = [
                'name' => $nome,
                'cpfCnpj' => preg_replace('/[^0-9]/', '', $cpf_cnpj),
                'email' => $email,
                'mobilePhone' => $telefone ? preg_replace('/[^0-9]/', '', $telefone) : null
            ];
            
            $resultado = $this->request('POST', '/customers', $dados);
            
            // Registrar no banco local
            $sql = "INSERT INTO asaas_clientes (cliente_id, asaas_customer_id, cpf_cnpj, data_criacao) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([null, $resultado['id'], $cpf_cnpj]);
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->registrarLog('criarOuBuscarCliente', 'erro', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar cliente por CPF/CNPJ
     */
    public function buscarClientePorCpfCnpj($cpf_cnpj) {
        try {
            $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cpf_cnpj);
            
            $resultado = $this->request('GET', '/customers?cpfCnpj=' . $cpf_cnpj_limpo);
            
            if (isset($resultado['data']) && count($resultado['data']) > 0) {
                return $resultado['data'][0];
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->registrarLog('buscarClientePorCpfCnpj', 'erro', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Criar cobrança PIX
     */
    public function criarCobrancaPix($customer_id, $valor, $data_vencimento, $descricao = null) {
        try {
            $dados = [
                'customer' => $customer_id,
                'billingType' => 'PIX',
                'value' => floatval($valor),
                'dueDate' => date('Y-m-d', strtotime($data_vencimento)),
                'description' => $descricao ?? 'Cobrança PIX'
            ];
            
            $resultado = $this->request('POST', '/payments', $dados);
            
            // Obter QR Code
            if (isset($resultado['id'])) {
                $qr_code = $this->obterQrCodePix($resultado['id']);
                $resultado['qr_code'] = $qr_code;
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->registrarLog('criarCobrancaPix', 'erro', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Criar cobrança Boleto
     */
    public function criarCobrancaBoleto($customer_id, $valor, $data_vencimento, $descricao = null) {
        try {
            $dados = [
                'customer' => $customer_id,
                'billingType' => 'BOLETO',
                'value' => floatval($valor),
                'dueDate' => date('Y-m-d', strtotime($data_vencimento)),
                'description' => $descricao ?? 'Cobrança Boleto'
            ];
            
            $resultado = $this->request('POST', '/payments', $dados);
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->registrarLog('criarCobrancaBoleto', 'erro', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obter QR Code PIX
     */
    public function obterQrCodePix($payment_id) {
        try {
            $resultado = $this->request('GET', '/payments/' . $payment_id . '/qrCode');
            return $resultado;
        } catch (Exception $e) {
            $this->registrarLog('obterQrCodePix', 'erro', $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obter status de pagamento
     */
    public function obterStatusPagamento($payment_id) {
        try {
            $resultado = $this->request('GET', '/payments/' . $payment_id);
            return $resultado;
        } catch (Exception $e) {
            $this->registrarLog('obterStatusPagamento', 'erro', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Validar token de webhook
     */
    public function validarTokenWebhook($token_recebido, $token_configurado) {
        return $token_recebido === $token_configurado;
    }
    
    /**
     * Registrar log
     */
    private function registrarLog($operacao, $status, $mensagem, $dados = null) {
        try {
            $sql = "INSERT INTO asaas_logs (operacao, status, dados_requisicao, mensagem_erro, data_criacao) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $operacao,
                $status,
                $dados ? json_encode($dados) : null,
                $mensagem
            ]);
        } catch (Exception $e) {
            error_log('Erro ao registrar log: ' . $e->getMessage());
        }
    }
}
?>
