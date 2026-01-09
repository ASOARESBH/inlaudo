<?php
/**
 * Biblioteca Mercado Pago
 * ERP INLAUDO - VERSÃO FINAL
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

class MercadoPago
{
    private $accessToken;
    private $webhookUrl;
    private $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->carregarConfig();
    }

    private function carregarConfig()
    {
        $conn = getConnection();

        $stmt = $conn->prepare("
            SELECT *
            FROM integracao_mercadopago
            WHERE gateway = 'mercadopago'
              AND ativo = 1
            LIMIT 1
        ");
        $stmt->execute();
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cfg) {
            throw new Exception('Configuração Mercado Pago não encontrada');
        }

        $this->accessToken = trim($cfg['access_token']);
        $this->webhookUrl  = trim($cfg['webhook_url']);
    }

    public function criarPreferencia(array $dados)
    {
        $payload = [
            'items' => [[
                'title'       => $dados['descricao'],
                'quantity'    => 1,
                'currency_id' => 'BRL',
                'unit_price'  => (float)$dados['valor']
            ]],
            'external_reference' => $dados['external_reference'],
            'notification_url'   => $this->webhookUrl
        ];

        $ch = curl_init($this->baseUrl . '/checkout/preferences');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($response, true);

        if ($http === 201 && isset($json['init_point'])) {
            return [
                'sucesso' => true,
                'init_point' => $json['init_point'],
                'preference_id' => $json['id']
            ];
        }

        return [
            'sucesso' => false,
            'erro' => $json['message'] ?? 'Erro Mercado Pago',
            'detalhes' => $json
        ];
    }
}
