(function($) {
    'use strict';
    
    // Prevenir conflictos con otros sistemas JS
    if (window.pipelineSimpleLoaded) {
        return;
    }
    window.pipelineSimpleLoaded = true;
    
    // Variables globales
    let currentFilters = {};
    let allLeads = [];
    let draggedElement = null;
    let sourceColumn = null;
    let showLeadsWithoutEvent = false; // Nueva variable para controlar mostrar leads sin evento
    
    // Inicialización
    $(document).ready(function() {
        validateLibraries();
        loadEventTypes(); // Cargar tipos de evento dinámicamente
        initializeEvents();
        initializeDragAndDrop();
        loadPipelineData();
    });
    
    // Cargar tipos de evento dinámicamente
    function loadEventTypes() {
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: 'get_event_types',
                nonce: ltb_leads.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    const $eventTypeFilter = $('#event_type_filter');
                    
                    // Limpiar opciones existentes excepto "Todos los tipos"
                    $eventTypeFilter.find('option:not(:first)').remove();
                    
                    // Agregar tipos dinámicos
                    response.data.forEach(function(tipo) {
                        $eventTypeFilter.append(
                            $('<option>', {
                                value: tipo.value,
                                text: tipo.label
                            })
                        );
                    });
                } else {
                    // Fallback a tipos por defecto si no hay datos
                    const defaultTypes = ['Bodas', 'XV años', 'Cumpleaños', 'Graduaciones', 'Empresarial', 'Otros'];
                    const $eventTypeFilter = $('#event_type_filter');
                    
                    defaultTypes.forEach(function(tipo) {
                        $eventTypeFilter.append(
                            $('<option>', {
                                value: tipo,
                                text: tipo
                            })
                        );
                    });
                }
            },
            error: function() {
                // Fallback en caso de error
                const defaultTypes = ['Bodas', 'XV años', 'Cumpleaños', 'Graduaciones', 'Empresarial', 'Otros'];
                const $eventTypeFilter = $('#event_type_filter');
                
                defaultTypes.forEach(function(tipo) {
                    $eventTypeFilter.append(
                        $('<option>', {
                            value: tipo,
                            text: tipo
                        })
                    );
                });
            }
        });
    }
    
    // Validar que las librerías requeridas estén cargadas
    function validateLibraries() {
        const requiredLibraries = [
            { name: 'jQuery', check: () => typeof $ !== 'undefined' },
            { name: 'Moment.js', check: () => typeof moment !== 'undefined' },
            { name: 'DateRangePicker', check: () => typeof $.fn.daterangepicker !== 'undefined' },
            { name: 'jQuery UI Autocomplete', check: () => typeof $.fn.autocomplete !== 'undefined' }
        ];
        
        const missingLibraries = [];
        
        requiredLibraries.forEach(lib => {
            if (!lib.check()) {
                missingLibraries.push(lib.name);
            }
        });
        
        if (missingLibraries.length > 0) {
            // Mostrar advertencia visual si hay librerías faltantes críticas
            if (missingLibraries.includes('jQuery')) {
                alert('Error crítico: jQuery no está cargado. El sistema no funcionará correctamente.');
            }
        } else {
        }
    }
    
    // Configurar eventos
    function initializeEvents() {
        // Limpiar eventos existentes para evitar duplicación
        $('#add_lead_btn').off('click.pipeline');
        $('#period_filter').off('change.pipeline');
        $('#mes_evento_basic, #anio_evento').off('change.pipeline');
        $('.close-modal').off('click.pipeline');
        $('#include_event').off('change.pipeline');
        $('#apply_filters').off('click.pipeline');
        $('#clear_filters').off('click.pipeline');
        $('#quick_search').off('keyup.pipeline');
        $('#lead_form').off('submit.pipeline');
        $('#priority_filter').off('change.pipeline');
        $('#show_leads_without_event').off('change.pipeline');
        
        // Checkbox para mostrar/ocultar leads sin evento
        $('#show_leads_without_event').on('change.pipeline', function() {
            showLeadsWithoutEvent = this.checked;
            applyFilters();
        });
        
        // Botón agregar lead
        $('#add_lead_btn').on('click.pipeline', function() {
            $('#lead_modal').addClass('active');
        });
        
        // Cerrar modal
        $('.close-modal').on('click.pipeline', function() {
            $('#lead_modal').removeClass('active');
            $('#lead_form')[0].reset();
            $('#event_fields').hide();
        });
        
        // Toggle campos de evento
        $('#include_event').on('change.pipeline', function() {
            $('#event_fields').toggle(this.checked);
        });
        
        // Aplicar filtros
        $('#apply_filters').on('click.pipeline', applyFilters);
        $('#clear_filters').on('click.pipeline', clearFilters);
        
        // Búsqueda en tiempo real
        $('#quick_search').on('keyup.pipeline', debounce(function() {
            applyFilters();
        }, 300));
        
        // Mostrar/ocultar filtros de fecha y aplicar automáticamente
        $('#period_filter').on('change.pipeline', function() {
            const value = $(this).val();
            
            // Ocultar todos los contenedores de fecha
            $('#custom_date_range').hide();
            $('#specific_month_range').hide();
            
            if (value === 'custom') {
                $('#custom_date_range').show();
                initializeDateRangePicker();
            } else if (value === 'specific_month') {
                $('#specific_month_range').show();
            } else {
                // Limpiar valores
                $('#date_range').val('');
                $('#mes_evento_basic').val('');
                $('#anio_evento').val('');
                
                // Aplicar filtros automáticamente (incluyendo "Todos los períodos")
                applyFilters();
            }
        });
        
        // Eventos para filtros de mes/año específico
        $('#mes_evento_basic, #anio_evento').on('change.pipeline', function() {
            applyFilters();
        });
        
        // Evento para filtro de prioridad
        $('#priority_filter').on('change.pipeline', function() {
            applyFilters();
        });
        
        // Evento para filtro de tipo de evento
        $('#event_type_filter').on('change.pipeline', function() {
            applyFilters();
        });
        
        // Formulario de lead
        $('#lead_form').on('submit.pipeline', function(e) {
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
        
        // Inicializar autocomplete para servicios cuando se muestra el modal
        $('#add_lead_btn').on('click.pipeline-autocomplete', function() {
            // Asegurar que el modal esté completamente visible antes de inicializar
            setTimeout(function() {
                if ($('#lead_modal').hasClass('active')) {
                    initializeServicesAutocomplete();
                }
            }, 300);
        });
        
        // También inicializar cuando se muestra el checkbox de evento
        $('#include_event').on('change.pipeline', function() {
            if (this.checked) {
                setTimeout(initializeServicesAutocomplete, 100);
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
        
        let totalLeadsVisible = 0; // Solo leads visibles
        let totalLeadsAll = 0;     // Todos los leads
        const counts = {};
        
        // Mostrar/ocultar columna de sin evento según el checkbox
        if (showLeadsWithoutEvent) {
            $('#sin-evento-column').show();
        } else {
            $('#sin-evento-column').hide();
        }
        
        // Procesar datos por estado
        Object.keys(data).forEach(function(status) {
            // Omitir 'otros' pero procesar 'sin-evento' si el checkbox está activo
            if (status === 'otros') {
                return;
            }
            
            const leads = data[status];
            
            // Contar todos los leads para estadística total
            if (status !== 'sin-evento' || showLeadsWithoutEvent) {
                totalLeadsAll += leads.length;
            }
            
            // Solo procesar sin-evento si el checkbox está activo
            if (status === 'sin-evento' && !showLeadsWithoutEvent) {
                return;
            }
            
            const $container = $('#' + status + '-cards');
            
            if ($container.length) {
                leads.forEach(function(lead) {
                    $container.append(createLeadCard(lead, status));
                });
                
                counts[status] = leads.length;
                totalLeadsVisible += leads.length;
            }
        });
        
        // Actualizar contadores
        Object.keys(counts).forEach(function(status) {
            $(`.pipeline-column[data-status="${status}"] .count`).text(counts[status] || 0);
        });
        
        // Actualizar total con los leads realmente visibles
        $('#total_leads').text(totalLeadsVisible);
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
        if (lead.servicio_titulo) {
            card.append($('<div class="lead-info service-info">').text('Servicio: ' + lead.servicio_titulo));
        }
        
        // Enlaces de ver detalles
        const actions = $('<div class="lead-actions">');
        const leadUrl = ltb_leads.site_url + '/lead-details/lead-' + lead.lead_id;
        actions.append($('<a>').attr('href', leadUrl).attr('target', '_blank').text('Ver lead').on('click', function(e) {
            e.stopPropagation(); // Evitar que se dispare el click del card
        }));
        
        // Si hay evento, agregar enlace al evento
        if (lead.evento_id) {
            const eventoUrl = ltb_leads.site_url + '/event-details/event-' + lead.evento_id;
            actions.append($('<a>').attr('href', eventoUrl).attr('target', '_blank').text('Ver evento').on('click', function(e) {
                e.stopPropagation(); // Evitar que se dispare el click del card
            }));
        }
        
        card.append(actions);
        
        return card;
    }
    
    // Aplicar filtros
    function applyFilters() {
        const period = $('#period_filter').val();
        let fechaInicio = '';
        let fechaFin = '';
        
        // Calcular fechas según el período seleccionado
        if (period && period !== 'custom' && period !== 'specific_month') {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth();
            const date = today.getDate();
            const day = today.getDay();
            
            switch(period) {
                case 'today':
                    fechaInicio = fechaFin = formatDate(today);
                    break;
                case 'this_week':
                    // Crear nuevas instancias de Date para evitar modificar 'today'
                    const firstDayOfWeek = new Date(year, month, date - day);
                    const lastDayOfWeek = new Date(year, month, date - day + 6);
                    fechaInicio = formatDate(firstDayOfWeek);
                    fechaFin = formatDate(lastDayOfWeek);
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
            const dateRange = $('#date_range').val();
            if (dateRange && dateRange.indexOf(' - ') !== -1) {
                const dates = dateRange.split(' - ');
                fechaInicio = dates[0];
                fechaFin = dates[1];
            }
        } else if (period === 'specific_month') {
            const mes = $('#mes_evento_basic').val();
            const anio = $('#anio_evento').val();
            
            if (mes && anio) {
                // Crear rango para el mes específico del año específico
                fechaInicio = `${anio}-${mes.padStart(2, '0')}-01`;
                const lastDay = new Date(parseInt(anio), parseInt(mes), 0).getDate();
                fechaFin = `${anio}-${mes.padStart(2, '0')}-${lastDay.toString().padStart(2, '0')}`;
            } else if (anio && !mes) {
                // Solo año seleccionado - todo el año
                fechaInicio = `${anio}-01-01`;
                fechaFin = `${anio}-12-31`;
            }
        }
        
        currentFilters = {
            search: $('#quick_search').val(),
            prioridad: $('#priority_filter').val(),
            tipo_evento: $('#event_type_filter').val() ? [$('#event_type_filter').val()] : [],
            fecha_evento_inicio: fechaInicio,  // Cambiado de fecha_inicio
            fecha_evento_fin: fechaFin,        // Cambiado de fecha_fin
            show_leads_without_event: showLeadsWithoutEvent
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
        $('#priority_filter').val('');
        $('#date_range').val('');
        $('#mes_evento_basic').val('');
        $('#anio_evento').val('');
        $('#custom_date_range').hide();
        $('#specific_month_range').hide();
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
                action: 'add_new_lead',
                form_type: includeEvent ? 'lead_and_event' : 'lead_only',
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
                action: 'update_evento_status',
                nonce: ltb_leads.nonce,
                evento_id: eventoId,
                lead_id: leadId,
                status: newStatus
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
    
    // Inicializar DateRangePicker
    function initializeDateRangePicker() {
        if (typeof daterangepicker === 'undefined' || typeof moment === 'undefined') {
            return;
        }
        
        $('#date_range').daterangepicker({
            autoUpdateInput: false,
            autoApply: true, // Auto-aplicar sin botones
            locale: {
                format: 'YYYY-MM-DD',
                separator: ' - ',
                daysOfWeek: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                           'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                firstDay: 1
            },
            // Permitir seleccionar la misma fecha (doble click)
            singleDatePicker: false
        });
        
        // Auto-aplicar filtros cuando se selecciona el rango
        $('#date_range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
            // Disparar filtros automáticamente
            applyFilters();
        });
        
        // Limpiar y aplicar cuando se cancela
        $('#date_range').on('cancel.daterangepicker', function(ev, picker) {
            $(this).val('');
            applyFilters();
        });
        
        // Detectar doble click en el mismo día
        $('#date_range').on('show.daterangepicker', function(ev, picker) {
            picker.container.find('.calendar td').on('dblclick', function() {
                const date = $(this).attr('data-title');
                if (date) {
                    const selectedDate = moment(date, 'r MMM DD, YYYY');
                    if (selectedDate.isValid()) {
                        $('#date_range').val(selectedDate.format('YYYY-MM-DD') + ' - ' + selectedDate.format('YYYY-MM-DD'));
                        picker.hide();
                        applyFilters();
                    }
                }
            });
        });
    }
    
    // Inicializar autocomplete para servicios
    function initializeServicesAutocomplete() {
        const $input = $('#servicio_autocomplete');
        
        // Verificar que el elemento existe y jQuery UI está disponible
        if ($input.length === 0) {
            return;
        }
        
        if (typeof $.fn.autocomplete === 'undefined') {
            return;
        }
        
        // Verificar que ltbLeadAdd está disponible
        if (typeof ltbLeadAdd === 'undefined') {
            return;
        }
        
        // Destruir autocomplete existente si ya está inicializado
        if ($input.hasClass('ui-autocomplete-input')) {
            $input.autocomplete('destroy');
        }
        
        $input.autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ltb_leads.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'search_services',
                        term: request.term,
                        nonce: ltbLeadAdd.nonce
                    },
                    success: function(data) {
                        if (data.success && data.data && data.data.length > 0) {
                            response(data.data);
                        } else {
                            response([{
                                label: 'No se encontraron servicios',
                                value: '',
                                disabled: true
                            }]);
                        }
                    },
                    error: function(xhr, status, error) {
                        response([{
                            label: 'Error al buscar servicios',
                            value: '',
                            disabled: true
                        }]);
                    }
                });
            },
            minLength: 2,
            delay: 300,
            select: function(event, ui) {
                if (ui.item.disabled) {
                    return false;
                }
                // Usar la URL del servicio como valor
                $(this).val(ui.item.url || ui.item.value);
                return false;
            },
            focus: function(event, ui) {
                if (ui.item.disabled) {
                    return false;
                }
                // Mostrar el label mientras navega
                $(this).val(ui.item.label || ui.item.value);
                return false;
            },
            open: function() {
                // Asegurar que el dropdown se vea correctamente
                $(this).autocomplete('widget').css('z-index', 10000);
            }
        });
        
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