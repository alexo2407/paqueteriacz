/**
 * UI Components JavaScript
 * Funciones y componentes JavaScript reutilizables
 */

const UIComponents = {
    /**
     * Inicializar todos los componentes
     */
    init() {
        this.initTooltips();
        this.initPopovers();
        this.initDatePickers();
        this.initSearchFilters();
    },

    /**
     * Inicializar tooltips de Bootstrap
     */
    initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },

    /**
     * Inicializar popovers de Bootstrap
     */
    initPopovers() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    },

    /**
     * Inicializar date pickers (requiere flatpickr)
     */
    initDatePickers() {
        if (typeof flatpickr !== 'undefined') {
            flatpickr('.datepicker', {
                dateFormat: 'd/m/Y',
                locale: 'es'
            });

            flatpickr('.datetimepicker', {
                enableTime: true,
                dateFormat: 'd/m/Y H:i',
                locale: 'es',
                time_24hr: true
            });

            flatpickr('.daterangepicker', {
                mode: 'range',
                dateFormat: 'd/m/Y',
                locale: 'es'
            });
        }
    },

    /**
     * Inicializar filtros de búsqueda en tiempo real
     */
    initSearchFilters() {
        document.querySelectorAll('[data-search-table]').forEach(input => {
            const tableId = input.getAttribute('data-search-table');
            const table = document.getElementById(tableId);
            if (!table) return;

            input.addEventListener('keyup', function () {
                const filter = this.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        });
    },

    /**
     * Mostrar toast de notificación
     */
    showToast(message, type = 'success') {
        const toastContainer = this.getOrCreateToastContainer();
        const id = 'toast-' + Date.now();

        const bgClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-success';

        const icon = {
            'success': 'check-circle-fill',
            'error': 'exclamation-circle-fill',
            'warning': 'exclamation-triangle-fill',
            'info': 'info-circle-fill'
        }[type] || 'check-circle-fill';

        const toastHtml = `
            <div id="${id}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${icon} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(id);
        const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
        toast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    },

    /**
     * Obtener o crear contenedor de toasts
     */
    getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = 9999;
            document.body.appendChild(container);
        }
        return container;
    },

    /**
     * Confirmación con SweetAlert2
     */
    async confirm(title, text, confirmButtonText = 'Sí, confirmar') {
        if (typeof Swal === 'undefined') {
            return confirm(text);
        }

        const result = await Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmButtonText,
            cancelButtonText: 'Cancelar'
        });

        return result.isConfirmed;
    },

    /**
     * Mostrar loading overlay
     */
    showLoading(message = 'Cargando...') {
        let overlay = document.getElementById('loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            `;
            overlay.innerHTML = `
                <div class="text-center text-white">
                    <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="loading-message">${message}</div>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            overlay.querySelector('.loading-message').textContent = message;
            overlay.style.display = 'flex';
        }
    },

    /**
     * Ocultar loading overlay
     */
    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    },

    /**
     * Formatear número como moneda
     */
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    /**
     * Formatear fecha
     */
    formatDate(date, includeTime = false) {
        const options = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        };

        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }

        return new Intl.DateTimeFormat('es-MX', options).format(new Date(date));
    },

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Copiar al portapapeles
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showToast('Copiado al portapapeles', 'success');
            return true;
        } catch (err) {
            this.showToast('Error al copiar', 'error');
            return false;
        }
    },

    /**
     * Crear skeleton loader
     */
    createSkeleton(rows = 5) {
        let html = '';
        for (let i = 0; i < rows; i++) {
            html += `
                <tr>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                    <td><div class="skeleton skeleton-text"></div></td>
                </tr>
            `;
        }
        return html;
    }
};

/**
 * Wizard Component
 */
class WizardComponent {
    constructor(elementId) {
        this.element = document.getElementById(elementId);
        if (!this.element) return;

        this.steps = this.element.querySelectorAll('.wizard-step');
        this.panels = this.element.querySelectorAll('.wizard-panel');
        this.currentStep = 0;

        this.init();
    }

    init() {
        this.updateView();
        this.attachEventListeners();
    }

    attachEventListeners() {
        this.element.querySelectorAll('[data-wizard-next]').forEach(btn => {
            btn.addEventListener('click', () => this.next());
        });

        this.element.querySelectorAll('[data-wizard-prev]').forEach(btn => {
            btn.addEventListener('click', () => this.prev());
        });
    }

    next() {
        if (this.currentStep < this.steps.length - 1) {
            this.steps[this.currentStep].classList.remove('active');
            this.steps[this.currentStep].classList.add('completed');
            this.currentStep++;
            this.updateView();
        }
    }

    prev() {
        if (this.currentStep > 0) {
            this.steps[this.currentStep].classList.remove('active');
            this.currentStep--;
            this.steps[this.currentStep].classList.remove('completed');
            this.updateView();
        }
    }

    updateView() {
        // Update steps
        this.steps.forEach((step, index) => {
            step.classList.toggle('active', index === this.currentStep);
        });

        // Update panels
        this.panels.forEach((panel, index) => {
            panel.style.display = index === this.currentStep ? 'block' : 'none';
        });
    }

    goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < this.steps.length) {
            this.currentStep = stepIndex;
            this.updateView();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
    UIComponents.init();
});

// Exportar para uso global
window.UIComponents = UIComponents;
window.WizardComponent = WizardComponent;
