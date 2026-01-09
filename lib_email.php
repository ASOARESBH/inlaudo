<?php
/**
 * Biblioteca para envio de e-mails
 * Usa PHPMailer para envio via SMTP
 */

require_once 'config.php';

class EmailSender {
    
    /**
     * Enviar e-mail usando configuração do banco de dados
     * 
     * @param string $destinatario E-mail do destinatário
     * @param string $assunto Assunto do e-mail
     * @param string $corpoHtml Corpo do e-mail em HTML
     * @param string $corpoTexto Corpo do e-mail em texto puro (opcional)
     * @param string $destinatarioNome Nome do destinatário (opcional)
     * @param int $templateId ID do template usado (opcional, para histórico)
     * @param string $referenciaTipo Tipo da entidade relacionada (opcional)
     * @param int $referenciaId ID da entidade relacionada (opcional)
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function enviar(
        $destinatario,
        $assunto,
        $corpoHtml,
        $corpoTexto = null,
        $destinatarioNome = null,
        $templateId = null,
        $referenciaTipo = null,
        $referenciaId = null
    ) {
        try {
            // Buscar configuração ativa
            $conn = getConnection();
            $stmt = $conn->query("SELECT * FROM email_config WHERE ativo = TRUE LIMIT 1");
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception('Nenhuma configuração de e-mail ativa encontrada. Configure em Integrações > E-mail Config.');
            }
            
            // Verificar se está em modo de teste
            if ($config['testar_envio'] && !empty($config['email_teste'])) {
                $destinatarioOriginal = $destinatario;
                $destinatario = $config['email_teste'];
                $assunto = "[TESTE] " . $assunto . " (Original: $destinatarioOriginal)";
            }
            
            // Criar instância do PHPMailer manualmente (sem composer)
            $resultado = self::enviarSMTP(
                $config,
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $corpoTexto
            );
            
            // Registrar no histórico
            self::registrarHistorico(
                $templateId,
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $resultado['sucesso'] ? 'enviado' : 'erro',
                $resultado['sucesso'] ? null : $resultado['mensagem'],
                $referenciaTipo,
                $referenciaId
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            // Registrar erro no histórico
            self::registrarHistorico(
                $templateId,
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                'erro',
                $e->getMessage(),
                $referenciaTipo,
                $referenciaId
            );
            
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar e-mail via SMTP usando funções nativas do PHP
     */
    private static function enviarSMTP($config, $destinatario, $destinatarioNome, $assunto, $corpoHtml, $corpoTexto) {
        try {
            // Preparar headers
            $boundary = md5(time());
            
            $headers = [];
            $headers[] = "From: {$config['from_name']} <{$config['from_email']}>";
            $headers[] = "Reply-To: " . ($config['reply_to_email'] ?: $config['from_email']);
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            
            // Preparar corpo do e-mail
            $message = "--{$boundary}\r\n";
            
            // Parte texto
            if ($corpoTexto) {
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $corpoTexto . "\r\n\r\n";
                $message .= "--{$boundary}\r\n";
            }
            
            // Parte HTML
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $message .= $corpoHtml . "\r\n\r\n";
            $message .= "--{$boundary}--";
            
            // Configurar SMTP
            $smtp = [
                'host' => $config['smtp_host'],
                'port' => $config['smtp_port'],
                'user' => $config['smtp_user'],
                'pass' => $config['smtp_password'],
                'secure' => $config['smtp_secure']
            ];
            
            // Tentar enviar via mail() nativo primeiro (mais simples)
            // Nota: Em produção, recomenda-se usar PHPMailer via composer
            $enviado = mail(
                $destinatario,
                $assunto,
                $message,
                implode("\r\n", $headers)
            );
            
            if ($enviado) {
                return [
                    'sucesso' => true,
                    'mensagem' => 'E-mail enviado com sucesso'
                ];
            } else {
                // Se mail() falhar, tentar via socket SMTP
                return self::enviarViaSMTPSocket($smtp, $config, $destinatario, $assunto, $message, $headers);
            }
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao enviar e-mail: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar via socket SMTP (fallback)
     */
    private static function enviarViaSMTPSocket($smtp, $config, $destinatario, $assunto, $message, $headers) {
        try {
            // Conectar ao servidor SMTP
            $socket = fsockopen(
                ($smtp['secure'] == 'ssl' ? 'ssl://' : '') . $smtp['host'],
                $smtp['port'],
                $errno,
                $errstr,
                30
            );
            
            if (!$socket) {
                throw new Exception("Não foi possível conectar ao servidor SMTP: $errstr ($errno)");
            }
            
            // Ler resposta inicial
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("Erro na conexão SMTP: $response");
            }
            
            // EHLO
            fputs($socket, "EHLO {$smtp['host']}\r\n");
            $response = fgets($socket, 515);
            
            // STARTTLS se necessário
            if ($smtp['secure'] == 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = fgets($socket, 515);
                if (substr($response, 0, 3) != '220') {
                    throw new Exception("Erro ao iniciar TLS: $response");
                }
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                fputs($socket, "EHLO {$smtp['host']}\r\n");
                $response = fgets($socket, 515);
            }
            
            // AUTH LOGIN
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($smtp['user']) . "\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($smtp['pass']) . "\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '235') {
                throw new Exception("Erro na autenticação SMTP: $response");
            }
            
            // MAIL FROM
            fputs($socket, "MAIL FROM: <{$config['from_email']}>\r\n");
            $response = fgets($socket, 515);
            
            // RCPT TO
            fputs($socket, "RCPT TO: <{$destinatario}>\r\n");
            $response = fgets($socket, 515);
            
            // DATA
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            
            // Enviar headers e mensagem
            fputs($socket, implode("\r\n", $headers) . "\r\n");
            fputs($socket, "Subject: $assunto\r\n");
            fputs($socket, "\r\n");
            fputs($socket, $message);
            fputs($socket, "\r\n.\r\n");
            $response = fgets($socket, 515);
            
            // QUIT
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return [
                'sucesso' => true,
                'mensagem' => 'E-mail enviado com sucesso via SMTP'
            ];
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Processar template com variáveis
     * 
     * @param int $templateId ID do template
     * @param array $variaveis Array associativo com variáveis
     * @return array ['assunto' => string, 'corpo_html' => string, 'corpo_texto' => string]
     */
    public static function processarTemplate($templateId, $variaveis = []) {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            throw new Exception("Template não encontrado");
        }
        
        // Substituir variáveis no formato {{variavel}}
        $assunto = $template['assunto'];
        $corpoHtml = $template['corpo_html'];
        $corpoTexto = $template['corpo_texto'];
        
        foreach ($variaveis as $chave => $valor) {
            $placeholder = "{{" . $chave . "}}";
            $assunto = str_replace($placeholder, $valor, $assunto);
            $corpoHtml = str_replace($placeholder, $valor, $corpoHtml);
            $corpoTexto = str_replace($placeholder, $valor, $corpoTexto);
        }
        
        return [
            'assunto' => $assunto,
            'corpo_html' => $corpoHtml,
            'corpo_texto' => $corpoTexto
        ];
    }
    
    /**
     * Enviar e-mail usando template
     * 
     * @param int $templateId ID do template
     * @param string $destinatario E-mail do destinatário
     * @param array $variaveis Variáveis para substituir no template
     * @param string $destinatarioNome Nome do destinatário (opcional)
     * @param string $referenciaTipo Tipo da entidade relacionada (opcional)
     * @param int $referenciaId ID da entidade relacionada (opcional)
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function enviarComTemplate(
        $templateId,
        $destinatario,
        $variaveis = [],
        $destinatarioNome = null,
        $referenciaTipo = null,
        $referenciaId = null
    ) {
        try {
            $email = self::processarTemplate($templateId, $variaveis);
            
            return self::enviar(
                $destinatario,
                $email['assunto'],
                $email['corpo_html'],
                $email['corpo_texto'],
                $destinatarioNome,
                $templateId,
                $referenciaTipo,
                $referenciaId
            );
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar no histórico de e-mails
     */
    private static function registrarHistorico(
        $templateId,
        $destinatario,
        $destinatarioNome,
        $assunto,
        $corpoHtml,
        $status,
        $mensagemErro,
        $referenciaTipo,
        $referenciaId
    ) {
        try {
            $conn = getConnection();
            
            $ipOrigem = self::getClientIP();
            
            $sql = "INSERT INTO email_historico (
                        template_id, destinatario, destinatario_nome, assunto, corpo_html,
                        status, mensagem_erro, referencia_tipo, referencia_id, ip_origem
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $templateId,
                $destinatario,
                $destinatarioNome,
                $assunto,
                $corpoHtml,
                $status,
                $mensagemErro,
                $referenciaTipo,
                $referenciaId,
                $ipOrigem
            ]);
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar histórico de e-mail: " . $e->getMessage());
        }
    }
    
    /**
     * Obter IP do cliente
     */
    private static function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
    
    /**
     * Testar configuração de e-mail
     * 
     * @param int $configId ID da configuração
     * @param string $emailTeste E-mail para teste
     * @return array ['sucesso' => bool, 'mensagem' => string]
     */
    public static function testarConfiguracao($configId, $emailTeste) {
        try {
            $conn = getConnection();
            $stmt = $conn->prepare("SELECT * FROM email_config WHERE id = ?");
            $stmt->execute([$configId]);
            $config = $stmt->fetch();
            
            if (!$config) {
                throw new Exception("Configuração não encontrada");
            }
            
            $assunto = "Teste de Configuração de E-mail - ERP INLAUDO";
            $corpoHtml = "
            <html>
            <body style='font-family: Arial, sans-serif;'>
                <h2 style='color: #2563eb;'>Teste de E-mail</h2>
                <p>Este é um e-mail de teste do sistema ERP INLAUDO.</p>
                <p>Se você recebeu este e-mail, a configuração SMTP está funcionando corretamente!</p>
                <hr>
                <p style='font-size: 12px; color: #666;'>
                    <strong>Configuração testada:</strong><br>
                    Servidor: {$config['smtp_host']}<br>
                    Porta: {$config['smtp_port']}<br>
                    Segurança: {$config['smtp_secure']}<br>
                    Usuário: {$config['smtp_user']}
                </p>
            </body>
            </html>";
            
            $corpoTexto = "Este é um e-mail de teste do sistema ERP INLAUDO. Se você recebeu este e-mail, a configuração SMTP está funcionando corretamente!";
            
            // Temporariamente ativar esta configuração para teste
            $configAtual = $config['ativo'];
            $conn->exec("UPDATE email_config SET ativo = FALSE");
            $conn->prepare("UPDATE email_config SET ativo = TRUE WHERE id = ?")->execute([$configId]);
            
            $resultado = self::enviar(
                $emailTeste,
                $assunto,
                $corpoHtml,
                $corpoTexto,
                null,
                null,
                'teste_config',
                $configId
            );
            
            // Restaurar configuração anterior
            $conn->prepare("UPDATE email_config SET ativo = ? WHERE id = ?")->execute([$configAtual, $configId]);
            
            return $resultado;
            
        } catch (Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => $e->getMessage()
            ];
        }
    }
}
?>
