<?php
/**
 * Biblioteca para gerenciamento de logs de integração
 */

require_once 'config.php';

class LogIntegracao {
    
    /**
     * Registrar log de integração
     * 
     * @param string $tipo Tipo da integração (stripe, cora, api_cnpj)
     * @param string $acao Ação executada (gerar_boleto, consultar, etc)
     * @param string $status Status (sucesso, erro, aviso)
     * @param string $mensagem Mensagem descritiva
     * @param mixed $requestData Dados enviados para API
     * @param mixed $responseData Resposta da API
     * @param int $codigoHttp Código HTTP da resposta
     * @param float $tempoResposta Tempo de resposta em segundos
     * @param int $referenciaId ID da entidade relacionada
     * @param string $referenciaTipo Tipo da entidade relacionada
     * @return int ID do log criado
     */
    public static function registrar(
        $tipo,
        $acao,
        $status,
        $mensagem,
        $requestData = null,
        $responseData = null,
        $codigoHttp = null,
        $tempoResposta = null,
        $referenciaId = null,
        $referenciaTipo = null
    ) {
        try {
            $conn = getConnection();
            
            // Converter arrays/objetos para JSON
            $requestJson = is_array($requestData) || is_object($requestData) 
                ? json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                : $requestData;
                
            $responseJson = is_array($responseData) || is_object($responseData) 
                ? json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                : $responseData;
            
            // Obter IP do usuário
            $ipOrigem = self::getClientIP();
            
            $sql = "INSERT INTO logs_integracao (
                        tipo, acao, status, mensagem, request_data, response_data,
                        codigo_http, tempo_resposta, ip_origem, referencia_id, referencia_tipo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $tipo,
                $acao,
                $status,
                $mensagem,
                $requestJson,
                $responseJson,
                $codigoHttp,
                $tempoResposta,
                $ipOrigem,
                $referenciaId,
                $referenciaTipo
            ]);
            
            return $conn->lastInsertId();
            
        } catch (PDOException $e) {
            // Em caso de erro ao registrar log, apenas registra no error_log do PHP
            error_log("Erro ao registrar log de integração: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Registrar sucesso
     */
    public static function sucesso($tipo, $acao, $mensagem, $requestData = null, $responseData = null, $codigoHttp = null, $tempoResposta = null, $referenciaId = null, $referenciaTipo = null) {
        return self::registrar($tipo, $acao, 'sucesso', $mensagem, $requestData, $responseData, $codigoHttp, $tempoResposta, $referenciaId, $referenciaTipo);
    }
    
    /**
     * Registrar erro
     */
    public static function erro($tipo, $acao, $mensagem, $requestData = null, $responseData = null, $codigoHttp = null, $tempoResposta = null, $referenciaId = null, $referenciaTipo = null) {
        return self::registrar($tipo, $acao, 'erro', $mensagem, $requestData, $responseData, $codigoHttp, $tempoResposta, $referenciaId, $referenciaTipo);
    }
    
    /**
     * Registrar aviso
     */
    public static function aviso($tipo, $acao, $mensagem, $requestData = null, $responseData = null, $codigoHttp = null, $tempoResposta = null, $referenciaId = null, $referenciaTipo = null) {
        return self::registrar($tipo, $acao, 'aviso', $mensagem, $requestData, $responseData, $codigoHttp, $tempoResposta, $referenciaId, $referenciaTipo);
    }
    
    /**
     * Buscar logs com filtros
     * 
     * @param array $filtros Filtros (tipo, status, data_inicio, data_fim)
     * @param int $limite Limite de registros
     * @param int $offset Offset para paginação
     * @return array Lista de logs
     */
    public static function buscar($filtros = [], $limite = 100, $offset = 0) {
        $conn = getConnection();
        
        $sql = "SELECT * FROM logs_integracao WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = ?";
            $params[] = $filtros['tipo'];
        }
        
        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['acao'])) {
            $sql .= " AND acao = ?";
            $params[] = $filtros['acao'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND data_log >= ?";
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND data_log <= ?";
            $params[] = $filtros['data_fim'] . ' 23:59:59';
        }
        
        if (!empty($filtros['referencia_tipo'])) {
            $sql .= " AND referencia_tipo = ?";
            $params[] = $filtros['referencia_tipo'];
        }
        
        if (!empty($filtros['referencia_id'])) {
            $sql .= " AND referencia_id = ?";
            $params[] = $filtros['referencia_id'];
        }
        
        $sql .= " ORDER BY data_log DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Contar logs com filtros
     */
    public static function contar($filtros = []) {
        $conn = getConnection();
        
        $sql = "SELECT COUNT(*) as total FROM logs_integracao WHERE 1=1";
        $params = [];
        
        if (!empty($filtros['tipo'])) {
            $sql .= " AND tipo = ?";
            $params[] = $filtros['tipo'];
        }
        
        if (!empty($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $sql .= " AND data_log >= ?";
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }
        
        if (!empty($filtros['data_fim'])) {
            $sql .= " AND data_log <= ?";
            $params[] = $filtros['data_fim'] . ' 23:59:59';
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    /**
     * Obter estatísticas de logs
     */
    public static function estatisticas($dataInicio = null, $dataFim = null) {
        $conn = getConnection();
        
        $sql = "SELECT 
                    tipo,
                    status,
                    COUNT(*) as total,
                    AVG(tempo_resposta) as tempo_medio
                FROM logs_integracao
                WHERE 1=1";
        
        $params = [];
        
        if ($dataInicio) {
            $sql .= " AND data_log >= ?";
            $params[] = $dataInicio . ' 00:00:00';
        }
        
        if ($dataFim) {
            $sql .= " AND data_log <= ?";
            $params[] = $dataFim . ' 23:59:59';
        }
        
        $sql .= " GROUP BY tipo, status ORDER BY tipo, status";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Limpar logs antigos
     * 
     * @param int $dias Número de dias para manter
     * @return int Número de registros deletados
     */
    public static function limparAntigos($dias = 90) {
        $conn = getConnection();
        
        $sql = "DELETE FROM logs_integracao WHERE data_log < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$dias]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Obter IP do cliente
     */
    private static function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}
?>
