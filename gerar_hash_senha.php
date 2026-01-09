<?php
/**
 * Script para gerar hash de senha
 * Uso: php gerar_hash_senha.php
 */

$senha = 'Admin259087@';
$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "Senha: " . $senha . "\n";
echo "Hash: " . $hash . "\n";
echo "\nUse este hash no SQL:\n";
echo "'" . $hash . "'\n";
?>
