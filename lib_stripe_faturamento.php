<?php
/**
 * Biblioteca para integração com Stripe - Faturamento (Invoices)
 * Documentação: https://stripe.com/docs/invoicing
 */

require_once 'config.php';
require_once 'lib_logs.php';

class StripeFaturamento {
    private $secretKey;
    private $publishableKey;
    private $baseUrl = 'https://api.stripe.com/v1';
    
    public function __construct() {
        $conn = getConnection();
        $stmt = $conn->query("SELECT api_key, api_secret, ativo FROM integracoes WHERE tipo = 'stripe'");
        $config = $stmt->fetch();
        
        if (!$config || !$config['ativo']) {
            throw new Exception('Integração Stripe não está ativa ou configurada.');
        }
        
        $this->publishableKey = $config['api_key'];
        $this->secretKey = $config['api_secret'];
    }
    
    /**
     * Criar ou obter customer no Stripe
     * 
     * @param array $dadosCliente Dados do cliente
     * @return string ID do customer no Stripe
     */
    public function criarOuObterCustomer($dadosCliente) {
        $startTime = microtime(true);
        
        try {
            // Verificar se já existe customer_id
            if (!empty($dadosCliente['stripe_customer_id'])) {
                // Verificar se o customer ainda existe no Stripe
                try {
                    $customer = $this->makeRequest('GET', "/customers/{$dadosCliente['stripe_customer_id']}");
                    if (!isset($customer['error'])) {
                        LogIntegracao::sucesso(
                            'stripe',
                            'obter_customer',
                            "Customer existente recuperado: {$customer['id']}",
                            ['customer_id' => $dadosCliente['stripe_customer_id']],
                            $customer,
                            200,
                            microtime(true) - $startTime,
                            $dadosCliente['id'],
                            'cliente'
                        );
                        return $customer['id'];
                    }
                } catch (Exception $e) {
                    // Customer não existe mais, criar novo
                }
            }
            
            // Criar novo customer
            $customerData = [
                'name' => $dadosCliente['tipo_pessoa'] == 'CNPJ' 
                    ? ($dadosCliente['razao_social'] ?: $dadosCliente['nome_fantasia'])
                    : $dadosCliente['nome'],
                'email' => $dadosCliente['email'],
                'phone' => $dadosCliente['celular'] ?: $dadosCliente['telefone'],
                'address' => [
                    'line1' => $dadosCliente['logradouro'] . ', ' . $dadosCliente['numero'],
                    'line2' => $dadosCliente['complemento'],
                    'city' => $dadosCliente['cidade'],
                    'state' => $dadosCliente['estado'],
                    'postal_code' => preg_replace('/[^0-9]/', '', $dadosCliente['cep']),
                    'country' => 'BR'
                ],
                'metadata' => [
                    'cliente_id' => $dadosCliente['id'],
                    'cnpj_cpf' => $dadosCliente['cnpj_cpf']
                ]
            ];
            
            $customer = $this->makeRequest('POST', '/customers', $customerData);
            
            if (isset($customer['error'])) {
                throw new Exception('Erro ao criar customer: ' . $customer['error']['message']);
            }
            
            // Salvar customer_id no banco
            $conn = getConnection();
            $stmt = $conn->prepare("UPDATE clientes SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$customer['id'], $dadosCliente['id']]);
            
            LogIntegracao::sucesso(
                'stripe',
                'criar_customer',
                "Novo customer criado: {$customer['id']}",
                $customerData,
                $customer,
                200,
                microtime(true) - $startTime,
                $dadosCliente['id'],
                'cliente'
            );
            
            return $customer['id'];
            
        } catch (Exception $e) {
            LogIntegracao::erro(
                'stripe',
                'criar_customer',
                $e->getMessage(),
                $dadosCliente,
                null,
                null,
                microtime(true) - $startTime,
                $dadosCliente['id'],
                'cliente'
            );
            throw $e;
        }
    }
    
    /**
     * Criar fatura (Invoice) no Stripe
     * 
     * @param array $dados Dados da fatura
     * @return array Resultado da criação
     */
    public function criarFatura($dados) {
        $startTime = microtime(true);
        
        try {
            // Validar dados obrigatórios
            $required = ['customer_id', 'descricao', 'valor', 'data_vencimento'];
            foreach ($required as $field) {
                if (empty($dados[$field])) {
                    throw new Exception("Campo obrigatório ausente: $field");
                }
            }
            
            // Criar item da fatura (Invoice Item)
            $invoiceItemData = [
                'customer' => $dados['customer_id'],
                'amount' => (int)($dados['valor'] * 100), // Converter para centavos
                'currency' => 'brl',
                'description' => $dados['descricao']
            ];
            
            $invoiceItem = $this->makeRequest('POST', '/invoiceitems', $invoiceItemData);
            
            if (isset($invoiceItem['error'])) {
                throw new Exception('Erro ao criar item da fatura: ' . $invoiceItem['error']['message']);
            }
            
            // Calcular dias até vencimento
            $diasVencimento = (int)((strtotime($dados['data_vencimento']) - time()) / 86400);
            
            // Criar fatura
            $invoiceData = [
                'customer' => $dados['customer_id'],
                'auto_advance' => true, // Finalizar automaticamente
                'collection_method' => 'send_invoice',
                'days_until_due' => max($diasVencimento, 1),
                'metadata' => [
                    'conta_receber_id' => $dados['conta_receber_id'] ?? '',
                    'cliente_id' => $dados['cliente_id'] ?? ''
                ]
            ];
            
            // Se forma de pagamento for boleto, configurar
            if (isset($dados['forma_pagamento']) && $dados['forma_pagamento'] == 'boleto') {
                $invoiceData['payment_settings'] = [
                    'payment_method_types' => ['boleto']
                ];
            }
            
            $invoice = $this->makeRequest('POST', '/invoices', $invoiceData);
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao criar fatura: ' . $invoice['error']['message']);
            }
            
            // Finalizar fatura (tornar pagável)
            $invoiceFinalizada = $this->makeRequest('POST', "/invoices/{$invoice['id']}/finalize");
            
            if (isset($invoiceFinalizada['error'])) {
                throw new Exception('Erro ao finalizar fatura: ' . $invoiceFinalizada['error']['message']);
            }
            
            // Extrair informações
            $resultado = [
                'sucesso' => true,
                'invoice_id' => $invoiceFinalizada['id'],
                'numero_fatura' => $invoiceFinalizada['number'],
                'status' => $invoiceFinalizada['status'],
                'valor' => $dados['valor'],
                'data_vencimento' => $dados['data_vencimento'],
                'url_fatura' => $invoiceFinalizada['invoice_pdf'] ?? null,
                'hosted_invoice_url' => $invoiceFinalizada['hosted_invoice_url'] ?? null,
                'payment_intent_id' => $invoiceFinalizada['payment_intent'] ?? null,
                'boleto_url' => null,
                'resposta_completa' => json_encode($invoiceFinalizada)
            ];
            
            // Se tiver boleto, extrair URL
            if (isset($invoiceFinalizada['payment_intent'])) {
                $paymentIntent = $this->makeRequest('GET', "/payment_intents/{$invoiceFinalizada['payment_intent']}");
                if (isset($paymentIntent['next_action']['boleto_display_details']['hosted_voucher_url'])) {
                    $resultado['boleto_url'] = $paymentIntent['next_action']['boleto_display_details']['hosted_voucher_url'];
                }
            }
            
            LogIntegracao::sucesso(
                'stripe',
                'criar_fatura',
                "Fatura criada com sucesso: {$resultado['invoice_id']}",
                $invoiceData,
                $invoiceFinalizada,
                200,
                microtime(true) - $startTime,
                $dados['conta_receber_id'] ?? null,
                'conta_receber'
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            LogIntegracao::erro(
                'stripe',
                'criar_fatura',
                $e->getMessage(),
                $dados,
                null,
                null,
                microtime(true) - $startTime,
                $dados['conta_receber_id'] ?? null,
                'conta_receber'
            );
            throw $e;
        }
    }
    
    /**
     * Consultar fatura
     * 
     * @param string $invoiceId ID da fatura no Stripe
     * @return array Dados da fatura
     */
    public function consultarFatura($invoiceId) {
        $startTime = microtime(true);
        
        try {
            $invoice = $this->makeRequest('GET', "/invoices/$invoiceId");
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao consultar fatura: ' . $invoice['error']['message']);
            }
            
            LogIntegracao::sucesso(
                'stripe',
                'consultar_fatura',
                "Fatura consultada: $invoiceId",
                ['invoice_id' => $invoiceId],
                $invoice,
                200,
                microtime(true) - $startTime
            );
            
            return [
                'invoice_id' => $invoice['id'],
                'status' => $invoice['status'],
                'valor' => ($invoice['amount_due'] ?? 0) / 100,
                'valor_pago' => ($invoice['amount_paid'] ?? 0) / 100,
                'resposta_completa' => json_encode($invoice)
            ];
            
        } catch (Exception $e) {
            LogIntegracao::erro(
                'stripe',
                'consultar_fatura',
                $e->getMessage(),
                ['invoice_id' => $invoiceId],
                null,
                null,
                microtime(true) - $startTime
            );
            throw $e;
        }
    }
    
