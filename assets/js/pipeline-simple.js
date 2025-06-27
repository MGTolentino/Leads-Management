(function($) {
    'use strict';
    
    // Variables globales
    let currentFilters = {};
    let allLeads = [];
    let draggedElement = null;
    let sourceColumn = null;
    
    // Inicialización
    $(document).ready(function() {
        // Validar librerías requeridas
        if (typeof moment === 'undefined') {
            console.error('Moment.js is required but not loaded');
            return;
        }
        
        if (typeof $.fn.daterangepicker === 'undefined') {
            console.error('DateRangePicker is required but not loaded');
            return;
        }
        
        initializeEvents();
        initializeDragAndDrop();
        
        // Inicializar date range picker y proceder si es exitoso
        if (initializeDateRangePicker()) {
            loadEventTypes();
            loadPipelineData();
        } else {
            console.error('Failed to initialize DateRangePicker');
        }
    });
    
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
    
    // Limpiar filtros
    function clearFilters() {
        $('#quick_search').val('');
        $('#period_filter').val('');
        $('#event_type_filter').val('');
        $('#priority_filter').val('');
        $('#value_filter').val('');
        $('#date_from').val('');
        $('#date_to').val('');
        $('#custom_date_range').hide();
        $('#month_year_selectors').hide();
        
        const dateRangePicker = $('#daterange_picker').data('daterangepicker');
        if (dateRangePicker && typeof moment !== 'undefined') {
            dateRangePicker.setStartDate(moment());
            dateRangePicker.setEndDate(moment());
        }
        $('#daterange_picker').val('');
        currentFilters = {};
        loadPipelineData();
    }
    
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
        
        // Mostrar/ocultar rango de fechas personalizado y selectores mes/año
        $('#period_filter').on('change', function() {
            const value = $(this).val();
            
            // Ocultar todos los selectores primero
            $('#custom_date_range').hide();
            $('#month_year_selectors').hide();
            
            if (value === 'custom') {
                $('#custom_date_range').show();
            } else if (value === 'month_year') {
                $('#month_year_selectors').show().css('display', 'flex');
            } else {
                $('#daterange_picker').val('');
                $('#year_selector, #month_selector').val('');
            }
        });
        
        // Manejar selección de mes/año - MEJORADO CON MEJOR SINCRONIZACIÓN
        $('#month_selector, #year_selector, #anio_evento, #mes_evento, #mes_evento_basic').on('change.pipeline', function() {
            console.log('[PIPELINE DEBUG] Date selector changed:', this.id, this.value);
            
            // Determinar qué selectores usar
            let year, month;
            
            // Para pipeline simple
            if (this.id === 'year_selector' || this.id === 'month_selector') {
                year = $('#year_selector').val();
                month = $('#month_selector').val();
            }
            // Para filtros principales
            else if (this.id === 'anio_evento' || this.id.includes('mes_evento')) {
                year = $('#anio_evento').val();
                month = $('#mes_evento_basic').val() || $('#mes_evento').val();
                
                // Sincronizar con selectores de pipeline si existen
                if ($('#year_selector').length && $('#month_selector').length) {
                    $('#year_selector').val(year);
                    $('#month_selector').val(month);
                }
            }
            
            console.log('[PIPELINE DEBUG] Final values:', { year, month });
            
            // Timeout aumentado para asegurar inicialización completa
            setTimeout(function() {
                if (year && month) {
                    // Usar moment.js para calcular fechas
                    const firstDay = moment(`${year}-${month.padStart(2, '0')}-01`, 'YYYY-MM-DD');
                    const lastDay = moment(firstDay).endOf('month');
                    
                    console.log('[PIPELINE DEBUG] Calculated dates:', {
                        firstDay: firstDay.format('YYYY-MM-DD'),
                        lastDay: lastDay.format('YYYY-MM-DD'),
                        valid: firstDay.isValid() && lastDay.isValid()
                    });
                    
                    if (firstDay.isValid() && lastDay.isValid()) {
                        // Actualizar campos ocultos de fecha
                        $('#fecha_evento_inicio').val(firstDay.format('YYYY-MM-DD'));
                        $('#fecha_evento_fin').val(lastDay.format('YYYY-MM-DD'));
                        
                        // Actualizar daterangepicker si existe
                        const dateRangePicker = $('#daterange_picker').data('daterangepicker');
                        if (dateRangePicker && typeof dateRangePicker.setStartDate === 'function') {
                            try {
                                dateRangePicker.setStartDate(firstDay);
                                dateRangePicker.setEndDate(lastDay);
                                $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                                
                                console.log('[PIPELINE DEBUG] DateRangePicker updated successfully');
                            } catch (error) {
                                console.error('[PIPELINE DEBUG] Error setting daterangepicker dates:', error);
                                // Fallback: set the value directly
                                $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                            }
                        } else {
                            console.warn('[PIPELINE DEBUG] DateRangePicker not available, setting value directly');
                            if ($('#daterange_picker').length) {
                                $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                            }
                        }
                        
                        // Trigger main filter system update if available
                        if (typeof window.actualizarVisualizacionFechaEvento === 'function') {
                            window.actualizarVisualizacionFechaEvento();
                        }
                        
                        // Actualizar currentFilters directamente y aplicar
                        currentFilters.fecha_evento_inicio = firstDay.format('YYYY-MM-DD');
                        currentFilters.fecha_evento_fin = lastDay.format('YYYY-MM-DD');
                        
                        console.log('[PIPELINE DEBUG] Updated currentFilters:', currentFilters);
                        
                        // Aplicar filtros directamente
                        loadPipelineData();
                    } else {
                        console.error('[PIPELINE DEBUG] Invalid dates generated from month/year selection');
                    }
                } else if (year && !month) {
                    // Solo año seleccionado - todo el año
                    const firstDay = moment(`${year}-01-01`, 'YYYY-MM-DD');
                    const lastDay = moment(`${year}-12-31`, 'YYYY-MM-DD');
                    
                    console.log('[PIPELINE DEBUG] Year-only selection:', {
                        firstDay: firstDay.format('YYYY-MM-DD'),
                        lastDay: lastDay.format('YYYY-MM-DD')
                    });
                    
                    if (firstDay.isValid() && lastDay.isValid()) {
                        // Actualizar campos ocultos de fecha
                        $('#fecha_evento_inicio').val(firstDay.format('YYYY-MM-DD'));
                        $('#fecha_evento_fin').val(lastDay.format('YYYY-MM-DD'));
                        
                        // Actualizar daterangepicker
                        const dateRangePicker = $('#daterange_picker').data('daterangepicker');
                        if (dateRangePicker && typeof dateRangePicker.setStartDate === 'function') {
                            try {
                                dateRangePicker.setStartDate(firstDay);
                                dateRangePicker.setEndDate(lastDay);
                                $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                            } catch (error) {
                                console.error('[PIPELINE DEBUG] Error setting daterangepicker dates for year:', error);
                                $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                            }
                        } else if ($('#daterange_picker').length) {
                            $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                        }
                        
                        // Trigger main filter system update if available
                        if (typeof window.actualizarVisualizacionFechaEvento === 'function') {
                            window.actualizarVisualizacionFechaEvento();
                        }
                        
                        // Actualizar currentFilters directamente y aplicar para año
                        currentFilters.fecha_evento_inicio = firstDay.format('YYYY-MM-DD');
                        currentFilters.fecha_evento_fin = lastDay.format('YYYY-MM-DD');
                        
                        console.log('[PIPELINE DEBUG] Updated currentFilters for year:', currentFilters);
                        
                        // Aplicar filtros directamente
                        loadPipelineData();
                    } else {
                        console.error('[PIPELINE DEBUG] Invalid dates generated from year selection');
                    }
                }
            }, 100); // Minimal delay for DOM updates
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
        
        console.log('[PIPELINE DEBUG] Loading pipeline data with filters:', currentFilters);
        
        $.ajax({
            url: ltb_leads.ajax_url,
            type: 'POST',
            data: {
                action: 'get_leads_by_status',
                nonce: ltb_leads.nonce,
                filters: currentFilters
            },
            success: function(response) {
                console.log('[PIPELINE DEBUG] AJAX response received:', response);
                if (response.success) {
                    renderPipeline(response.data);
                    allLeads = response.data;
                    console.log('[PIPELINE DEBUG] Pipeline rendered successfully');
                } else {
                    console.error('[PIPELINE DEBUG] AJAX response failed:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('[PIPELINE DEBUG] AJAX request failed:', { xhr, status, error });
                alert('Error al cargar los datos');
            },
            complete: function() {
                $('.pipeline-board').removeClass('loading');
                console.log('[PIPELINE DEBUG] AJAX request completed');
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
        if (lead.servicio_titulo) {
            card.append($('<div class="lead-info">').text('Servicio: ' + lead.servicio_titulo));
        } else if (lead.evento_servicio_de_interes) {
            card.append($('<div class="lead-info">').text('Servicio: ' + lead.evento_servicio_de_interes));
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
        
        console.log('[PIPELINE DEBUG] applyFilters called with period:', period);
        console.log('[PIPELINE DEBUG] Current currentFilters before processing:', currentFilters);
        
        // Si ya hay fechas en currentFilters, priorizarlas sobre el period selector
        if (currentFilters.fecha_evento_inicio && currentFilters.fecha_evento_fin) {
            fechaInicio = currentFilters.fecha_evento_inicio;
            fechaFin = currentFilters.fecha_evento_fin;
            console.log('[PIPELINE DEBUG] Using existing dates from currentFilters:', { fechaInicio, fechaFin });
        }
        // Calcular fechas según el período seleccionado solo si no hay fechas ya establecidas
        else if (period && period !== 'custom') {
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
                case 'january':
                case 'february':
                case 'march':
                case 'april':
                case 'may':
                case 'june':
                case 'july':
                case 'august':
                case 'september':
                case 'october':
                case 'november':
                case 'december':
                    const monthMap = {
                        'january': 0, 'february': 1, 'march': 2, 'april': 3,
                        'may': 4, 'june': 5, 'july': 6, 'august': 7,
                        'september': 8, 'october': 9, 'november': 10, 'december': 11
                    };
                    const monthIndex = monthMap[period];
                    fechaInicio = formatDate(new Date(year, monthIndex, 1));
                    fechaFin = formatDate(new Date(year, monthIndex + 1, 0));
                    break;
            }
        } else if (period === 'custom') {
            // Priorizar campos ocultos de fecha para mejor sincronización
            const hiddenStartDate = $('#fecha_evento_inicio').val();
            const hiddenEndDate = $('#fecha_evento_fin').val();
            
            if (hiddenStartDate && hiddenEndDate) {
                fechaInicio = hiddenStartDate;
                fechaFin = hiddenEndDate;
                console.log('[PIPELINE DEBUG] Using hidden date fields:', { fechaInicio, fechaFin });
            } else {
                // Fallback a daterangepicker
                const daterangePicker = $('#daterange_picker').data('daterangepicker');
                const inputValue = $('#daterange_picker').val();
                
                if (daterangePicker && daterangePicker.startDate && daterangePicker.endDate) {
                    fechaInicio = daterangePicker.startDate.format('YYYY-MM-DD');
                    fechaFin = daterangePicker.endDate.format('YYYY-MM-DD');
                    console.log('[PIPELINE DEBUG] Using daterangepicker data:', { fechaInicio, fechaFin });
                } else if (inputValue && inputValue.trim()) {
                // Parse input value as fallback
                const parts = inputValue.split(' - ');
                if (parts.length === 2) {
                    const startMoment = moment(parts[0], 'DD/MM/YYYY');
                    const endMoment = moment(parts[1], 'DD/MM/YYYY');
                    if (startMoment.isValid() && endMoment.isValid()) {
                        fechaInicio = startMoment.format('YYYY-MM-DD');
                        fechaFin = endMoment.format('YYYY-MM-DD');
                    }
                } else if (parts.length === 1 && parts[0].trim()) {
                    // Single date
                    const singleMoment = moment(parts[0].trim(), 'DD/MM/YYYY');
                    if (singleMoment.isValid()) {
                        fechaInicio = fechaFin = singleMoment.format('YYYY-MM-DD');
                    }
                }
            }
        }
        
        // Preserve existing date values if they exist and no new dates were calculated
        const finalFechaInicio = fechaInicio || currentFilters.fecha_evento_inicio || '';
        const finalFechaFin = fechaFin || currentFilters.fecha_evento_fin || '';
        
        currentFilters = {
            search: $('#quick_search').val(),
            tipo_evento: $('#event_type_filter').val() ? [$('#event_type_filter').val()] : [],
            prioridad: $('#priority_filter').val(),
            valor_potencial: $('#value_filter').val(),
            fecha_evento_inicio: finalFechaInicio,
            fecha_evento_fin: finalFechaFin
        };
        
        console.log('[PIPELINE DEBUG] Final filters after applyFilters:', currentFilters);
        
        loadPipelineData();
    }
    
    // Formatear fecha para envío
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
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
                if (response.success) {
                    const $select = $('#event_type_filter');
                    $select.find('option:not(:first)').remove(); // Mantener la primera opción
                    
                    response.data.forEach(function(tipo) {
                        $select.append($('<option>', {
                            value: tipo.value,
                            text: tipo.label
                        }));
                    });
                }
            },
            error: function() {
                console.log('Error al cargar tipos de evento');
            }
        });
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
                action: 'update_evento_status',
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
    
    // Inicializar date range picker - MEJORADO
    function initializeDateRangePicker() {
        console.log('[PIPELINE DEBUG] Initializing DateRangePicker...');
        
        if (typeof moment === 'undefined') {
            console.error('[PIPELINE DEBUG] Moment.js not loaded');
            return false;
        }
        
        if (!$('#daterange_picker').length) {
            console.log('[PIPELINE DEBUG] DateRangePicker element not found - this is OK for some views');
            return true; // Return true to continue initialization even without daterangepicker
        }
        
        console.log('[PIPELINE DEBUG] DateRangePicker element found, proceeding with initialization...');
        
        try {
            $('#daterange_picker').daterangepicker({
                startDate: moment(),
                endDate: moment(),
                locale: {
                    format: 'DD/MM/YYYY',
                    separator: ' - ',
                    applyLabel: 'Aplicar',
                    cancelLabel: 'Cancelar',
                    fromLabel: 'Desde',
                    toLabel: 'Hasta',
                    customRangeLabel: 'Rango personalizado',
                    daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    firstDay: 1
                },
                opens: 'left',
                drops: 'down',
                showDropdowns: true,
                showWeekNumbers: false,
                showISOWeekNumbers: false,
                autoUpdateInput: true,
                autoApply: true,
                singleDatePicker: false,
                alwaysShowCalendars: true,
                ranges: {
                    'Hoy': [moment(), moment()],
                    'Ayer': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Últimos 7 días': [moment().subtract(6, 'days'), moment()],
                    'Últimos 30 días': [moment().subtract(29, 'days'), moment()],
                    'Este mes': [moment().startOf('month'), moment().endOf('month')],
                    'Mes pasado': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            });

            // Actualizar input cuando se selecciona rango - MEJORADO
            $('#daterange_picker').on('apply.daterangepicker', function(ev, picker) {
                console.log('[PIPELINE DEBUG] DateRangePicker apply event:', {
                    startDate: picker.startDate.format('YYYY-MM-DD'),
                    endDate: picker.endDate.format('YYYY-MM-DD'),
                    singleDate: picker.singleDatePicker
                });
                
                // Actualizar campos ocultos para sincronización con filtros principales
                if (picker.startDate && picker.endDate) {
                    $('#fecha_evento_inicio').val(picker.startDate.format('YYYY-MM-DD'));
                    $('#fecha_evento_fin').val(picker.endDate.format('YYYY-MM-DD'));
                    
                    // Actualizar selectores de año/mes si la fecha es del mismo mes
                    if (picker.startDate.isSame(picker.endDate, 'month')) {
                        const year = picker.startDate.format('YYYY');
                        const month = picker.startDate.format('MM');
                        
                        $('#anio_evento').val(year);
                        $('#mes_evento, #mes_evento_basic').val(month);
                        $('#year_selector').val(year);
                        $('#month_selector').val(month);
                        
                        console.log('[PIPELINE DEBUG] Synchronized selectors:', { year, month });
                    }
                }
                
                // Actualizar valor mostrado
                if (picker.singleDatePicker) {
                    $(this).val(picker.startDate.format('DD/MM/YYYY'));
                } else {
                    $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
                }
                
                // Trigger main filter system update if available
                if (typeof window.actualizarVisualizacionFechaEvento === 'function') {
                    window.actualizarVisualizacionFechaEvento();
                }
                
                applyFilters();
            });

            // Limpiar input cuando se cancela
            $('#daterange_picker').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                applyFilters();
            });
            
            // Enable double-click for single date selection
            $('#daterange_picker').on('dblclick', function() {
                const picker = $(this).data('daterangepicker');
                if (picker) {
                    picker.singleDatePicker = !picker.singleDatePicker;
                    picker.updateView();
                    // Update placeholder text
                    if (picker.singleDatePicker) {
                        $(this).attr('placeholder', 'Seleccionar fecha única (doble-click para rango)');
                    } else {
                        $(this).attr('placeholder', 'Seleccionar rango de fechas (doble-click para fecha única)');
                    }
                }
            });
            
            // Set initial placeholder
            $('#daterange_picker').attr('placeholder', 'Seleccionar rango de fechas (doble-click para fecha única)');
            
            console.log('[PIPELINE DEBUG] DateRangePicker initialized successfully');
            
            // Exponer la función para que pueda ser llamada desde filters.js
            window.updateDaterangepicker = function() {
                const startDate = $('#fecha_evento_inicio').val();
                const endDate = $('#fecha_evento_fin').val();
                
                if (startDate && endDate) {
                    const startMoment = moment(startDate, 'YYYY-MM-DD');
                    const endMoment = moment(endDate, 'YYYY-MM-DD');
                    
                    if (startMoment.isValid() && endMoment.isValid()) {
                        const drpInstance = $('#daterange_picker').data('daterangepicker');
                        if (drpInstance) {
                            drpInstance.setStartDate(startMoment);
                            drpInstance.setEndDate(endMoment);
                            $('#daterange_picker').val(startMoment.format('DD/MM/YYYY') + ' - ' + endMoment.format('DD/MM/YYYY'));
                        }
                    }
                }
            };
            
            return true;
        } catch (error) {
            console.error('[PIPELINE DEBUG] DateRangePicker initialization failed:', error);
            return false;
        }
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
    
    }
    
})(jQuery);