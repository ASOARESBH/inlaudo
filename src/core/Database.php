<?php
/**
 * Database - Gerenciador de Conexão com Banco de Dados
 * 
 * Fornece métodos para interagir com o banco de dados
 */

namespace App\Core;

use PDO;
use PDOException;

class Database {
    
    /**
     * Instância singleton
     */
    private static $instance = null;
    
    /**
     * Conexão PDO
     */
    private $connection = null;
    
    /**
     * Obter instância singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Construtor privado (singleton)
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Conectar ao banco de dados
     */
    private function connect() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter conexão
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Executar query
     */
    public function query($sql) {
        try {
            return $this->connection->query($sql);
        } catch (PDOException $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage());
        }
    }
    
    /**
     * Preparar statement
     */
    public function prepare($sql) {
        try {
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            throw new \Exception('Erro ao preparar statement: ' . $e->getMessage());
        }
    }
    
    /**
     * Executar query com parâmetros
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception('Erro ao executar query: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter um registro
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Obter todos os registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Inserir registro
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        return $this->execute($sql, array_values($data));
    }
    
    /**
     * Atualizar registro
     */
    public function update($table, $data, $where) {
        $set = implode(', ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($data)));
        
        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($where)));
        
        $sql = "UPDATE {$table} SET {$set} WHERE {$whereClause}";
        
        $params = array_merge(array_values($data), array_values($where));
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Deletar registro
     */
    public function delete($table, $where) {
        $whereClause = implode(' AND ', array_map(function($key) {
            return "{$key} = ?";
        }, array_keys($where)));
        
        $sql = "DELETE FROM {$table} WHERE {$whereClause}";
        
        return $this->execute($sql, array_values($where));
    }
    
    /**
     * Obter ID do último registro inserido
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Iniciar transação
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar transação
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Reverter transação
     */
    public function rollback() {
        return $this->connection->rollback();
    }
}
