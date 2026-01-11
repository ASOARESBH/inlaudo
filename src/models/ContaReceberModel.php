<?php
/**
 * Model - Conta a Receber
 * 
 * Gerencia operações com contas a receber
 */

namespace App\Models;

use App\Core\Model;

class ContaReceberModel extends Model {
    
    /**
     * Nome da tabela
     */
    protected $table = 'contas_receber';
    
    /**
     * Obter contas pendentes
     */
    public function getPendentes() {
        $sql = "
            SELECT cr.*, c.nome as cliente_nome
            FROM {$this->table} cr
            LEFT JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.status = 'pendente'
            ORDER BY cr.data_vencimento ASC
        ";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obter contas vencidas
     */
    public function getVencidas() {
        $sql = "
            SELECT cr.*, c.nome as cliente_nome
            FROM {$this->table} cr
            LEFT JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.status IN ('pendente', 'vencido')
            AND cr.data_vencimento < CURDATE()
            ORDER BY cr.data_vencimento ASC
        ";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obter contas vencendo
     */
    public function getVencendo($dias = 7) {
        $sql = "
            SELECT cr.*, c.nome as cliente_nome
            FROM {$this->table} cr
            LEFT JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.status = 'pendente'
            AND cr.data_vencimento > CURDATE()
            AND cr.data_vencimento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY cr.data_vencimento ASC
        ";
        
        return $this->db->fetchAll($sql, [$dias]);
    }
    
    /**
     * Obter contas por cliente
     */
    public function getByCliente($cliente_id) {
        return $this->where('cliente_id', $cliente_id);
    }
    
    /**
     * Obter total de contas pendentes
     */
    public function getTotalPendente() {
        $sql = "
            SELECT COUNT(*) as total, SUM(valor) as valor_total
            FROM {$this->table}
            WHERE status = 'pendente'
        ";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Obter total de contas vencidas
     */
    public function getTotalVencido() {
        $sql = "
            SELECT COUNT(*) as total, SUM(valor) as valor_total
            FROM {$this->table}
            WHERE status IN ('pendente', 'vencido')
            AND data_vencimento < CURDATE()
        ";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Obter resumo de contas
     */
    public function getResumo() {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) as pagas,
                SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) as vencidas,
                SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as canceladas,
                SUM(valor) as valor_total,
                SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as valor_pendente
            FROM {$this->table}
        ";
        
        return $this->db->fetchOne($sql);
    }
}
