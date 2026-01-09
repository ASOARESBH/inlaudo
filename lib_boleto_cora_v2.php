<?php
/**
 * Biblioteca para integração com API CORA v2
 * Emissão de Boletos Registrados usando mTLS (Mutual TLS)
 * 
 * Documentação: https://developers.cora.com.br/reference/emiss%C3%A3o-de-boleto-registrado-v2
 */

require_once 'config.php';
require_once 'lib_logs.php';

class CoraAPIv2 {
    
    private $clientId;
    private $certificatePath;
    private $privateKeyPath;
    private $baseUrl;
    private $ambiente; // 'stage' ou 'production'
    
    /**
     * Construtor
     * 
     * @param string $clientId Client ID da CORA
     * @param string $certificatePath Caminho do certificado PEM
     * @param string $privateKeyPath Caminho da chave privada
     * @param string $ambiente 'stage' ou 'production'
     */
    public function __construct($clientId, $certificatePath, $privateKeyPath, $ambiente = 'production') {
        $this->clientId = $clientId;
        $this->certificatePath = $certificatePath;
        $this->privateKeyPath = $privateKeyPath;
        $this->ambiente = $ambiente;
        
        // Definir URL base conforme ambiente
        if ($ambiente == 'stage') {
            $this->baseUrl = 'https://matls-clients.api.stage.cora.com.br';
        } else {
            $this->baseUrl = 'https://matls-clients.api.cora.com.br';
        }
    }
    
    /**
     * Gerar UUID v4 para Idempotency-Key
     * 
     * @return string UUID v4
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versão 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Fazer requisição HTTP com mTLS
     * 
     * @param string $method Método HTTP (GET, POST, DELETE)
     * @param string $endpoint Endpoint da API (ex: /v2/invoices/)
     * @param array $data Dados para enviar (opcional)
     * @param array $headers Headers adicionais (opcional)
     * @return array ['sucesso' => bool, 'dados' => array, 'mensagem' => string, 'codigo_http' => int]
     */
    private function request($method, $endpoint, $data = null, $headers = []) {
        $url = $this->baseUrl . $endpoint;
        
        // Headers padrão
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key: ' . $this->generateUUID()
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Configurar cURL com mTLS
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        
        // Configurar certificado mTLS
        curl_setopt($ch, CURLOPT_SSLCERT, $this->certificatePath);
        curl_setopt($ch, CURLOPT_SSLKEY, $this->privateKeyPath);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        
        // Verificar SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Método HTTP
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method == 'GET') {
            // GET é o padrão
        }
        
        // Capturar informações da requisição
        $tempoInicio = microtime(true);
        $requestData = $data ? json_encode($data, JSON_PRETTY_PRINT) : null;
        
        // Executar requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        $tempoFim = microtime(true);
        $tempoResposta = round(($tempoFim - $tempoInicio) * 1000); // em ms
        
        curl_close($ch);
        
        // Processar resposta
        if ($curlError) {
            // Registrar log de erro
            LogIntegracao::registrar(
                'cora_api_v2',
                $method . ' ' . $endpoint,
                'erro',
                'Erro cURL: ' . $curlError,
                $requestData,
                null,
                0,
                $tempoResposta
            );
            
            return [
                'sucesso' => false,
                'mensagem' => 'Erro de conexão: ' . $curlError,
                'codigo_http' => 0,
                'dados' => null
            ];
        }
        
        $responseData = json_decode($response, true);
        
        // Verificar se a resposta é JSON válido
        if (json_last_error() !== JSON_ERROR_NONE) {
            LogIntegracao::registrar(
                'cora_api_v2',
                $method . ' ' . $endpoint,
                'erro',
                'Resposta inválida (não é JSON)',
                $requestData,
                $response,
                $httpCode,
                $tempoResposta
            );
            
            return [
                'sucesso' => false,
                'mensagem' => 'Resposta inválida da API',
                'codigo_http' => $httpCode,
                'dados' => null
            ];
        }
        
        // Verificar código HTTP
        $sucesso = ($httpCode >= 200 && $httpCode < 300);
        
