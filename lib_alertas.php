<?php
/**
 * Biblioteca de Alertas de Contas Vencidas
 * ERP INLAUDO - Sistema de Alertas em Popup
 * 
 * Responsável por:
 * - Gerar alertas de contas vencidas
 * - Obter alertas não visualizados
 * - Marcar alertas como visualizados
 * - Gerenciar ações de alerta
 */

class AlertasContasVencidas {
    
    /**
     * Gerar alertas de contas vencidas
     * 
     * @return bool Sucesso
     */
    public static function gerarAlertas() {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("CALL sp_gerar_alertas_contas_vencidas()");
            $resultado = $stmt->execute();
            
            LogIntegracao::registrar(
                'alertas_sistema',
                'gerar_alertas',
                'sucesso',
                'Alertas de contas vencidas gerados com sucesso',
                null,
                null,
                200,
                0
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            LogIntegracao::registrar(
                'alertas_sistema',
                'gerar_alertas',
                'erro',
                'Erro ao gerar alertas: ' . $e->getMessage(),
                null,
                null,
                500,
                0
            );
            
            return false;
        }
    }
    
    /**
     * Obter alertas não visualizados do usuário
     * 
     * @param int $usuario_id ID do usuário
     * @return array Lista de alertas
     */
    public static function obterAlertas($usuario_id) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("CALL sp_obter_alertas_nao_visualizados(?)");
            $stmt->execute([$usuario_id]);
            
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $alertas ?: [];
            
        } catch (Exception $e) {
            LogIntegracao::registrar(
                'alertas_sistema',
                'obter_alertas',
                'erro',
                'Erro ao obter alertas: ' . $e->getMessage(),
                json_encode(['usuario_id' => $usuario_id]),
                null,
                500,
                0
            );
            
            return [];
        }
    }
    
    /**
     * Obter contagem de alertas não visualizados
     * 
     * @param int $usuario_id ID do usuário
     * @return int Quantidade de alertas
     */
    public static function contarAlertas($usuario_id) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM alertas_contas_vencidas
                WHERE usuario_id = ? AND visualizado = 0
            ");
            
            $stmt->execute([$usuario_id]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $resultado['total'] ?? 0;
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Marcar alerta como visualizado
     * 
     * @param int $alerta_id ID do alerta
     * @param string $acao Ação tomada (ver, cancelar, ignorar)
     * @return bool Sucesso
     */
    public static function marcarVisualizado($alerta_id, $acao = 'ver') {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("CALL sp_marcar_alerta_visualizado(?, ?)");
            $resultado = $stmt->execute([$alerta_id, $acao]);
            
            LogIntegracao::registrar(
                'alertas_sistema',
                'marcar_visualizado',
                'sucesso',
                'Alerta marcado como visualizado. Ação: ' . $acao,
                json_encode(['alerta_id' => $alerta_id, 'acao' => $acao]),
                null,
                200,
                0
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            LogIntegracao::registrar(
                'alertas_sistema',
                'marcar_visualizado',
                'erro',
                'Erro ao marcar alerta: ' . $e->getMessage(),
                json_encode(['alerta_id' => $alerta_id]),
                null,
                500,
                0
            );
            
            return false;
        }
    }
    
    /**
     * Obter detalhes de um alerta
     * 
     * @param int $alerta_id ID do alerta
     * @return array Dados do alerta
     */
    public static function obterDetalhes($alerta_id) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                SELECT 
                    acv.*,
                    cr.descricao as conta_descricao,
                    cr.data_vencimento,
                    cr.status as conta_status,
                    c.nome as cliente_nome,
                    c.cnpj_cpf,
                    c.email as cliente_email,
                    c.telefone
                FROM alertas_contas_vencidas acv
                INNER JOIN contas_receber cr ON cr.id = acv.conta_receber_id
                INNER JOIN clientes c ON c.id = cr.cliente_id
                WHERE acv.id = ?
            ");
            
            $stmt->execute([$alerta_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter alertas críticos (vencidos e vencendo hoje)
     * 
     * @param int $usuario_id ID do usuário
     * @return array Lista de alertas críticos
     */
    public static function obterAlertasCriticos($usuario_id) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                SELECT 
                    acv.id,
                    acv.conta_receber_id,
                    acv.tipo_alerta,
                    acv.titulo,
                    acv.valor,
                    acv.dias_vencido,
                    c.nome as cliente_nome,
                    cr.data_vencimento
                FROM alertas_contas_vencidas acv
                INNER JOIN contas_receber cr ON cr.id = acv.conta_receber_id
                INNER JOIN clientes c ON c.id = cr.cliente_id
                WHERE acv.usuario_id = ?
                AND acv.visualizado = 0
                AND (
                    acv.tipo_alerta = 'vencido'
                    OR acv.tipo_alerta = 'vencendo_hoje'
                )
                ORDER BY acv.valor DESC, acv.dias_vencido DESC
                LIMIT 10
            ");
            
            $stmt->execute([$usuario_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obter resumo de alertas
     * 
     * @param int $usuario_id ID do usuário
     * @return array Resumo com contagens
     */
    public static function obterResumo($usuario_id) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN tipo_alerta = 'vencido' THEN 1 ELSE 0 END) as vencidos,
                    SUM(CASE WHEN tipo_alerta = 'vencendo_hoje' THEN 1 ELSE 0 END) as vencendo_hoje,
                    SUM(CASE WHEN tipo_alerta = 'vencendo_amanha' THEN 1 ELSE 0 END) as vencendo_amanha,
                    SUM(CASE WHEN tipo_alerta = 'vencendo_semana' THEN 1 ELSE 0 END) as vencendo_semana,
                    SUM(valor) as valor_total
                FROM alertas_contas_vencidas
                WHERE usuario_id = ? AND visualizado = 0
            ");
            
            $stmt->execute([$usuario_id]);
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => $resultado['total'] ?? 0,
                'vencidos' => $resultado['vencidos'] ?? 0,
                'vencendo_hoje' => $resultado['vencendo_hoje'] ?? 0,
                'vencendo_amanha' => $resultado['vencendo_amanha'] ?? 0,
                'vencendo_semana' => $resultado['vencendo_semana'] ?? 0,
                'valor_total' => $resultado['valor_total'] ?? 0
            ];
            
        } catch (Exception $e) {
            return [
                'total' => 0,
                'vencidos' => 0,
                'vencendo_hoje' => 0,
                'vencendo_amanha' => 0,
                'vencendo_semana' => 0,
                'valor_total' => 0
            ];
        }
    }
    
    /**
     * Limpar alertas antigos
     * 
     * @param int $dias Manter últimos N dias
     * @return int Registros deletados
     */
    public static function limparAntigos($dias = 30) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("CALL sp_limpar_alertas_antigos(?)");
            $stmt->execute([$dias]);
            
            LogIntegracao::registrar(
                'alertas_sistema',
                'limpar_antigos',
                'sucesso',
                'Alertas antigos removidos',
                json_encode(['dias' => $dias]),
                null,
                200,
                0
            );
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter configuração de alertas
     * 
     * @param string $chave Chave da configuração
     * @param mixed $padrao Valor padrão
     * @return mixed Valor da configuração
     */
    public static function obterConfig($chave, $padrao = null) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                SELECT valor, tipo
                FROM config_alertas
                WHERE chave = ?
            ");
            
            $stmt->execute([$chave]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                return $padrao;
            }
            
            // Converter tipo
            switch ($config['tipo']) {
                case 'boolean':
                    return (bool)$config['valor'];
                case 'integer':
                    return (int)$config['valor'];
                case 'json':
                    return json_decode($config['valor'], true);
                default:
                    return $config['valor'];
            }
            
        } catch (Exception $e) {
            return $padrao;
        }
    }
    
    /**
     * Atualizar configuração de alertas
     * 
     * @param string $chave Chave da configuração
     * @param mixed $valor Novo valor
     * @return bool Sucesso
     */
    public static function atualizarConfig($chave, $valor) {
        try {
            $conn = getConnection();
            
            $stmt = $conn->prepare("
                UPDATE config_alertas
                SET valor = ?
                WHERE chave = ?
            ");
            
            return $stmt->execute([$valor, $chave]);
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Gerar HTML do alerta para popup
     * 
     * @param array $alerta Dados do alerta
     * @return string HTML do alerta
     */
    public static function gerarHTMLAlerta($alerta) {
        $icone = self::obterIconeAlerta($alerta['tipo_alerta']);
        $classe = self::obterClasseAlerta($alerta['tipo_alerta']);
        $valor = formatMoeda($alerta['valor']);
        
        $html = <<<HTML
<div class="alerta-item {$classe}" data-alerta-id="{$alerta['id']}">
    <div class="alerta-header">
        <span class="alerta-icone">{$icone}</span>
        <h4 class="alerta-titulo">{$alerta['titulo']}</h4>
        <button class="btn-fechar-alerta" onclick="fecharAlerta({$alerta['id']})">×</button>
    </div>
    <div class="alerta-body">
        <p class="alerta-cliente"><strong>Cliente:</strong> {$alerta['cliente_nome']}</p>
        <p class="alerta-valor"><strong>Valor:</strong> <span class="valor-destaque">{$valor}</span></p>
        <p class="alerta-vencimento"><strong>Vencimento:</strong> {$alerta['data_vencimento']}</p>
        <p class="alerta-descricao">{$alerta['descricao']}</p>
    </div>
    <div class="alerta-footer">
        <button class="btn btn-primary" onclick="verConta({$alerta['conta_receber_id']}, {$alerta['id']})">
            👁️ Ver Conta
        </button>
        <button class="btn btn-danger" onclick="cancelarConta({$alerta['conta_receber_id']}, {$alerta['id']})">
            ✕ Cancelar
        </button>
        <button class="btn btn-secondary" onclick="ignorarAlerta({$alerta['id']})">
            Ignorar
        </button>
    </div>
</div>
HTML;
        
        return $html;
    }
    
    /**
     * Obter ícone baseado no tipo de alerta
     * 
     * @param string $tipo Tipo de alerta
     * @return string Ícone/emoji
     */
    private static function obterIconeAlerta($tipo) {
        $icones = [
            'vencido' => '⚠️',
            'vencendo_hoje' => '🔴',
            'vencendo_amanha' => '🟡',
            'vencendo_semana' => '🟢'
        ];
        
        return $icones[$tipo] ?? '📌';
    }
    
    /**
     * Obter classe CSS baseada no tipo de alerta
     * 
     * @param string $tipo Tipo de alerta
     * @return string Classe CSS
     */
    private static function obterClasseAlerta($tipo) {
        $classes = [
            'vencido' => 'alerta-critico',
            'vencendo_hoje' => 'alerta-urgente',
            'vencendo_amanha' => 'alerta-aviso',
            'vencendo_semana' => 'alerta-info'
        ];
        
        return $classes[$tipo] ?? 'alerta-default';
    }
    
    /**
     * Obter descrição do tipo de alerta
     * 
     * @param string $tipo Tipo de alerta
     * @return string Descrição
     */
    public static function obterDescricaoTipo($tipo) {
        $descricoes = [
            'vencido' => 'Conta Vencida',
            'vencendo_hoje' => 'Vencendo Hoje',
            'vencendo_amanha' => 'Vencendo Amanhã',
            'vencendo_semana' => 'Vencendo Esta Semana'
        ];
        
        return $descricoes[$tipo] ?? 'Alerta';
    }
}

/**
 * Função auxiliar para formatar moeda
 */
if (!function_exists('formatMoeda')) {
    function formatMoeda($valor) {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }
}
