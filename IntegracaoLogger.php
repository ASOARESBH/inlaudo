<?php
/**
 * Logger de Integrações
 * ERP INLAUDO - Sistema de Debug e Diagnóstico
 * 
 * Registra todas as operações de integração para facilitar debug
 */

class IntegracaoLogger {
    private $conn;
    private $gateway;
    private $tipoOperacao;
    private $contaReceberId;
    private $clienteId;
    private $tempoInicio;
    
    /**
     * Construtor
     */
    public function __construct($gateway, $tipoOperacao, $contaReceberId = null, $clienteId = null) {
        require_once __DIR__ . '/../config.php';
        $this->conn = getConnection();
        $this->gateway = $gateway;
        $this->tipoOperacao = $tipoOperacao;
        $this->contaReceberId = $contaReceberId;
        $this->clienteId = $clienteId;
        $this->tempoInicio = microtime(true);
    }
    
    /**
     * Registrar log DEBUG
     */
    public function debug($mensagem, $dados = null) {
        $this->log('DEBUG', $mensagem, $dados);
    }
    
    /**
     * Registrar log INFO
     */
    public function info($mensagem, $dados = null) {
        $this->log('INFO', $mensagem, $dados);
    }
    
    /**
     * Registrar log WARNING
     */
    public function warning($mensagem, $dados = null) {
        $this->log('WARNING', $mensagem, $dados);
    }
    
    /**
     * Registrar log ERROR
     */
    public function error($mensagem, $dados = null, $stackTrace = null) {
        $this->log('ERROR', $mensagem, $dados, null, null, $stackTrace);
    }
    
    /**
     * Registrar log CRITICAL
     */
    public function critical($mensagem, $dados = null, $stackTrace = null) {
        $this->log('CRITICAL', $mensagem, $dados, null, null, $stackTrace);
    }
    
    /**
     * Registrar requisição HTTP
     */
    public function logRequest($mensagem, $dadosRequest, $dadosResponse = null, $codigoHttp = null) {
        $this->log('INFO', $mensagem, null, $dadosRequest, $dadosResponse, null, $codigoHttp);
    }
    
    /**
     * Registrar erro de requisição HTTP
     */
    public function logRequestError($mensagem, $dadosRequest, $dadosResponse = null, $codigoHttp = null, $stackTrace = null) {
        $this->log('ERROR', $mensagem, null, $dadosRequest, $dadosResponse, $stackTrace, $codigoHttp);
    }
    
    /**
     * Método principal de log
     */
    private function log($nivel, $mensagem, $dados = null, $dadosRequest = null, $dadosResponse = null, $stackTrace = null, $codigoHttp = null) {
        try {
            $tempoExecucao = microtime(true) - $this->tempoInicio;
            
            $stmt = $this->conn->prepare("
                INSERT INTO logs_integracao (
                    gateway,
                    tipo_operacao,
                    conta_receber_id,
                    cliente_id,
                    nivel,
                    mensagem,
                    dados_request,
                    dados_response,
                    codigo_http,
                    tempo_execucao,
                    ip_origem,
                    user_agent,
                    stack_trace
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->gateway,
                $this->tipoOperacao,
                $this->contaReceberId,
                $this->clienteId,
                $nivel,
                $mensagem,
                $dadosRequest ? json_encode($dadosRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($dados ? json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null),
                $dadosResponse ? json_encode($dadosResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null,
                $codigoHttp,
                $tempoExecucao,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $stackTrace
            ]);
            
            // Log também em arquivo para backup
            $this->logToFile($nivel, $mensagem, $dados);
            
        } catch (Exception $e) {
            // Se falhar ao salvar no banco, salva apenas em arquivo
            $this->logToFile('ERROR', 'Falha ao salvar log no banco: ' . $e->getMessage(), [
                'mensagem_original' => $mensagem,
                'dados_originais' => $dados
            ]);
        }
    }
    
    /**
     * Log em arquivo (backup)
     */
    private function logToFile($nivel, $mensagem, $dados = null) {
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/integracao_' . $this->gateway . '_' . date('Y-m-d') . '.log';
        
        $logLine = sprintf(
            "[%s] [%s] [%s] %s",
            date('Y-m-d H:i:s'),
            $nivel,
            $this->tipoOperacao,
            $mensagem
        );
        
        if ($dados) {
            $logLine .= "\nDados: " . json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        $logLine .= "\n" . str_repeat('-', 80) . "\n";
        
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }
    
    /**
     * Obter tempo de execução atual
     */
    public function getTempoExecucao() {
        return microtime(true) - $this->tempoInicio;
    }
}
