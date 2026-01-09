<?php
/**
 * INSTRUÇÕES DE INTEGRAÇÃO DE ALERTAS AO LOGIN
 * ERP INLAUDO - Sistema de Alertas de Contas Vencidas
 * 
 * Este arquivo contém as instruções para integrar o sistema de alertas
 * ao fluxo de login do administrador.
 */

?>

<!-- 
================================================================================
PASSO 1: MODIFICAR login.php
================================================================================

Adicione as seguintes linhas APÓS a linha 64 (antes do header redirect):

    // ============================================================
    // GERAR E VERIFICAR ALERTAS DE CONTAS VENCIDAS
    // ============================================================
    require_once 'lib_alertas.php';
    
    // Gerar alertas de contas vencidas
    AlertasContasVencidas::gerarAlertas();
    
    // Armazenar flag de alertas na sessão
    $_SESSION['alertas_gerados'] = true;
    
    // Redirecionar para dashboard administrativo
    header('Location: index.php?alertas=1');
    exit;

CÓDIGO COMPLETO (linhas 42-65):

                } else {
                    // Login bem-sucedido para admin/usuario (SEM validar senha)
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_nivel'] = $usuario['nivel'];
                    $_SESSION['usuario_tipo'] = $usuario['tipo_usuario'] ?? 'usuario';
                    $_SESSION['login_time'] = time();
                    $_SESSION['ultimo_acesso'] = time();
                    $_SESSION['acesso_temporario'] = true; // Flag de acesso sem senha
                    
                    // Atualizar último acesso
                    $stmtUpdate = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                    $stmtUpdate->execute([$usuario['id']]);
                    
                    // Registrar log de acesso
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $stmtLog = $conn->prepare("INSERT INTO logs_acesso (usuario_id, email, acao, ip, user_agent) VALUES (?, ?, 'login_sem_senha', ?, ?)");
                    $stmtLog->execute([$usuario['id'], $email, $ip, $userAgent]);
                    
                    // ============================================================
                    // GERAR E VERIFICAR ALERTAS DE CONTAS VENCIDAS
                    // ============================================================
                    require_once 'lib_alertas.php';
                    
                    // Gerar alertas de contas vencidas
                    AlertasContasVencidas::gerarAlertas();
                    
                    // Armazenar flag de alertas na sessão
                    $_SESSION['alertas_gerados'] = true;
                    
                    // Redirecionar para dashboard administrativo
                    header('Location: index.php?alertas=1');
                    exit;
                }

================================================================================
PASSO 2: MODIFICAR index.php (Dashboard)
================================================================================

Adicione NO INÍCIO do arquivo (após require_once 'config.php'):

    // ============================================================
    // VERIFICAR E EXIBIR ALERTAS DE CONTAS VENCIDAS
    // ============================================================
    require_once 'lib_alertas.php';
    
    $mostrarAlertas = isset($_GET['alertas']) && $_GET['alertas'] == '1';
    $totalAlertas = AlertasContasVencidas::contarAlertas($_SESSION['usuario_id'] ?? 0);

Adicione NO FINAL do arquivo (antes de include 'footer.php'):

    <!-- Modal de Alertas -->
    <?php include 'modal_alertas.php'; ?>
    
    <!-- Script para abrir modal se houver alertas -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mostrarAlertas = <?php echo $mostrarAlertas ? 'true' : 'false'; ?>;
            const totalAlertas = <?php echo $totalAlertas; ?>;
            
            if (mostrarAlertas && totalAlertas > 0) {
                // Aguardar um pouco para o DOM estar pronto
                setTimeout(function() {
                    abrirModalAlertas();
                }, 500);
            }
        });
    </script>

CÓDIGO COMPLETO DO INÍCIO DO index.php:

    <?php
    session_start();
    require_once 'config.php';
    
    $pageTitle = 'Dashboard';
    
    // ============================================================
    // VERIFICAR E EXIBIR ALERTAS DE CONTAS VENCIDAS
    // ============================================================
    require_once 'lib_alertas.php';
    
    $mostrarAlertas = isset($_GET['alertas']) && $_GET['alertas'] == '1';
    $totalAlertas = AlertasContasVencidas::contarAlertas($_SESSION['usuario_id'] ?? 0);
    
    // Buscar estatísticas
    $conn = getConnection();
    
    // ... resto do código ...

================================================================================
PASSO 3: ADICIONAR NOTIFICAÇÃO NO HEADER
================================================================================

