<?php
/**
 * Model: Gateway de Pagamento
 * Versão: 2.3.0
 * 
 * Gerencia gateways de pagamento e transações
 */

class GatewayPagamentoModel {
    private $conn;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? getConnection();
    }
    
    /**
     * Lista todos os gateways ativos
     * 
     * @return array
     */
    public function listarGatewaysAtivos() {
        $sql = "
            SELECT * FROM gateways_pagamento 
            WHERE ativo = 1 
            ORDER BY ordem_exibicao ASC, nome ASC
        ";
        
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca gateway por código
     * 
     * @param string $codigo
     * @return array|null
     */
    public function buscarPorCodigo($codigo) {
        $sql = "SELECT * FROM gateways_pagamento WHERE codigo = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$codigo]);
        return $stmt->fetch();
    }
    
    /**
     * Busca gateway por ID
     * 
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id) {
        $sql = "SELECT * FROM gateways_pagamento WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Cria transação de pagamento
     * 
     * @param array $dados
     * @return int ID da transação criada
     */
    public function criarTransacao($dados) {
        $sql = "
            INSERT INTO gateway_transacoes (
                conta_receber_id,
                gateway_id,
                gateway_transaction_id,
                gateway_payment_id,
                gateway_charge_id,
                status_erp,
                status_gateway,
                valor_original,
                valor_taxa,
                valor_liquido,
                forma_pagamento,
                dados_pagamento,
                payment_url,
                boleto_url,
                pix_qrcode,
                pix_copia_cola,
                data_vencimento,
                metadata_json,
                ip_origem,
                user_agent
            ) VALUES (
                :conta_receber_id,
                :gateway_id,
                :gateway_transaction_id,
                :gateway_payment_id,
                :gateway_charge_id,
                :status_erp,
                :status_gateway,
                :valor_original,
                :valor_taxa,
                :valor_liquido,
                :forma_pagamento,
                :dados_pagamento,
                :payment_url,
                :boleto_url,
                :pix_qrcode,
                :pix_copia_cola,
                :data_vencimento,
                :metadata_json,
                :ip_origem,
                :user_agent
            )
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':conta_receber_id' => $dados['conta_receber_id'],
            ':gateway_id' => $dados['gateway_id'],
            ':gateway_transaction_id' => $dados['gateway_transaction_id'] ?? null,
            ':gateway_payment_id' => $dados['gateway_payment_id'] ?? null,
            ':gateway_charge_id' => $dados['gateway_charge_id'] ?? null,
            ':status_erp' => $dados['status_erp'] ?? 'pendente',
            ':status_gateway' => $dados['status_gateway'] ?? null,
            ':valor_original' => $dados['valor_original'],
            ':valor_taxa' => $dados['valor_taxa'] ?? 0.00,
            ':valor_liquido' => $dados['valor_liquido'] ?? $dados['valor_original'],
            ':forma_pagamento' => $dados['forma_pagamento'] ?? null,
            ':dados_pagamento' => isset($dados['dados_pagamento']) ? json_encode($dados['dados_pagamento']) : null,
            ':payment_url' => $dados['payment_url'] ?? null,
            ':boleto_url' => $dados['boleto_url'] ?? null,
            ':pix_qrcode' => $dados['pix_qrcode'] ?? null,
            ':pix_copia_cola' => $dados['pix_copia_cola'] ?? null,
            ':data_vencimento' => $dados['data_vencimento'] ?? null,
            ':metadata_json' => isset($dados['metadata']) ? json_encode($dados['metadata']) : null,
            ':ip_origem' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    /**
     * Atualiza status de transação
     * 
     * @param int $transacao_id
     * @param string $status_erp
     * @param string $status_gateway
     * @param array $dados_adicionais
     * @return bool
     */
    public function atualizarStatusTransacao($transacao_id, $status_erp, $status_gateway = null, $dados_adicionais = []) {
        $sql = "
            UPDATE gateway_transacoes 
            SET 
                status_erp = :status_erp,
                status_gateway = :status_gateway,
                data_pagamento = :data_pagamento,
                data_atualizacao = NOW()
            WHERE id = :id
        ";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $transacao_id,
            ':status_erp' => $status_erp,
            ':status_gateway' => $status_gateway,
            ':data_pagamento' => ($status_erp === 'pago') ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    /**
     * Busca transação por ID do gateway
     * 
     * @param int $gateway_id
     * @param string $gateway_transaction_id
     * @return array|null
     */
    public function buscarTransacaoPorGatewayId($gateway_id, $gateway_transaction_id) {
        $sql = "
            SELECT * FROM gateway_transacoes 
            WHERE gateway_id = ? AND gateway_transaction_id = ? 
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$gateway_id, $gateway_transaction_id]);
        return $stmt->fetch();
    }
    
    /**
     * Busca transações de uma conta a receber
     * 
     * @param int $conta_receber_id
     * @return array
     */
    public function buscarTransacoesPorConta($conta_receber_id) {
        $sql = "
            SELECT 
                gt.*,
                g.nome as gateway_nome,
                g.codigo as gateway_codigo
            FROM gateway_transacoes gt
            LEFT JOIN gateways_pagamento g ON gt.gateway_id = g.id
            WHERE gt.conta_receber_id = ?
            ORDER BY gt.data_criacao DESC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$conta_receber_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Mapeia status do gateway para status ERP
     * 
     * @param int $gateway_id
     * @param string $status_gateway
     * @return string Status ERP padronizado
     */
    public function mapearStatus($gateway_id, $status_gateway) {
        $sql = "
            SELECT status_erp 
            FROM gateway_status_mapping 
            WHERE gateway_id = ? AND status_gateway = ? 
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$gateway_id, $status_gateway]);
        $result = $stmt->fetch();
        
        // Se não encontrar mapeamento, retornar pendente como padrão
        return $result ? $result['status_erp'] : 'pendente';
    }
    
    /**
     * Vincula gateway a uma conta a receber
     * 
     * @param int $conta_receber_id
     * @param int $gateway_id
     * @param array|null $gateways_disponiveis Array de IDs de gateways disponíveis
     * @return bool
     */
    public function vincularGatewayAConta($conta_receber_id, $gateway_id, $gateways_disponiveis = null) {
        $sql = "
            UPDATE contas_receber 
            SET 
                gateway_id = :gateway_id,
                gateways_disponiveis = :gateways_disponiveis,
                data_atualizacao = NOW()
            WHERE id = :id
        ";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $conta_receber_id,
            ':gateway_id' => $gateway_id,
            ':gateways_disponiveis' => $gateways_disponiveis ? json_encode($gateways_disponiveis) : null
        ]);
    }
    
    /**
     * Define gateways disponíveis para um contrato
     * 
     * @param int $contrato_id
     * @param int $gateway_id Gateway preferencial
     * @param array $gateways_disponiveis Array de IDs de gateways disponíveis
     * @return bool
     */
    public function definirGatewaysContrato($contrato_id, $gateway_id, $gateways_disponiveis = []) {
        $sql = "
            UPDATE contratos 
            SET 
                gateway_id = :gateway_id,
                gateways_disponiveis = :gateways_disponiveis,
                data_atualizacao = NOW()
            WHERE id = :id
        ";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $contrato_id,
            ':gateway_id' => $gateway_id,
            ':gateways_disponiveis' => json_encode($gateways_disponiveis)
        ]);
    }
    
    /**
     * Busca gateways disponíveis para uma conta
     * 
     * @param int $conta_receber_id
     * @return array
     */
    public function buscarGatewaysDisponiveis($conta_receber_id) {
        $sql = "
            SELECT 
                cr.gateways_disponiveis,
                cr.gateway_id as gateway_preferencial
            FROM contas_receber cr
            WHERE cr.id = ?
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$conta_receber_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return [];
        }
        
        $gateway_ids = $result['gateways_disponiveis'] 
            ? json_decode($result['gateways_disponiveis'], true) 
            : [];
        
        if (empty($gateway_ids)) {
            // Se não tiver gateways específicos, retornar todos ativos
            return $this->listarGatewaysAtivos();
        }
        
        // Buscar gateways específicos
        $placeholders = implode(',', array_fill(0, count($gateway_ids), '?'));
        $sql = "
            SELECT * FROM gateways_pagamento 
            WHERE id IN ($placeholders) AND ativo = 1
            ORDER BY 
                CASE WHEN id = ? THEN 0 ELSE 1 END,
                ordem_exibicao ASC
        ";
        
        $params = array_merge($gateway_ids, [$result['gateway_preferencial']]);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
