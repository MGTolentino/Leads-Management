(function($) {
    'use strict';
    
    // Variables globales
    let currentFilters = {};
    let allLeads = [];
    let draggedElement = null;
    let sourceColumn = null;
    
    // Inicialización
    $(document).ready(function() {
        initializeEvents();
        initializeDragAndDrop();
        loadPipelineData();
    });
    
    // Configurar eventos
    function initializeEvents() {
        // Botón agregar lead
        $('#add_lead_btn').on('click', function() {
            $('#lead_modal').addClass('active');
        });
        
        // Cerrar modal
        $('.close-modal').on('click', function() {
            $('#lead_modal').removeClass('active');
            $('#lead_form')[0].reset();
            $('#event_fields').hide();
        });
        
        // Toggle campos de evento
        $('#include_event').on('change', function() {
            $('#event_fields').toggle(this.checked);
        });
        
        // Aplicar filtros
        $('#apply_filters').on('click', applyFilters);
        $('#clear_filters').on('click', clearFilters);
        
        // Búsqueda en tiempo real
        $('#quick_search').on('keyup', debounce(function() {
            applyFilters();
        }, 300));
        
        // Mostrar/ocultar rango de fechas personalizado
        $('#period_filter').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom_date_range').show();
            } else {
                $('#custom_date_range').hide();
                $('#date_from, #date_to').val('');
            }
        });
        
        // Formulario de lead
        $('#lead_form').on('submit', function(e) {
            e.preventDefault();
            saveLead();
        });
        
        // Click en tarjetas para ver detalles
        $(document).on('click', '.lead-card', function(e) {
            // Solo abrir detalles si no estamos arrastrando
            if (!$(this).hasClass('dragging')) {
                const leadId = $(this).data('lead-id');
                window.open(ltb_leads.site_url + '/lead-details/lead-' + leadId, '_blank');
            }
        });
    }
    
    // Inicializar drag and drop
    function initializeDragAndDrop() {
        // Hacer las tarjetas arrastrables
        $(document).on('dragstart', '.lead-card', function(e) {
            draggedElement = this;
            sourceColumn = $(this).closest('.pipeline-column').data('status');
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
        });
        
        $(document).on('dragend', '.lead-card', function(e) {
            $(this).removeClass('dragging');
            draggedElement = null;
            sourceColumn = null;
        });
        
        // Configurar las columnas como zonas de drop
        $(document).on('dragover', '.column-content', function(e) {
            e.preventDefault();
            $(this).closest('.pipeline-column').addClass('drag-over');
        });
        
        $(document).on('dragleave', '.column-content', function(e) {
            $(this).closest('.pipeline-column').removeClass('drag-over');
        });
        
        $(document).on('drop', '.column-content', function(e) {
            e.preventDefault();
            const $targetColumn = $(this).closest('.pipeline-column');
            $targetColumn.removeClass('drag-over');
            
            if (draggedElement) {
                const targetStatus = $targetColumn.data('status');
                const leadId = $(draggedElement).data('lead-id');
                const eventoId = $(draggedElement).data('evento-id');
                
                if (sourceColumn !== targetStatus && eventoId) {
                    // Mover visualmente primero
                    $(this).append(draggedElement);
                    
                    // Actualizar en el servidor
                    updateLeadStatus(leadId, eventoId, targetStatus, sourceColumn);
                }
            }
        });
    }
    
    // Cargar datos del pipeline
    function loadPipelineData() {
        $('.pipeline-board').addClass('loading');
        
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: 'get_leads_by_status',
                nonce: ltb_leads.nonce,
                filters: currentFilters
            },
            success: function(response) {
                if (response.success) {
                    renderPipeline(response.data);
                    allLeads = response.data;
                }
            },
            error: function() {
                alert('Error al cargar los datos');
            },
            complete: function() {
                $('.pipeline-board').removeClass('loading');
            }
        });
    }
    
    // Renderizar pipeline
    function renderPipeline(data) {
        // Limpiar columnas
        $('.column-content').empty();
        
        let totalLeads = 0;
        const counts = {};
        
        // Procesar datos por estado
        Object.keys(data).forEach(function(status) {
            if (status === 'sin-evento' || status === 'otros') {
                return; // Omitir estos estados
            }
            
            const leads = data[status];
            const $container = $('#' + status + '-cards');
            
            if ($container.length) {
                leads.forEach(function(lead) {
                    $container.append(createLeadCard(lead, status));
                });
                
                counts[status] = leads.length;
                totalLeads += leads.length;
            }
        });
        
        // Actualizar contadores
        Object.keys(counts).forEach(function(status) {
            $(`.pipeline-column[data-status="${status}"] .count`).text(counts[status] || 0);
        });
        
        $('#total_leads').text(totalLeads);
    }
    
    // Crear tarjeta de lead
    function createLeadCard(lead, status) {
        const card = $('<div class="lead-card">');
        card.attr('data-lead-id', lead.lead_id);
        card.attr('data-evento-id', lead.evento_id || '');
        card.attr('draggable', lead.evento_id ? 'true' : 'false'); // Solo arrastrables si tienen evento
        
        // Nombre
        card.append($('<div class="lead-name">').text(lead.nombre_completo));
        
        // Información básica
        if (lead.tipo_evento) {
            card.append($('<div class="lead-info">').text('Evento: ' + lead.tipo_evento));
        }
        if (lead.fecha_evento) {
            card.append($('<div class="lead-info">').text('Fecha: ' + lead.fecha_evento));
        }
        if (lead.lead_celular) {
            card.append($('<div class="lead-info">').text('Tel: ' + lead.lead_celular));
        }
        
        // Solo mostrar enlace de ver detalles
        const actions = $('<div class="lead-actions">');
        actions.append($('<a href="#">').text('Ver detalles'));
        card.append(actions);
        
        return card;
    }
    
    // Aplicar filtros
    function applyFilters() {
        const period = $('#period_filter').val();
        let fechaInicio = '';
        let fechaFin = '';
        
        // Calcular fechas según el período seleccionado
        if (period && period !== 'custom') {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth();
            const date = today.getDate();
            
            switch(period) {
                case 'today':
                    fechaInicio = fechaFin = formatDate(today);
                    break;
                case 'this_week':
                    const firstDay = new Date(today.setDate(date - today.getDay()));
                    const lastDay = new Date(today.setDate(date - today.getDay() + 6));
                    fechaInicio = formatDate(firstDay);
                    fechaFin = formatDate(lastDay);
                    break;
                case 'this_month':
                    fechaInicio = formatDate(new Date(year, month, 1));
                    fechaFin = formatDate(new Date(year, month + 1, 0));
                    break;
                case 'this_year':
                    fechaInicio = formatDate(new Date(year, 0, 1));
                    fechaFin = formatDate(new Date(year, 11, 31));
                    break;
            }
        } else if (period === 'custom') {
            fechaInicio = $('#date_from').val();
            fechaFin = $('#date_to').val();
        }
        
        currentFilters = {
            search: $('#quick_search').val(),
            status: $('#status_filter').val() ? [$('#status_filter').val()] : [],
            tipo_evento: $('#event_type_filter').val() ? [$('#event_type_filter').val()] : [],
            mes_evento: $('#event_month_filter').val(),
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin
        };
        
        loadPipelineData();
    }
    
    // Formatear fecha para envío
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Limpiar filtros
    function clearFilters() {
        $('#quick_search').val('');
        $('#period_filter').val('');
        $('#event_type_filter').val('');
        $('#event_month_filter').val('');
        $('#status_filter').val('');
        $('#date_from').val('');
        $('#date_to').val('');
        $('#custom_date_range').hide();
        currentFilters = {};
        loadPipelineData();
    }
    
    // Guardar lead
    function saveLead() {
        const formData = $('#lead_form').serialize();
        const includeEvent = $('#include_event').is(':checked');
        
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: includeEvent ? 'add_lead_with_event' : 'add_lead_only',
                nonce: ltb_leads.nonce,
                ...Object.fromEntries(new URLSearchParams(formData))
            },
            success: function(response) {
                if (response.success) {
                    $('#lead_modal').removeClass('active');
                    $('#lead_form')[0].reset();
                    loadPipelineData();
                    alert('Lead guardado correctamente');
                } else {
                    alert('Error: ' + (response.data.message || 'Error al guardar'));
                }
            },
            error: function() {
                alert('Error de conexión');
            }
        });
    }
    
    // Actualizar status mediante drag & drop
    function updateLeadStatus(leadId, eventoId, newStatus, oldStatus) {
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: 'update_event_status',
                nonce: ltb_leads.nonce,
                evento_id: eventoId,
                lead_id: leadId,
                evento_status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Actualizar contadores
                    updateColumnCounts();
                } else {
                    // Si falla, revertir el movimiento
                    alert('Error al actualizar estado');
                    loadPipelineData();
                }
            },
            error: function() {
                alert('Error de conexión');
                loadPipelineData();
            }
        });
    }
    
    // Actualizar contadores de columnas
    function updateColumnCounts() {
        $('.pipeline-column').each(function() {
            const count = $(this).find('.lead-card').length;
            $(this).find('.count').text(count);
        });
        
        // Actualizar total
        const total = $('.lead-card').length;
        $('#total_leads').text(total);
    }
    
    // Función debounce para búsqueda
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
    
})(jQuery);