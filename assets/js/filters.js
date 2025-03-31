jQuery(function($) {
    // Inicializar datepickers
    const datepickerConfig = {
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
        closeText: 'Cerrar',
        currentText: 'Hoy',
        monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
        dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
        dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá']
    };

    // Aplicar configuración para el datepicker de fecha de ingreso (solo pasado hasta hoy)
    $('#fecha_ingreso').datepicker({
        ...datepickerConfig,
        maxDate: new Date() // Solo hasta hoy
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

// Función para actualizar la visualización de fechas
function actualizarVisualizacionFechas() {
    const fechaInicio = $('#fecha_evento_inicio').val();
    const fechaFin = $('#fecha_evento_fin').val();
    
    let textoDisplay = '';
    
    if (fechaInicio && fechaFin) {
        if (fechaInicio === fechaFin) {
            textoDisplay = `${formatearFechaAmigable(fechaInicio)}`;
        } else {
            textoDisplay = `Desde ${formatearFechaAmigable(fechaInicio)} hasta ${formatearFechaAmigable(fechaFin)}`;
        }
    } else if (fechaInicio) {
        textoDisplay = `${formatearFechaAmigable(fechaInicio)}`;
    } else if (fechaFin) {
        textoDisplay = `${formatearFechaAmigable(fechaFin)}`;
    }
    
    $('#fecha_evento_display').text(textoDisplay);
}


// Aplicar configuración para los datepickers de fecha de evento (inicio y fin)
$('#fecha_evento_inicio, #fecha_evento_fin').datepicker({
     ...datepickerConfig,
    dateFormat: 'yy-mm-dd', // Esto asegura que la fecha esté en formato YYYY-MM-DD
    yearRange: '-1:+10', // Permite seleccionar hasta 10 años en el futuro
    onSelect: function(dateText, inst) {
        // Si se selecciona fecha de inicio y es posterior a la fecha fin, actualizar fecha fin
        if (this.id === 'fecha_evento_inicio') {
            var fechaInicio = $(this).datepicker('getDate');
            var fechaFin = $('#fecha_evento_fin').datepicker('getDate');
            if (fechaFin !== null && fechaInicio > fechaFin) {
                $('#fecha_evento_fin').datepicker('setDate', fechaInicio);
            }
        }
        // Si se selecciona fecha fin y es anterior a la fecha inicio, actualizar fecha inicio
        else if (this.id === 'fecha_evento_fin') {
            var fechaFin = $(this).datepicker('getDate');
            var fechaInicio = $('#fecha_evento_inicio').datepicker('getDate');
            if (fechaInicio !== null && fechaFin < fechaInicio) {
                $('#fecha_evento_inicio').datepicker('setDate', fechaFin);
            }
        }
		        actualizarVisualizacionFechas();

    }
});
	
	// Botón para seleccionar mes completo
$('#btn_mes_completo').on('click', function() {
    const fechaSeleccionada = $('#fecha_evento_inicio').val() || $('#fecha_evento_fin').val();
    
    if (fechaSeleccionada) {
        // Extraer año y mes
        const partes = fechaSeleccionada.split('-');
        if (partes.length >= 2) {
            const mesCompleto = `${partes[0]}-${partes[1]}`;
            
            // Establecer ambas fechas con el mismo valor
            $('#fecha_evento_inicio').val(mesCompleto);
            $('#fecha_evento_fin').val(mesCompleto);
            
            // Actualizar visualización
            actualizarVisualizacionFechas();
        }
    } else {
        alert('Por favor, selecciona primero una fecha');
    }
});

// Botón para seleccionar año completo
$('#btn_anio_completo').on('click', function() {
    const fechaSeleccionada = $('#fecha_evento_inicio').val() || $('#fecha_evento_fin').val();
    
    if (fechaSeleccionada) {
        // Extraer año
        const partes = fechaSeleccionada.split('-');
        if (partes.length >= 1) {
            const anioCompleto = partes[0];
            
            // Establecer ambas fechas con el mismo valor
            $('#fecha_evento_inicio').val(anioCompleto);
            $('#fecha_evento_fin').val(anioCompleto);
            
            // Actualizar visualización
            actualizarVisualizacionFechas();
        }
    } else {
        alert('Por favor, selecciona primero una fecha');
    }
});

 // Estado de los filtros
let currentFilters = {
    fecha_inicio: '',
    fecha_fin: '',
    fecha_evento_inicio: '',
    fecha_evento_fin: '',
    search: '',
    orderby: 'fecha_solicitud',
    order: 'DESC',
    paged: 1,
    per_page: 25,
};

    // Formatear fecha para mostrar
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
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
                        
      // 1. Generar fila para la tabla
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
                    
                    updatePagination(response.data.total, response.data.pages);
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
    }

$('#aplicar_filtros').on('click', function(e) {
    e.preventDefault();
    currentFilters = {
        ...currentFilters,
        fecha_inicio: $('#fecha_ingreso').val(),
        fecha_evento_inicio: $('#fecha_evento_inicio').val(),
        fecha_evento_fin: $('#fecha_evento_fin').val(),
        search: $('#search_leads').val(),
        orderby: $('#ordenamiento').val(),
        order: $('#orden').val(),
        paged: 1
    };
    
    console.log('Aplicando filtros:', currentFilters);
    updateTable();
    
    // Actualizar vista pipeline si está visible
    if ($('.view-container[data-view="pipeline"]').is(':visible')) {
        if (typeof window.refreshPipelineView === 'function') {
            window.refreshPipelineView();
        }
    }
});

// Evento del botón Limpiar Filtros
$('#limpiar_filtros').on('click', function(e) {
    e.preventDefault();
    $('.datepicker-input').val('');
    $('#search_leads').val('');
    $('#ordenamiento').val('fecha_solicitud');
    $('#orden').val('DESC');
	    $('#fecha_evento_display').text('');

    
    // Preservar el valor actual de per_page
    const currentPerPage = currentFilters.per_page;
    
    currentFilters = {
        fecha_inicio: '',
        fecha_fin: '',
        fecha_evento_inicio: '',
        fecha_evento_fin: '',
        search: '',
        orderby: 'fecha_solicitud',
        order: 'DESC',
        paged: 1,
        per_page: currentPerPage // Mantener valor
    };
    
    updateTable();
    
    // Actualizar vista pipeline si está visible
    if ($('.view-container[data-view="pipeline"]').is(':visible')) {
        if (typeof window.refreshPipelineView === 'function') {
            window.refreshPipelineView();
        }
    }
});
	
    // Delegación de eventos para paginación
    $(document).on('click', '.pagination-btn', function() {
        if ($(this).hasClass('prev-page')) {
            currentFilters.paged = Math.max(1, currentFilters.paged - 1);
        } else if ($(this).hasClass('next-page')) {
            currentFilters.paged++;
        } else {
            currentFilters.paged = parseInt($(this).data('page'));
        }
        updateTable();
    });
    
    // Agregar evento para los botones de eliminar
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
                        // Actualizar tabla
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
        
        updateTable();
    });
    
    // Controlador para cambios en el selector de límite por página
    $('#per_page_select').on('change', function() {
        currentFilters.per_page = parseInt($(this).val());
        currentFilters.paged = 1; // Reiniciar a la primera página
        updateTable();
    });
    
    // Toggle de filtros en móvil
    $('#toggle_filters_btn').on('click', function() {
        $('#filters_container').slideToggle(300);
        $(this).toggleClass('active');
    });

    // En window resize, mostrar filtros en desktop
    $(window).on('resize', function() {
        if (window.innerWidth >= 768) {
            $('#filters_container').show();
        }
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

    // Cargar preferencia guardada al iniciar
    $(document).ready(function() {
        // Primero mostrar el selector de vista
        $('.view-selector').show();
        
        // En desktop, iniciar con pipeline por defecto
        if (window.innerWidth >= 768) {
            // Activar la vista pipeline
            $('.view-btn').removeClass('active');
            $('.view-btn[data-view="pipeline"]').addClass('active');
            
            $('.view-container').removeClass('active');
            $('.view-container[data-view="pipeline"]').addClass('active');
        } else {
            // En móvil, iniciar con cards
            $('.view-btn').removeClass('active');
            $('.view-btn[data-view="cards"]').addClass('active');
            
            $('.view-container').removeClass('active');
            $('.view-container[data-view="cards"]').addClass('active');
        }
        
        // Establecer valor inicial del selector de elementos por página
        $('#per_page_select').val(currentFilters.per_page);
    });

    // Ajustar visibilidad del selector de vista en resize
    $(window).on('resize', function() {
        // Siempre mostrar el selector de vista
        $('.view-selector').show();
        
        // Al cambiar a desktop, mostrar pipeline
        if (window.innerWidth >= 768) {
            // Solo cambiar a pipeline si no está activa
            if (!$('.view-container[data-view="pipeline"]').hasClass('active')) {
                $('.view-btn').removeClass('active');
                $('.view-btn[data-view="pipeline"]').addClass('active');
                
                $('.view-container').removeClass('active');
                $('.view-container[data-view="pipeline"]').addClass('active');
            }
        } else {
            // Al cambiar a móvil, mostrar tarjetas si está en pipeline
            if ($('.view-container[data-view="pipeline"]').hasClass('active')) {
                $('.view-btn').removeClass('active');
                $('.view-btn[data-view="cards"]').addClass('active');
                
                $('.view-container').removeClass('active');
                $('.view-container[data-view="cards"]').addClass('active');
            }
        }
    });

    // Inicializar tabla
    updateTable();
});