Se você tiver um arquivo header.php, adicione um badge de alertas:

    <!-- Badge de Alertas (adicionar no header, próximo ao menu) -->
    <div class="header-alertas">
        <?php
        require_once 'lib_alertas.php';
        $totalAlertas = AlertasContasVencidas::contarAlertas($_SESSION['usuario_id'] ?? 0);
        if ($totalAlertas > 0) {
            echo '<button class="btn-alertas" onclick="abrirModalAlertas()">';
            echo '⚠️ ' . $totalAlertas . ' Alerta' . ($totalAlertas > 1 ? 's' : '');
            echo '</button>';
        }
        ?>
    </div>

    <!-- CSS para o badge -->
    <style>
        .header-alertas {
            margin-left: auto;
        }
        
        .btn-alertas {
            background: linear-gradient(135deg, #ff4444 0%, #ff6b6b 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-alertas:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(255, 68, 68, 0.4);
        }
    </style>

================================================================================
PASSO 4: VERIFICAR BANCO DE DADOS
================================================================================

Execute o script SQL para criar as tabelas:

    mysql -h localhost -u seu_usuario -p seu_banco < ALERTAS_CONTAS_VENCIDAS.sql

Ou via phpMyAdmin:
    1. Abra phpMyAdmin
    2. Selecione seu banco de dados
    3. Vá para SQL
    4. Cole o conteúdo de ALERTAS_CONTAS_VENCIDAS.sql
    5. Clique em Executar

================================================================================
PASSO 5: TESTAR O SISTEMA
================================================================================

1. Faça login como administrador
2. O sistema deve:
   - Gerar alertas de contas vencidas
   - Exibir modal com alertas ao fazer login
   - Mostrar resumo de alertas (vencidos, vencendo hoje, etc)
   - Permitir ver conta (redireciona para contas_receber.php)
   - Permitir cancelar conta
   - Permitir ignorar alerta

3. Verifique os logs:
   - Tabela: alertas_contas_vencidas
   - Tabela: logs_integracao (sistema: 'alertas_sistema')

================================================================================
PASSO 6: CONFIGURAR ALERTAS (OPCIONAL)
================================================================================

Via phpMyAdmin, customize as configurações:

    UPDATE config_alertas SET valor = '1' WHERE chave = 'alertas_ativados';
    UPDATE config_alertas SET valor = '1' WHERE chave = 'alertas_mostrar_popup_login';
    UPDATE config_alertas SET valor = '0' WHERE chave = 'alertas_dias_vencido';
    UPDATE config_alertas SET valor = '7' WHERE chave = 'alertas_dias_vencendo';
    UPDATE config_alertas SET valor = '0' WHERE chave = 'alertas_valor_minimo';
    UPDATE config_alertas SET valor = '1' WHERE chave = 'alertas_som_ativado';

Ou via API:

    POST /api_alertas.php?acao=atualizar_config
    {
        "chave": "alertas_dias_vencendo",
        "valor": "7"
    }

================================================================================
PASSO 7: AGENDAR GERAÇÃO AUTOMÁTICA (OPCIONAL)
================================================================================

Para gerar alertas automaticamente a cada hora, descomente no SQL:

    CREATE EVENT IF NOT EXISTS `evt_gerar_alertas_horario`
    ON SCHEDULE EVERY 1 HOUR
    DO CALL sp_gerar_alertas_contas_vencidas();

Ou execute manualmente via cron:

    0 * * * * curl https://seu_site.com.br/api_alertas.php?acao=gerar_alertas

================================================================================
ARQUIVOS NECESSÁRIOS
================================================================================

Copie os seguintes arquivos para seu servidor:

1. lib_alertas.php - Biblioteca de alertas
2. modal_alertas.php - Modal/popup HTML/CSS/JavaScript
3. api_alertas.php - API para gerenciar alertas
4. ALERTAS_CONTAS_VENCIDAS.sql - Script de banco de dados

================================================================================
ESTRUTURA DE DADOS
================================================================================

Tabelas criadas:
- alertas_contas_vencidas: Armazena alertas
- config_alertas: Configurações do sistema

Views criadas:
- v_alertas_resumo: Resumo de alertas por usuário
- v_alertas_criticos: Alertas críticos

Procedures criadas:
- sp_gerar_alertas_contas_vencidas(): Gera novos alertas
- sp_obter_alertas_nao_visualizados(): Obtém alertas do usuário
- sp_marcar_alerta_visualizado(): Marca como visualizado
- sp_limpar_alertas_antigos(): Remove alertas antigos

================================================================================
FUNCIONALIDADES
================================================================================

✅ Popup ao fazer login (se houver alertas)
✅ Resumo de alertas (vencidos, vencendo hoje, amanhã, semana)
✅ Filtro por tipo de alerta
✅ Valor total em alertas
✅ Botão "Ver Conta" (redireciona para contas_receber.php)
✅ Botão "Cancelar" (cancela a conta)
✅ Botão "Ignorar" (marca como visualizado)
✅ Logs detalhados de todas as ações
✅ API para integração com outros sistemas
✅ Configurações personalizáveis
✅ Geração automática de alertas

================================================================================
TROUBLESHOOTING
================================================================================

Problema: Modal não aparece ao fazer login
Solução: Verifique se lib_alertas.php está sendo incluído em login.php

Problema: Alertas não aparecem
Solução: Verifique se existem contas vencidas no banco
         Execute: SELECT * FROM contas_receber WHERE status IN ('pendente', 'vencido') AND data_vencimento < CURDATE();

Problema: Erro ao clicar em "Ver Conta"
Solução: Verifique se contas_receber.php existe e está acessível

Problema: Alertas duplicados
Solução: Verifique se sp_gerar_alertas_contas_vencidas está sendo executada múltiplas vezes

================================================================================
SUPORTE
================================================================================

Para dúvidas ou problemas, consulte:
- lib_alertas.php - Documentação das funções
- modal_alertas.php - Documentação do JavaScript
- api_alertas.php - Documentação dos endpoints
- ALERTAS_CONTAS_VENCIDAS.sql - Documentação do banco

================================================================================
-->
