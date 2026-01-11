/**
 * Validação e Máscaras de Formulários - ERP Inlaudo
 * Versão: 1.0.0
 */

// Formata valor monetário
function formatMoeda(valor) {
    valor = valor.replace(/\D/g, '');
    valor = (parseInt(valor) / 100).toFixed(2);
    valor = valor.replace('.', ',');
    valor = valor.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    return valor;
}

// Formata CPF
function formatCPF(valor) {
    valor = valor.replace(/\D/g, '');
    valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
    valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
    valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    return valor;
}

// Formata CNPJ
function formatCNPJ(valor) {
    valor = valor.replace(/\D/g, '');
    valor = valor.replace(/^(\d{2})(\d)/, '$1.$2');
    valor = valor.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    valor = valor.replace(/\.(\d{3})(\d)/, '.$1/$2');
    valor = valor.replace(/(\d{4})(\d)/, '$1-$2');
    return valor;
}

// Formata Telefone
function formatTelefone(valor) {
    valor = valor.replace(/\D/g, '');
    if (valor.length <= 10) {
        valor = valor.replace(/^(\d{2})(\d)/g, '($1) $2');
        valor = valor.replace(/(\d)(\d{4})$/, '$1-$2');
    } else {
        valor = valor.replace(/^(\d{2})(\d)/g, '($1) $2');
        valor = valor.replace(/(\d)(\d{4})$/, '$1-$2');
    }
    return valor;
}

// Formata CEP
function formatCEP(valor) {
    valor = valor.replace(/\D/g, '');
    valor = valor.replace(/^(\d{5})(\d)/, '$1-$2');
    return valor;
}

// Confirmação de exclusão
function confirmarExclusao(mensagem = 'Tem certeza que deseja excluir?') {
    return confirm(mensagem);
}
