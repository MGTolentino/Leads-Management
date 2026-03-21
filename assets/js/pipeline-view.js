
(function($) {
    'use strict';
    
    // Variables globales para la vista pipeline
    let isDragging = false;
    let currentDragElement = null;
    let originalColumn = null;
    let currentFilters = {};
    
    // Inicialización cuando el DOM está listo
    $(document).ready(function() {
        
        // Inicializar vista pipeline si estamos en desktop
        if (window.innerWidth >= 768) {
            // Activar la vista pipeline
            $('.view-btn[data-view="pipeline"]').addClass('active');
            $('.view-container[data-view="pipeline"]').addClass('active');
            
            // Desactivar otras vistas
            $('.view-btn[data-view="table"], .view-btn[data-view="cards"]').removeClass('active');
            $('.view-container[data-view="table"], .view-container[data-view="cards"]').removeClass('active');
            
            // Cargar datos del pipeline
            loadPipelineData();
        }
        
        // Inicializar eventos
        initDragAndDrop();
        initStatusUpdateModal();
        
        // Manejar filtros (si están presentes)
        $('#apply_filters').on('click', function() {
            if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
                loadPipelineData();
            }
        });
        
        $('#clear_filters').on('click', function() {
            if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
                loadPipelineData();
            }
        });
        
        // Escuchar cambios de vista
        $('.view-btn').on('click', function() {
            const viewType = $(this).data('view');
            
            if (viewType === 'pipeline' && !$('.view-container[data-view="pipeline"]').hasClass('active')) {
                // Solo cargar datos si cambiamos a vista pipeline
                loadPipelineData();
            }
        });
        
        // Manejar cambio de tamaño de ventana
        $(window).on('resize', function() {
            if (window.innerWidth >= 768) {
                // En desktop, mostrar pipeline si no está activo
                if (!$('.view-container[data-view="pipeline"]').hasClass('active')) {
                    // Activar la vista pipeline
                    $('.view-btn').removeClass('active');
                    $('.view-btn[data-view="pipeline"]').addClass('active');
                    
                    $('.view-container').removeClass('active');
                    $('.view-container[data-view="pipeline"]').addClass('active');
                    
                    // Cargar datos
                    loadPipelineData();
                }
            } else {
                // En móvil, cambiar a tarjetas si estamos en pipeline
                if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
                    $('.view-btn').removeClass('active');
                    $('.view-btn[data-view="cards"]').addClass('active');
                    
                    $('.view-container').removeClass('active');
                    $('.view-container[data-view="cards"]').addClass('active');
                }
            }
        });
    });
    

/**
 * Carga los datos de leads agrupados por status
 */
function loadPipelineData() {
    showLoading();
    
    // Recopilar filtros actuales
    const filters = getFilters();
    
    // Hacer la petición AJAX para cargar datos
    $.ajax({
        url: ltb_leads.ajax_url,
        type: 'POST',
        data: {
            action: 'get_leads_by_status',
            nonce: ltb_leads.nonce,
            filters: filters
        },
        success: function(response) {
            if (response.success) {
                renderPipelineData(response.data);
            } else {
                showError(response.data.message || 'Error al cargar los datos');
            }
        },
        error: function() {
            showError('Error de conexión al cargar los datos');
        },
        complete: function() {
            hideLoading();
        }
    });
}
    
  /**
 * Renderiza los datos en la vista pipeline
 */
function renderPipelineData(data) {
    // Limpiar todas las columnas
    $('.pipeline-cards').empty();
    
    // Inicializar contadores con estados dinámicos
    const counts = {};
    // Usar solo estados activos para el pipeline
    const statusOptions = window.leadManagementConfig?.statusOptions || {};
    Object.keys(statusOptions).forEach(function(status) {
        counts[status] = 0;
    });
    
    // Iterar por cada grupo de status
    Object.keys(data).forEach(function(status) {
        // Saltarse las categorías especiales que no queremos mostrar en pipeline
        if (status === 'sin-evento' || status === 'otros') {
            return;
        }
        
        const leads = data[status];
        const $container = $(`#${status}-cards`);
        
        if ($container.length === 0) {
            return;
        }
        
        leads.forEach(function(lead) {
            $container.append(createLeadCard(lead, status));
            counts[status]++;
        });
    });
    
    // Actualizar contadores
    Object.keys(counts).forEach(function(status) {
        $(`.pipeline-column[data-status="${status}"] .lead-count`).text(counts[status]);
    });
	
	
	// Calcular leads no mostrados
let hiddenLeads = 0;
if (data['sin-evento']) hiddenLeads += data['sin-evento'].length;
if (data['otros']) hiddenLeads += data['otros'].length;

// Mostrar mensaje informativo si hay leads ocultos
if (hiddenLeads > 0) {
    const $infoMessage = $('<div>', {
        class: 'pipeline-info-message',
        text: `Hay ${hiddenLeads} leads adicionales sin estado definido o con estados obsoletos. Estos se muestran en la vista tabla.`
    });
    
    // Si ya existe un mensaje, reemplazarlo
    $('.pipeline-info-message').remove();
    
    // Agregar mensaje antes del contenedor de columnas
    $('.pipeline-columns').before($infoMessage);
}
}
    
