<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model NotaFiscal
 * 
 * Representa uma Nota Fiscal (NF-e ou NFC-e) no sistema
 * 
 * @package App\Models
 * @author Sistema ERP INLAUDO
 * @version 2.3.0
 */
class NotaFiscalModel extends Model
{
    /**
     * Nome da tabela
     */
    protected $table = 'notas_fiscais';

    /**
     * Campos preenchíveis
     */
    protected $fillable = [
        'chave_acesso',
        'tipo_nota',
        'fornecedor_id',
        'cnpj_fornecedor',
        'nome_fornecedor',
        'data_emissao',
        'data_saida_entrada',
        'valor_total',
        'valor_icms',
        'valor_ipi',
        'valor_pis',
        'valor_cofins',
        'numero_nf',
        'serie_nf',
        'natureza_operacao',
        'tipo_documento',
        'status_nfe',
        'protocolo_autorizacao',
        'caminho_arquivo',
        'caminho_arquivo_normalizado',
        'hash_xml',
        'tamanho_arquivo',
        'usuario_id'
    ];

    /**
     * Campos que não devem ser preenchidos em massa
     */
    protected $guarded = ['id', 'data_importacao', 'data_atualizacao'];

    /**
     * Campos que devem ser convertidos para tipos específicos
     */
    protected $casts = [
        'valor_total' => 'float',
        'valor_icms' => 'float',
        'valor_ipi' => 'float',
        'valor_pis' => 'float',
        'valor_cofins' => 'float',
        'tamanho_arquivo' => 'int',
        'data_emissao' => 'date',
        'data_saida_entrada' => 'date',
        'data_importacao' => 'datetime',
        'data_atualizacao' => 'datetime'
    ];

    /**
     * Obter fornecedor associado
     * 
     * @return FornecedorModel|null
     */
    public function fornecedor()
    {
        return FornecedorModel::find($this->fornecedor_id);
    }

    /**
     * Obter usuário que importou
     * 
     * @return UsuarioModel|null
     */
    public function usuario()
    {
        return UsuarioModel::find($this->usuario_id);
    }

