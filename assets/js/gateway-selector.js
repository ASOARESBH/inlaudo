/**
 * Gateway Selector - Componente de Seleção de Gateways
 * Versão: 2.3.0
 * 
 * Permite seleção de múltiplos gateways de pagamento
 */

class GatewaySelector {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            multiple: true,
            preferencial: null,
            gateways: [],
            selected: [],
            onChange: null,
            ...options
        };
        
        this.init();
    }
    
    init() {
        if (!this.container) {
            console.error('Container não encontrado:', this.containerId);
            return;
        }
        
        this.render();
        this.attachEvents();
    }
    
    render() {
        const html = `
            <div class="gateway-selector">
                <div class="gateway-selector-header">
                    <label class="form-label">
                        <i class="fas fa-credit-card"></i>
                        Meios de Pagamento Disponíveis
                    </label>
                    <small class="text-muted">
                        ${this.options.multiple ? 'Selecione um ou mais meios de pagamento' : 'Selecione o meio de pagamento'}
                    </small>
                </div>
                
                <div class="gateway-selector-list">
                    ${this.renderGateways()}
                </div>
                
                ${this.options.multiple ? this.renderPreferencialSelector() : ''}
                
                <input type="hidden" name="gateway_id" id="gateway_id" value="${this.options.preferencial || ''}">
                <input type="hidden" name="gateways_disponiveis" id="gateways_disponiveis" value="${JSON.stringify(this.options.selected)}">
            </div>
        `;
        
        this.container.innerHTML = html;
    }
    
    renderGateways() {
        return this.options.gateways.map(gateway => {
            const isSelected = this.options.selected.includes(gateway.id);
            const isPreferencial = this.options.preferencial == gateway.id;
            
            return `
                <div class="gateway-item ${isSelected ? 'selected' : ''} ${isPreferencial ? 'preferencial' : ''}" 
                     data-gateway-id="${gateway.id}">
                    <div class="gateway-checkbox">
                        <input type="checkbox" 
                               id="gateway_${gateway.id}" 
                               value="${gateway.id}"
                               ${isSelected ? 'checked' : ''}
                               ${!this.options.multiple ? 'name="gateway_radio"' : ''}>
                    </div>
                    
                    <div class="gateway-icon" style="color: ${gateway.cor_hex || '#6C757D'}">
                        <i class="${gateway.icone || 'fas fa-credit-card'}"></i>
                    </div>
                    
                    <div class="gateway-info">
                        <div class="gateway-name">${gateway.nome}</div>
                        <div class="gateway-description">${gateway.descricao || ''}</div>
                        ${gateway.taxa_percentual > 0 || gateway.taxa_fixa > 0 ? `
                            <div class="gateway-taxa">
                                Taxa: ${gateway.taxa_percentual}% 
                                ${gateway.taxa_fixa > 0 ? '+ R$ ' + gateway.taxa_fixa : ''}
                            </div>
                        ` : ''}
                    </div>
                    
                    ${isPreferencial ? `
                        <div class="gateway-badge">
                            <span class="badge badge-primary">Preferencial</span>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }
    
    renderPreferencialSelector() {
        const selectedGateways = this.options.gateways.filter(g => 
            this.options.selected.includes(g.id)
        );
        
        if (selectedGateways.length === 0) {
            return '';
        }
        
        return `
            <div class="gateway-preferencial-selector">
                <label class="form-label">
                    <i class="fas fa-star"></i>
                    Gateway Preferencial
                </label>
                <select class="form-control" id="gateway_preferencial">
                    <option value="">Selecione o gateway preferencial</option>
                    ${selectedGateways.map(g => `
                        <option value="${g.id}" ${this.options.preferencial == g.id ? 'selected' : ''}>
                            ${g.nome}
                        </option>
                    `).join('')}
                </select>
                <small class="text-muted">
                    O gateway preferencial será exibido primeiro para o cliente
                </small>
            </div>
        `;
    }
    
    attachEvents() {
        const items = this.container.querySelectorAll('.gateway-item');
        
        items.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            
            // Click no item
            item.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    checkbox.checked = !checkbox.checked;
                    this.handleSelection(checkbox);
                }
            });
            
            // Change no checkbox
            checkbox.addEventListener('change', () => {
                this.handleSelection(checkbox);
            });
        });
        
        // Seletor de preferencial
        const preferencialSelect = this.container.querySelector('#gateway_preferencial');
        if (preferencialSelect) {
            preferencialSelect.addEventListener('change', (e) => {
                this.options.preferencial = e.target.value;
                this.updateHiddenInputs();
                this.updateUI();
                
                if (this.options.onChange) {
                    this.options.onChange(this.getSelected());
                }
            });
        }
    }
    
    handleSelection(checkbox) {
        const gatewayId = parseInt(checkbox.value);
        const item = checkbox.closest('.gateway-item');
        
        if (!this.options.multiple) {
            // Modo single: desmarcar outros
            this.container.querySelectorAll('.gateway-item').forEach(i => {
                i.classList.remove('selected', 'preferencial');
                i.querySelector('input').checked = false;
            });
            
            this.options.selected = [];
            this.options.preferencial = null;
        }
        
        if (checkbox.checked) {
            item.classList.add('selected');
            if (!this.options.selected.includes(gatewayId)) {
                this.options.selected.push(gatewayId);
            }
            
            // Se for o primeiro selecionado, tornar preferencial
            if (this.options.selected.length === 1) {
                this.options.preferencial = gatewayId;
            }
        } else {
            item.classList.remove('selected', 'preferencial');
            this.options.selected = this.options.selected.filter(id => id !== gatewayId);
            
            // Se era o preferencial, escolher outro
            if (this.options.preferencial == gatewayId) {
                this.options.preferencial = this.options.selected[0] || null;
            }
        }
        
        this.updateHiddenInputs();
        this.updateUI();
        
        if (this.options.onChange) {
            this.options.onChange(this.getSelected());
        }
    }
    
    updateHiddenInputs() {
        const gatewayIdInput = document.getElementById('gateway_id');
        const gatewaysDisponiveisInput = document.getElementById('gateways_disponiveis');
        
        if (gatewayIdInput) {
            gatewayIdInput.value = this.options.preferencial || '';
        }
        
        if (gatewaysDisponiveisInput) {
            gatewaysDisponiveisInput.value = JSON.stringify(this.options.selected);
        }
    }
    
    updateUI() {
        // Atualizar badges de preferencial
        this.container.querySelectorAll('.gateway-item').forEach(item => {
            const gatewayId = parseInt(item.dataset.gatewayId);
            const badge = item.querySelector('.gateway-badge');
            
            if (gatewayId == this.options.preferencial) {
                item.classList.add('preferencial');
                if (!badge) {
                    const info = item.querySelector('.gateway-info');
                    info.insertAdjacentHTML('afterend', `
                        <div class="gateway-badge">
                            <span class="badge badge-primary">Preferencial</span>
                        </div>
                    `);
                }
            } else {
                item.classList.remove('preferencial');
                if (badge) {
                    badge.remove();
                }
            }
        });
        
        // Atualizar seletor de preferencial
        const preferencialContainer = this.container.querySelector('.gateway-preferencial-selector');
        if (preferencialContainer) {
            preferencialContainer.remove();
        }
        
        if (this.options.multiple && this.options.selected.length > 0) {
            const list = this.container.querySelector('.gateway-selector-list');
            list.insertAdjacentHTML('afterend', this.renderPreferencialSelector());
            
            // Re-attach event
            const preferencialSelect = this.container.querySelector('#gateway_preferencial');
            if (preferencialSelect) {
                preferencialSelect.addEventListener('change', (e) => {
                    this.options.preferencial = e.target.value;
                    this.updateHiddenInputs();
                    this.updateUI();
                    
                    if (this.options.onChange) {
                        this.options.onChange(this.getSelected());
                    }
                });
            }
        }
    }
    
    getSelected() {
        return {
            preferencial: this.options.preferencial,
            disponiveis: this.options.selected
        };
    }
    
    setSelected(selected, preferencial = null) {
        this.options.selected = selected;
        this.options.preferencial = preferencial || selected[0] || null;
        this.render();
        this.attachEvents();
    }
}

// Exportar para uso global
window.GatewaySelector = GatewaySelector;
