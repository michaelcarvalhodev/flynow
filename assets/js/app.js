/**
 * FLYNOW - Core JavaScript
 */

// ============================================
// Utility Functions
// ============================================

/**
 * Formata valor para moeda brasileira
 */
function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

/**
 * Formata percentual
 */
function formatPercent(value) {
    return value.toFixed(1).replace('.', ',') + '%';
}

/**
 * Converte string monetária para número
 */
function parseMoney(value) {
    if (typeof value === 'number') return value;
    return parseFloat(value.replace(/[R$\s.]/g, '').replace(',', '.')) || 0;
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// Form Handling
// ============================================

/**
 * Máscara de moeda para inputs
 */
function maskMoney(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = (parseInt(value) / 100).toFixed(2);
        e.target.value = formatMoney(value);
    });
}

/**
 * Máscara de CPF
 */
function maskCPF(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length >= 3) value = value.slice(0, 3) + '.' + value.slice(3);
        if (value.length >= 7) value = value.slice(0, 7) + '.' + value.slice(7);
        if (value.length >= 11) value = value.slice(0, 11) + '-' + value.slice(11);
        
        e.target.value = value;
    });
}

/**
 * Inicializa máscaras
 */
function initMasks() {
    document.querySelectorAll('[data-mask="money"]').forEach(maskMoney);
    document.querySelectorAll('[data-mask="cpf"]').forEach(maskCPF);
}

// ============================================
// AJAX Helpers
// ============================================

/**
 * Fetch com tratamento de erro
 */
async function api(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Erro na requisição');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * POST request
 */
async function postData(url, data) {
    return api(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}

// ============================================
// Notifications
// ============================================

/**
 * Mostra notificação toast
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    toast.innerHTML = `<span>${message}</span>`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// Modals
// ============================================

/**
 * Abre modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Fecha modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

/**
 * Inicializa modals
 */
function initModals() {
    // Fecha ao clicar no overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // Fecha ao clicar no botão de fechar
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal-overlay').classList.remove('active');
            document.body.style.overflow = '';
        });
    });
}

// ============================================
// Tables
// ============================================

/**
 * Ordenação de tabela
 */
function sortTable(tableId, column) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    rows.sort((a, b) => {
        const aVal = a.cells[column].textContent;
        const bVal = b.cells[column].textContent;
        return aVal.localeCompare(bVal, 'pt-BR', { numeric: true });
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// ============================================
// Confirmation Dialog
// ============================================

/**
 * Confirmação antes de ação
 */
function confirmAction(message, onConfirm) {
    if (confirm(message)) {
        onConfirm();
    }
}

// ============================================
// Loading State
// ============================================

/**
 * Mostra loading
 */
function showLoading() {
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="spinner"></div>
        <span>Carregando...</span>
    `;
    document.body.appendChild(overlay);
}

/**
 * Esconde loading
 */
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.remove();
}

// ============================================
// Initialize
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    initMasks();
    initModals();
    
    // Adiciona animações CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});
