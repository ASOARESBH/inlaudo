<?php
/**
 * Modal de Alertas de Contas Vencidas
 * ERP INLAUDO - Popup ao Login
 * 
 * Este arquivo contém o HTML, CSS e JavaScript para exibir
 * alertas de contas vencidas em um modal/popup
 */
?>

<!-- MODAL DE ALERTAS -->
<div id="modalAlertas" class="modal-alertas" style="display: none;">
    <div class="modal-alertas-overlay" onclick="fecharModalAlertas()"></div>
    <div class="modal-alertas-container">
        <!-- Header -->
        <div class="modal-alertas-header">
            <div class="modal-alertas-titulo">
                <span class="modal-alertas-icone">⚠️</span>
                <h2>Alertas de Contas Vencidas</h2>
                <span class="modal-alertas-badge" id="totalAlertas">0</span>
            </div>
            <button class="modal-alertas-fechar" onclick="fecharModalAlertas()">×</button>
        </div>
        
        <!-- Resumo -->
        <div class="modal-alertas-resumo" id="resumoAlertas">
            <div class="resumo-item resumo-vencido">
                <span class="resumo-icone">⚠️</span>
                <div class="resumo-info">
                    <span class="resumo-label">Vencidas</span>
                    <span class="resumo-valor" id="resumoVencidos">0</span>
                </div>
            </div>
            <div class="resumo-item resumo-urgente">
                <span class="resumo-icone">🔴</span>
                <div class="resumo-info">
                    <span class="resumo-label">Vencendo Hoje</span>
                    <span class="resumo-valor" id="resumoHoje">0</span>
                </div>
            </div>
            <div class="resumo-item resumo-aviso">
                <span class="resumo-icone">🟡</span>
                <div class="resumo-info">
                    <span class="resumo-label">Vencendo Amanhã</span>
                    <span class="resumo-valor" id="resumoAmanha">0</span>
                </div>
            </div>
            <div class="resumo-item resumo-info">
                <span class="resumo-icone">🟢</span>
                <div class="resumo-info">
                    <span class="resumo-label">Próxima Semana</span>
                    <span class="resumo-valor" id="resumoSemana">0</span>
                </div>
            </div>
        </div>
        
        <!-- Valor Total -->
        <div class="modal-alertas-valor-total">
            <span>Valor Total em Alertas:</span>
            <span class="valor-total" id="valorTotalAlertas">R$ 0,00</span>
        </div>
        
        <!-- Abas de Filtro -->
        <div class="modal-alertas-abas">
            <button class="aba-btn aba-ativo" onclick="filtrarAlertas('todos')" data-filtro="todos">
                Todos
            </button>
            <button class="aba-btn" onclick="filtrarAlertas('vencido')" data-filtro="vencido">
                Vencidas
            </button>
            <button class="aba-btn" onclick="filtrarAlertas('vencendo_hoje')" data-filtro="vencendo_hoje">
                Hoje
            </button>
            <button class="aba-btn" onclick="filtrarAlertas('vencendo_amanha')" data-filtro="vencendo_amanha">
                Amanhã
            </button>
            <button class="aba-btn" onclick="filtrarAlertas('vencendo_semana')" data-filtro="vencendo_semana">
                Semana
            </button>
        </div>
        
        <!-- Lista de Alertas -->
        <div class="modal-alertas-body" id="listaAlertas">
            <div class="alertas-carregando">
                <div class="spinner"></div>
                <p>Carregando alertas...</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="modal-alertas-footer">
            <button class="btn btn-secondary" onclick="fecharModalAlertas()">
                Fechar
            </button>
            <button class="btn btn-primary" onclick="atualizarAlertas()">
                🔄 Atualizar
            </button>
        </div>
    </div>
</div>

<!-- ESTILOS CSS -->
<style>
/* Modal de Alertas */
.modal-alertas {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-alertas-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    cursor: pointer;
}

