<?php

namespace App\Models;

use PDO;
use Exception;

/**
 * Modelo de Dados Asaas
 * 
 * Responsável por operações no banco de dados relacionadas ao Asaas
 * 
 * @author Backend Developer
 * @version 1.0.0
 */
class AsaasModel
{
    /**
     * Conexão PDO
     * @var PDO
     */
    private $pdo;
    
    /**
     * Construtor
     * 
     * @param PDO $pdo Conexão com banco de dados
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Obter configuração ativa
     * 
     * @return array|null
     */
    public function getActive()
    {
        $sql = "SELECT * FROM integracao_asaas WHERE ativo = 1 LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter configuração por ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getById($id)
    {
        $sql = "SELECT * FROM integracao_asaas WHERE id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualizar configuração
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        $allowed = ['api_key', 'webhook_token', 'webhook_url', 'ambiente', 'ativo'];
        $data = array_intersect_key($data, array_flip($allowed));
        
        if (empty($data)) {
            throw new Exception('Nenhum campo válido para atualizar');
        }
        
        $data['data_atualizacao'] = date('Y-m-d H:i:s');
        
        $set = [];
        $values = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = ?";
            $values[] = $value;
        }
        $values[] = $id;
        
        $sql = "UPDATE integracao_asaas SET " . implode(', ', $set) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    /**
     * Registrar log
     * 
     * @param string $operacao
     * @param string $status
     * @param array $requisicao
     * @param array $resposta
     * @param string $erro
     * @return int ID do log inserido
     */
    public function logOperation($operacao, $status, $requisicao = null, $resposta = null, $erro = null)
    {
        $sql = "
            INSERT INTO asaas_logs (operacao, status, dados_requisicao, dados_resposta, mensagem_erro, data_criacao)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $operacao,
            $status,
            $requisicao ? json_encode($requisicao) : null,
            $resposta ? json_encode($resposta) : null,
            $erro
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Buscar cliente mapeado
     * 
     * @param int $clienteId
     * @return array|null
     */
    public function findMappedCustomer($clienteId)
    {
        $sql = "SELECT * FROM asaas_clientes WHERE cliente_id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Mapear cliente
     * 
     * @param int $clienteId
     * @param string $asaasCustomerId
     * @param string $cpfCnpj
     * @return int ID do mapeamento
     */
    public function mapCustomer($clienteId, $asaasCustomerId, $cpfCnpj)
    {
        $sql = "
            INSERT INTO asaas_clientes (cliente_id, asaas_customer_id, cpf_cnpj, data_criacao)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE asaas_customer_id = VALUES(asaas_customer_id)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clienteId, $asaasCustomerId, $cpfCnpj]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Mapear pagamento
     * 
     * @param int $contaReceberId
     * @param string $asaasPaymentId
     * @param string $tipoCobranca
     * @param float $valor
     * @param string $dataVencimento
     * @param array $dadosAdicionais
     * @return int ID do mapeamento
     */
    public function mapPayment($contaReceberId, $asaasPaymentId, $tipoCobranca, $valor, $dataVencimento, $dadosAdicionais = [])
    {
        $sql = "
            INSERT INTO asaas_pagamentos 
            (conta_receber_id, asaas_payment_id, tipo_cobranca, valor, data_vencimento, 
             url_boleto, nosso_numero, linha_digitavel, qr_code_pix, payload_pix, data_criacao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $contaReceberId,
            $asaasPaymentId,
            $tipoCobranca,
            $valor,
            $dataVencimento,
            $dadosAdicionais['bankSlipUrl'] ?? null,
            $dadosAdicionais['nossoNumero'] ?? null,
            $dadosAdicionais['identificationField'] ?? null,
            $dadosAdicionais['encodedImage'] ?? null,
            $dadosAdicionais['payload'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Buscar pagamento mapeado
     * 
     * @param string $asaasPaymentId
     * @return array|null
     */
    public function findMappedPayment($asaasPaymentId)
    {
        $sql = "SELECT * FROM asaas_pagamentos WHERE asaas_payment_id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$asaasPaymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Registrar webhook recebido
     * 
     * @param string $eventId
     * @param string $tipoEvento
     * @param string $paymentId
     * @param string $payload
     * @return int ID do webhook
     */
    public function registerWebhook($eventId, $tipoEvento, $paymentId, $payload)
    {
        $sql = "
            INSERT INTO asaas_webhooks (event_id, tipo_evento, payment_id, payload, processado, data_recebimento)
            VALUES (?, ?, ?, ?, 0, NOW())
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId, $tipoEvento, $paymentId, $payload]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Verificar se webhook já foi processado
     * 
     * @param string $eventId
     * @return bool
     */
    public function webhookExists($eventId)
    {
        $sql = "SELECT id FROM asaas_webhooks WHERE event_id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eventId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Marcar webhook como processado
     * 
     * @param string $eventId
     * @return bool
     */
    public function markWebhookProcessed($eventId)
    {
        $sql = "UPDATE asaas_webhooks SET processado = 1, data_processamento = NOW() WHERE event_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$eventId]);
    }
    
    /**
     * Obter logs
     * 
     * @param array $filtros
     * @param int $limite
     * @param int $offset
     * @return array
     */
    public function getLogs($filtros = [], $limite = 100, $offset = 0)
    {
        $sql = "SELECT * FROM asaas_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['operacao'])) {
            $sql .= " AND operacao LIKE ?";
            $params[] = "%{$filtros['operacao']}%";
        }
        
        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['data'])) {
            $sql .= " AND DATE(data_criacao) = ?";
            $params[] = $filtros['data'];
        }
        
        $sql .= " ORDER BY data_criacao DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Contar logs
     * 
     * @param array $filtros
     * @return int
     */
    public function countLogs($filtros = [])
    {
        $sql = "SELECT COUNT(*) as total FROM asaas_logs WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['operacao'])) {
            $sql .= " AND operacao LIKE ?";
            $params[] = "%{$filtros['operacao']}%";
        }
        
        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['data'])) {
            $sql .= " AND DATE(data_criacao) = ?";
            $params[] = $filtros['data'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total'] ?? 0;
    }
}
