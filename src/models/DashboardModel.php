<?php
/**
 * DashboardModel.php
 * Model para consultas do Dashboard
 * 
 * @author ERP INLAUDO
 * @version 1.0.0
 * @date 2026-01-09
 */

class DashboardModel {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Retorna total de clientes ativos
     */
    public function getTotalClientesAtivos() {
        try {
            $stmt = $this->conn->query("
                SELECT COUNT(*) as total 
                FROM clientes 
                WHERE ativo = 1
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Erro ao buscar clientes ativos: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Retorna total de leads (clientes cadastrados nos últimos 30 dias)
     */
    public function getTotalLeads() {
        try {
            $stmt = $this->conn->query("
                SELECT COUNT(*) as total 
                FROM clientes 
                WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Erro ao buscar leads: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Retorna receita do mês atual (contas a receber pagas)
     */
    public function getReceitaMensal() {
        try {
            $stmt = $this->conn->query("
                SELECT COALESCE(SUM(valor), 0) as total 
                FROM contas_receber 
                WHERE MONTH(data_vencimento) = MONTH(NOW()) 
                AND YEAR(data_vencimento) = YEAR(NOW())
                AND status = 'pago'
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Erro ao buscar receita mensal: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Retorna total e valor de contas a receber pendentes
     */
    public function getContasReceber() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(valor), 0) as valor_total
                FROM contas_receber 
                WHERE status IN ('pendente', 'vencido')
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'total' => (int)($result['total'] ?? 0),
                'valor_total' => (float)($result['valor_total'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Erro ao buscar contas a receber: " . $e->getMessage());
            return ['total' => 0, 'valor_total' => 0];
        }
    }
    
    /**
     * Retorna total e valor de contas a pagar pendentes
     */
    public function getContasPagar() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(valor), 0) as valor_total
                FROM contas_pagar 
                WHERE status IN ('pendente', 'vencido')
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'total' => (int)($result['total'] ?? 0),
                'valor_total' => (float)($result['valor_total'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("Erro ao buscar contas a pagar: " . $e->getMessage());
            return ['total' => 0, 'valor_total' => 0];
        }
    }
    
    /**
     * Retorna total de contas vencidas (a receber)
     */
    public function getContasVencidas() {
        try {
            $stmt = $this->conn->query("
                SELECT COUNT(*) as total 
                FROM contas_receber 
                WHERE status = 'vencido'
                OR (status = 'pendente' AND data_vencimento < CURDATE())
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            error_log("Erro ao buscar contas vencidas: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Retorna dados do fluxo de caixa (últimos 10 meses)
     */
    public function getFluxoCaixa() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    DATE_FORMAT(mes, '%b/%y') as mes_formatado,
                    COALESCE(SUM(entradas), 0) as entradas,
                    COALESCE(SUM(saidas), 0) as saidas
                FROM (
                    SELECT 
                        DATE_FORMAT(data_vencimento, '%Y-%m-01') as mes,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as entradas,
                        0 as saidas
                    FROM contas_receber
                    WHERE data_vencimento >= DATE_SUB(NOW(), INTERVAL 10 MONTH)
                    GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')
                    
                    UNION ALL
                    
                    SELECT 
                        DATE_FORMAT(data_vencimento, '%Y-%m-01') as mes,
                        0 as entradas,
                        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as saidas
                    FROM contas_pagar
                    WHERE data_vencimento >= DATE_SUB(NOW(), INTERVAL 10 MONTH)
                    GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')
                ) as fluxo
                GROUP BY mes
                ORDER BY mes ASC
                LIMIT 10
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (Exception $e) {
            error_log("Erro ao buscar fluxo de caixa: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retorna distribuição de contas por status
     */
    public function getContasPorStatus() {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    status,
                    COUNT(*) as total
                FROM contas_receber
                GROUP BY status
                ORDER BY total DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (Exception $e) {
            error_log("Erro ao buscar contas por status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retorna últimas contas a receber criadas
     */
    public function getUltimasContasReceber($limit = 5) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    cr.id,
                    cr.descricao,
                    cr.valor,
                    cr.data_vencimento,
                    cr.status,
                    c.nome as cliente_nome
                FROM contas_receber cr
                LEFT JOIN clientes c ON cr.cliente_id = c.id
                ORDER BY cr.data_criacao DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (Exception $e) {
            error_log("Erro ao buscar últimas contas: " . $e->getMessage());
            return [];
        }
    }
}
