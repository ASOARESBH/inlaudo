<?php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['cnpj'])) {
    echo json_encode(['status' => 'ERROR', 'message' => 'CNPJ não informado']);
    exit;
}

$cnpj = preg_replace('/[^0-9]/', '', $_GET['cnpj']);

if (strlen($cnpj) != 14) {
    echo json_encode(['status' => 'ERROR', 'message' => 'CNPJ inválido']);
    exit;
}

// Tentar ReceitaWS primeiro
$url = "https://receitaws.com.br/v1/cnpj/" . $cnpj;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    
    if (isset($data['status']) && $data['status'] == 'ERROR') {
        // Se ReceitaWS falhar, tentar API alternativa (BrasilAPI)
        $urlAlternativa = "https://brasilapi.com.br/api/cnpj/v1/" . $cnpj;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlAlternativa);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $responseAlt = curl_exec($ch);
        $httpCodeAlt = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCodeAlt == 200 && $responseAlt) {
            $dataAlt = json_decode($responseAlt, true);
            
            // Converter formato BrasilAPI para formato padrão
            $resultado = [
                'status' => 'OK',
                'nome' => $dataAlt['razao_social'] ?? '',
                'fantasia' => $dataAlt['nome_fantasia'] ?? '',
                'email' => $dataAlt['email'] ?? '',
                'telefone' => $dataAlt['ddd_telefone_1'] ?? '',
                'cep' => $dataAlt['cep'] ?? '',
                'logradouro' => $dataAlt['logradouro'] ?? '',
                'numero' => $dataAlt['numero'] ?? '',
                'complemento' => $dataAlt['complemento'] ?? '',
                'bairro' => $dataAlt['bairro'] ?? '',
                'municipio' => $dataAlt['municipio'] ?? '',
                'uf' => $dataAlt['uf'] ?? '',
                'situacao' => $dataAlt['descricao_situacao_cadastral'] ?? '',
                'data_abertura' => $dataAlt['data_inicio_atividade'] ?? '',
            ];
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'ERROR', 'message' => 'CNPJ não encontrado']);
        }
    } else {
        // ReceitaWS funcionou
        echo $response;
    }
} else {
    // Se ReceitaWS não responder, tentar BrasilAPI
    $urlAlternativa = "https://brasilapi.com.br/api/cnpj/v1/" . $cnpj;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlAlternativa);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $responseAlt = curl_exec($ch);
    $httpCodeAlt = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCodeAlt == 200 && $responseAlt) {
        $dataAlt = json_decode($responseAlt, true);
        
        // Converter formato BrasilAPI para formato padrão
        $resultado = [
            'status' => 'OK',
            'nome' => $dataAlt['razao_social'] ?? '',
            'fantasia' => $dataAlt['nome_fantasia'] ?? '',
            'email' => $dataAlt['email'] ?? '',
            'telefone' => $dataAlt['ddd_telefone_1'] ?? '',
            'cep' => $dataAlt['cep'] ?? '',
            'logradouro' => $dataAlt['logradouro'] ?? '',
            'numero' => $dataAlt['numero'] ?? '',
            'complemento' => $dataAlt['complemento'] ?? '',
            'bairro' => $dataAlt['bairro'] ?? '',
            'municipio' => $dataAlt['municipio'] ?? '',
            'uf' => $dataAlt['uf'] ?? '',
            'situacao' => $dataAlt['descricao_situacao_cadastral'] ?? '',
            'data_abertura' => $dataAlt['data_inicio_atividade'] ?? '',
        ];
        
        echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['status' => 'ERROR', 'message' => 'Erro ao consultar CNPJ']);
    }
}
?>
