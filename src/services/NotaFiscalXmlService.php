<?php

namespace App\Services;

use Exception;
use SimpleXMLElement;

/**
 * Service NotaFiscalXmlService
 * 
 * Responsável pela leitura, validação e extração de dados de arquivos XML de NF-e/NFC-e
 * 
 * @package App\Services
 * @author Sistema ERP INLAUDO
 * @version 2.3.0
 */
class NotaFiscalXmlService
{
    /**
     * Namespaces do XML de NF-e
     */
    private const NAMESPACES = [
        'nfe' => 'http://www.portalfiscal.inf.br/nfe',
        'nfce' => 'http://www.portalfiscal.inf.br/nfce'
    ];

    /**
     * Caminho base para armazenamento
     */
    private $caminhoBase;

    /**
     * Configurações do módulo
     */
    private $config;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->caminhoBase = BASE_PATH . '/storage/notas_fiscais';
        $this->config = $this->carregarConfig();
    }

    /**
     * Carregar configurações do banco de dados
     * 
     * @return array
     */
    private function carregarConfig()
    {
        $db = \App\Core\Database::getInstance();
        $configs = $db->fetchAll("SELECT chave, valor FROM notas_fiscais_config");
        
        $config = [];
        foreach ($configs as $item) {
            $config[$item['chave']] = $item['valor'];
        }

        return $config;
    }

    /**
     * Processar arquivo XML enviado
     * 
     * @param string $caminhoArquivo Caminho do arquivo XML
     * @param int $usuarioId ID do usuário que está importando
     * @return array Dados extraídos do XML
     * @throws Exception
     */
    public function processar($caminhoArquivo, $usuarioId)
    {
        // Validar arquivo
        if (!file_exists($caminhoArquivo)) {
            throw new Exception('Arquivo não encontrado: ' . $caminhoArquivo);
        }

        // Validar tamanho
        $tamanhoArquivo = filesize($caminhoArquivo);
        $tamanhoMaximo = (int)($this->config['tamanho_maximo_arquivo'] ?? 10485760);

        if ($tamanhoArquivo > $tamanhoMaximo) {
            throw new Exception('Arquivo excede o tamanho máximo de ' . ($tamanhoMaximo / 1024 / 1024) . 'MB');
        }

        // Ler e validar XML
        $xml = $this->lerXml($caminhoArquivo);
        
        // Extrair dados
        $dados = $this->extrairDados($xml, $caminhoArquivo, $tamanhoArquivo, $usuarioId);

        // Validar duplicidade
        $this->validarDuplicidade($dados['chave_acesso']);

        return $dados;
    }

    /**
     * Ler e validar arquivo XML
     * 
     * @param string $caminhoArquivo
     * @return SimpleXMLElement
     * @throws Exception
     */
    private function lerXml($caminhoArquivo)
    {
        // Desabilitar erros de XML
        libxml_use_internal_errors(true);

        // Ler arquivo
        $conteudo = file_get_contents($caminhoArquivo);
        
        if ($conteudo === false) {
            throw new Exception('Erro ao ler arquivo XML');
        }

        // Fazer parse
        $xml = simplexml_load_string($conteudo);

        if ($xml === false) {
            $erros = libxml_get_errors();
            $mensagem = 'XML inválido: ';
            foreach ($erros as $erro) {
                $mensagem .= $erro->message . ' ';
            }
            libxml_clear_errors();
            throw new Exception($mensagem);
        }

        libxml_clear_errors();

        return $xml;
    }

    /**
     * Extrair dados do XML
     * 
     * @param SimpleXMLElement $xml
     * @param string $caminhoArquivo
     * @param int $tamanhoArquivo
     * @param int $usuarioId
     * @return array
     * @throws Exception
     */
    private function extrairDados($xml, $caminhoArquivo, $tamanhoArquivo, $usuarioId)
    {
        try {
            // Registrar namespaces
            $namespaces = $xml->getNamespaces(true);
            
            // Determinar tipo de nota
            $tipoNota = $this->determinarTipo($xml);

            // Extrair informações da nota fiscal
            $infNfe = $xml->xpath('//nfe:infNFe | //nfce:infNFe', $namespaces);
            
            if (empty($infNfe)) {
                throw new Exception('Estrutura de NF-e inválida: infNFe não encontrado');
            }

            $infNfe = $infNfe[0];
            $chaveAcesso = (string)$infNfe->attributes()['Id'];
            $chaveAcesso = str_replace('NFe', '', $chaveAcesso);

            // Extrair dados do emitente
            $emit = $infNfe->xpath('.//nfe:emit | .//nfce:emit', $namespaces);
            if (empty($emit)) {
                throw new Exception('Emitente não encontrado no XML');
            }
            $emit = $emit[0];

            $cnpjEmitente = (string)$emit->xpath('.//nfe:CNPJ | .//nfce:CNPJ', $namespaces)[0];
            $nomeEmitente = (string)$emit->xpath('.//nfe:xNome | .//nfce:xNome', $namespaces)[0];

            // Extrair datas
            $ide = $infNfe->xpath('.//nfe:ide | .//nfce:ide', $namespaces)[0];
            $dataEmissao = (string)$ide->xpath('.//nfe:dEmi | .//nfce:dEmi', $namespaces)[0];
            $dataSaidaEntrada = (string)$ide->xpath('.//nfe:dSaiEnt | .//nfce:dSaiEnt', $namespaces)[0] ?: $dataEmissao;

            // Extrair valores
            $total = $infNfe->xpath('.//nfe:total | .//nfce:total', $namespaces)[0];
            $icmsTot = $total->xpath('.//nfe:ICMSTot | .//nfce:ICMSTot', $namespaces)[0];

            $valorTotal = (float)str_replace(',', '.', (string)$icmsTot->xpath('.//nfe:vNF | .//nfce:vNF', $namespaces)[0]);
            $valorIcms = (float)str_replace(',', '.', (string)$icmsTot->xpath('.//nfe:vICMS | .//nfce:vICMS', $namespaces)[0] ?: 0);
            $valorIpi = (float)str_replace(',', '.', (string)$icmsTot->xpath('.//nfe:vIPI | .//nfce:vIPI', $namespaces)[0] ?: 0);
            $valorPis = (float)str_replace(',', '.', (string)$icmsTot->xpath('.//nfe:vPIS | .//nfce:vPIS', $namespaces)[0] ?: 0);
            $valorCofins = (float)str_replace(',', '.', (string)$icmsTot->xpath('.//nfe:vCOFINS | .//nfce:vCOFINS', $namespaces)[0] ?: 0);

            // Extrair número e série
            $numeroNf = (string)$ide->xpath('.//nfe:cNF | .//nfce:cNF', $namespaces)[0];
            $serieNf = (string)$ide->xpath('.//nfe:serie | .//nfce:serie', $namespaces)[0];

            // Extrair natureza da operação
            $naturezaOperacao = (string)$ide->xpath('.//nfe:natOp | .//nfce:natOp', $namespaces)[0] ?: '';

            // Extrair status da NF-e
            $protNFe = $infNfe->xpath('.//nfe:protNFe | .//nfce:protNFe', $namespaces);
            $statusNfe = 'pendente';
            $protocoloAutorizacao = '';

            if (!empty($protNFe)) {
                $protNFe = $protNFe[0];
                $infProt = $protNFe->xpath('.//nfe:infProt | .//nfce:infProt', $namespaces)[0];
                $cStat = (string)$infProt->xpath('.//nfe:cStat | .//nfce:cStat', $namespaces)[0];
                $protocoloAutorizacao = (string)$infProt->xpath('.//nfe:nProt | .//nfce:nProt', $namespaces)[0];

                // Mapear status
                if ($cStat == '100') {
                    $statusNfe = 'autorizada';
                } elseif ($cStat == '101' || $cStat == '135') {
                    $statusNfe = 'cancelada';
                } elseif ($cStat == '110') {
                    $statusNfe = 'denegada';
                }
            }

            // Calcular hash do arquivo
            $hashXml = hash_file('sha256', $caminhoArquivo);

            // Determinar tipo de documento
            $tipoDocumento = $this->determinarTipoDocumento($infNfe, $namespaces);

            // Organizar caminho do arquivo
            $caminhoArmazenado = $this->organizarCaminho(
                $cnpjEmitente,
                $nomeEmitente,
                $dataEmissao,
                basename($caminhoArquivo)
            );

            return [
                'chave_acesso' => $chaveAcesso,
                'tipo_nota' => $tipoNota,
                'cnpj_fornecedor' => $cnpjEmitente,
                'nome_fornecedor' => $nomeEmitente,
                'data_emissao' => $dataEmissao,
                'data_saida_entrada' => $dataSaidaEntrada,
                'valor_total' => $valorTotal,
                'valor_icms' => $valorIcms,
                'valor_ipi' => $valorIpi,
                'valor_pis' => $valorPis,
                'valor_cofins' => $valorCofins,
                'numero_nf' => $numeroNf,
                'serie_nf' => $serieNf,
                'natureza_operacao' => $naturezaOperacao,
                'tipo_documento' => $tipoDocumento,
                'status_nfe' => $statusNfe,
                'protocolo_autorizacao' => $protocoloAutorizacao,
                'caminho_arquivo' => $caminhoArmazenado,
                'caminho_arquivo_normalizado' => str_replace('\\', '/', $caminhoArmazenado),
                'hash_xml' => $hashXml,
                'tamanho_arquivo' => $tamanhoArquivo,
                'usuario_id' => $usuarioId,
                'xml_original' => $caminhoArquivo
            ];

        } catch (Exception $e) {
            throw new Exception('Erro ao extrair dados do XML: ' . $e->getMessage());
        }
    }

    /**
     * Determinar tipo de nota (NF-e ou NFC-e)
     * 
     * @param SimpleXMLElement $xml
     * @return string
     */
    private function determinarTipo($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        
        // Verificar namespace de NFC-e
        if (isset($namespaces['nfce']) || strpos((string)$xml, 'nfce') !== false) {
            return 'nfce';
        }

        return 'nfe';
    }

    /**
     * Determinar tipo de documento
     * 
     * @param SimpleXMLElement $infNfe
     * @param array $namespaces
     * @return string
     */
    private function determinarTipoDocumento($infNfe, $namespaces)
    {
        $det = $infNfe->xpath('.//nfe:det | .//nfce:det', $namespaces);
        
        if (empty($det)) {
            return 'misto';
        }

        $temProduto = false;
        $temServico = false;

        foreach ($det as $item) {
            $prod = $item->xpath('.//nfe:prod | .//nfce:prod', $namespaces);
            $serv = $item->xpath('.//nfe:serv | .//nfce:serv', $namespaces);

            if (!empty($prod)) $temProduto = true;
            if (!empty($serv)) $temServico = true;
        }

        if ($temProduto && $temServico) {
            return 'misto';
        } elseif ($temServico) {
            return 'servico';
        }

        return 'produto';
    }

    /**
     * Validar duplicidade de nota fiscal
     * 
     * @param string $chaveAcesso
     * @throws Exception
     */
    private function validarDuplicidade($chaveAcesso)
    {
        $permitirDuplicatas = $this->config['permitir_duplicatas'] === 'true';

        if (!$permitirDuplicatas && \App\Models\NotaFiscalModel::existePorChave($chaveAcesso)) {
            throw new Exception('Nota fiscal com chave ' . $chaveAcesso . ' já foi importada');
        }
    }

    /**
     * Organizar caminho do arquivo
     * 
     * @param string $cnpj
     * @param string $nomeEmitente
     * @param string $dataEmissao
     * @param string $nomeArquivo
     * @return string
     */
    private function organizarCaminho($cnpj, $nomeEmitente, $dataEmissao, $nomeArquivo)
    {
        $organizarPorFornecedor = $this->config['organizar_por_fornecedor'] === 'true';

        if ($organizarPorFornecedor) {
            // Organizar por fornecedor/ano/mês
            $pasta = $this->caminhoBase . '/' . $cnpj . '_' . $this->sanitizarNome($nomeEmitente);
        } else {
            // Organizar apenas por ano/mês
            $pasta = $this->caminhoBase;
        }

        // Adicionar ano e mês
        $ano = date('Y', strtotime($dataEmissao));
        $mes = date('m', strtotime($dataEmissao));
        $pasta .= '/' . $ano . '/' . $mes;

        // Criar pasta se não existir
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0755, true);
        }

        return $pasta . '/' . $nomeArquivo;
    }

    /**
     * Sanitizar nome de arquivo/pasta
     * 
     * @param string $nome
     * @return string
     */
    private function sanitizarNome($nome)
    {
        // Remover caracteres especiais
        $nome = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nome);
        // Limitar tamanho
        return substr($nome, 0, 50);
    }

    /**
     * Extrair itens da nota fiscal
     * 
     * @param SimpleXMLElement $xml
     * @return array
     */
    public function extrairItens($xml)
    {
        $namespaces = $xml->getNamespaces(true);
        $itens = [];

        $det = $xml->xpath('//nfe:det | //nfce:det', $namespaces);

        foreach ($det as $item) {
            $numero = (int)$item->attributes()['nItem'];
            $prod = $item->xpath('.//nfe:prod | .//nfce:prod', $namespaces)[0];

            $itens[] = [
                'numero_item' => $numero,
                'codigo_produto' => (string)$prod->xpath('.//nfe:cProd | .//nfce:cProd', $namespaces)[0],
                'descricao_produto' => (string)$prod->xpath('.//nfe:xProd | .//nfce:xProd', $namespaces)[0],
                'quantidade' => (float)str_replace(',', '.', (string)$prod->xpath('.//nfe:qCom | .//nfce:qCom', $namespaces)[0]),
                'unidade_medida' => (string)$prod->xpath('.//nfe:uCom | .//nfce:uCom', $namespaces)[0],
                'valor_unitario' => (float)str_replace(',', '.', (string)$prod->xpath('.//nfe:vUnCom | .//nfce:vUnCom', $namespaces)[0]),
                'valor_total' => (float)str_replace(',', '.', (string)$prod->xpath('.//nfe:vItem | .//nfce:vItem', $namespaces)[0]),
                'valor_desconto' => (float)str_replace(',', '.', (string)$prod->xpath('.//nfe:vDesc | .//nfce:vDesc', $namespaces)[0] ?: 0)
            ];
        }

        return $itens;
    }

    /**
     * Copiar arquivo para local de armazenamento
     * 
     * @param string $origem
     * @param string $destino
     * @return bool
     * @throws Exception
     */
    public function copiarArquivo($origem, $destino)
    {
        if (!copy($origem, $destino)) {
            throw new Exception('Erro ao copiar arquivo XML');
        }

        return true;
    }
}