        // Registrar log
        LogIntegracao::registrar(
            'cora_api_v2',
            $method . ' ' . $endpoint,
            $sucesso ? 'sucesso' : 'erro',
            $sucesso ? 'Requisição bem-sucedida' : ($responseData['message'] ?? 'Erro na requisição'),
            $requestData,
            json_encode($responseData, JSON_PRETTY_PRINT),
            $httpCode,
            $tempoResposta
        );
        
        return [
            'sucesso' => $sucesso,
            'mensagem' => $responseData['message'] ?? ($sucesso ? 'Sucesso' : 'Erro desconhecido'),
            'codigo_http' => $httpCode,
            'dados' => $responseData
        ];
    }
    
    /**
     * Emitir boleto registrado
     * 
     * @param array $dadosCliente Dados do cliente
     * @param array $dadosCobranca Dados da cobrança
     * @return array ['sucesso' => bool, 'dados' => array, 'mensagem' => string]
     */
    public function emitirBoleto($dadosCliente, $dadosCobranca) {
        try {
            // Montar estrutura de dados conforme API v2
            $payload = [
                'code' => $dadosCobranca['codigo_unico'], // ID único no sistema do cliente
                'customer' => [
                    'name' => $dadosCliente['nome'],
                    'email' => $dadosCliente['email'] ?: 'naotem@email.com',
                    'document' => [
                        'identity' => preg_replace('/[^0-9]/', '', $dadosCliente['documento']), // Apenas números
                        'type' => strlen(preg_replace('/[^0-9]/', '', $dadosCliente['documento'])) == 11 ? 'CPF' : 'CNPJ'
                    ],
                    'address' => [
                        'street' => $dadosCliente['endereco']['logradouro'] ?? '',
                        'number' => $dadosCliente['endereco']['numero'] ?? 'S/N',
                        'district' => $dadosCliente['endereco']['bairro'] ?? '',
                        'city' => $dadosCliente['endereco']['cidade'] ?? '',
                        'state' => $dadosCliente['endereco']['uf'] ?? '',
                        'complement' => $dadosCliente['endereco']['complemento'] ?? '',
                        'country' => 'BRA',
                        'zip_code' => preg_replace('/[^0-9]/', '', $dadosCliente['endereco']['cep'] ?? '')
                    ]
                ],
                'services' => [
                    [
                        'name' => $dadosCobranca['descricao'],
                        'description' => $dadosCobranca['descricao'],
                        'amount' => (int)($dadosCobranca['valor'] * 100) // Converter para centavos
                    ]
                ],
                'payment_terms' => [
                    'due_date' => $dadosCobranca['data_vencimento'] // Formato: YYYY-MM-DD
                ],
                'pix' => [
                    'enabled' => true // Habilitar Pix no boleto
                ]
            ];
            
            // Adicionar multa se configurada
            if (isset($dadosCobranca['multa']) && $dadosCobranca['multa']['valor'] > 0) {
                $payload['payment_terms']['fine'] = [
                    'date' => date('Y-m-d', strtotime($dadosCobranca['data_vencimento'] . ' +1 day')),
                    'rate' => (float)$dadosCobranca['multa']['percentual'] // Percentual
                ];
            }
            
            // Adicionar juros se configurado
            if (isset($dadosCobranca['juros']) && $dadosCobranca['juros']['valor'] > 0) {
                $payload['payment_terms']['interest'] = [
                    'date' => date('Y-m-d', strtotime($dadosCobranca['data_vencimento'] . ' +1 day')),
                    'rate' => (float)$dadosCobranca['juros']['percentual_mes'] // Percentual ao mês
                ];
            }
            
            // Adicionar desconto se configurado
            if (isset($dadosCobranca['desconto']) && $dadosCobranca['desconto']['valor'] > 0) {
                $payload['payment_terms']['discount'] = [
                    'date' => $dadosCobranca['desconto']['data_limite'],
                    'amount' => (int)($dadosCobranca['desconto']['valor'] * 100) // Centavos
                ];
            }
            
            // Fazer requisição
            $resultado = $this->request('POST', '/v2/invoices/', $payload);
            
            if ($resultado['sucesso']) {
                return [
                    'sucesso' => true,
                    'dados' => [
                        'id_cora' => $resultado['dados']['id'],
                        'status' => $resultado['dados']['status'],
                        'linha_digitavel' => $resultado['dados']['digitable_line'] ?? null,
                        'codigo_barras' => $resultado['dados']['barcode'] ?? null,
                        'url_pdf' => $resultado['dados']['pdf_url'] ?? null,
                        'url_boleto' => $resultado['dados']['invoice_url'] ?? null,
                        'qr_code_pix' => $resultado['dados']['pix']['qr_code'] ?? null,
                        'pix_copia_cola' => $resultado['dados']['pix']['emv'] ?? null,
                        'valor_total' => $resultado['dados']['total_amount'] / 100, // Converter de centavos
                        'data_criacao' => $resultado['dados']['created_at'],
                        'resposta_completa' => json_encode($resultado['dados'])
                    ],
                    'mensagem' => 'Boleto emitido com sucesso'
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => $resultado['mensagem'],
                    'dados' => $resultado['dados']
                ];
            }
            
        } catch (Exception $e) {
            LogIntegracao::registrar(
                'cora_api_v2',
                'emitirBoleto',
                'erro',
                'Exceção: ' . $e->getMessage(),
                json_encode($dadosCliente) . ' | ' . json_encode($dadosCobranca),
                null,
                0,
                0
            );
            
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao emitir boleto: ' . $e->getMessage(),
                'dados' => null
            ];
        }
    }
    
    /**
     * Consultar boleto por ID
     * 
     * @param string $invoiceId ID do boleto na CORA
     * @return array ['sucesso' => bool, 'dados' => array, 'mensagem' => string]
     */
    public function consultarBoleto($invoiceId) {
        $resultado = $this->request('GET', '/v2/invoices/' . $invoiceId);
        
        if ($resultado['sucesso']) {
            return [
                'sucesso' => true,
                'dados' => $resultado['dados'],
                'mensagem' => 'Boleto consultado com sucesso'
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => $resultado['mensagem'],
                'dados' => $resultado['dados']
            ];
        }
    }
    
    /**
     * Listar boletos com paginação
     * 
     * @param int $page Número da página (inicia em 0)
     * @param int $size Quantidade de itens por página (padrão: 50)
     * @return array ['sucesso' => bool, 'dados' => array, 'mensagem' => string]
     */
    public function listarBoletos($page = 0, $size = 50) {
        $endpoint = '/v2/invoices/?page=' . $page . '&size=' . $size;
        $resultado = $this->request('GET', $endpoint);
        
        if ($resultado['sucesso']) {
            return [
                'sucesso' => true,
                'dados' => $resultado['dados'],
                'mensagem' => 'Boletos listados com sucesso'
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => $resultado['mensagem'],
                'dados' => $resultado['dados']
            ];
        }
    }
    
    /**
     * Cancelar boleto
     * 
     * @param string $invoiceId ID do boleto na CORA
     * @return array ['sucesso' => bool, 'dados' => array, 'mensagem' => string]
     */
    public function cancelarBoleto($invoiceId) {
        $resultado = $this->request('DELETE', '/v2/invoices/' . $invoiceId);
        
        if ($resultado['sucesso']) {
            return [
                'sucesso' => true,
                'dados' => $resultado['dados'],
                'mensagem' => 'Boleto cancelado com sucesso'
            ];
        } else {
            return [
                'sucesso' => false,
                'mensagem' => $resultado['mensagem'],
                'dados' => $resultado['dados']
            ];
        }
    }
    
    /**
     * Testar conexão com a API
     * 
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public function testarConexao() {
        try {
            // Tentar listar boletos (primeira página)
            $resultado = $this->listarBoletos(0, 1);
            
            if ($resultado['sucesso']) {
                return [
                    'sucesso' => true,
                    'mensagem' => 'Conexão com API CORA estabelecida com sucesso! Ambiente: ' . $this->ambiente
                ];
            } else {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Falha na conexão: ' . $resultado['mensagem']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao testar conexão: ' . $e->getMessage()
            ];
        }
    }
}
?>
