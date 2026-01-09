<?php
/**
 * Biblioteca para geração de boletos via Stripe
 * Documentação: https://stripe.com/docs/payments/boleto
 */

require_once 'config.php';

class BoletoStripe {
    private $secretKey;
    private $publishableKey;
    
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
     * Gerar boleto via Stripe
     * 
     * @param array $dados Dados do boleto
     * @return array Resposta da API
     */
    public function gerarBoleto($dados) {
        // Validar dados obrigatórios
        $required = ['valor', 'data_vencimento', 'cliente_nome', 'cliente_email', 'cliente_documento'];
        foreach ($required as $field) {
            if (empty($dados[$field])) {
                throw new Exception("Campo obrigatório ausente: $field");
            }
        }
        
        // Converter valor para centavos (Stripe trabalha com centavos)
        $valorCentavos = (int)($dados['valor'] * 100);
        
        // Converter data de vencimento para timestamp
        $dataVencimento = strtotime($dados['data_vencimento']);
        $diasAteVencimento = (int)(($dataVencimento - time()) / 86400);
        
        if ($diasAteVencimento < 1) {
            throw new Exception('A data de vencimento deve ser futura.');
        }
        
        // Preparar dados para a API Stripe
        $paymentIntentData = [
            'amount' => $valorCentavos,
            'currency' => 'brl',
            'payment_method_types' => ['boleto'],
            'payment_method_options' => [
                'boleto' => [
                    'expires_after_days' => min($diasAteVencimento, 30) // Máximo 30 dias
                ]
            ],
            'description' => $dados['descricao'] ?? 'Pagamento via boleto',
            'receipt_email' => $dados['cliente_email'],
            'metadata' => [
                'cliente_nome' => $dados['cliente_nome'],
                'cliente_documento' => $dados['cliente_documento'],
                'conta_receber_id' => $dados['conta_receber_id'] ?? ''
            ]
        ];
        
        // Fazer requisição para API Stripe
        $response = $this->makeRequest('POST', 'https://api.stripe.com/v1/payment_intents', $paymentIntentData);
        
        if (isset($response['error'])) {
            throw new Exception('Erro Stripe: ' . $response['error']['message']);
        }
        
        // Extrair informações do boleto
        $resultado = [
            'sucesso' => true,
            'boleto_id' => $response['id'],
            'status' => $response['status'],
            'valor' => $dados['valor'],
            'data_vencimento' => $dados['data_vencimento'],
            'url_boleto' => $response['next_action']['boleto_display_details']['hosted_voucher_url'] ?? null,
            'codigo_barras' => null, // Stripe não retorna código de barras diretamente
            'linha_digitavel' => null,
            'resposta_completa' => json_encode($response)
        ];
        
        return $resultado;
    }
    
    /**
     * Consultar status de um boleto
     * 
     * @param string $boletoId ID do boleto no Stripe
     * @return array Status do boleto
     */
    public function consultarBoleto($boletoId) {
        $response = $this->makeRequest('GET', "https://api.stripe.com/v1/payment_intents/$boletoId");
        
        if (isset($response['error'])) {
            throw new Exception('Erro ao consultar boleto: ' . $response['error']['message']);
        }
        
        return [
            'boleto_id' => $response['id'],
            'status' => $this->mapearStatus($response['status']),
            'valor' => $response['amount'] / 100,
            'resposta_completa' => json_encode($response)
        ];
    }
    
    /**
     * Cancelar um boleto
     * 
     * @param string $boletoId ID do boleto no Stripe
     * @return array Resultado do cancelamento
     */
    public function cancelarBoleto($boletoId) {
        $response = $this->makeRequest('POST', "https://api.stripe.com/v1/payment_intents/$boletoId/cancel");
        
        if (isset($response['error'])) {
            throw new Exception('Erro ao cancelar boleto: ' . $response['error']['message']);
        }
        
        return [
            'sucesso' => true,
            'boleto_id' => $response['id'],
            'status' => 'cancelado'
        ];
    }
    
    /**
     * Fazer requisição para API Stripe
     */
    private function makeRequest($method, $url, $data = []) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * Mapear status do Stripe para status do sistema
     */
    private function mapearStatus($statusStripe) {
        $mapeamento = [
            'requires_payment_method' => 'pendente',
            'requires_confirmation' => 'pendente',
            'requires_action' => 'pendente',
            'processing' => 'pendente',
            'succeeded' => 'pago',
            'canceled' => 'cancelado'
        ];
        
        return $mapeamento[$statusStripe] ?? 'pendente';
    }
}
?>
