<?php
/**
 * Model Base - Classe abstrata para todos os modelos
 * 
 * Fornece métodos comuns para interagir com o banco de dados
 */

namespace App\Core;

abstract class Model {
    
    /**
     * Nome da tabela
     */
    protected $table = '';
    
    /**
     * Chave primária
     */
    protected $primaryKey = 'id';
    
    /**
     * Instância do banco de dados
     */
    protected $db = null;
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obter todos os registros
     */
    public function all() {
        $sql = "SELECT * FROM {$this->table}";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obter registro por ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Obter registros com filtro
     */
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ?";
        return $this->db->fetchAll($sql, [$value]);
    }
    
    /**
     * Obter primeiro registro com filtro
     */
    public function firstWhere($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} ? LIMIT 1";
        return $this->db->fetchOne($sql, [$value]);
    }
    
    /**
     * Contar registros
     */
    public function count() {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $result = $this->db->fetchOne($sql);
        return $result['total'] ?? 0;
    }
    
    /**
     * Criar novo registro
     */
    public function create($data) {
        return $this->db->insert($this->table, $data);
    }
    
    /**
     * Atualizar registro
     */
    public function update($id, $data) {
        return $this->db->update($this->table, $data, [$this->primaryKey => $id]);
    }
    
    /**
     * Deletar registro
     */
    public function delete($id) {
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }
    
    /**
     * Obter o ID do último registro inserido
     */
    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
    
    /**
     * Executar query customizada
     */
    public function query($sql, $params = []) {
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Obter um registro com query customizada
     */
    public function fetchOne($sql, $params = []) {
        return $this->db->fetchOne($sql, $params);
    }
    
    /**
     * Obter todos os registros com query customizada
     */
    public function fetchAll($sql, $params = []) {
        return $this->db->fetchAll($sql, $params);
    }
}