function createLeadCard(lead, status) {
    // Crear estructura base de la tarjeta
    const $card = $('<div>', {
        class: 'pipeline-card',
        'data-lead-id': lead.lead_id,
        'data-evento-id': lead.evento_id || '',
        'data-status': status
    });
    
    // 1ª fila: Nombre del lead (título más pequeño)
    $card.append($('<h5>', { class: 'card-title', text: lead.nombre_completo }));
    
    // 2ª fila: Tipo de evento y fecha juntos
    if (lead.tipo_evento || lead.fecha_evento) {
        const $eventInfoRow = $('<div>', { class: 'card-event-info' });
        
        // Si hay tipo de evento, añadirlo
        if (lead.tipo_evento) {
            $eventInfoRow.append($('<span>', { 
                class: 'card-event-type', 
                text: lead.tipo_evento 
            }));
            
            // Si además hay fecha, añadir un separador
            if (lead.fecha_evento) {
                $eventInfoRow.append($('<span>', { text: ' • ' }));
            }
        }
        
        // Si hay fecha de evento, añadirla
        if (lead.fecha_evento) {
            $eventInfoRow.append($('<span>', { 
                class: 'card-date', 
                text: lead.fecha_evento 
            }));
        }
        
        $card.append($eventInfoRow);
    }
    
    // 3ª fila: Servicio de interés
    if (lead.servicio_titulo) {
        $card.append($('<div>', { 
            class: 'card-service', 
            text: 'Servicio: ' + lead.servicio_titulo 
        }));
    }
    
    // Footer con acciones
    const $footer = $('<div>', { class: 'card-footer' });
    const $actions = $('<div>', { class: 'card-actions' });
    
    // Acción Ver Detalles
    const $viewLink = $('<a>', {
        href: ltb_leads.site_url + '/lead-details/lead-' + lead.lead_id,
        text: 'Ver',
        target: '_blank'
    });
    $actions.append($viewLink);
    
    // Acción Actualizar Status (solo si hay evento)
    if (lead.evento_id) {
        const $updateStatusLink = $('<a>', {
            href: '#',
            text: 'Actualizar',
            class: 'update-status-link',
            'data-lead-id': lead.lead_id,
            'data-evento-id': lead.evento_id,
            'data-status': status
        });
        $actions.append($updateStatusLink);
    }
    
    $footer.append($actions);
    $card.append($footer);
    
    return $card;
}

    
    /**
     * Inicializa funcionalidad de drag and drop
     */
    function initDragAndDrop() {
        // Delegación de eventos para manejar cards creadas dinámicamente
        $(document).on('mousedown', '.pipeline-card', function(e) {
            // Solo iniciar drag con click izquierdo y no en links
            if (e.which !== 1 || $(e.target).is('a') || $(e.target).parents('a').length) {
                return;
            }
            
            isDragging = true;
            currentDragElement = $(this);
            originalColumn = $(this).closest('.pipeline-column');
            
            // Crear elemento fantasma para arrastrar
            const $ghost = $(this).clone().addClass('dragging');
            $('body').append($ghost);
            
            // Posicionar fantasma donde está el mouse
            $ghost.css({
                width: $(this).outerWidth(),
                position: 'absolute',
                zIndex: 1000,
                pointerEvents: 'none',
                opacity: 0.7,
                left: e.pageX - ($(this).outerWidth() / 2),
                top: e.pageY - 20
            });
            
            // Ocultar temporalmente la tarjeta original
            $(this).css('visibility', 'hidden');
            
            // Prevenir selección de texto
            e.preventDefault();
            
            // Registrar handlers de movimiento y liberación
            $(document).on('mousemove.drag', function(moveEvent) {
                if (!isDragging) return;
                
                // Mover fantasma con el cursor
                $ghost.css({
                    left: moveEvent.pageX - ($ghost.outerWidth() / 2),
                    top: moveEvent.pageY - 20
                });
                
                // Detectar columna destino
                const $columns = $('.pipeline-column');
                $columns.removeClass('drag-over');
                
                $columns.each(function() {
                    const rect = this.getBoundingClientRect();
                    if (
                        moveEvent.clientX >= rect.left && 
                        moveEvent.clientX <= rect.right && 
                        moveEvent.clientY >= rect.top && 
                        moveEvent.clientY <= rect.bottom
                    ) {
                        $(this).addClass('drag-over');
                    }
                });
            });
            
            $(document).on('mouseup.drag', function(upEvent) {
    if (!isDragging) return;
    
    isDragging = false;
    
    // Eliminar fantasma
    $('.dragging').remove();
    
    // Restaurar visibilidad de la tarjeta original
    currentDragElement.css('visibility', 'visible');
    
    // Detectar columna destino
    const $targetColumn = $('.pipeline-column.drag-over');
    $targetColumn.removeClass('drag-over');
    
    if ($targetColumn.length && !$targetColumn.is(originalColumn)) {
        const newStatus = $targetColumn.data('status');
        const leadId = currentDragElement.data('lead-id');
        const eventoId = currentDragElement.data('evento-id');
        const targetColumnName = $targetColumn.find('.pipeline-column-header h3').text().trim();
        
        // Solo permitir mover tarjetas con eventos
        if (eventoId) {
            // Mostrar confirmación simple
            if (confirm(`¿Estás seguro de cambiar este lead a "${targetColumnName}"?`)) {
                // Usuario confirmó, actualizar status directamente
                updateEventoStatusSimple(leadId, eventoId, newStatus);
            }
        } else {
            // Mostrar mensaje de que no se puede mover
            alert('Solo se pueden mover leads con eventos asignados');
        }
    }
    
    // Limpiar
    $(document).off('mousemove.drag mouseup.drag');
    currentDragElement = null;
    originalColumn = null;
});
        });
    }
    
    /**
     * Inicializa modal para actualizar status
     */
    function initStatusUpdateModal() {
        // Abrir modal para actualizar status
        $(document).on('click', '.update-status-link', function(e) {
            e.preventDefault();
            
            const leadId = $(this).data('lead-id');
            const eventoId = $(this).data('evento-id');
            const currentStatus = $(this).data('status');
            
            showStatusUpdateModal(leadId, eventoId, currentStatus);
        });
        
        // Enviar formulario de actualización de status
        $('#submit_status_update').on('click', function() {
            const leadId = $('#update_lead_id').val();
            const eventoId = $('#update_evento_id').val();
            const newStatus = $('#update_evento_status').val();
            const comentarios = $('#update_comentarios').val();
            
            updateEventoStatus(leadId, eventoId, newStatus, comentarios);
        });
        
        // Cerrar modal
        $('.modal-close, .modal-close-btn').on('click', function() {
            $('#status_update_modal').removeClass('active');
            $('body').removeClass('modal-open');
        });
    }
    
    /**
     * Muestra el modal para actualizar status
     */
    function showStatusUpdateModal(leadId, eventoId, status) {
        $('#update_lead_id').val(leadId);
        $('#update_evento_id').val(eventoId);
        $('#update_evento_status').val(status);
        $('#update_comentarios').val('');
        
        $('#status_update_modal').addClass('active');
        $('body').addClass('modal-open');
    }
    
    /**
     * Actualiza el status de un evento
     */
    function updateEventoStatus(leadId, eventoId, newStatus, comentarios) {
        showLoading();
        
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: 'update_evento_status',
                nonce: ltb_leads.nonce,
                lead_id: leadId,
                evento_id: eventoId,
                status: newStatus,
                comentarios: comentarios
            },
            success: function(response) {
                if (response.success) {
                    // Cerrar modal
                    $('#status_update_modal').removeClass('active');
                    $('body').removeClass('modal-open');
                    
                    // Recargar datos para reflejar cambio
                    loadPipelineData();
                } else {
                    showError(response.data.message || 'Error al actualizar el status');
                }
            },
            error: function() {
                showError('Error de conexión al actualizar el status');
            },
            complete: function() {
                hideLoading();
            }
        });
    }
	
	/**
 * Actualiza el status de un evento sin comentarios adicionales
 */