.modal-alertas-container {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Header */
.modal-alertas-header {
    padding: 24px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-alertas-titulo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-alertas-titulo h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #333;
}

.modal-alertas-icone {
    font-size: 2rem;
}

.modal-alertas-badge {
    background: #ff4444;
    color: white;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.modal-alertas-fechar {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
}

.modal-alertas-fechar:hover {
    color: #333;
}

/* Resumo */
.modal-alertas-resumo {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    padding: 16px 24px;
    background: #f9f9f9;
}

.resumo-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    background: white;
    border-left: 4px solid #ccc;
}

.resumo-vencido {
    border-left-color: #ff4444;
    background: #fff5f5;
}

.resumo-urgente {
    border-left-color: #ff6b6b;
    background: #fff0f0;
}

.resumo-aviso {
    border-left-color: #ffa500;
    background: #fffaf0;
}

.resumo-info {
    border-left-color: #4CAF50;
    background: #f0fff4;
}

.resumo-icone {
    font-size: 1.5rem;
}

.resumo-info {
    display: flex;
    flex-direction: column;
}

.resumo-label {
    font-size: 0.8rem;
    color: #666;
    font-weight: 500;
}

.resumo-valor {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

/* Valor Total */
.modal-alertas-valor-total {
    padding: 12px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}

.valor-total {
    font-size: 1.5rem;
    font-weight: bold;
}

/* Abas */
.modal-alertas-abas {
    display: flex;
    gap: 8px;
    padding: 12px 24px;
    border-bottom: 1px solid #f0f0f0;
    overflow-x: auto;
}

.aba-btn {
    padding: 8px 16px;
    border: none;
    background: #f0f0f0;
    color: #666;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    white-space: nowrap;
}

.aba-btn:hover {
    background: #e0e0e0;
}

.aba-btn.aba-ativo {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* Body */
.modal-alertas-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
}

.alertas-carregando {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 200px;
    gap: 16px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f0f0f0;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Itens de Alerta */
.alerta-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.2s;
    border-left: 4px solid #ccc;
}

.alerta-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.alerta-item.alerta-critico {
    border-left-color: #ff4444;
    background: #fff5f5;
}

.alerta-item.alerta-urgente {
    border-left-color: #ff6b6b;
    background: #fff0f0;
}

.alerta-item.alerta-aviso {
    border-left-color: #ffa500;
    background: #fffaf0;
}

.alerta-item.alerta-info {
    border-left-color: #4CAF50;
    background: #f0fff4;
}

/* Header do Alerta */
.alerta-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.alerta-icone {
    font-size: 1.5rem;
}

.alerta-titulo {
    margin: 0;
    flex: 1;
    font-size: 1rem;
    color: #333;
}

.btn-fechar-alerta {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #999;
    transition: color 0.2s;
}

.btn-fechar-alerta:hover {
    color: #333;
}

/* Body do Alerta */
.alerta-body {
    margin-bottom: 12px;
}

.alerta-body p {
    margin: 8px 0;
    font-size: 0.9rem;
    color: #666;
}

.alerta-cliente {
    font-weight: 500;
}

.alerta-valor {
    font-weight: 600;
}

.valor-destaque {
    color: #ff4444;
    font-size: 1.1rem;
}

.alerta-vencimento {
    color: #ff6b6b;
}

.alerta-descricao {
    color: #999;
    font-size: 0.85rem;
    font-style: italic;
}

/* Footer do Alerta */
.alerta-footer {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.alerta-footer .btn {
    flex: 1;
    min-width: 100px;
    padding: 8px 12px;
    font-size: 0.85rem;
}

/* Footer Modal */
.modal-alertas-footer {
    padding: 16px 24px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.modal-alertas-footer .btn {
    padding: 10px 24px;
    font-weight: 600;
}

/* Botões */
.btn {
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #e0e0e0;
    color: #333;
}

.btn-secondary:hover {
    background: #d0d0d0;
}

.btn-danger {
    background: #ff4444;
    color: white;
}

.btn-danger:hover {
    background: #ff2222;
}

/* Responsivo */
@media (max-width: 768px) {
    .modal-alertas-container {
        width: 95%;
        max-height: 95vh;
    }
    
    .modal-alertas-resumo {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-alertas-titulo h2 {
        font-size: 1.2rem;
    }
    
    .alerta-footer {
        flex-direction: column;
    }
    
    .alerta-footer .btn {
        width: 100%;
    }
}

/* Mensagem vazia */
.alertas-vazio {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.alertas-vazio-icone {
    font-size: 3rem;
    margin-bottom: 16px;
}

.alertas-vazio p {
    font-size: 1rem;
    margin: 0;
}
</style>

<!-- SCRIPTS JAVASCRIPT -->
<script>
// Variáveis globais
let filtroAtual = 'todos';
let alertasGlobais = [];

/**
 * Abrir modal de alertas
 */
function abrirModalAlertas() {
    const modal = document.getElementById('modalAlertas');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Carregar alertas
    carregarAlertas();
    
    // Reproduzir som se configurado
    reproduzirSomAlerta();
}

/**
 * Fechar modal de alertas
 */
function fecharModalAlertas() {
    const modal = document.getElementById('modalAlertas');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

/**
 * Carregar alertas via AJAX
 */
function carregarAlertas() {
    fetch('api_alertas.php?acao=obter_alertas', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            alertasGlobais = data.alertas;
            atualizarResumo(data.resumo);
            exibirAlertas(alertasGlobais);
        }
    })
    .catch(error => console.error('Erro ao carregar alertas:', error));
}

/**
 * Atualizar resumo de alertas
 */
function atualizarResumo(resumo) {
    document.getElementById('totalAlertas').textContent = resumo.total;
    document.getElementById('resumoVencidos').textContent = resumo.vencidos;
    document.getElementById('resumoHoje').textContent = resumo.vencendo_hoje;
    document.getElementById('resumoAmanha').textContent = resumo.vencendo_amanha;
    document.getElementById('resumoSemana').textContent = resumo.vencendo_semana;
    
    const valorTotal = resumo.valor_total.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
    document.getElementById('valorTotalAlertas').textContent = valorTotal;
}

/**
 * Exibir alertas na lista
 */
function exibirAlertas(alertas) {
    const lista = document.getElementById('listaAlertas');
    
    if (!alertas || alertas.length === 0) {
        lista.innerHTML = `
            <div class="alertas-vazio">
                <div class="alertas-vazio-icone">✓</div>
                <p>Nenhum alerta no momento!</p>
            </div>
        `;
        return;
    }
    
    lista.innerHTML = alertas.map(alerta => `
        <div class="alerta-item ${obterClasseAlerta(alerta.tipo_alerta)}" data-alerta-id="${alerta.id}">
            <div class="alerta-header">
                <span class="alerta-icone">${obterIconeAlerta(alerta.tipo_alerta)}</span>
                <h4 class="alerta-titulo">${alerta.titulo}</h4>
                <button class="btn-fechar-alerta" onclick="ignorarAlerta(${alerta.id})">×</button>
            </div>
            <div class="alerta-body">
                <p class="alerta-cliente"><strong>Cliente:</strong> ${alerta.cliente_nome}</p>
                <p class="alerta-valor"><strong>Valor:</strong> <span class="valor-destaque">${formatarMoeda(alerta.valor)}</span></p>
                <p class="alerta-vencimento"><strong>Vencimento:</strong> ${alerta.data_vencimento}</p>
                ${alerta.dias_vencido ? `<p><strong>Dias Vencido:</strong> ${alerta.dias_vencido}</p>` : ''}
            </div>
            <div class="alerta-footer">
                <button class="btn btn-primary" onclick="verConta(${alerta.conta_receber_id}, ${alerta.id})">
                    👁️ Ver Conta
                </button>
                <button class="btn btn-danger" onclick="cancelarConta(${alerta.conta_receber_id}, ${alerta.id})">
                    ✕ Cancelar
                </button>
                <button class="btn btn-secondary" onclick="ignorarAlerta(${alerta.id})">
                    Ignorar
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Filtrar alertas
 */
function filtrarAlertas(filtro) {
    filtroAtual = filtro;
    
    // Atualizar abas ativas
    document.querySelectorAll('.aba-btn').forEach(btn => {
        btn.classList.remove('aba-ativo');
        if (btn.dataset.filtro === filtro) {
            btn.classList.add('aba-ativo');
        }
    });
    
    // Filtrar e exibir
    if (filtro === 'todos') {
        exibirAlertas(alertasGlobais);
    } else {
        const alertasFiltrados = alertasGlobais.filter(a => a.tipo_alerta === filtro);
        exibirAlertas(alertasFiltrados);
    }
}

/**
 * Ver conta (redirecionar)
 */
function verConta(contaId, alertaId) {
    // Marcar como visualizado
    marcarAlertaVisualizado(alertaId, 'ver');
    
    // Redirecionar para conta
    window.location.href = `contas_receber.php?id=${contaId}&alerta=1`;
}

/**
 * Cancelar conta
 */
function cancelarConta(contaId, alertaId) {
    if (confirm('Tem certeza que deseja cancelar esta conta?')) {
        fetch('api_alertas.php?acao=cancelar_conta', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                conta_id: contaId,
                alerta_id: alertaId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.sucesso) {
                alert('Conta cancelada com sucesso!');
                carregarAlertas();
            } else {
                alert('Erro ao cancelar conta: ' + data.mensagem);
            }
        })
        .catch(error => console.error('Erro:', error));
    }
}

/**
 * Ignorar alerta
 */
function ignorarAlerta(alertaId) {
    marcarAlertaVisualizado(alertaId, 'ignorar');
    
    // Remover do DOM
    const elemento = document.querySelector(`[data-alerta-id="${alertaId}"]`);
    if (elemento) {
        elemento.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            elemento.remove();
        }, 300);
    }
}

/**
 * Marcar alerta como visualizado
 */
function marcarAlertaVisualizado(alertaId, acao) {
    fetch('api_alertas.php?acao=marcar_visualizado', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            alerta_id: alertaId,
            acao: acao
        })
    })
    .catch(error => console.error('Erro:', error));
}

/**
 * Atualizar alertas
 */
function atualizarAlertas() {
    carregarAlertas();
}

/**
 * Obter ícone do alerta
 */
function obterIconeAlerta(tipo) {
    const icones = {
        'vencido': '⚠️',
        'vencendo_hoje': '🔴',
        'vencendo_amanha': '🟡',
        'vencendo_semana': '🟢'
    };
    return icones[tipo] || '📌';
}

/**
 * Obter classe CSS do alerta
 */
function obterClasseAlerta(tipo) {
    const classes = {
        'vencido': 'alerta-critico',
        'vencendo_hoje': 'alerta-urgente',
        'vencendo_amanha': 'alerta-aviso',
        'vencendo_semana': 'alerta-info'
    };
    return classes[tipo] || 'alerta-default';
}

/**
 * Formatar moeda
 */
function formatarMoeda(valor) {
    return valor.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

/**
 * Reproduzir som de alerta
 */
function reproduzirSomAlerta() {
    // Tentar reproduzir som (se disponível)
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRiYAAABXQVZFZm10IBAAAAABAAEAQB8AAAB9AAACABAAZGF0YQIAAAAAAA==');
        audio.play().catch(() => {});
    } catch (e) {
        // Som não disponível
    }
}

// Fechar modal ao clicar fora
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalAlertas');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal.querySelector('.modal-alertas-overlay')) {
                fecharModalAlertas();
            }
        });
    }
});

// Adicionar animação de saída
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(style);
</script>
