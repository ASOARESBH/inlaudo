/**
 * ERP INLAUDO - JavaScript Principal
 * 
 * Funções gerais da aplicação
 */

// ============================================================
// CONFIGURAÇÃO GLOBAL
// ============================================================
const APP = {
    baseUrl: document.querySelector('meta[name="base-url"]')?.content || '/',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
    
    /**
     * Inicializar aplicação
     */
    init: function() {
        this.setupEventListeners();
        this.setupAjax();
    },
    
    /**
     * Configurar listeners de eventos
     */
    setupEventListeners: function() {
        // Fechar modais ao clicar no X
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.target.closest('.modal').classList.remove('show');
            });
        });
        
        // Fechar modais ao clicar fora
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    },
    
    /**
     * Configurar AJAX
     */
    setupAjax: function() {
        // Adicionar CSRF token a todas as requisições AJAX
        if (this.csrfToken) {
            fetch = (function(originalFetch) {
                return function(...args) {
                    let [resource, config] = args;
                    
                    if (!config) {
                        config = {};
                    }
                    
                    if (!config.headers) {
                        config.headers = {};
                    }
                    
                    config.headers['X-CSRF-Token'] = APP.csrfToken;
                    
                    return originalFetch(resource, config);
                };
            })(fetch);
        }
    }
};

// ============================================================
// FUNÇÕES UTILITÁRIAS
// ============================================================

/**
 * Mostrar modal
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
    }
}

/**
 * Fechar modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

/**
 * Mostrar alerta
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    const container = document.querySelector('.container') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Fazer requisição AJAX
 */
async function fetchApi(url, options = {}) {
    try {
        const response = await fetch(APP.baseUrl + url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': APP.csrfToken,
                ...options.headers
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erro na requisição:', error);
        showAlert('Erro ao processar requisição', 'danger');
        throw error;
    }
}

/**
 * Formatar moeda
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Formatar data
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('pt-BR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(date);
}

/**
 * Validar email
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validar CNPJ
 */
function isValidCNPJ(cnpj) {
    cnpj = cnpj.replace(/\D/g, '');
    
    if (cnpj.length !== 14) return false;
    
    let size = cnpj.length - 2;
    let numbers = cnpj.substring(0, size);
    let digits = cnpj.substring(size);
    let sum = 0;
    let pos = size - 7;
    
    for (let i = size; i >= 1; i--) {
        sum += numbers.charAt(size - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    let result = sum % 11 < 2 ? 0 : 11 - sum % 11;
    if (result !== parseInt(digits.charAt(0))) return false;
    
    size = size - 1;
    numbers = cnpj.substring(0, size);
    sum = 0;
    pos = size - 7;
    
    for (let i = size; i >= 1; i--) {
        sum += numbers.charAt(size - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    result = sum % 11 < 2 ? 0 : 11 - sum % 11;
    if (result !== parseInt(digits.charAt(1))) return false;
    
    return true;
}

/**
 * Validar CPF
 */
function isValidCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;
    
    let sum = 0;
    let remainder;
    
    for (let i = 1; i <= 9; i++) {
        sum += parseInt(cpf.substring(i - 1, i)) * (11 - i);
    }
    
    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.substring(9, 10))) return false;
    
    sum = 0;
    for (let i = 1; i <= 10; i++) {
        sum += parseInt(cpf.substring(i - 1, i)) * (12 - i);
    }
    
    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) remainder = 0;
    if (remainder !== parseInt(cpf.substring(10, 11))) return false;
    
    return true;
}

/**
 * Máscara para CNPJ
 */
function maskCNPJ(value) {
    value = value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
    value = value.replace(/(\d{4})(\d)/, '$1-$2');
    return value;
}

/**
 * Máscara para CPF
 */
function maskCPF(value) {
    value = value.replace(/\D/g, '');
    value = value.replace(/^(\d{3})(\d)/, '$1.$2');
    value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
    value = value.replace(/\.(\d{3})(\d)/, '.$1-$2');
    return value;
}

/**
 * Máscara para telefone
 */
function maskPhone(value) {
    value = value.replace(/\D/g, '');
    value = value.replace(/^(\d{2})(\d)/, '($1) $2');
    value = value.replace(/(\d{4})(\d)/, '$1-$2');
    return value;
}

/**
 * Máscara para moeda
 */
function maskCurrency(value) {
    value = value.replace(/\D/g, '');
    value = (value / 100).toFixed(2);
    value = value.replace('.', ',');
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return 'R$ ' + value;
}

// ============================================================
// INICIALIZAR AO CARREGAR
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    APP.init();
});
