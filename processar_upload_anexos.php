<?php
/**
 * Script para processar upload de anexos em contas a receber
 * Máximo: 4 arquivos por conta
 * Formatos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG
 * Tamanho máximo: 10MB por arquivo
 */

function processarUploadAnexos($contaReceberId, $files, $conn) {
    // Diretório de upload
    $uploadDir = __DIR__ . '/uploads/contas_receber/';
    
    // Criar diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Verificar quantos anexos já existem
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contas_receber_anexos WHERE conta_receber_id = ?");
    $stmt->execute([$contaReceberId]);
    $result = $stmt->fetch();
    $anexosExistentes = $result['total'];
    
    // Máximo de 4 arquivos
    $maxAnexos = 4;
    $anexosDisponiveis = $maxAnexos - $anexosExistentes;
    
    if ($anexosDisponiveis <= 0) {
        throw new Exception("Limite de {$maxAnexos} anexos atingido para esta conta.");
    }
    
    // Formatos permitidos
    $formatosPermitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    
    // Tamanho máximo: 10MB
    $tamanhoMaximo = 10 * 1024 * 1024; // 10MB em bytes
    
    $totalFiles = count($files['name']);
    $filesParaProcessar = min($totalFiles, $anexosDisponiveis);
    
    $uploadedFiles = [];
    $errors = [];
    
    for ($i = 0; $i < $filesParaProcessar; $i++) {
        // Verificar se houve erro no upload
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Erro ao fazer upload do arquivo: " . $files['name'][$i];
            continue;
        }
        
        // Verificar tamanho
        if ($files['size'][$i] > $tamanhoMaximo) {
            $errors[] = "Arquivo muito grande: " . $files['name'][$i] . " (Máx: 10MB)";
            continue;
        }
        
        // Verificar formato
        $nomeOriginal = $files['name'][$i];
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        
        if (!in_array($extensao, $formatosPermitidos)) {
            $errors[] = "Formato não permitido: " . $nomeOriginal;
            continue;
        }
        
        // Gerar nome único
        $nomeArquivo = 'conta_' . $contaReceberId . '_' . time() . '_' . uniqid() . '.' . $extensao;
        $caminhoCompleto = $uploadDir . $nomeArquivo;
        $caminhoRelativo = 'uploads/contas_receber/' . $nomeArquivo;
        
        // Mover arquivo
        if (move_uploaded_file($files['tmp_name'][$i], $caminhoCompleto)) {
            // Salvar no banco
            try {
                $stmt = $conn->prepare("
                    INSERT INTO contas_receber_anexos (
                        conta_receber_id,
                        nome_arquivo,
                        nome_original,
                        caminho_arquivo,
                        tipo_arquivo,
                        tamanho_arquivo,
                        data_upload
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $contaReceberId,
                    $nomeArquivo,
                    $nomeOriginal,
                    $caminhoRelativo,
                    $files['type'][$i],
                    $files['size'][$i]
                ]);
                
                $uploadedFiles[] = [
                    'id' => $conn->lastInsertId(),
                    'nome_original' => $nomeOriginal,
                    'nome_arquivo' => $nomeArquivo,
                    'caminho' => $caminhoRelativo,
                    'tamanho' => $files['size'][$i]
                ];
                
            } catch (PDOException $e) {
                // Deletar arquivo se falhar ao salvar no banco
                unlink($caminhoCompleto);
                $errors[] = "Erro ao salvar no banco: " . $nomeOriginal;
            }
        } else {
            $errors[] = "Erro ao mover arquivo: " . $nomeOriginal;
        }
    }
    
    return [
        'success' => count($uploadedFiles) > 0,
        'uploaded' => $uploadedFiles,
        'errors' => $errors,
        'total_uploaded' => count($uploadedFiles),
        'total_errors' => count($errors)
    ];
}

/**
 * Deletar anexo
 */
function deletarAnexo($anexoId, $conn) {
    try {
        // Buscar dados do anexo
        $stmt = $conn->prepare("SELECT * FROM contas_receber_anexos WHERE id = ?");
        $stmt->execute([$anexoId]);
        $anexo = $stmt->fetch();
        
        if (!$anexo) {
            return [
                'success' => false,
                'message' => 'Anexo não encontrado'
            ];
        }
        
        // Deletar arquivo físico
        $caminhoCompleto = __DIR__ . '/' . $anexo['caminho_arquivo'];
        if (file_exists($caminhoCompleto)) {
            unlink($caminhoCompleto);
        }
        
        // Deletar do banco
        $stmt = $conn->prepare("DELETE FROM contas_receber_anexos WHERE id = ?");
        $stmt->execute([$anexoId]);
        
        return [
            'success' => true,
            'message' => 'Anexo excluído com sucesso'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erro ao excluir anexo: ' . $e->getMessage()
        ];
    }
}
?>
