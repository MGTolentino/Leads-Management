/**
 * Unified Pipeline Management System
 * Modern ES6+ implementation replacing duplicate filter systems
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

class PipelineManager {
    constructor(config = {}) {
        this.config = {
            ajaxUrl: config.ajaxUrl || ltb_leads.ajax_url,
            nonce: config.nonce || ltb_leads.nonce,
            siteUrl: config.siteUrl || ltb_leads.site_url,
            ...config
        };
        
        this.filters = new FilterManager();
        this.dragDrop = new DragDropManager();
        this.cache = new CacheManager();
        this.currentFilters = {};
        this.allLeads = [];
        this.isLoading = false;
    }
    
    /**
     * Initialize the pipeline system
     */
    async init() {
        try {
            await this.loadEventTypes();
            this.initializeEvents();
            this.dragDrop.init();
            await this.loadPipelineData();
        } catch (error) {
            console.error('Pipeline initialization failed:', error);
            this.showError('Error al inicializar el sistema');
        }
    }
    
    /**
     * Load event types dynamically
     */
    async loadEventTypes() {
        try {
            const response = await this.ajax('get_event_types');
            
            if (response.success && response.data.length > 0) {
                this.populateEventTypes(response.data);
            } else {
                this.useDefaultEventTypes();
            }
        } catch (error) {
            this.useDefaultEventTypes();
        }
    }
    
    /**
     * Populate event type filter
     */
    populateEventTypes(types) {
        const select = document.getElementById('event_type_filter');
        if (!select) return;
        
        // Clear existing options except first
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        types.forEach(type => {
            const option = new Option(type.label, type.value);
            select.add(option);
        });
    }
    
    /**
     * Use default event types as fallback
     */
    useDefaultEventTypes() {
        const defaultTypes = ['Bodas', 'XV años', 'Cumpleaños', 'Graduaciones', 'Empresarial', 'Otros'];
        this.populateEventTypes(defaultTypes.map(t => ({ label: t, value: t })));
    }
    
    /**
     * Initialize all event listeners
     */
    initializeEvents() {
        // Botón agregar lead
        const addLeadBtn = document.getElementById('add_lead_btn');
        if (addLeadBtn) {
            addLeadBtn.addEventListener('click', () => this.openAddLeadModal());
        }
        // Date range picker
        this.initDateRangePicker();
        
        // Filter change events
        document.querySelectorAll('.filter-input').forEach(input => {
            input.addEventListener('change', () => this.applyFilters());
        });
        
        // Search input with debounce
        const searchInput = document.getElementById('search_filter');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => this.applyFilters(), 300);
            });
        }
        
        // Show/hide leads without events
        const toggleNoEvent = document.getElementById('show_no_event');
        if (toggleNoEvent) {
            toggleNoEvent.addEventListener('change', () => this.toggleLeadsWithoutEvent());
        }
        
        // Clear filters button
        const clearBtn = document.getElementById('clear_filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => this.clearFilters());
        }
        
        // Export button
        const exportBtn = document.getElementById('export_leads');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportLeads());
        }
    }
    
    /**
     * Initialize date range picker with modern configuration
     */
    initDateRangePicker() {
        const dateInput = document.getElementById('date_range');
        if (!dateInput || !window.moment || !jQuery.fn.daterangepicker) return;
        
        jQuery(dateInput).daterangepicker({
            autoUpdateInput: false,
            locale: {
                format: 'DD/MM/YYYY',
                separator: ' - ',
                applyLabel: 'Aplicar',
                cancelLabel: 'Cancelar',
                fromLabel: 'Desde',
                toLabel: 'Hasta',
                customRangeLabel: 'Personalizado',
                weekLabel: 'S',
                daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                           'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                firstDay: 1
            },
            ranges: {
                'Hoy': [moment(), moment()],
                'Ayer': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Últimos 7 días': [moment().subtract(6, 'days'), moment()],
                'Últimos 30 días': [moment().subtract(29, 'days'), moment()],
                'Este mes': [moment().startOf('month'), moment().endOf('month')],
                'Mes pasado': [moment().subtract(1, 'month').startOf('month'), 
                             moment().subtract(1, 'month').endOf('month')],
                'Este año': [moment().startOf('year'), moment().endOf('year')]
            }
        });
        
        jQuery(dateInput).on('apply.daterangepicker', (ev, picker) => {
            const startDate = picker.startDate.format('YYYY-MM-DD');
            const endDate = picker.endDate.format('YYYY-MM-DD');
            
            dateInput.value = `${picker.startDate.format('DD/MM/YYYY')} - ${picker.endDate.format('DD/MM/YYYY')}`;
            
            this.currentFilters.fecha_inicio = startDate;
            this.currentFilters.fecha_fin = endDate;
            
            this.applyFilters();
        });
        
        jQuery(dateInput).on('cancel.daterangepicker', () => {
            dateInput.value = '';
            delete this.currentFilters.fecha_inicio;
            delete this.currentFilters.fecha_fin;
            this.applyFilters();
        });
    }
    
    /**
     * Apply all active filters
     */
    async applyFilters() {
        // Collect all filter values
        const filters = {
            ...this.currentFilters,
            search: document.getElementById('search_filter')?.value || '',
            status: this.getSelectedStatuses(),
            event_type: document.getElementById('event_type_filter')?.value || '',
            ejecutivo: document.getElementById('ejecutivo_filter')?.value || '',
            salon: document.getElementById('salon_filter')?.value || ''
        };
        
        // Remove empty filters
        Object.keys(filters).forEach(key => {
            if (!filters[key] || (Array.isArray(filters[key]) && filters[key].length === 0)) {
                delete filters[key];
            }
        });
        
        this.currentFilters = filters;
        await this.loadPipelineData();
    }
    
    /**
     * Get selected status checkboxes
     */
    getSelectedStatuses() {
        const statuses = [];
        document.querySelectorAll('input[name="status[]"]:checked').forEach(cb => {
            statuses.push(cb.value);
        });
        return statuses;
    }
    
    /**
     * Clear all filters
     */
    clearFilters() {
        // Clear form inputs
        document.querySelectorAll('.filter-input').forEach(input => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        
        // Clear date range
        const dateInput = document.getElementById('date_range');
        if (dateInput) {
            dateInput.value = '';
            jQuery(dateInput).data('daterangepicker')?.setStartDate(moment());
            jQuery(dateInput).data('daterangepicker')?.setEndDate(moment());
        }
        
        // Reset filters and reload
        this.currentFilters = {};
        this.loadPipelineData();
    }
    
    /**
     * Load pipeline data with current filters
     */
    async loadPipelineData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading(true);
        
        try {
            const cacheKey = this.cache.makeKey(this.currentFilters);
            let data = this.cache.get(cacheKey);
            
            if (!data) {
                const response = await this.ajax('get_pipeline_data', {
                    filters: this.currentFilters
                });
                
                if (response.success) {
                    data = response.data;
                    this.cache.set(cacheKey, data, 300); // Cache for 5 minutes
                } else {
                    throw new Error(response.message || 'Error loading data');
                }
            }
            
            // Verificar la estructura de datos recibida
            if (data.pipeline) {
                // Si viene con estructura de pipeline, usar esa
                this.renderPipelineFromStructure(data.pipeline);
                this.allLeads = this.flattenPipeline(data.pipeline);
            } else if (data.leads) {
                // Si vienen leads planos, agruparlos
                this.allLeads = data.leads;
                this.renderPipeline(data);
            }
            
            this.updateStats(data.stats || {});
            
        } catch (error) {
            console.error('Error loading pipeline:', error);
            this.showError('Error al cargar los datos del pipeline');
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }
    
    /**
     * Render the pipeline view
     */
    renderPipeline(data) {
        const container = document.getElementById('pipeline-container');
        if (!container) return;
        
        // Group leads by status
        const grouped = this.groupLeadsByStatus(data.leads || []);
        
        // Clear and rebuild columns
        container.innerHTML = '';
        
        const statuses = ['nuevo', 'contactado', 'visitado', 'cotizado', 'contratado', 'perdido'];
        
        statuses.forEach(status => {
            const column = this.createColumn(status, grouped[status] || []);
            container.appendChild(column);
        });
        
        // Re-initialize drag and drop
        this.dragDrop.refresh();
    }
    
    /**
     * Render pipeline from structured data
     */
    renderPipelineFromStructure(pipeline) {
        const container = document.getElementById('pipeline-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        const statuses = ['nuevo', 'contactado', 'visitado', 'cotizado', 'contratado', 'perdido'];
        
        statuses.forEach(status => {
            const leads = pipeline[status] || [];
            const column = this.createColumn(status, leads);
            container.appendChild(column);
        });
        
        this.dragDrop.refresh();
    }
    
    /**
     * Flatten pipeline structure to array
     */
    flattenPipeline(pipeline) {
        let allLeads = [];
        Object.values(pipeline).forEach(statusLeads => {
            if (Array.isArray(statusLeads)) {
                allLeads = allLeads.concat(statusLeads);
            }
        });
        return allLeads;
    }
    
    /**
     * Create a pipeline column
     */
    createColumn(status, leads) {
        const column = document.createElement('div');
        column.className = `pipeline-column status-${status}`;
        column.dataset.status = status;
        
        const header = document.createElement('div');
        header.className = 'column-header';
        header.innerHTML = `
            <h3>${this.getStatusLabel(status)}</h3>
            <span class="lead-count">${leads.length}</span>
        `;
        
        const body = document.createElement('div');
        body.className = 'column-body';
        
        leads.forEach(lead => {
            const card = this.createLeadCard(lead);
            body.appendChild(card);
        });
        
        column.appendChild(header);
        column.appendChild(body);
        
        return column;
    }
    
    /**
     * Create a lead card element
     */
    createLeadCard(lead) {
        const card = document.createElement('div');
        card.className = 'lead-card';
        card.dataset.leadId = lead.id;
        card.draggable = true;
        
        const eventDate = lead.event_date ? 
            new Date(lead.event_date).toLocaleDateString('es-MX') : 'Sin fecha';
        
        // Validar datos antes de mostrar
        const nombre = lead.nombre || 'Sin nombre';
        const apellido = lead.apellido || '';
        const email = lead.email || 'Sin email';
        const telefono = lead.telefono || 'Sin teléfono';
        const leadId = lead.id || lead._ID || 0;
        
        card.innerHTML = `
            <div class="lead-header">
                <span class="lead-name">${nombre} ${apellido}</span>
                <span class="lead-id">#${leadId}</span>
            </div>
            <div class="lead-details">
                <p class="lead-email">${email}</p>
                <p class="lead-phone">${telefono}</p>
                <p class="lead-event">${lead.event_type || 'Sin evento'} - ${eventDate}</p>
            </div>
            <div class="lead-actions">
                <a href="${this.config.siteUrl}/lead-details/${leadId}" class="btn-view">Ver</a>
                <button class="btn-edit" data-lead-id="${leadId}">Editar</button>
            </div>
        `;
        
        // Add click event for edit button
        card.querySelector('.btn-edit').addEventListener('click', (e) => {
            e.stopPropagation();
            this.editLead(lead.id);
        });
        
        return card;
    }
    
    /**
     * Group leads by status
     */
    groupLeadsByStatus(leads) {
        return leads.reduce((grouped, lead) => {
            const status = lead.status || 'nuevo';
            if (!grouped[status]) {
                grouped[status] = [];
            }
            grouped[status].push(lead);
            return grouped;
        }, {});
    }
    
    /**
     * Get status label in Spanish
     */
    getStatusLabel(status) {
        const labels = {
            'nuevo': 'Nuevo',
            'contactado': 'Contactado',
            'visitado': 'Visitado',
            'cotizado': 'Cotizado',
            'contratado': 'Contratado',
            'perdido': 'Perdido'
        };
        return labels[status] || status;
    }
    
    /**
     * Update statistics display
     */
    updateStats(stats) {
        if (!stats) return;
        
        Object.keys(stats).forEach(key => {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    }
    
    /**
     * Toggle leads without events visibility
     */
    toggleLeadsWithoutEvent() {
        const showNoEvent = document.getElementById('show_no_event')?.checked;
        this.currentFilters.show_no_event = showNoEvent;
        this.loadPipelineData();
    }
    
    /**
     * Export leads to CSV
     */
    async exportLeads() {
        try {
            const response = await this.ajax('export_leads', {
                filters: this.currentFilters
            });
            
            if (response.success && response.data.csv_url) {
                window.location.href = response.data.csv_url;
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            this.showError('Error al exportar los datos');
        }
    }
    
    /**
     * Edit a lead
     */
    editLead(leadId) {
        window.location.href = `${this.config.siteUrl}/lead-details/${leadId}`;
    }
    
    /**
     * Open add lead modal
     */
    openAddLeadModal() {
        // Si existe el modal del sistema anterior, usarlo
        if (typeof jQuery !== 'undefined' && jQuery('#add_lead_modal').length) {
            jQuery('#add_lead_modal').fadeIn();
        } else {
            // Crear un modal simple
            this.createSimpleAddLeadModal();
        }
    }
    
    /**
     * Create simple add lead modal
     */
    createSimpleAddLeadModal() {
        const modal = document.createElement('div');
        modal.className = 'ltb-modal-overlay';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="ltb-modal">
                <div class="ltb-modal-header">
                    <h2 class="ltb-modal-title">Agregar Nuevo Lead</h2>
                    <button class="ltb-modal-close" onclick="this.closest('.ltb-modal-overlay').remove()">×</button>
                </div>
                <div class="ltb-modal-body">
                    <form id="quick-add-lead-form">
                        <div style="display: grid; gap: 1rem;">
                            <div>
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: var(--text-secondary);">Nombre *</label>
                                <input type="text" name="nombre" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.375rem;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: var(--text-secondary);">Apellido *</label>
                                <input type="text" name="apellido" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.375rem;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: var(--text-secondary);">Email *</label>
                                <input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.375rem;">
                            </div>
                            <div>
                                <label style="display: block; margin-bottom: 0.25rem; font-size: 0.875rem; color: var(--text-secondary);">Teléfono *</label>
                                <input type="tel" name="telefono" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 0.375rem;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="ltb-modal-footer">
                    <button class="ltb-btn ltb-btn-secondary" onclick="this.closest('.ltb-modal-overlay').remove()">Cancelar</button>
                    <button class="ltb-btn ltb-btn-primary" onclick="window.pipelineManager.saveQuickLead()">Guardar Lead</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    /**
     * Save quick lead
     */
    async saveQuickLead() {
        const form = document.getElementById('quick-add-lead-form');
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'save_lead_data');
        formData.append('nonce', this.config.nonce);
        
        try {
            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Cerrar modal y recargar pipeline
                document.querySelector('.ltb-modal-overlay')?.remove();
                this.showSuccess('Lead agregado exitosamente');
                await this.loadPipelineData();
            } else {
                this.showError(result.data || 'Error al guardar el lead');
            }
        } catch (error) {
            this.showError('Error al guardar el lead');
        }
    }
    
    /**
     * Show success message
     */
    showSuccess(message) {
        const toast = document.createElement('div');
        toast.className = 'ltb-toast ltb-toast-success';
        toast.innerHTML = `
            <span>${message}</span>
        `;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 9999;
            animation: slideInRight 0.3s ease;
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    /**
     * Show loading indicator
     */
    showLoading(show) {
        const loader = document.getElementById('pipeline-loader');
        if (loader) {
            loader.style.display = show ? 'block' : 'none';
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-error';
        alert.textContent = message;
        
        const container = document.getElementById('pipeline-messages');
        if (container) {
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }
    }
    
    /**
     * Make AJAX request
     */
    async ajax(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.config.nonce);
        
        Object.keys(data).forEach(key => {
            if (typeof data[key] === 'object') {
                formData.append(key, JSON.stringify(data[key]));
            } else {
                formData.append(key, data[key]);
            }
        });
        
        const response = await fetch(this.config.ajaxUrl, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
}

/**
 * Filter Manager Class
 */
class FilterManager {
    constructor() {
        this.activeFilters = {};
    }
    
    set(key, value) {
        if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
            delete this.activeFilters[key];
        } else {
            this.activeFilters[key] = value;
        }
    }
    
    get(key) {
        return this.activeFilters[key];
    }
    
    getAll() {
        return { ...this.activeFilters };
    }
    
    clear() {
        this.activeFilters = {};
    }
    
    has(key) {
        return key in this.activeFilters;
    }
}

/**
 * Drag and Drop Manager Class
 */
class DragDropManager {
    constructor() {
        this.draggedElement = null;
        this.sourceColumn = null;
    }
    
    init() {
        this.refresh();
    }
    
    refresh() {
        // Add event listeners to all draggable items
        document.querySelectorAll('.lead-card').forEach(card => {
            card.addEventListener('dragstart', (e) => this.handleDragStart(e));
            card.addEventListener('dragend', (e) => this.handleDragEnd(e));
        });
        
        // Add event listeners to all drop zones
        document.querySelectorAll('.pipeline-column').forEach(column => {
            column.addEventListener('dragover', (e) => this.handleDragOver(e));
            column.addEventListener('drop', (e) => this.handleDrop(e));
            column.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        });
    }
    
    handleDragStart(e) {
        this.draggedElement = e.target;
        this.sourceColumn = e.target.closest('.pipeline-column');
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', e.target.innerHTML);
    }
    
    handleDragEnd(e) {
        e.target.classList.remove('dragging');
        this.draggedElement = null;
        this.sourceColumn = null;
        
        // Remove all drag-over classes
        document.querySelectorAll('.drag-over').forEach(el => {
            el.classList.remove('drag-over');
        });
    }
    
    handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        
        e.dataTransfer.dropEffect = 'move';
        
        const column = e.target.closest('.pipeline-column');
        if (column && !column.classList.contains('drag-over')) {
            column.classList.add('drag-over');
        }
        
        return false;
    }
    
    handleDragLeave(e) {
        const column = e.target.closest('.pipeline-column');
        if (column) {
            column.classList.remove('drag-over');
        }
    }
    
    async handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }
        
        const targetColumn = e.target.closest('.pipeline-column');
        if (!targetColumn || !this.draggedElement) return;
        
        targetColumn.classList.remove('drag-over');
        
        if (targetColumn !== this.sourceColumn) {
            const leadId = this.draggedElement.dataset.leadId;
            const newStatus = targetColumn.dataset.status;
            
            // Move the element visually
            targetColumn.querySelector('.column-body').appendChild(this.draggedElement);
            
            // Update counts
            this.updateColumnCounts();
            
            // Send update to server
            await this.updateLeadStatus(leadId, newStatus);
        }
        
        return false;
    }
    
    updateColumnCounts() {
        document.querySelectorAll('.pipeline-column').forEach(column => {
            const count = column.querySelectorAll('.lead-card').length;
            const countElement = column.querySelector('.lead-count');
            if (countElement) {
                countElement.textContent = count;
            }
        });
    }
    
    async updateLeadStatus(leadId, status) {
        try {
            const formData = new FormData();
            formData.append('action', 'update_lead_status');
            formData.append('nonce', ltb_leads.nonce);
            formData.append('lead_id', leadId);
            formData.append('status', status);
            
            const response = await fetch(ltb_leads.ajax_url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Update failed');
            }
        } catch (error) {
            console.error('Error updating lead status:', error);
            // Revert the visual change if needed
            window.location.reload();
        }
    }
}

/**
 * Simple Cache Manager
 */
class CacheManager {
    constructor(ttl = 300000) { // 5 minutes default
        this.cache = new Map();
        this.ttl = ttl;
    }
    
    makeKey(data) {
        return JSON.stringify(data);
    }
    
    set(key, value, ttl = null) {
        const expiry = Date.now() + (ttl || this.ttl);
        this.cache.set(key, { value, expiry });
    }
    
    get(key) {
        const item = this.cache.get(key);
        
        if (!item) return null;
        
        if (Date.now() > item.expiry) {
            this.cache.delete(key);
            return null;
        }
        
        return item.value;
    }
    
    clear() {
        this.cache.clear();
    }
    
    has(key) {
        return this.cache.has(key) && this.get(key) !== null;
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on the pipeline page
    if (document.getElementById('pipeline-container')) {
        const pipeline = new PipelineManager({
            ajaxUrl: window.ltb_leads?.ajax_url,
            nonce: window.ltb_leads?.nonce,
            siteUrl: window.ltb_leads?.site_url
        });
        
        pipeline.init();
        
        // Make pipeline available globally for debugging
        window.pipelineManager = pipeline;
    }
});