    /**
     * Cancelar fatura
     * 
     * @param string $invoiceId ID da fatura no Stripe
     * @return array Resultado do cancelamento
     */
    public function cancelarFatura($invoiceId) {
        $startTime = microtime(true);
        
        try {
            $invoice = $this->makeRequest('POST', "/invoices/$invoiceId/void");
            
            if (isset($invoice['error'])) {
                throw new Exception('Erro ao cancelar fatura: ' . $invoice['error']['message']);
            }
            
            LogIntegracao::sucesso(
                'stripe',
                'cancelar_fatura',
                "Fatura cancelada: $invoiceId",
                ['invoice_id' => $invoiceId],
                $invoice,
                200,
                microtime(true) - $startTime
            );
            
            return [
                'sucesso' => true,
                'invoice_id' => $invoice['id'],
                'status' => 'void'
            ];
            
        } catch (Exception $e) {
            LogIntegracao::erro(
                'stripe',
                'cancelar_fatura',
                $e->getMessage(),
                ['invoice_id' => $invoiceId],
                null,
                null,
                microtime(true) - $startTime
            );
            throw $e;
        }
    }
    
    /**
     * Fazer requisição para API Stripe
     */
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildQuery($data));
        } elseif ($method === 'GET') {
            // GET já está configurado
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Construir query string para Stripe (suporta arrays aninhados)
     */
    private function buildQuery($data, $prefix = '') {
        $query = [];
        foreach ($data as $key => $value) {
            $k = $prefix ? "{$prefix}[{$key}]" : $key;
            if (is_array($value)) {
                $query[] = $this->buildQuery($value, $k);
            } else {
                $query[] = urlencode($k) . '=' . urlencode($value);
            }
        }
        return implode('&', $query);
    }
}
?>
