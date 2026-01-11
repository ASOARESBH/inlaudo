<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model Fornecedor
 * 
 * Representa um fornecedor/emitente de NF-e no sistema
 * 
 * @package App\Models
 * @author Sistema ERP INLAUDO
 * @version 2.3.0
 */
class FornecedorModel extends Model
{
    /**
     * Nome da tabela
     */
    protected $table = 'fornecedores';

    /**
     * Campos preenchíveis
     */
    protected $fillable = [
        'cnpj',
        'nome_fantasia',
        'razao_social',
        'email',
        'telefone',
        'endereco',
        'cidade',
        'estado',
        'cep',
        'ativo'
    ];

    /**
     * Campos que não devem ser preenchidos em massa
     */
    protected $guarded = ['id', 'data_criacao', 'data_atualizacao'];

    /**
     * Campos que devem ser convertidos para tipos específicos
     */
    protected $casts = [
        'ativo' => 'bool',
        'data_criacao' => 'datetime',
        'data_atualizacao' => 'datetime'
    ];

    /**
     * Obter notas fiscais do fornecedor
     * 
     * @return array
     */
    public function notasFiscais()
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM notas_fiscais WHERE fornecedor_id = ? ORDER BY data_emissao DESC",
            [$this->id]
        );
    }

    /**
     * Obter fornecedor por CNPJ
     * 
     * @param string $cnpj
     * @return array|null
     */
    public static function porCnpj($cnpj)
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM fornecedores WHERE cnpj = ?",
            [$cnpj]
        );
    }

    /**
     * Verificar se fornecedor existe
     * 
     * @param string $cnpj
     * @return bool
     */
    public static function existePorCnpj($cnpj)
    {
        $db = \App\Core\Database::getInstance();
        $resultado = $db->fetchOne(
            "SELECT id FROM fornecedores WHERE cnpj = ?",
            [$cnpj]
        );
        return !empty($resultado);
    }

    /**
     * Listar fornecedores
     * 
     * @param bool $apenasAtivos
     * @param int $limite
     * @return array
     */
    public static function listar($apenasAtivos = true, $limite = null)
    {
        $db = \App\Core\Database::getInstance();
        
        $where = $apenasAtivos ? 'WHERE ativo = 1' : '';
        $limit = $limite ? " LIMIT $limite" : '';
        
        return $db->fetchAll(
            "SELECT * FROM fornecedores $where ORDER BY nome_fantasia ASC $limit"
        );
    }

    /**
     * Criar ou atualizar fornecedor
     * 
     * @param array $dados
     * @return int ID do fornecedor
     */
    public static function criarOuAtualizar($dados)
    {
        $db = \App\Core\Database::getInstance();

        // Verificar se já existe
        $fornecedor = self::porCnpj($dados['cnpj']);

        if ($fornecedor) {
            // Atualizar
            $db->execute(
                "UPDATE fornecedores SET nome_fantasia = ?, razao_social = ?, email = ?, 
                 telefone = ?, endereco = ?, cidade = ?, estado = ?, cep = ?
                 WHERE id = ?",
                [
                    $dados['nome_fantasia'] ?? $fornecedor['nome_fantasia'],
                    $dados['razao_social'] ?? $fornecedor['razao_social'],
                    $dados['email'] ?? $fornecedor['email'],
                    $dados['telefone'] ?? $fornecedor['telefone'],
                    $dados['endereco'] ?? $fornecedor['endereco'],
                    $dados['cidade'] ?? $fornecedor['cidade'],
                    $dados['estado'] ?? $fornecedor['estado'],
                    $dados['cep'] ?? $fornecedor['cep'],
                    $fornecedor['id']
                ]
            );
            return $fornecedor['id'];
        } else {
            // Criar novo
            $db->execute(
                "INSERT INTO fornecedores (cnpj, nome_fantasia, razao_social, email, 
                 telefone, endereco, cidade, estado, cep, ativo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [
                    $dados['cnpj'],
                    $dados['nome_fantasia'] ?? '',
                    $dados['razao_social'] ?? '',
                    $dados['email'] ?? '',
                    $dados['telefone'] ?? '',
                    $dados['endereco'] ?? '',
                    $dados['cidade'] ?? '',
                    $dados['estado'] ?? '',
                    $dados['cep'] ?? ''
                ]
            );
            return $db->lastInsertId();
        }
    }

    /**
     * Obter estatísticas do fornecedor
     * 
     * @return array
     */
    public function estatisticas()
    {
        $db = \App\Core\Database::getInstance();

        return [
            'total_notas' => $db->fetchOne(
                "SELECT COUNT(*) as total FROM notas_fiscais WHERE fornecedor_id = ?",
                [$this->id]
            )['total'] ?? 0,

            'valor_total' => $db->fetchOne(
                "SELECT SUM(valor_total) as total FROM notas_fiscais WHERE fornecedor_id = ?",
                [$this->id]
            )['total'] ?? 0,

            'ultima_nota' => $db->fetchOne(
                "SELECT data_emissao FROM notas_fiscais WHERE fornecedor_id = ? 
                 ORDER BY data_emissao DESC LIMIT 1",
                [$this->id]
            )['data_emissao'] ?? null
        ];
    }

    /**
     * Validar dados obrigatórios
     * 
     * @return array Erros encontrados
     */
    public function validar()
    {
        $erros = [];

        if (empty($this->cnpj)) {
            $erros[] = 'CNPJ é obrigatório';
        }

        if (empty($this->nome_fantasia) && empty($this->razao_social)) {
            $erros[] = 'Nome fantasia ou razão social é obrigatório';
        }

        return $erros;
    }
}
