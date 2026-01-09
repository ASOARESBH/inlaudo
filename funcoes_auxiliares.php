<?php
/**
 * Funções Auxiliares para Formatação
 * 
 * Adicionar ao config.php ou incluir via require_once
 */

/**
 * Formata CNPJ
 * 
 * @param string $cnpj CNPJ sem formatação
 * @return string CNPJ formatado (00.000.000/0000-00)
 */
function formatCNPJ($cnpj) {
    if (empty($cnpj)) {
        return '';
    }
    
    // Remove tudo que não é número
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return $cnpj; // Retorna sem formatar se inválido
    }
    
    // Formata: 00.000.000/0000-00
    return substr($cnpj, 0, 2) . '.' . 
           substr($cnpj, 2, 3) . '.' . 
           substr($cnpj, 5, 3) . '/' . 
           substr($cnpj, 8, 4) . '-' . 
           substr($cnpj, 12, 2);
}

/**
 * Formata CPF
 * 
 * @param string $cpf CPF sem formatação
 * @return string CPF formatado (000.000.000-00)
 */
function formatCPF($cpf) {
    if (empty($cpf)) {
        return '';
    }
    
    // Remove tudo que não é número
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    // Verifica se tem 11 dígitos
    if (strlen($cpf) != 11) {
        return $cpf; // Retorna sem formatar se inválido
    }
    
    // Formata: 000.000.000-00
    return substr($cpf, 0, 3) . '.' . 
           substr($cpf, 3, 3) . '.' . 
           substr($cpf, 6, 3) . '-' . 
           substr($cpf, 9, 2);
}

/**
 * Formata Telefone
 * 
 * @param string $telefone Telefone sem formatação
 * @return string Telefone formatado
 */
function formatTelefone($telefone) {
    if (empty($telefone)) {
        return '';
    }
    
    // Remove tudo que não é número
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    $len = strlen($telefone);
    
    // Celular com 11 dígitos: (00) 00000-0000
    if ($len == 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . 
               substr($telefone, 2, 5) . '-' . 
               substr($telefone, 7, 4);
    }
    
    // Telefone fixo com 10 dígitos: (00) 0000-0000
    if ($len == 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . 
               substr($telefone, 2, 4) . '-' . 
               substr($telefone, 6, 4);
    }
    
    // Celular sem DDD (9 dígitos): 00000-0000
    if ($len == 9) {
        return substr($telefone, 0, 5) . '-' . 
               substr($telefone, 5, 4);
    }
    
    // Telefone fixo sem DDD (8 dígitos): 0000-0000
    if ($len == 8) {
        return substr($telefone, 0, 4) . '-' . 
               substr($telefone, 4, 4);
    }
    
    // Retorna sem formatar se não se encaixar em nenhum padrão
    return $telefone;
}

/**
 * Formata Data
 * 
 * @param string $data Data no formato Y-m-d H:i:s ou Y-m-d
 * @return string Data formatada (d/m/Y ou d/m/Y H:i)
 */
function formatData($data) {
    if (empty($data) || $data == '0000-00-00' || $data == '0000-00-00 00:00:00') {
        return '-';
    }
    
    // Tenta converter para timestamp
    $timestamp = strtotime($data);
    
    if ($timestamp === false) {
        return $data; // Retorna original se não conseguir converter
    }
    
    // Se tem hora (mais de 10 caracteres)
    if (strlen($data) > 10) {
        return date('d/m/Y H:i', $timestamp);
    }
    
    // Só data
    return date('d/m/Y', $timestamp);
}

/**
 * Formata Moeda (Real Brasileiro)
 * 
 * @param float $valor Valor numérico
 * @return string Valor formatado (R$ 0.000,00)
 */
function formatMoeda($valor) {
    if ($valor === null || $valor === '') {
        return 'R$ 0,00';
    }
    
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Sanitiza string para prevenir XSS
 * 
 * @param string $string String a ser sanitizada
 * @return string String sanitizada
 */
function sanitize($string) {
    if (empty($string)) {
        return '';
    }
    
    // Remove tags HTML
    $string = strip_tags($string);
    
    // Remove espaços extras
    $string = trim($string);
    
    return $string;
}

/**
 * Valida CNPJ
 * 
 * @param string $cnpj CNPJ a ser validado
 * @return bool True se válido, False se inválido
 */
function validaCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    $multiplicador = 5;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    if ($cnpj[12] != $digito1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    $multiplicador = 6;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $multiplicador;
        $multiplicador = ($multiplicador == 2) ? 9 : $multiplicador - 1;
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    return $cnpj[13] == $digito2;
}

/**
 * Valida CPF
 * 
 * @param string $cpf CPF a ser validado
 * @return bool True se válido, False se inválido
 */
function validaCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $cpf[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    if ($cpf[9] != $digito1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma += $cpf[$i] * (11 - $i);
    }
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    return $cpf[10] == $digito2;
}

/**
 * Valida E-mail
 * 
 * @param string $email E-mail a ser validado
 * @return bool True se válido, False se inválido
 */
function validaEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Formata CEP
 * 
 * @param string $cep CEP sem formatação
 * @return string CEP formatado (00000-000)
 */
function formatCEP($cep) {
    if (empty($cep)) {
        return '';
    }
    
    $cep = preg_replace('/[^0-9]/', '', $cep);
    
    if (strlen($cep) != 8) {
        return $cep;
    }
    
    return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
}

// Mensagem de sucesso ao incluir
if (!defined('FUNCOES_AUXILIARES_CARREGADAS')) {
    define('FUNCOES_AUXILIARES_CARREGADAS', true);
}
?>
