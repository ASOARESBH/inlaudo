<?php
/**
 * Service - Alertas
 * 
 * Gerencia lógica de negócio para alertas de contas vencidas
 */

namespace App\Services;

use App\Core\Database;

class AlertaService {
    
    /**
     * Instância do banco de dados
     */
    private $db;
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Gerar alertas de contas vencidas
     */
    public function gerarAlertas() {
        try {
            $this->db->beginTransaction();
            
            // Gerar alertas para contas vencidas
            $this->gerarAlertasVencidos();
            
            // Gerar alertas para contas vencendo
            $this->gerarAlertasVencendo();
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Gerar alertas para contas vencidas
     */
    private function gerarAlertasVencidos() {
        $sql = "
            INSERT INTO alertas_contas_vencidas (
                conta_receber_id,
                usuario_id,
                tipo_alerta,
                titulo,
                descricao,
                valor,
                dias_vencido,
                data_criacao
            )
            SELECT 
                cr.id,
                u.id,
                'vencido',
                CONCAT('Conta Vencida - ', cr.descricao),
                CONCAT(
                    'Cliente: ', c.nome, '\n',
                    'Valor: R$ ', FORMAT(cr.valor, 2, 'pt_BR'), '\n',
                    'Vencimento: ', DATE_FORMAT(cr.data_vencimento, '%d/%m/%Y')
                ),
                cr.valor,
                DATEDIFF(CURDATE(), cr.data_vencimento),
                NOW()
            FROM contas_receber cr
            INNER JOIN clientes c ON c.id = cr.cliente_id
            CROSS JOIN usuarios u
            WHERE cr.status IN ('pendente', 'vencido')
            AND cr.data_vencimento < CURDATE()
            AND u.tipo_usuario IN ('admin', 'usuario')
            AND u.ativo = 1
            AND NOT EXISTS (
                SELECT 1 FROM alertas_contas_vencidas acv
                WHERE acv.conta_receber_id = cr.id
                AND acv.usuario_id = u.id
                AND DATE(acv.data_criacao) = CURDATE()
            )
            ON DUPLICATE KEY UPDATE
                data_atualizacao = NOW()
        ";
        
        $this->db->query($sql);
    }
    
    /**
     * Gerar alertas para contas vencendo
     */
    private function gerarAlertasVencendo() {
        $dias = ALERTAS_DIAS_VENCENDO;
        
        $sql = "
            INSERT INTO alertas_contas_vencidas (
                conta_receber_id,
                usuario_id,
                tipo_alerta,
                titulo,
                descricao,
                valor,
                dias_vencido,
                data_criacao
            )
            SELECT 
                cr.id,
                u.id,
                CASE 
                    WHEN DATEDIFF(cr.data_vencimento, CURDATE()) = 0 THEN 'vencendo_hoje'
                    WHEN DATEDIFF(cr.data_vencimento, CURDATE()) = 1 THEN 'vencendo_amanha'
                    ELSE 'vencendo_semana'
                END,
                CONCAT('Conta Vencendo - ', cr.descricao),
                CONCAT(
                    'Cliente: ', c.nome, '\n',
                    'Valor: R$ ', FORMAT(cr.valor, 2, 'pt_BR'), '\n',
                    'Vencimento: ', DATE_FORMAT(cr.data_vencimento, '%d/%m/%Y')
                ),
                cr.valor,
                DATEDIFF(cr.data_vencimento, CURDATE()),
                NOW()
            FROM contas_receber cr
            INNER JOIN clientes c ON c.id = cr.cliente_id
            CROSS JOIN usuarios u
            WHERE cr.status = 'pendente'
            AND cr.data_vencimento > CURDATE()
            AND cr.data_vencimento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND u.tipo_usuario IN ('admin', 'usuario')
            AND u.ativo = 1
            AND NOT EXISTS (
                SELECT 1 FROM alertas_contas_vencidas acv
                WHERE acv.conta_receber_id = cr.id
                AND acv.usuario_id = u.id
                AND DATE(acv.data_criacao) = CURDATE()
            )
            ON DUPLICATE KEY UPDATE
                data_atualizacao = NOW()
        ";
        
        $this->db->execute($sql, [$dias]);
    }
    
    /**
     * Obter alertas não visualizados do usuário
     */
    public function obterAlertas($usuario_id) {
        $sql = "
            SELECT 
                acv.id,
                acv.conta_receber_id,
                acv.tipo_alerta,
                acv.titulo,
                acv.descricao,
                acv.valor,
                acv.dias_vencido,
                acv.data_criacao,
                cr.descricao as conta_descricao,
                cr.data_vencimento,
                c.nome as cliente_nome,
                c.cnpj_cpf
            FROM alertas_contas_vencidas acv
            INNER JOIN contas_receber cr ON cr.id = acv.conta_receber_id
            INNER JOIN clientes c ON c.id = cr.cliente_id
            WHERE acv.usuario_id = ?
            AND acv.visualizado = 0
            ORDER BY 
                CASE acv.tipo_alerta
                    WHEN 'vencido' THEN 1
                    WHEN 'vencendo_hoje' THEN 2
                    WHEN 'vencendo_amanha' THEN 3
                    WHEN 'vencendo_semana' THEN 4
                END,
                acv.valor DESC,
                acv.data_criacao DESC
        ";
        
        return $this->db->fetchAll($sql, [$usuario_id]);
    }
    
    /**
     * Contar alertas não visualizados
     */
    public function contarAlertas($usuario_id) {
        $sql = "
            SELECT COUNT(*) as total
            FROM alertas_contas_vencidas
            WHERE usuario_id = ? AND visualizado = 0
        ";
        
        $resultado = $this->db->fetchOne($sql, [$usuario_id]);
        return $resultado['total'] ?? 0;
    }
    
    /**
     * Obter resumo de alertas
     */
    public function obterResumo($usuario_id) {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN tipo_alerta = 'vencido' THEN 1 ELSE 0 END) as vencidos,
                SUM(CASE WHEN tipo_alerta = 'vencendo_hoje' THEN 1 ELSE 0 END) as vencendo_hoje,
                SUM(CASE WHEN tipo_alerta = 'vencendo_amanha' THEN 1 ELSE 0 END) as vencendo_amanha,
                SUM(CASE WHEN tipo_alerta = 'vencendo_semana' THEN 1 ELSE 0 END) as vencendo_semana,
                SUM(valor) as valor_total
            FROM alertas_contas_vencidas
            WHERE usuario_id = ? AND visualizado = 0
        ";
        
        $resultado = $this->db->fetchOne($sql, [$usuario_id]);
        
        return [
            'total' => $resultado['total'] ?? 0,
            'vencidos' => $resultado['vencidos'] ?? 0,
            'vencendo_hoje' => $resultado['vencendo_hoje'] ?? 0,
            'vencendo_amanha' => $resultado['vencendo_amanha'] ?? 0,
            'vencendo_semana' => $resultado['vencendo_semana'] ?? 0,
            'valor_total' => $resultado['valor_total'] ?? 0
        ];
    }
    
    /**
     * Marcar alerta como visualizado
     */
    public function marcarVisualizado($alerta_id, $acao = 'ver') {
        $sql = "
            UPDATE alertas_contas_vencidas
            SET 
                visualizado = 1,
                data_visualizacao = NOW(),
                acao_tomada = ?
            WHERE id = ?
        ";
        
        return $this->db->execute($sql, [$acao, $alerta_id]);
    }
    
    /**
     * Cancelar conta
     */
    public function cancelarConta($conta_id) {
        $sql = "
            UPDATE contas_receber
            SET status = 'cancelado', data_atualizacao = NOW()
            WHERE id = ?
        ";
        
        return $this->db->execute($sql, [$conta_id]);
    }
}