function updateEventoStatusSimple(leadId, eventoId, newStatus) {
    showLoading();
    
    $.ajax({
        url: ltb_leads.ajax_url,
        type: 'POST',
        data: {
            action: 'update_evento_status',
            nonce: ltb_leads.nonce,
            lead_id: leadId,
            evento_id: eventoId,
            status: newStatus,
            comentarios: '' // Sin comentarios adicionales
        },
        success: function(response) {
            if (response.success) {
                // Recargar datos para reflejar cambio
                loadPipelineData();
            } else {
                showError(response.data.message || 'Error al actualizar el status');
            }
        },
        error: function() {
            showError('Error de conexión al actualizar el status');
        },
        complete: function() {
            hideLoading();
        }
    });
}
    
/**
 * Obtiene los filtros activos
 */
function getFilters() {
    // Crear un objeto con los valores actuales de los filtros en la interfaz
    return {
        fecha_inicio: $('#fecha_ingreso').val(),
        fecha_fin: '',
        fecha_evento_inicio: $('#fecha_evento_inicio').val(),
        fecha_evento_fin: $('#fecha_evento_fin').val(),
        search: $('#search_leads').val(),
        orderby: $('#ordenamiento').val() || 'fecha_solicitud',
        order: $('#orden').val() || 'DESC'
    };
}
    
    /**
     * Muestra el overlay de carga
     */
    function showLoading() {
        $('.loading-overlay').addClass('active');
    }
    
    /**
     * Oculta el overlay de carga
     */
    function hideLoading() {
        $('.loading-overlay').removeClass('active');
    }
    
    /**
     * Muestra un mensaje de error
     */
    function showError(message) {
        alert(message);
    }
	
	// Exponer función para cargar datos del pipeline a nivel global
window.refreshPipelineView = function() {
    if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
        loadPipelineData();
    }
};
    
})(jQuery);