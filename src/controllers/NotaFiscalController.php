<?php

namespace App\Controllers;

use App\Models\NotaFiscalModel;
use App\Models\FornecedorModel;
use App\Services\NotaFiscalXmlService;
use App\Core\Database;
use Exception;

/**
 * Controller NotaFiscalController
 * 
 * Controla todas as operações relacionadas a Notas Fiscais
 * 
 * @package App\Controllers
 * @author Sistema ERP INLAUDO
 * @version 2.3.0
 */
class NotaFiscalController
{
    /**
     * Banco de dados
     */
    private $db;

    /**
     * Service de XML
     */
    private $xmlService;

    /**
     * Usuário autenticado
     */
    private $usuarioId;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->xmlService = new NotaFiscalXmlService();
        $this->usuarioId = $_SESSION['usuario_id'] ?? null;

        // Verificar autenticação
        if (!$this->usuarioId) {
            throw new Exception('Usuário não autenticado');
        }
    }

    /**
     * Verificar permissão do usuário
     * 
     * @param string $tipoPermissao
     * @return bool
     */
    private function verificarPermissao($tipoPermissao)
    {
        $resultado = $this->db->fetchOne(
            "SELECT id FROM permissoes_notas_fiscais 
             WHERE usuario_id = ? AND tipo_permissao = ? AND ativo = 1",
            [$this->usuarioId, $tipoPermissao]
        );

        return !empty($resultado);
    }

    /**
     * Listar notas fiscais
     * 
     * @param array $filtros
     * @param int $pagina
     * @return array
     * @throws Exception
     */
    public function listar($filtros = [], $pagina = 1)
    {
        if (!$this->verificarPermissao('visualizar')) {
            throw new Exception('Permissão negada: visualizar notas fiscais');
        }

        return NotaFiscalModel::listar($filtros, $pagina, 20);
    }

    /**
     * Obter nota fiscal por ID
     * 
     * @param int $id
     * @return array|null
     * @throws Exception
     */
    public function obter($id)
    {
        if (!$this->verificarPermissao('visualizar')) {
            throw new Exception('Permissão negada: visualizar notas fiscais');
        }

        return $this->db->fetchOne(
            "SELECT nf.*, f.nome_fantasia FROM notas_fiscais nf
             LEFT JOIN fornecedores f ON nf.fornecedor_id = f.id
             WHERE nf.id = ?",
            [$id]
        );
    }

    /**
     * Importar arquivo XML
     * 
     * @param string $caminhoArquivo
     * @return array Dados importados
     * @throws Exception
     */
    public function importarXml($caminhoArquivo)
    {
        if (!$this->verificarPermissao('importar')) {
            throw new Exception('Permissão negada: importar notas fiscais');
        }

        try {
            // Processar XML
            $dados = $this->xmlService->processar($caminhoArquivo, $this->usuarioId);

            // Criar ou atualizar fornecedor
            $fornecedorId = FornecedorModel::criarOuAtualizar([
                'cnpj' => $dados['cnpj_fornecedor'],
                'nome_fantasia' => $dados['nome_fornecedor'],
                'razao_social' => $dados['nome_fornecedor']
            ]);

            $dados['fornecedor_id'] = $fornecedorId;

            // Copiar arquivo para armazenamento
            $caminhoDestino = $dados['caminho_arquivo'];
            $this->xmlService->copiarArquivo($caminhoArquivo, $caminhoDestino);

            // Salvar no banco de dados
            $this->db->execute(
                "INSERT INTO notas_fiscais (
                    chave_acesso, tipo_nota, fornecedor_id, cnpj_fornecedor, 
                    nome_fornecedor, data_emissao, data_saida_entrada, 
                    valor_total, valor_icms, valor_ipi, valor_pis, valor_cofins,
                    numero_nf, serie_nf, natureza_operacao, tipo_documento,
                    status_nfe, protocolo_autorizacao, caminho_arquivo,
                    caminho_arquivo_normalizado, hash_xml, tamanho_arquivo, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $dados['chave_acesso'],
                    $dados['tipo_nota'],
                    $dados['fornecedor_id'],
                    $dados['cnpj_fornecedor'],
                    $dados['nome_fornecedor'],
                    $dados['data_emissao'],
                    $dados['data_saida_entrada'],
                    $dados['valor_total'],
                    $dados['valor_icms'],
                    $dados['valor_ipi'],
                    $dados['valor_pis'],
                    $dados['valor_cofins'],
                    $dados['numero_nf'],
                    $dados['serie_nf'],
                    $dados['natureza_operacao'],
                    $dados['tipo_documento'],
                    $dados['status_nfe'],
                    $dados['protocolo_autorizacao'],
                    $dados['caminho_arquivo'],
                    $dados['caminho_arquivo_normalizado'],
                    $dados['hash_xml'],
                    $dados['tamanho_arquivo'],
                    $this->usuarioId
                ]
            );

            $notaFiscalId = $this->db->lastInsertId();

            // Extrair e salvar itens
            $xml = simplexml_load_file($caminhoArquivo);
            $itens = $this->xmlService->extrairItens($xml);

            foreach ($itens as $item) {
                $this->db->execute(
                    "INSERT INTO notas_fiscais_itens (
                        nota_fiscal_id, numero_item, codigo_produto, descricao_produto,
                        quantidade, unidade_medida, valor_unitario, valor_total, valor_desconto
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $notaFiscalId,
                        $item['numero_item'],
                        $item['codigo_produto'],
                        $item['descricao_produto'],
                        $item['quantidade'],
                        $item['unidade_medida'],
                        $item['valor_unitario'],
                        $item['valor_total'],
                        $item['valor_desconto']
                    ]
                );
            }

            // Registrar log de importação
            $this->registrarLog('sucesso', basename($caminhoArquivo), $dados['chave_acesso'], null);

            return [
                'sucesso' => true,
                'mensagem' => 'Nota fiscal importada com sucesso',
                'nota_fiscal_id' => $notaFiscalId,
                'chave_acesso' => $dados['chave_acesso']
            ];

        } catch (Exception $e) {
            // Registrar log de erro
            $this->registrarLog('erro', basename($caminhoArquivo), '', $e->getMessage());

            throw $e;
        }
    }

    /**
     * Importar múltiplos arquivos
     * 
     * @param array $caminhos
     * @return array
     */
    public function importarMultiplos($caminhos)
    {
        $resultados = [
            'sucesso' => [],
            'erro' => [],
            'duplicado' => []
        ];

        foreach ($caminhos as $caminho) {
            try {
                $resultado = $this->importarXml($caminho);
                $resultados['sucesso'][] = $resultado;
            } catch (Exception $e) {
                $mensagem = $e->getMessage();

                if (strpos($mensagem, 'já foi importada') !== false) {
                    $resultados['duplicado'][] = [
                        'arquivo' => basename($caminho),
                        'erro' => $mensagem
                    ];
                } else {
                    $resultados['erro'][] = [
                        'arquivo' => basename($caminho),
                        'erro' => $mensagem
                    ];
                }
            }
        }

        return $resultados;
    }

    /**
     * Deletar nota fiscal
     * 
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function deletar($id)
    {
        if (!$this->verificarPermissao('deletar')) {
            throw new Exception('Permissão negada: deletar notas fiscais');
        }

        $notaFiscal = $this->obter($id);

        if (!$notaFiscal) {
            throw new Exception('Nota fiscal não encontrada');
        }

        // Deletar arquivo
        if (file_exists($notaFiscal['caminho_arquivo'])) {
            @unlink($notaFiscal['caminho_arquivo']);
        }

        // Deletar do banco
        NotaFiscalModel::deletar($id);

        return true;
    }

    /**
     * Obter estatísticas
     * 
     * @return array
     * @throws Exception
     */
    public function estatisticas()
    {
        if (!$this->verificarPermissao('visualizar')) {
            throw new Exception('Permissão negada: visualizar notas fiscais');
        }

        return NotaFiscalModel::estatisticas();
    }

    /**
     * Obter fornecedores
     * 
     * @return array
     */
    public function obterFornecedores()
    {
        return FornecedorModel::listar(true);
    }

    /**
     * Registrar log de importação
     * 
     * @param string $status
     * @param string $nomeArquivo
     * @param string $chaveAcesso
     * @param string|null $mensagemErro
     */
    private function registrarLog($status, $nomeArquivo, $chaveAcesso, $mensagemErro = null)
    {
        $this->db->execute(
            "INSERT INTO notas_fiscais_log_importacao (
                usuario_id, nome_arquivo, status, chave_acesso, mensagem_erro
            ) VALUES (?, ?, ?, ?, ?)",
            [
                $this->usuarioId,
                $nomeArquivo,
                $status,
                $chaveAcesso,
                $mensagemErro
            ]
        );
    }

    /**
     * Obter histórico de importação
     * 
     * @param int $limite
     * @return array
     */
    public function obterHistorico($limite = 20)
    {
        return $this->db->fetchAll(
            "SELECT * FROM notas_fiscais_log_importacao 
             WHERE usuario_id = ? 
             ORDER BY data_importacao DESC 
             LIMIT ?",
            [$this->usuarioId, $limite]
        );
    }

    /**
     * Download de arquivo XML
     * 
     * @param int $id
     * @return string Caminho do arquivo
     * @throws Exception
     */
    public function download($id)
    {
        if (!$this->verificarPermissao('exportar')) {
            throw new Exception('Permissão negada: exportar notas fiscais');
        }

        $notaFiscal = $this->obter($id);

        if (!$notaFiscal) {
            throw new Exception('Nota fiscal não encontrada');
        }

        if (!file_exists($notaFiscal['caminho_arquivo'])) {
            throw new Exception('Arquivo não encontrado no servidor');
        }

        return $notaFiscal['caminho_arquivo'];
    }
}
