jQuery(function($) {
    // Estado de los filtros
    let currentFilters = {
        fecha_ingreso_inicio: '',
        fecha_ingreso_fin: '',
        fecha_evento_inicio: '',
        fecha_evento_fin: '',
        tipo_evento: [],
        status: [],
        invitados: '',
        prioridad: '',
        valor_potencial: '',
        probabilidad: '',
        responsable: [],
        ultima_interaccion: '',
        tiempo_sin_actividad: '',
        proxima_accion: '',
        fuente: [],
        campana: [],
        ubicacion: [],
        industria: [],
        estado_propuesta: '',
        rango_cotizacion: '',
        temporada: '',
        servicios_requeridos: [],
        venue: [],
        etiquetas: [],
        search: '',
        orderby: 'fecha_solicitud',
        order: 'DESC',
        paged: 1,
        per_page: 25,
    };
    
    // Elementos del DOM
    const $filterContainer = $('#filters_container');
    const $toggleFiltersBtn = $('#toggle_filters_btn');
    const $applyFiltersBtn = $('#aplicar_filtros');
    const $clearFiltersBtn = $('#limpiar_filtros');
    const $clearBasicFiltersBtn = $('#limpiar_filtros_basicos');
    const $activeFiltersCount = $('#active_filters_count');
    const $activeFiltersContainer = $('#filtros_activos');
    
    // Inicializar Select2
    $('.select2-multi').select2({
        placeholder: 'Seleccionar...',
        allowClear: true,
        closeOnSelect: false
    });
    
    // Inicializar Select2 para etiquetas (con opción de agregar nuevas)
    $('.select2-tags').select2({
        placeholder: 'Seleccionar o crear...',
        allowClear: true,
        closeOnSelect: false,
        tags: true
    });
    
    // Inicializar secciones colapsables con comportamiento de acordeón
    $('.section-header').on('click', function() {
        const target = $(this).data('target');
        const $content = $('#' + target);
        const $icon = $(this).find('.toggle-icon');
        const $allHeaders = $('.section-header');
        const $allContents = $('.section-content');
        const $allIcons = $('.section-header .toggle-icon');
        
        // Si ya está abierto, simplemente ciérralo
        if ($content.hasClass('open')) {
            $content.slideUp(200, function() {
                $content.removeClass('open');
                $icon.removeClass('open');
                $(this).removeClass('active');
            });
            return;
        }
        
        // Cierra todos los contenidos abiertos
        $allContents.slideUp(200, function() {
            $allContents.removeClass('open');
        });
        
        // Resetea todos los headers y iconos
        $allHeaders.removeClass('active');
        $allIcons.removeClass('open');
        
        // Activa el header actual
        $(this).addClass('active');
        
        // Abre solo el contenido seleccionado
        $content.slideDown(200, function() {
            $content.addClass('open');
            $icon.addClass('open');
        });
    });
    
    // Configuración común para datepickers
    const datepickerConfig = {
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        yearRange: '2000:+10',
        closeText: 'Cerrar',
        currentText: 'Hoy',
        monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
        dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
        dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
        firstDay: 1, // Semana comienza el lunes
        showWeek: true,
        weekHeader: 'Sm'
    };
    
    // Inicializar datepickers de fecha de evento
    $('#fecha_evento_inicio, #fecha_evento_fin').datepicker({
        ...datepickerConfig,
        onSelect: function(dateText, inst) {
            // Actualizar campo relacionado si es necesario
            if (this.id === 'fecha_evento_inicio') {
                const fechaInicio = $(this).datepicker('getDate');
                const fechaFin = $('#fecha_evento_fin').datepicker('getDate');
                
                if (fechaFin && fechaInicio > fechaFin) {
                    $('#fecha_evento_fin').datepicker('setDate', fechaInicio);
                }
            } else if (this.id === 'fecha_evento_fin') {
                const fechaFin = $(this).datepicker('getDate');
                const fechaInicio = $('#fecha_evento_inicio').datepicker('getDate');
                
                if (fechaInicio && fechaFin < fechaInicio) {
                    $('#fecha_evento_inicio').datepicker('setDate', fechaFin);
                }
            }
            
            // Actualizar visualización amigable
            actualizarVisualizacionFechaEvento();
        }
    });
    
    // Inicializar datepickers de fecha de ingreso
    $('#fecha_ingreso_inicio, #fecha_ingreso_fin').datepicker({
        ...datepickerConfig,
        maxDate: new Date(), // Solo fechas hasta hoy
        onSelect: function(dateText, inst) {
            // Actualizar campo relacionado si es necesario
            if (this.id === 'fecha_ingreso_inicio') {
                const fechaInicio = $(this).datepicker('getDate');
                const fechaFin = $('#fecha_ingreso_fin').datepicker('getDate');
                
                if (fechaFin && fechaInicio > fechaFin) {
                    $('#fecha_ingreso_fin').datepicker('setDate', fechaInicio);
                }
            } else if (this.id === 'fecha_ingreso_fin') {
                const fechaFin = $(this).datepicker('getDate');
                const fechaInicio = $('#fecha_ingreso_inicio').datepicker('getDate');
                
                if (fechaInicio && fechaFin < fechaInicio) {
                    $('#fecha_ingreso_inicio').datepicker('setDate', fechaFin);
                }
            }
        }
    });
    
    // Función para formatear fecha en español
    function formatearFechaAmigable(fecha) {
        if (!fecha) return '';
        
        const meses = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
        ];
        
        // Para formato YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
            const partes = fecha.split('-');
            const year = parseInt(partes[0]);
            const month = parseInt(partes[1]) - 1;
            const day = parseInt(partes[2]);
            
            return `${day} de ${meses[month]} de ${year}`;
        }
        
        // Para formato YYYY-MM (mes completo)
        if (/^\d{4}-\d{2}$/.test(fecha)) {
            const partes = fecha.split('-');
            const year = parseInt(partes[0]);
            const month = parseInt(partes[1]) - 1;
            
            return `${meses[month]} de ${year}`;
        }
        
        // Para formato YYYY (año completo)
        if (/^\d{4}$/.test(fecha)) {
            return `Año ${fecha}`;
        }
        
        return fecha;
    }
    
    // Función para actualizar visualización de fecha de evento
    function actualizarVisualizacionFechaEvento() {
        const fechaInicio = $('#fecha_evento_inicio').val();
        const fechaFin = $('#fecha_evento_fin').val();
        
        let textoDisplay = '';
        
        if (fechaInicio && fechaFin) {
            if (fechaInicio === fechaFin) {
                textoDisplay = formatearFechaAmigable(fechaInicio);
            } else {
                textoDisplay = `Del ${formatearFechaAmigable(fechaInicio)} al ${formatearFechaAmigable(fechaFin)}`;
            }
        } else if (fechaInicio) {
            textoDisplay = `Desde ${formatearFechaAmigable(fechaInicio)}`;
        } else if (fechaFin) {
            textoDisplay = `Hasta ${formatearFechaAmigable(fechaFin)}`;
        }
        
        $('#fecha_evento_display').text(textoDisplay);
    }
    
    // Selectores de año y mes para fecha de evento
    $('#anio_evento').on('change', function() {
        const anio = $(this).val();
        const mes = $('#mes_evento_basic').val() || $('#mes_evento').val();
        
        if (anio) {
            if (mes) {
                // Si también hay mes seleccionado, poner el rango del mes completo
                const ultimoDia = new Date(anio, parseInt(mes), 0).getDate();
                $('#fecha_evento_inicio').val(`${anio}-${mes}-01`);
                $('#fecha_evento_fin').val(`${anio}-${mes}-${ultimoDia}`);
            } else {
                // Solo año, poner el rango del año completo
                $('#fecha_evento_inicio').val(`${anio}-01-01`);
                $('#fecha_evento_fin').val(`${anio}-12-31`);
            }
            actualizarVisualizacionFechaEvento();
        }
    });
    
    $('#mes_evento, #mes_evento_basic').on('change', function() {
        const mes = $(this).val();
        const anio = $('#anio_evento').val() || new Date().getFullYear();
        
        if (mes) {
            const ultimoDia = new Date(anio, parseInt(mes), 0).getDate();
            $('#fecha_evento_inicio').val(`${anio}-${mes}-01`);
            $('#fecha_evento_fin').val(`${anio}-${mes}-${ultimoDia}`);
            
            // Sincronizar los dos selectores de mes
            if (this.id === 'mes_evento') {
                $('#mes_evento_basic').val(mes);
            } else {
                $('#mes_evento').val(mes);
            }
            
            // Si no hay año seleccionado, seleccionarlo automáticamente
            if (!$('#anio_evento').val()) {
                $('#anio_evento').val(anio);
            }
            
            actualizarVisualizacionFechaEvento();
        }
    });
    
    // Selectores de año y mes para fecha de ingreso
    $('#anio_ingreso').on('change', function() {
        const anio = $(this).val();
        const mes = $('#mes_ingreso').val();
        
        if (anio) {
            if (mes) {
                // Si también hay mes seleccionado, poner el rango del mes completo
                const ultimoDia = new Date(anio, parseInt(mes), 0).getDate();
                $('#fecha_ingreso_inicio').val(`${anio}-${mes}-01`);
                $('#fecha_ingreso_fin').val(`${anio}-${mes}-${ultimoDia}`);
            } else {
                // Solo año, poner el rango del año completo
                $('#fecha_ingreso_inicio').val(`${anio}-01-01`);
                $('#fecha_ingreso_fin').val(`${anio}-12-31`);
            }
            // Actualizar la visualización amigable
            actualizarVisualizacionFechaIngreso();
        }
    });
    
    $('#mes_ingreso').on('change', function() {
        const mes = $(this).val();
        const anio = $('#anio_ingreso').val() || new Date().getFullYear();
        
        if (mes) {
            const ultimoDia = new Date(anio, parseInt(mes), 0).getDate();
            $('#fecha_ingreso_inicio').val(`${anio}-${mes}-01`);
            $('#fecha_ingreso_fin').val(`${anio}-${mes}-${ultimoDia}`);
            
            // Si no hay año seleccionado, seleccionarlo automáticamente
            if (!$('#anio_ingreso').val()) {
                $('#anio_ingreso').val(anio);
            }
            
            // Actualizar la visualización amigable
            actualizarVisualizacionFechaIngreso();
        }
    });
    
    // Función para actualizar visualización de fecha de ingreso
    function actualizarVisualizacionFechaIngreso() {
        const fechaInicio = $('#fecha_ingreso_inicio').val();
        const fechaFin = $('#fecha_ingreso_fin').val();
        
        let textoDisplay = '';
        
        if (fechaInicio && fechaFin) {
            if (fechaInicio === fechaFin) {
                textoDisplay = formatearFechaAmigable(fechaInicio);
            } else {
                textoDisplay = `Del ${formatearFechaAmigable(fechaInicio)} al ${formatearFechaAmigable(fechaFin)}`;
            }
        } else if (fechaInicio) {
            textoDisplay = `Desde ${formatearFechaAmigable(fechaInicio)}`;
        } else if (fechaFin) {
            textoDisplay = `Hasta ${formatearFechaAmigable(fechaFin)}`;
        }
        
        if ($('#fecha_ingreso_display').length) {
            $('#fecha_ingreso_display').text(textoDisplay);
        } else {
            // Si no existe el elemento, crearlo después de los inputs de fecha
            $('.date-range-inputs').first().after('<div id="fecha_ingreso_display" class="date-friendly-format">' + textoDisplay + '</div>');
        }
    }
    
    // Funciones para los botones de presets de fecha
    function establecerHoy() {
        const hoy = new Date();
        const fechaFormateada = $.datepicker.formatDate('yy-mm-dd', hoy);
        
        $('#fecha_evento_inicio').val(fechaFormateada);
        $('#fecha_evento_fin').val(fechaFormateada);
        
        // Limpiar selectores de año y mes
        $('#anio_evento, #mes_evento').val('');
        
        actualizarVisualizacionFechaEvento();
    }
    
    function establecerSemana() {
        const hoy = new Date();
        const inicioSemana = new Date(hoy);
        const diaSemana = hoy.getDay();
        
        // Ajustar al lunes de esta semana
        inicioSemana.setDate(hoy.getDate() - diaSemana + (diaSemana === 0 ? -6 : 1));
        
        // Calcular fin de semana (domingo)
        const finSemana = new Date(inicioSemana);
        finSemana.setDate(inicioSemana.getDate() + 6);
        
        $('#fecha_evento_inicio').val($.datepicker.formatDate('yy-mm-dd', inicioSemana));
        $('#fecha_evento_fin').val($.datepicker.formatDate('yy-mm-dd', finSemana));
        
        // Limpiar selectores de año y mes
        $('#anio_evento, #mes_evento').val('');
        
        actualizarVisualizacionFechaEvento();
    }
    
    function establecerMes() {
        const hoy = new Date();
        const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        const finMes = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
        
        $('#fecha_evento_inicio').val($.datepicker.formatDate('yy-mm-dd', inicioMes));
        $('#fecha_evento_fin').val($.datepicker.formatDate('yy-mm-dd', finMes));
        
        // Actualizar selectores de año y mes
        $('#anio_evento').val(hoy.getFullYear());
        $('#mes_evento').val(String(hoy.getMonth() + 1).padStart(2, '0'));
        
        actualizarVisualizacionFechaEvento();
    }
    
    function establecerAnio() {
        const hoy = new Date();
        const inicioAnio = new Date(hoy.getFullYear(), 0, 1);
        const finAnio = new Date(hoy.getFullYear(), 11, 31);
        
        $('#fecha_evento_inicio').val($.datepicker.formatDate('yy-mm-dd', inicioAnio));
        $('#fecha_evento_fin').val($.datepicker.formatDate('yy-mm-dd', finAnio));
        
        // Actualizar selector de año y limpiar mes
        $('#anio_evento').val(hoy.getFullYear());
        $('#mes_evento').val('');
        
        actualizarVisualizacionFechaEvento();
    }
    
    // Asignar eventos a los botones de presets
    $('#btn_hoy').on('click', establecerHoy);
    $('#btn_semana').on('click', establecerSemana);
    $('#btn_mes').on('click', establecerMes);
    $('#btn_anio').on('click', establecerAnio);
    
    // Toggle de visibilidad de filtros en móvil
    $toggleFiltersBtn.on('click', function() {
        $filterContainer.slideToggle(300);
        $(this).toggleClass('active');
    });
    
    // Función para aplicar filtros
    $applyFiltersBtn.on('click', function(e) {
        e.preventDefault();
        
        // Recopilar valores de los filtros de categorización
        currentFilters.fecha_ingreso_inicio = $('#fecha_ingreso_inicio').val();
        currentFilters.fecha_ingreso_fin = $('#fecha_ingreso_fin').val();
        currentFilters.fecha_evento_inicio = $('#fecha_evento_inicio').val();
        currentFilters.fecha_evento_fin = $('#fecha_evento_fin').val();
        currentFilters.tipo_evento = $('#tipo_evento_filter').val() || [];
        currentFilters.status = $('#status_filter').val() || [];
        currentFilters.invitados = $('#invitados_filter').val();
        
        // Recopilar valores de los filtros de calificación
        currentFilters.prioridad = $('#prioridad_filter').val();
        currentFilters.valor_potencial = $('#valor_potencial_filter').val();
        currentFilters.probabilidad = $('#probabilidad_filter').val();
        
        // Recopilar valores de los filtros de seguimiento
        currentFilters.responsable = $('#responsable_filter').val() || [];
        currentFilters.ultima_interaccion = $('#ultima_interaccion_filter').val();
        currentFilters.tiempo_sin_actividad = $('#tiempo_sin_actividad_filter').val();
        currentFilters.proxima_accion = $('#proxima_accion_filter').val();
        
        // Recopilar valores de los filtros de origen
        currentFilters.fuente = $('#fuente_filter').val() || [];
        currentFilters.campana = $('#campana_filter').val() || [];
        
        // Recopilar valores de los filtros demográficos
        currentFilters.ubicacion = $('#ubicacion_filter').val() || [];
        currentFilters.industria = $('#industria_filter').val() || [];
        
        // Recopilar valores de los filtros de conversión
        currentFilters.estado_propuesta = $('#estado_propuesta_filter').val();
        currentFilters.rango_cotizacion = $('#rango_cotizacion_filter').val();
        
        // Recopilar valores de los filtros específicos de eventos
        currentFilters.temporada = $('#temporada_filter').val();
        currentFilters.servicios_requeridos = $('#servicios_requeridos_filter').val() || [];
        currentFilters.venue = $('#venue_filter').val() || [];
        
        // Recopilar valores de las etiquetas
        currentFilters.etiquetas = $('#etiquetas_filter').val() || [];
        
        // Otros filtros
        currentFilters.search = $('#search_leads').val();
        currentFilters.orderby = $('#ordenamiento').val();
        currentFilters.order = $('#orden').val();
        currentFilters.paged = 1;
        
        // Actualizar tabla
        updateTable();
        
        // Actualizar vista pipeline si está visible
        if ($('.view-container[data-view="pipeline"]').is(':visible')) {
            if (typeof window.refreshPipelineView === 'function') {
                window.refreshPipelineView();
            }
        }
    });
    
    // Función para limpiar todos los filtros
    $clearFiltersBtn.on('click', function(e) {
        e.preventDefault();
        limpiarTodosFiltros();
    });
    
    // Función para limpiar solo los filtros básicos
    $clearBasicFiltersBtn.on('click', function(e) {
        e.preventDefault();
        limpiarFiltrosBasicos();
    });
    
    // Función para limpiar todos los filtros
    function limpiarTodosFiltros() {
        
        // Limpiar campos de formulario - Categorización
        $('#fecha_ingreso_inicio, #fecha_ingreso_fin, #fecha_evento_inicio, #fecha_evento_fin').val('');
        $('#anio_evento, #mes_evento, #anio_ingreso, #mes_ingreso').val('');
        $('#tipo_evento_filter, #status_filter').val(null).trigger('change');
        $('#invitados_filter').val('');
        
        // Limpiar campos de formulario - Calificación
        $('#prioridad_filter, #valor_potencial_filter, #probabilidad_filter').val('');
        
        // Limpiar campos de formulario - Seguimiento
        $('#responsable_filter').val(null).trigger('change');
        $('#ultima_interaccion_filter, #tiempo_sin_actividad_filter, #proxima_accion_filter').val('');
        
        // Limpiar campos de formulario - Origen
        $('#fuente_filter, #campana_filter').val(null).trigger('change');
        
        // Limpiar campos de formulario - Demográficos
        $('#ubicacion_filter, #industria_filter').val(null).trigger('change');
        
        // Limpiar campos de formulario - Conversión
        $('#estado_propuesta_filter, #rango_cotizacion_filter').val('');
        
        // Limpiar campos de formulario - Eventos específicos
        $('#temporada_filter').val('');
        $('#servicios_requeridos_filter, #venue_filter').val(null).trigger('change');
        
        // Limpiar campos de formulario - Etiquetas
        $('#etiquetas_filter').val(null).trigger('change');
        
        // Limpiar otros campos
        $('#search_leads').val('');
        $('#ordenamiento').val('fecha_solicitud');
        $('#orden').val('DESC');
        $('#fecha_evento_display').text('');
        
        // Reiniciar filtros
        currentFilters = {
            fecha_ingreso_inicio: '',
            fecha_ingreso_fin: '',
            fecha_evento_inicio: '',
            fecha_evento_fin: '',
            tipo_evento: [],
            status: [],
            invitados: '',
            prioridad: '',
            valor_potencial: '',
            probabilidad: '',
            responsable: [],
            ultima_interaccion: '',
            tiempo_sin_actividad: '',
            proxima_accion: '',
            fuente: [],
            campana: [],
            ubicacion: [],
            industria: [],
            estado_propuesta: '',
            rango_cotizacion: '',
            temporada: '',
            servicios_requeridos: [],
            venue: [],
            etiquetas: [],
            search: '',
            orderby: 'fecha_solicitud',
            order: 'DESC',
            paged: 1,
            per_page: currentFilters.per_page
        };
        
        // Actualizar tabla
        updateTable();
        
        // Actualizar vista pipeline si está visible
        if ($('.view-container[data-view="pipeline"]').is(':visible')) {
            if (typeof window.refreshPipelineView === 'function') {
                window.refreshPipelineView();
            }
        }
        
        // Limpiar chips de filtros activos
        actualizarChipsFiltrosActivos([]);
        actualizarContadorFiltros(0);
    }
    
    // Función para limpiar solo los filtros básicos
    function limpiarFiltrosBasicos() {
        // Limpiar fechas de evento
        $('#fecha_evento_inicio, #fecha_evento_fin').val('');
        $('#anio_evento, #mes_evento_basic, #mes_evento').val('');
        
        // Limpiar búsqueda
        $('#search_leads').val('');
        
        // Limpiar status y tipo de evento
        $('#status_filter, #tipo_evento_filter').val(null).trigger('change');
        
        // Actualizar filtros relacionados
        currentFilters.fecha_evento_inicio = '';
        currentFilters.fecha_evento_fin = '';
        currentFilters.search = '';
        currentFilters.status = [];
        currentFilters.tipo_evento = [];
        currentFilters.paged = 1;
        
        // Actualizar tabla
        updateTable();
        
        // Actualizar vista pipeline si está visible
        if ($('.view-container[data-view="pipeline"]').is(':visible')) {
            if (typeof window.refreshPipelineView === 'function') {
                window.refreshPipelineView();
            }
        }
    }
    
    // Actualizar tabla con los resultados
    function updateTable() {
        const tableBody = $('.leads-table tbody');
        const cardsContainer = $('.leads-cards');
        const loadingOverlay = $('.loading-overlay');
        
        loadingOverlay.show();
        
        $.ajax({
            url: ltbLeadsFilters.ajaxurl,
            type: 'POST',
            data: {
                action: 'filter_leads',
                nonce: ltbLeadsFilters.nonce,
                ...currentFilters
            },
            success: function(response) {
                if (response.success) {
                    // Limpiar contenedores
                    tableBody.empty();
                    cardsContainer.empty();
                    
                    // Actualizar chips de filtros activos
                    if (response.data.applied_filters) {
                        actualizarChipsFiltrosActivos(response.data.applied_filters);
                        actualizarContadorFiltros(response.data.applied_filters.length);
                    }
                    
                    // Renderizar datos
                    response.data.data.forEach(function(lead) {
                        if (lead.is_obsolete_status) {
                            console.log('Lead con estado obsoleto:', lead);
                        }
                        
                        // Generar slug para la URL
                        const nombreCompleto = `${lead.lead_nombre} ${lead.lead_apellido}`.toLowerCase()
                            .normalize("NFD")
                            .replace(/[\u0300-\u036f]/g, "")
                            .replace(/[^a-z0-9\s]/g, "")
                            .replace(/\s+/g, "-");
                        
                        const editUrl = `/lead-details/${nombreCompleto}-${lead.lead_id}`;
                        
                        // Preparar celdas condicionales
                        const statusCell = lead.evento_status ? 
                            `<span class="status-badge ${lead.evento_status}">${lead.evento_status}</span>` : 
                            '-';
                        
                        let servicioCell = '-';
                        if (lead.tipo_de_evento && lead.servicio_url) {
                            servicioCell = `
                                <a href="${lead.servicio_url}" class="link-servicio" target="_blank">
                                    ${lead.servicio_titulo || 'Ver servicio'}
                                </a>
                            `;
                        }
                        
                        // Generar fila para la tabla
                        const rowClass = lead.is_obsolete_status ? 'obsolete-status-row' : '';
                        const row = `
                            <tr class="${rowClass}">
                                <td>${lead.fecha_solicitud}</td>
                                <td>${lead.is_obsolete_status ? 
                                    `<span class="status-warning-icon dashicons dashicons-warning" title="Estado obsoleto o sin evento"></span> ` : ''}
                                    ${lead.lead_nombre} ${lead.lead_apellido}</td>
                                <td>${lead.tipo_de_evento || '-'}</td>
                                <td>${lead.fecha_de_evento || '-'}</td>
                                <td>${statusCell}</td>
                                <td>${lead.lead_celular}</td>
                                <td>${lead.lead_e_mail}</td>
                                <td>${servicioCell}</td>
                                <td class="actions-column">
                                    <a href="${editUrl}" class="button button-edit" title="Editar">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                    <button class="button button-delete" data-lead-id="${lead.lead_id}" title="Eliminar">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </td>
                            </tr>
                        `;
                        
                        // Generar tarjeta para vista cards
                        const cardClass = lead.is_obsolete_status ? 'lead-card obsolete-status-card' : 'lead-card';
                        const card = `
                            <div class="${cardClass}">
                                <div class="lead-card-header">
                                    <div class="lead-card-title">
                                        ${lead.is_obsolete_status ? 
                                            `<span class="status-warning-icon dashicons dashicons-warning" title="Estado obsoleto o sin evento"></span>` : ''}
                                        <h3>${lead.lead_nombre} ${lead.lead_apellido}</h3>
                                        ${lead.evento_status ? 
                                            `<span class="status-badge ${lead.evento_status}">${lead.evento_status}</span>` : 
                                            ''}
                                    </div>
                                    <div class="lead-card-date">
                                        <span class="label">Solicitud:</span>
                                        <span>${lead.fecha_solicitud}</span>
                                    </div>
                                </div>
                                
                                <div class="lead-card-content">
                                    <div class="lead-card-row">
                                        <span class="label">Tipo:</span>
                                        <span>${lead.tipo_de_evento || 'No especificado'}</span>
                                    </div>
                                    
                                    <div class="lead-card-row">
                                        <span class="label">Fecha evento:</span>
                                        <span>${lead.fecha_de_evento || 'No especificada'}</span>
                                    </div>
                                    
                                    <div class="lead-card-row">
                                        <span class="label">Contacto:</span>
                                        <div class="contact-info">
                                            <a href="tel:${lead.lead_celular}" class="contact-mini-btn">
                                                <span class="dashicons dashicons-phone"></span>
                                            </a>
                                            <a href="mailto:${lead.lead_e_mail}" class="contact-mini-btn">
                                                <span class="dashicons dashicons-email"></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="lead-card-footer">
                                    <a href="${editUrl}" class="card-btn edit-btn">
                                        <span class="dashicons dashicons-visibility"></span>
                                        Ver detalles
                                    </a>
                                    <button class="card-btn delete-btn" data-lead-id="${lead.lead_id}">
                                        <span class="dashicons dashicons-trash"></span>
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        // Agregar a sus respectivos contenedores
                        tableBody.append(row);
                        cardsContainer.append(card);
                    });
                    
                    // Actualizar paginación
                    updatePagination(response.data.total, response.data.pages);
                } else {
                    console.error('Error al filtrar leads:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al filtrar leads:', error);
            },
            complete: function() {
                loadingOverlay.hide();
            }
        });
    }
    
    // Función para generar y mostrar chips de filtros activos
    function actualizarChipsFiltrosActivos(filtrosAplicados) {
        $activeFiltersContainer.empty();
        
        if (!filtrosAplicados || filtrosAplicados.length === 0) {
            return;
        }
        
        filtrosAplicados.forEach(function(filtro) {
            const chip = $(`
                <div class="filter-chip" data-type="${filtro.type}">
                    <span class="filter-chip-text">${filtro.label}</span>
                    <span class="filter-chip-close">×</span>
                </div>
            `);
            
            // Agregar evento para eliminar filtro
            chip.find('.filter-chip-close').on('click', function() {
                eliminarFiltro(filtro.type, filtro.value);
            });
            
            $activeFiltersContainer.append(chip);
        });
    }
    
    // Función para actualizar contador de filtros activos
    function actualizarContadorFiltros(count) {
        $activeFiltersCount.text(count);
        
        if (count > 0) {
            $toggleFiltersBtn.addClass('has-filters');
        } else {
            $toggleFiltersBtn.removeClass('has-filters');
        }
    }
    
    // Función para eliminar un filtro
    function eliminarFiltro(tipo, valor) {
        // Determinar qué campo/s hay que limpiar
        switch (tipo) {
            case 'fecha_evento':
                $('#fecha_evento_inicio, #fecha_evento_fin').val('');
                $('#anio_evento, #mes_evento').val('');
                $('#fecha_evento_display').text('');
                currentFilters.fecha_evento_inicio = '';
                currentFilters.fecha_evento_fin = '';
                break;
                
            case 'fecha_ingreso':
                $('#fecha_ingreso_inicio, #fecha_ingreso_fin').val('');
                $('#anio_ingreso, #mes_ingreso').val('');
                currentFilters.fecha_ingreso_inicio = '';
                currentFilters.fecha_ingreso_fin = '';
                break;
                
            case 'tipo_evento':
                $('#tipo_evento_filter').val(null).trigger('change');
                currentFilters.tipo_evento = [];
                break;
                
            case 'status':
                $('#status_filter').val(null).trigger('change');
                currentFilters.status = [];
                break;
                
            case 'invitados':
                $('#invitados_filter').val('');
                currentFilters.invitados = '';
                break;
                
            case 'prioridad':
                $('#prioridad_filter').val('');
                currentFilters.prioridad = '';
                break;
                
            case 'valor_potencial':
                $('#valor_potencial_filter').val('');
                currentFilters.valor_potencial = '';
                break;
                
            case 'probabilidad':
                $('#probabilidad_filter').val('');
                currentFilters.probabilidad = '';
                break;
                
            case 'responsable':
                $('#responsable_filter').val(null).trigger('change');
                currentFilters.responsable = [];
                break;
                
            case 'ultima_interaccion':
                $('#ultima_interaccion_filter').val('');
                currentFilters.ultima_interaccion = '';
                break;
                
            case 'tiempo_sin_actividad':
                $('#tiempo_sin_actividad_filter').val('');
                currentFilters.tiempo_sin_actividad = '';
                break;
                
            case 'proxima_accion':
                $('#proxima_accion_filter').val('');
                currentFilters.proxima_accion = '';
                break;
                
            case 'fuente':
                $('#fuente_filter').val(null).trigger('change');
                currentFilters.fuente = [];
                break;
                
            case 'campana':
                $('#campana_filter').val(null).trigger('change');
                currentFilters.campana = [];
                break;
                
            case 'ubicacion':
                $('#ubicacion_filter').val(null).trigger('change');
                currentFilters.ubicacion = [];
                break;
                
            case 'industria':
                $('#industria_filter').val(null).trigger('change');
                currentFilters.industria = [];
                break;
                
            case 'estado_propuesta':
                $('#estado_propuesta_filter').val('');
                currentFilters.estado_propuesta = '';
                break;
                
            case 'rango_cotizacion':
                $('#rango_cotizacion_filter').val('');
                currentFilters.rango_cotizacion = '';
                break;
                
            case 'temporada':
                $('#temporada_filter').val('');
                currentFilters.temporada = '';
                break;
                
            case 'servicios_requeridos':
                $('#servicios_requeridos_filter').val(null).trigger('change');
                currentFilters.servicios_requeridos = [];
                break;
                
            case 'venue':
                $('#venue_filter').val(null).trigger('change');
                currentFilters.venue = [];
                break;
                
            case 'etiquetas':
                $('#etiquetas_filter').val(null).trigger('change');
                currentFilters.etiquetas = [];
                break;
                
            case 'search':
                $('#search_leads').val('');
                currentFilters.search = '';
                break;
        }
        
        // Reiniciar paginación
        currentFilters.paged = 1;
        
        // Actualizar tabla
        updateTable();
        
        // Actualizar vista pipeline si está visible
        if ($('.view-container[data-view="pipeline"]').is(':visible')) {
            if (typeof window.refreshPipelineView === 'function') {
                window.refreshPipelineView();
            }
        }
    }
    
    // Actualizar paginación
    function updatePagination(total, pages) {
        const paginationContainer = $('.leads-pagination');
        paginationContainer.empty();
        
        if (pages <= 1) return;
        
        let paginationHtml = '<div class="pagination-wrapper">';
        
        // Información de página actual
        paginationHtml += `
            <div class="pagination-info">
                <span>Mostrando ${currentFilters.per_page} de ${total} resultados</span>
            </div>
        `;
        
        // Botón anterior
        paginationHtml += `
            <button class="pagination-btn prev-page" ${currentFilters.paged === 1 ? 'disabled' : ''}>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            </button>
        `;
        
        // Números de página (con elipsis para muchas páginas)
        if (pages <= 7) {
            // Pocas páginas: mostrar todas
            for (let i = 1; i <= pages; i++) {
                paginationHtml += `
                    <button class="pagination-btn page-number ${currentFilters.paged === i ? 'active' : ''}" 
                            data-page="${i}">
                        ${i}
                    </button>
                `;
            }
        } else {
            // Muchas páginas: mostrar con elipsis
            const currentPage = currentFilters.paged;
            
            // Primera página
            paginationHtml += `
                <button class="pagination-btn page-number ${currentPage === 1 ? 'active' : ''}" 
                        data-page="1">
                    1
                </button>
            `;
            
            // Elipsis izquierda (si es necesario)
            if (currentPage > 3) {
                paginationHtml += `<span class="pagination-ellipsis">...</span>`;
            }
            
            // Páginas centrales alrededor de la actual
            const startPage = Math.max(2, currentPage - 1);
            const endPage = Math.min(pages - 1, currentPage + 1);
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `
                    <button class="pagination-btn page-number ${currentPage === i ? 'active' : ''}" 
                            data-page="${i}">
                        ${i}
                    </button>
                `;
            }
            
            // Elipsis derecha (si es necesario)
            if (currentPage < pages - 2) {
                paginationHtml += `<span class="pagination-ellipsis">...</span>`;
            }
            
            // Última página
            paginationHtml += `
                <button class="pagination-btn page-number ${currentPage === pages ? 'active' : ''}" 
                        data-page="${pages}">
                    ${pages}
                </button>
            `;
        }
        
        // Botón siguiente
        paginationHtml += `
            <button class="pagination-btn next-page" ${currentFilters.paged === pages ? 'disabled' : ''}>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
        `;
        
        paginationHtml += '</div>';
        paginationContainer.html(paginationHtml);
        
        // Eventos de paginación
        $('.pagination-btn').on('click', function() {
            if ($(this).attr('disabled')) return;
            
            if ($(this).hasClass('prev-page')) {
                currentFilters.paged = Math.max(1, currentFilters.paged - 1);
            } else if ($(this).hasClass('next-page')) {
                currentFilters.paged = Math.min(pages, currentFilters.paged + 1);
            } else {
                currentFilters.paged = parseInt($(this).data('page'));
            }
            
            updateTable();
        });
    }
    
    // Evento para botones de eliminar lead
    $(document).on('click', '.button-delete', function(e) {
        e.preventDefault();
        const leadId = $(this).data('lead-id');
        
        if (confirm('¿Estás seguro de que deseas eliminar este lead? Esta acción no se puede deshacer.')) {
            $.ajax({
                url: ltbLeadsFilters.ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_lead',
                    nonce: ltbLeadsFilters.nonce,
                    lead_id: leadId
                },
                success: function(response) {
                    if (response.success) {
                        updateTable();
                        alert('Lead eliminado correctamente');
                    } else {
                        alert('Error al eliminar lead: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión al eliminar lead');
                }
            });
        }
    });
    
    // Ordenamiento por columnas
    $('.leads-table th[data-sort]').on('click', function() {
        const column = $(this).data('sort');
        
        if (currentFilters.orderby === column) {
            currentFilters.order = currentFilters.order === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentFilters.orderby = column;
            currentFilters.order = 'ASC';
        }
        
        currentFilters.paged = 1;
        updateTable();
    });
    
    // Cambio en selector de elementos por página
    $('#per_page_select').on('change', function() {
        currentFilters.per_page = parseInt($(this).val());
        currentFilters.paged = 1;
        updateTable();
    });
    
    // Selector de vista
    $('.view-btn').on('click', function() {
        const viewType = $(this).data('view');
        
        // Actualizar botones
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        // Actualizar contenedores
        $('.view-container').removeClass('active');
        $(`.view-container[data-view="${viewType}"]`).addClass('active');
        
        // Si es la vista pipeline, recargar los datos
        if (viewType === 'pipeline' && typeof window.refreshPipelineView === 'function') {
            window.refreshPipelineView();
        }
        
        // Guardar preferencia en localStorage
        localStorage.setItem('leads_view_preference', viewType);
    });
    
    // Inicialización
    $(document).ready(function() {
        // Mostrar el selector de vista
        $('.view-selector').show();
        
        // Iniciar con vista adecuada según el tamaño de pantalla
        if (window.innerWidth >= 768) {
            $('.view-btn[data-view="pipeline"]').addClass('active');
            $('.view-container[data-view="pipeline"]').addClass('active');
        } else {
            $('.view-btn[data-view="cards"]').addClass('active');
            $('.view-container[data-view="cards"]').addClass('active');
        }
        
        // Ocultar todas las secciones de filtros por defecto
        $('.section-content').hide().removeClass('open');
        $('.section-header').removeClass('active');
        $('.section-header .toggle-icon').removeClass('open');
        
        // Abrir solo la primera sección de filtros (categorización)
        $('#categorizacion-filters').show().addClass('open');
        $('.section-header[data-target="categorizacion-filters"]').addClass('active');
        $('.section-header[data-target="categorizacion-filters"] .toggle-icon').addClass('open');
        
        // Establecer valor inicial del selector de elementos por página
        $('#per_page_select').val(currentFilters.per_page);
        
        // Cargar datos iniciales
        updateTable();
    });
    
    // Ajustar visibilidad según tamaño de ventana
    $(window).on('resize', function() {
        if (window.innerWidth >= 768) {
            $filterContainer.show();
            
            if (!$('.view-container[data-view="pipeline"]').hasClass('active')) {
                $('.view-btn').removeClass('active');
                $('.view-btn[data-view="pipeline"]').addClass('active');
                
                $('.view-container').removeClass('active');
                $('.view-container[data-view="pipeline"]').addClass('active');
                
                if (typeof window.refreshPipelineView === 'function') {
                    window.refreshPipelineView();
                }
            }
        } else {
            if ($toggleFiltersBtn.hasClass('active')) {
                $filterContainer.show();
            } else {
                $filterContainer.hide();
            }
            
            if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
                $('.view-btn').removeClass('active');
                $('.view-btn[data-view="cards"]').addClass('active');
                
                $('.view-container').removeClass('active');
                $('.view-container[data-view="cards"]').addClass('active');
            }
        }
    });
});