(function($) {
    'use strict';
    
    // Variables globales
    let currentFilters = {};
    let allLeads = [];
    
    // Inicialización
    $(document).ready(function() {
        initializeEvents();
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
        
        // Formulario de lead
        $('#lead_form').on('submit', function(e) {
            e.preventDefault();
            saveLead();
        });
        
        // Click en tarjetas para ver detalles
        $(document).on('click', '.lead-card', function(e) {
            if (!$(e.target).hasClass('update-status')) {
                const leadId = $(this).data('lead-id');
                window.open(ltb_leads.site_url + '/lead-details/lead-' + leadId, '_blank');
            }
        });
        
        // Actualizar status
        $(document).on('click', '.update-status', function(e) {
            e.preventDefault();
            const leadId = $(this).data('lead-id');
            const eventoId = $(this).data('evento-id');
            const currentStatus = $(this).data('status');
            updateStatus(leadId, eventoId, currentStatus);
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
        
        // Acciones
        const actions = $('<div class="lead-actions">');
        actions.append($('<a href="#">').text('Ver detalles'));
        
        if (lead.evento_id) {
            actions.append(
                $('<a href="#" class="update-status">')
                    .text('Cambiar estado')
                    .attr('data-lead-id', lead.lead_id)
                    .attr('data-evento-id', lead.evento_id)
                    .attr('data-status', status)
            );
        }
        
        card.append(actions);
        return card;
    }
    
    // Aplicar filtros
    function applyFilters() {
        currentFilters = {
            search: $('#quick_search').val(),
            status: $('#status_filter').val() ? [$('#status_filter').val()] : [],
            fecha_inicio: $('#date_from').val(),
            fecha_fin: $('#date_to').val()
        };
        
        loadPipelineData();
    }
    
    // Limpiar filtros
    function clearFilters() {
        $('#quick_search').val('');
        $('#status_filter').val('');
        $('#date_from').val('');
        $('#date_to').val('');
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
    
    // Actualizar status
    function updateStatus(leadId, eventoId, currentStatus) {
        const newStatus = prompt('Seleccione el nuevo estado:\n\n' + getStatusOptions(currentStatus));
        
        if (newStatus && newStatus !== currentStatus) {
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
                        loadPipelineData();
                    } else {
                        alert('Error al actualizar estado');
                    }
                }
            });
        }
    }
    
    // Obtener opciones de estado
    function getStatusOptions(currentStatus) {
        const options = window.leadManagementConfig?.statusOptions || {};
        let text = '';
        let index = 1;
        
        for (const [value, label] of Object.entries(options)) {
            if (value !== currentStatus) {
                text += index + '. ' + label + ' (' + value + ')\n';
                index++;
            }
        }
        
        return text;
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