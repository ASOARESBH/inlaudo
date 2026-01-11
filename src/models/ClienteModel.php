<?php
/**
 * Model - Cliente
 * 
 * Gerencia operações com clientes
 */

namespace App\Models;

use App\Core\Model;

class ClienteModel extends Model {
    
    /**
     * Nome da tabela
     */
    protected $table = 'clientes';
    
    /**
     * Obter clientes ativos
     */
    public function getAtivos() {
        $sql = "SELECT * FROM {$this->table} WHERE ativo = 1 ORDER BY nome";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obter clientes por tipo
     */
    public function getByTipo($tipo) {
        return $this->where('tipo_cliente', $tipo);
    }
    
    /**
     * Buscar cliente por CNPJ/CPF
     */
    public function findByCnpjCpf($cnpj_cpf) {
        return $this->firstWhere('cnpj_cpf', $cnpj_cpf);
    }
    
    /**
     * Buscar cliente por email
     */
    public function findByEmail($email) {
        return $this->firstWhere('email', $email);
    }
    
    /**
     * Obter clientes com contas vencidas
     */
    public function getComContasVencidas() {
        $sql = "
            SELECT DISTINCT c.* 
            FROM {$this->table} c
            INNER JOIN contas_receber cr ON cr.cliente_id = c.id
            WHERE cr.status IN ('pendente', 'vencido')
            AND cr.data_vencimento < CURDATE()
            ORDER BY c.nome
        ";
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obter total de clientes por tipo
     */
    public function getTotalPorTipo() {
        $sql = "
            SELECT tipo_cliente, COUNT(*) as total
            FROM {$this->table}
            GROUP BY tipo_cliente
        ";
        
        return $this->db->fetchAll($sql);
    }
}