    /**
     * Obter itens da nota fiscal
     * 
     * @return array
     */
    public function itens()
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM notas_fiscais_itens WHERE nota_fiscal_id = ? ORDER BY numero_item ASC",
            [$this->id]
        );
    }

    /**
     * Verificar se nota fiscal já existe (por chave de acesso)
     * 
     * @param string $chaveAcesso
     * @return bool
     */
    public static function existePorChave($chaveAcesso)
    {
        $db = \App\Core\Database::getInstance();
        $resultado = $db->fetchOne(
            "SELECT id FROM notas_fiscais WHERE chave_acesso = ?",
            [$chaveAcesso]
        );
        return !empty($resultado);
    }

    /**
     * Obter nota fiscal por chave de acesso
     * 
     * @param string $chaveAcesso
     * @return array|null
     */
    public static function porChave($chaveAcesso)
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM notas_fiscais WHERE chave_acesso = ?",
            [$chaveAcesso]
        );
    }

    /**
     * Listar notas fiscais com filtros
     * 
     * @param array $filtros
     * @param int $pagina
     * @param int $porPagina
     * @return array
     */
    public static function listar($filtros = [], $pagina = 1, $porPagina = 20)
    {
        $db = \App\Core\Database::getInstance();
        
        $where = [];
        $params = [];

        // Filtro por fornecedor
        if (!empty($filtros['fornecedor_id'])) {
            $where[] = "nf.fornecedor_id = ?";
            $params[] = $filtros['fornecedor_id'];
        }

        // Filtro por tipo de nota
        if (!empty($filtros['tipo_nota'])) {
            $where[] = "nf.tipo_nota = ?";
            $params[] = $filtros['tipo_nota'];
        }

        // Filtro por status
        if (!empty($filtros['status_nfe'])) {
            $where[] = "nf.status_nfe = ?";
            $params[] = $filtros['status_nfe'];
        }

        // Filtro por período (data inicial)
        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(nf.data_emissao) >= ?";
            $params[] = $filtros['data_inicio'];
        }

        // Filtro por período (data final)
        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(nf.data_emissao) <= ?";
            $params[] = $filtros['data_fim'];
        }

        // Filtro por valor mínimo
        if (!empty($filtros['valor_minimo'])) {
            $where[] = "nf.valor_total >= ?";
            $params[] = $filtros['valor_minimo'];
        }

        // Filtro por valor máximo
        if (!empty($filtros['valor_maximo'])) {
            $where[] = "nf.valor_total <= ?";
            $params[] = $filtros['valor_maximo'];
        }

        // Filtro por busca (chave, nome fornecedor)
        if (!empty($filtros['busca'])) {
            $where[] = "(nf.chave_acesso LIKE ? OR nf.nome_fornecedor LIKE ?)";
            $params[] = "%{$filtros['busca']}%";
            $params[] = "%{$filtros['busca']}%";
        }

        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Obter total
        $sql_total = "SELECT COUNT(*) as total FROM notas_fiscais nf $where_clause";
        $resultado_total = $db->fetchOne($sql_total, $params);
        $total = $resultado_total['total'] ?? 0;

        // Obter registros
        $offset = ($pagina - 1) * $porPagina;
        $sql = "SELECT nf.*, f.nome_fantasia 
                FROM notas_fiscais nf
                LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id
                $where_clause
                ORDER BY nf.data_emissao DESC, nf.id DESC
                LIMIT ? OFFSET ?";

        $params[] = $porPagina;
        $params[] = $offset;

        $registros = $db->fetchAll($sql, $params);

        return [
            'dados' => $registros,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => ceil($total / $porPagina)
        ];
    }

    /**
     * Obter estatísticas
     * 
     * @return array
     */
    public static function estatisticas()
    {
        $db = \App\Core\Database::getInstance();

        return [
            'total_notas' => $db->fetchOne(
                "SELECT COUNT(*) as total FROM notas_fiscais"
            )['total'] ?? 0,

            'total_nfe' => $db->fetchOne(
                "SELECT COUNT(*) as total FROM notas_fiscais WHERE tipo_nota = 'nfe'"
            )['total'] ?? 0,

            'total_nfce' => $db->fetchOne(
                "SELECT COUNT(*) as total FROM notas_fiscais WHERE tipo_nota = 'nfce'"
            )['total'] ?? 0,

            'valor_total' => $db->fetchOne(
                "SELECT SUM(valor_total) as total FROM notas_fiscais"
            )['total'] ?? 0,

            'total_fornecedores' => $db->fetchOne(
                "SELECT COUNT(DISTINCT fornecedor_id) as total FROM notas_fiscais"
            )['total'] ?? 0,

            'notas_mes_atual' => $db->fetchOne(
                "SELECT COUNT(*) as total FROM notas_fiscais 
                 WHERE YEAR(data_emissao) = YEAR(NOW()) 
                 AND MONTH(data_emissao) = MONTH(NOW())"
            )['total'] ?? 0
        ];
    }

    /**
     * Obter notas fiscais por fornecedor
     * 
     * @param int $fornecedorId
     * @param int $limite
     * @return array
     */
    public static function porFornecedor($fornecedorId, $limite = 10)
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchAll(
            "SELECT * FROM notas_fiscais 
             WHERE fornecedor_id = ? 
             ORDER BY data_emissao DESC 
             LIMIT ?",
            [$fornecedorId, $limite]
        );
    }

    /**
     * Obter notas fiscais recentes
     * 
     * @param int $dias
     * @param int $limite
     * @return array
     */
    public static function recentes($dias = 30, $limite = 10)
    {
        $db = \App\Core\Database::getInstance();
        return $db->fetchAll(
            "SELECT nf.*, f.nome_fantasia 
             FROM notas_fiscais nf
             LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id
             WHERE DATE(nf.data_importacao) >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY nf.data_importacao DESC
             LIMIT ?",
            [$dias, $limite]
        );
    }

    /**
     * Deletar nota fiscal e seus itens
     * 
     * @param int $id
     * @return bool
     */
    public static function deletar($id)
    {
        $db = \App\Core\Database::getInstance();
        
        try {
            // Iniciar transação
            $db->beginTransaction();

            // Deletar itens
            $db->execute(
                "DELETE FROM notas_fiscais_itens WHERE nota_fiscal_id = ?",
                [$id]
            );

            // Deletar nota fiscal
            $db->execute(
                "DELETE FROM notas_fiscais WHERE id = ?",
                [$id]
            );

            // Confirmar transação
            $db->commit();

            return true;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Validar dados obrigatórios
     * 
     * @return array Erros encontrados
     */
    public function validar()
    {
        $erros = [];

        if (empty($this->chave_acesso)) {
            $erros[] = 'Chave de acesso é obrigatória';
        }

        if (empty($this->fornecedor_id)) {
            $erros[] = 'Fornecedor é obrigatório';
        }

        if (empty($this->data_emissao)) {
            $erros[] = 'Data de emissão é obrigatória';
        }

        if ($this->valor_total < 0) {
            $erros[] = 'Valor total não pode ser negativo';
        }

        return $erros;
    }
}
