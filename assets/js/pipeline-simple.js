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
        
        // Manejar selección de mes/año
        $('#month_selector, #year_selector').on('change', function() {
            const year = $('#year_selector').val();
            const month = $('#month_selector').val();
            
            if (year && month) {
                // Usar moment.js para calcular fechas
                const firstDay = moment(`${year}-${month}-01`, 'YYYY-MM-DD');
                const lastDay = moment(firstDay).endOf('month');
                
                const dateRangePicker = $('#daterange_picker').data('daterangepicker');
                if (dateRangePicker && firstDay.isValid() && lastDay.isValid()) {
                    dateRangePicker.setStartDate(firstDay);
                    dateRangePicker.setEndDate(lastDay);
                    $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                    
                    // Aplicar filtros automáticamente
                    applyFilters();
                } else {
                    console.error('DateRangePicker not available or invalid dates');
                }
            } else if (year && !month) {
                // Solo año seleccionado - todo el año
                const firstDay = moment(`${year}-01-01`, 'YYYY-MM-DD');
                const lastDay = moment(`${year}-12-31`, 'YYYY-MM-DD');
                
                const dateRangePicker = $('#daterange_picker').data('daterangepicker');
                if (dateRangePicker && firstDay.isValid() && lastDay.isValid()) {
                    dateRangePicker.setStartDate(firstDay);
                    dateRangePicker.setEndDate(lastDay);
                    $('#daterange_picker').val(firstDay.format('DD/MM/YYYY') + ' - ' + lastDay.format('DD/MM/YYYY'));
                    
                    // Aplicar filtros automáticamente
                    applyFilters();
                } else {
                    console.error('DateRangePicker not available or invalid dates');
                }
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
            const daterangePicker = $('#daterange_picker').data('daterangepicker');
            if (daterangePicker && daterangePicker.startDate && daterangePicker.endDate) {
                fechaInicio = daterangePicker.startDate.format('YYYY-MM-DD');
                fechaFin = daterangePicker.endDate.format('YYYY-MM-DD');
            }
        }
        
        currentFilters = {
            search: $('#quick_search').val(),
            tipo_evento: $('#event_type_filter').val() ? [$('#event_type_filter').val()] : [],
            prioridad: $('#priority_filter').val(),
            valor_potencial: $('#value_filter').val(),
            fecha_evento_inicio: fechaInicio,
            fecha_evento_fin: fechaFin
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
    
    // Inicializar date range picker
    function initializeDateRangePicker() {
        if (typeof moment === 'undefined') {
            console.error('Moment.js not loaded');
            return false;
        }
        
        if (!$('#daterange_picker').length) {
            console.error('DateRangePicker element not found');
            return false;
        }
        
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
                autoUpdateInput: false
            });

            // Actualizar input cuando se selecciona rango
            $('#daterange_picker').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));
                applyFilters();
            });

            // Limpiar input cuando se cancela
            $('#daterange_picker').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                applyFilters();
            });
            
            return true;
        } catch (error) {
            console.error('DateRangePicker initialization failed:', error);
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