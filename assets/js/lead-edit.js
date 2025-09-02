jQuery(function($) {
    // Toggle formulario de seguimiento
    $('.add-followup-toggle, .add-followup-btn').on('click', function() {
        $('.followup-form-container').slideToggle(300);
    });
    
    // Mostrar mensaje para funcionalidades futuras
    $('.disabled, .coming-soon').parent('button').on('click', function(e) {
        e.preventDefault();
        alert('Esta funcionalidad estará disponible próximamente.');
    });
    
    // Variables para edición
    let isEditMode = false;
    let originalValues = {};
    let editingSections = {};
    let modifiedFields = {};

    // Función para habilitar/deshabilitar la edición de un campo específico
    function toggleFieldEdit(element, enable) {
        const field = element.data('field');
        const value = element.text().trim();
        const eventoId = element.data('evento-id') || 'lead';
        
        // Si es habilitar edición, guardar valor original
        if (enable && !originalValues[eventoId + '_' + field]) {
            // Guardar valor original (puede ser vacío)
            originalValues[eventoId + '_' + field] = value || '';
        }
        
        // Crear campo editable según el tipo
        if (enable) {
            if (field === 'evento_status') {
                const statusHTML = createStatusSelector(value, eventoId);
                element.html(statusHTML);
                
                // Marcar como modificado cuando cambie el valor
                element.find('select').on('change', function() {
                    const newValue = $(this).val();
                    const originalValue = originalValues[eventoId + '_' + field] || '';
                    
                    // Marcar como modificado si hay cualquier cambio (incluyendo de vacío a valor)
                    if (newValue !== originalValue) {
                        markAsModified(eventoId, field);
                    }
                });
            } else if (field === 'tipo_de_evento') {
                const eventTypeHTML = createEventTypeSelector(value, eventoId);
                element.html(eventTypeHTML);
                
                // Marcar como modificado cuando cambie el valor
                element.find('select').on('change', function() {
                    markAsModified(eventoId, field);
                });
            } else if (field === 'comentarios_evento') {
                const textareaHTML = `<textarea class="edit-input" name="${field}" data-evento-id="${eventoId}">${value}</textarea>`;
                element.html(textareaHTML);
                
                // Marcar como modificado cuando cambie el texto
                element.find('textarea').on('input', function() {
                    markAsModified(eventoId, field);
                });
            } else if (field === 'fecha_de_evento') {
                const dateInputHTML = `<input type="text" class="edit-input datepicker-field" name="${field}" data-evento-id="${eventoId}" value="${value}" readonly>`;
                element.html(dateInputHTML);
                
                // Inicializar datepicker
                element.find('.datepicker-field').datepicker({
                    dateFormat: 'dd/mm/yy',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: '-1:+10',
                    closeText: 'Cerrar',
                    currentText: 'Hoy',
                    monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
                    onSelect: function(dateText) {
                        $(this).val(dateText);
                        markAsModified(eventoId, field);
                    }
                });
            } else {
                const inputHTML = `<input type="text" class="edit-input" name="${field}" data-evento-id="${eventoId}" value="${value}">`;
                element.html(inputHTML);
                
                // Marcar como modificado al escribir
                element.find('input').on('input', function() {
                    markAsModified(eventoId, field);
                });
            }
        } else {
            // Si es deshabilitar, restaurar valor original
            if (originalValues[eventoId + '_' + field]) {
                element.text(originalValues[eventoId + '_' + field]);
            }
        }
    }

    // Función para habilitar/deshabilitar la edición del servicio de interés
    function toggleServiceEdit(element, enable, providedEventoId) {
    if (!enable) {
        // Restaurar el enlace original
        const eventoId = providedEventoId || element.closest('.evento-item').find('.evento-header').data('evento-id');
        if (originalValues['servicio_url_' + eventoId] && originalValues['servicio_titulo_' + eventoId]) {
            element.html(`<a href="${originalValues['servicio_url_' + eventoId]}" target="_blank" class="service-link">${originalValues['servicio_titulo_' + eventoId]}</a>`);
        } else if (originalValues['servicio_placeholder_' + eventoId]) {
            element.html(`<span class="service-placeholder">No especificado</span>`);
        }
        return;
    }
    
    // Habilitar edición del servicio
    const serviceLink = element.find('.service-link');
    const servicePlaceholder = element.find('.service-placeholder');
    const eventoId = providedEventoId || element.closest('.evento-item').find('.evento-header').data('evento-id');
    
    let serviceUrl = '';
    let serviceTitle = '';
    
    if (serviceLink.length) {
        // Si existe un enlace de servicio
        serviceUrl = serviceLink.attr('href') || '';
        serviceTitle = serviceLink.text().trim();
        // Guardar valores originales
        originalValues['servicio_url_' + eventoId] = serviceUrl;
        originalValues['servicio_titulo_' + eventoId] = serviceTitle;
    } else if (servicePlaceholder.length) {
        // Si es un placeholder (campo vacío)
        originalValues['servicio_placeholder_' + eventoId] = true;
    }
    
    const serviceHTML = `
        <div class="service-edit-container">
            <input type="text" class="edit-input service-search" placeholder="Buscar servicio..." value="${serviceTitle}">
            <input type="hidden" class="edit-input" name="evento_servicio_de_interes" data-evento-id="${eventoId}" value="${serviceUrl}">
        </div>
    `;
    
    element.html(serviceHTML);
    
    // Inicializar autocompletado
    if (typeof $.fn.autocomplete !== 'undefined') {
        element.find('.service-search').val(serviceTitle).autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ltbLeadEdit.ajaxurl,
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'search_services',
                        nonce: ltbLeadEdit.nonce,
                        term: request.term
                    },
                    success: function(data) {
                        if (data.success) {
                            response(data.data);
                        } else {
                            response([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        response([]);
                    }
                });
            },
            minLength: 2,
            delay: 300,
            appendTo: 'body',
            position: { collision: 'flip' },
            select: function(event, ui) {
                $(this).siblings('input[name="evento_servicio_de_interes"]').val(ui.item.url);
                $(this).val(ui.item.label);
                markAsModified(eventoId, 'evento_servicio_de_interes');
                return false;
            }
        });
        
		// Marcar como modificado al escribir
		element.find('.service-search').on('input', function() {
			markAsModified(eventoId, 'evento_servicio_de_interes');
		});
    }
    }

    // Marcar un campo como modificado
    function markAsModified(eventoId, field) {
        if (!modifiedFields[eventoId]) {
            modifiedFields[eventoId] = {};
        }
        modifiedFields[eventoId][field] = true;
    }

    // Función para agregar campos faltantes del evento al DOM
    function addMissingEventFields(eventoId) {
        const eventItem = $(`.evento-item`).has(`.evento-header[data-evento-id="${eventoId}"]`);
        const eventContent = eventItem.find('.evento-content');
        const eventInfo = eventContent.find('.event-info');
        
        // Verificar y agregar campo de invitados si no existe
        if (!eventInfo.find(`[data-field="evento_asistentes"][data-evento-id="${eventoId}"]`).length) {
            // Buscar el row donde debería estar o crear uno nuevo
            let invitadosRow = eventInfo.find('.event-row').eq(1); // Segunda fila generalmente
            if (!invitadosRow.length || invitadosRow.find('.event-item').length >= 2) {
                // Si no existe la fila o está llena, buscar o crear una apropiada
                const statusItem = eventInfo.find(`[data-field="evento_status"]`).closest('.event-item');
                if (statusItem.length && statusItem.parent().find('.event-item').length === 1) {
                    // Agregar en la misma fila que el status
                    statusItem.before(`
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-groups"></span>
                                <span>Invitados:</span>
                            </div>
                            <div class="event-value" data-field="evento_asistentes" data-evento-id="${eventoId}">
                                No especificado
                            </div>
                        </div>
                    `);
                }
            }
        }
        
        // Verificar y agregar campos de ubicación/dirección si no existen
        const hasUbicacion = eventInfo.find(`[data-field="ubicacion_evento"][data-evento-id="${eventoId}"]`).length > 0;
        const hasDireccion = eventInfo.find(`[data-field="direccion_evento"][data-evento-id="${eventoId}"]`).length > 0;
        
        if (!hasUbicacion || !hasDireccion) {
            // Buscar si existe una fila para ubicación/dirección
            let locationRow = eventInfo.find('.event-row').filter(function() {
                return $(this).find('[data-field="ubicacion_evento"], [data-field="direccion_evento"]').length > 0;
            });
            
            if (!locationRow.length) {
                // Crear nueva fila después de la segunda fila
                const secondRow = eventInfo.find('.event-row').eq(1);
                locationRow = $('<div class="event-row"></div>');
                if (secondRow.length) {
                    secondRow.after(locationRow);
                } else {
                    eventInfo.append(locationRow);
                }
            }
            
            // Agregar campo de ubicación si no existe
            if (!hasUbicacion) {
                locationRow.append(`
                    <div class="event-item">
                        <div class="event-label">
                            <span class="dashicons dashicons-location"></span>
                            <span>Ubicación:</span>
                        </div>
                        <div class="event-value" data-field="ubicacion_evento" data-evento-id="${eventoId}">
                            
                        </div>
                    </div>
                `);
            }
            
            // Agregar campo de dirección si no existe
            if (!hasDireccion) {
                locationRow.append(`
                    <div class="event-item">
                        <div class="event-label">
                            <span class="dashicons dashicons-location-alt"></span>
                            <span>Dirección:</span>
                        </div>
                        <div class="event-value" data-field="direccion_evento" data-evento-id="${eventoId}">
                            
                        </div>
                    </div>
                `);
            }
        }
        
        // Verificar y agregar servicio de interés si no existe
        if (!eventInfo.find('.service-link').length && !eventInfo.find('.service-placeholder').length) {
            // Buscar donde insertar el servicio (después de ubicación/dirección)
            let insertAfter = eventInfo.find('.event-row').filter(function() {
                return $(this).find('[data-field="ubicacion_evento"], [data-field="direccion_evento"]').length > 0;
            });
            
            if (!insertAfter.length) {
                insertAfter = eventInfo.find('.event-row').last();
            }
            
            insertAfter.after(`
                <div class="event-row">
                    <div class="event-item full-width">
                        <div class="event-label">
                            <span class="dashicons dashicons-admin-links"></span>
                            <span>Servicio de interés:</span>
                        </div>
                        <div class="event-value">
                            <span class="service-placeholder">No especificado</span>
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Verificar y agregar comentarios si no existe
        if (!eventInfo.find(`[data-field="comentarios_evento"][data-evento-id="${eventoId}"]`).length) {
            // Agregar al final, antes de los botones de acción
            const actionsDiv = eventContent.find('.event-actions');
            const commentsHTML = `
                <div class="event-row">
                    <div class="event-item full-width">
                        <div class="event-label">
                            <span class="dashicons dashicons-admin-comments"></span>
                            <span>Comentarios:</span>
                        </div>
                        <div class="event-value event-comments" data-field="comentarios_evento" data-evento-id="${eventoId}">
                            
                        </div>
                    </div>
                </div>
            `;
            
            if (actionsDiv.length) {
                actionsDiv.before(commentsHTML);
            } else {
                eventInfo.append(commentsHTML);
            }
        }
    }

    // Añadir iconos de edición
    function addEditIcons() {
        // Añadir iconos a campos individuales
        $('[data-field]').each(function() {
            const element = $(this);
            const field = element.data('field');
            const eventoId = element.data('evento-id') || 'lead';
            
            // Añadir icono de lápiz
            const editIcon = $('<span class="edit-field-icon"><span class="dashicons dashicons-edit"></span></span>');
            element.append(editIcon);
            
            // Manejar clic en icono
            editIcon.on('click', function(e) {
                e.stopPropagation();
                toggleFieldEdit(element, true);
                $(this).hide();
            });
        });
        
        // Añadir iconos a servicios (tanto links como placeholders)
        $('.event-item.full-width .event-value').filter(function() {
            return $(this).has('.service-link').length || $(this).has('.service-placeholder').length;
        }).each(function() {
            const element = $(this);
            
            // No agregar icono si ya existe
            if (element.find('.edit-field-icon').length) {
                return;
            }
            
            // Añadir icono de lápiz
            const editIcon = $('<span class="edit-field-icon"><span class="dashicons dashicons-edit"></span></span>');
            element.append(editIcon);
            
            // Manejar clic en icono
            editIcon.on('click', function(e) {
                e.stopPropagation();
                const eventoId = element.closest('.evento-item').find('.evento-header').data('evento-id');
                toggleServiceEdit(element, true, eventoId);
                $(this).hide();
            });
        });
        
        // Manejar nombre y apellido en encabezado
$('.lead-name').prepend('<div class="edit-section-button name-edit-button" data-section="nombre-apellido"><span class="dashicons dashicons-edit"></span></div>');

        // Manejar clic en botón de editar nombre
        $('.name-edit-button').on('click', function() {
            if (editingSections['nombre-apellido']) {
                return; // Ya estamos editando
            }
            
            // Ocultar nombre completo y mostrar campos separados
            $('.nombre-completo-display').hide();
            $('.nombre-apellido-edit').show();
            
            // Activar edición para nombre y apellido
            $('.nombre-apellido-edit [data-field]').each(function() {
                toggleFieldEdit($(this), true);
                $(this).find('.edit-field-icon').hide();
            });
            
            editingSections['nombre-apellido'] = true;
        });
        
        // Añadir iconos para secciones
$('.lead-info-column').prepend('<div class="edit-section-button" data-section="lead"><span class="dashicons dashicons-edit"></span> Editar información de contacto</div>');

// Añadir botón de edición para cada evento
$('.evento-content').each(function() {
    const eventoId = $(this).closest('.evento-item').find('.evento-header').data('evento-id');
    if (eventoId) {
        // No agregar el botón si ya existe
        if (!$(this).find('.edit-section-button[data-section="evento"]').length) {
            $(this).prepend(`<div class="edit-section-button" data-section="evento" data-evento-id="${eventoId}"><span class="dashicons dashicons-edit"></span> Editar todo el evento</div>`);
        }
    }
});
        
        // Manejar clic en botones de sección
        $('.edit-section-button').on('click', function() {
            const section = $(this).data('section');
            const eventoId = $(this).data('evento-id');
            
            // Evitar activar la edición si ya estamos editando esta sección
            if ((section === 'lead' && editingSections['lead']) || 
                (section === 'evento' && eventoId && editingSections['evento_' + eventoId]) ||
                (section === 'nombre-apellido' && editingSections['nombre-apellido'])) {
                return;
            }
            
            if (section === 'lead') {
    // Editar todos los campos del lead, incluyendo nombre y apellido
    // Primero activar la edición del nombre y apellido
    $('.nombre-completo-display').hide();
    $('.nombre-apellido-edit').show();
    
    // Activar edición para nombre y apellido
    $('.nombre-apellido-edit [data-field]').each(function() {
        toggleFieldEdit($(this), true);
        $(this).find('.edit-field-icon').hide();
    });
    
    // Luego activar el resto de campos
    $('.lead-info-column [data-field]').each(function() {
        // No modificar los campos de nombre/apellido que ya se activaron
        if (!$(this).closest('.nombre-apellido-edit').length) {
            toggleFieldEdit($(this), true);
            $(this).find('.edit-field-icon').hide();
        }
    });
    
    // Marcar ambas secciones como en edición
    editingSections['lead'] = true;
    editingSections['nombre-apellido'] = true;
} else if (section === 'evento' && eventoId) {
    // Primero agregar campos faltantes al DOM
    addMissingEventFields(eventoId);
    
    // Agregar iconos de edición a los campos recién agregados
    const eventItem = $(this).closest('.evento-item');
    
    // Agregar iconos a campos normales recién agregados
    eventItem.find('[data-field]').each(function() {
        const $field = $(this);
        if (!$field.find('.edit-field-icon').length) {
            const editIcon = $('<span class="edit-field-icon"><span class="dashicons dashicons-edit"></span></span>');
            $field.append(editIcon);
            editIcon.hide(); // Ocultarlo porque vamos a editar todo
        }
    });
    
    // Agregar icono al servicio si fue agregado dinámicamente
    const serviceElement = eventItem.find('.service-placeholder').closest('.event-value');
    if (serviceElement.length && !serviceElement.find('.edit-field-icon').length) {
        const editIcon = $('<span class="edit-field-icon"><span class="dashicons dashicons-edit"></span></span>');
        serviceElement.append(editIcon);
        editIcon.hide(); // Ocultarlo porque vamos a editar todo
    }
    
    // Editar todos los campos del evento
    $(`.evento-content [data-evento-id="${eventoId}"]`).each(function() {
        toggleFieldEdit($(this), true);
        $(this).find('.edit-field-icon').hide();
    });
    
    // También editar el servicio si existe o agregarlo si no existe
    // Buscar el elemento de servicio - puede tener service-link O service-placeholder
    let serviceRow = eventItem.find('.event-row').filter(function() {
        return $(this).find('.event-label').text().includes('Servicio de interés');
    });
    
    if (serviceRow.length) {
        let serviceElement = serviceRow.find('.event-value');
        if (serviceElement.length) {
            toggleServiceEdit(serviceElement, true, eventoId);
            serviceElement.find('.edit-field-icon').hide();
        }
    }
    
    editingSections['evento_' + eventoId] = true;
}
        });
    }
	
	// Esta función genera el HTML para las opciones de estado
function createStatusOptionsHTML() {
    // Usar los estados pasados desde PHP
    const statusOptions = window.leadManagementConfig?.statusOptions || {};
    
    let html = '';
    for (const [value, label] of Object.entries(statusOptions)) {
        html += `<option value="${value}">${label}</option>`;
    }
    return html;
}

    // Crear selector de estado
    function createStatusSelector(currentValue, eventoId) {
    // Este será llenado por PHP de forma dinámica
    const statusOptions = window.leadManagementConfig?.statusOptions || {
        'nuevo': 'Nuevo',
        'con-presupuesto': 'Con presupuesto',
        'por-cerrar': 'Por cerrar',
        'con-contrato': 'Con contrato',
        'perdido': 'Perdido'
    };
    
    let html = `<select class="edit-input" name="evento_status" data-evento-id="${eventoId}">`;
    
    // Agregar opción vacía si el valor actual está vacío
    if (!currentValue || currentValue === '') {
        html += `<option value="" selected>-- Seleccionar estado --</option>`;
    }
    
    for (const [value, label] of Object.entries(statusOptions)) {
        const selected = value === currentValue ? 'selected' : '';
        html += `<option value="${value}" ${selected}>${label}</option>`;
    }
    html += `</select>`;
    
    return html;
}

    // Crear selector de tipo de evento
    function createEventTypeSelector(currentValue, eventoId) {
        // Usar los tipos de evento desde la configuración de PHP
        const eventTypes = window.leadManagementConfig?.eventTypes || {
            'Bodas': 'Bodas',
            'XV años': 'XV años', 
            'Cumpleaños': 'Cumpleaños',
            'Graduaciones': 'Graduaciones',
            'Empresarial': 'Empresarial',
            'Otros': 'Otros'
        };
        
let html = `<select class="edit-input" name="tipo_de_evento" data-evento-id="${eventoId}">`;
        for (const [value, label] of Object.entries(eventTypes)) {
            const selected = value === currentValue ? 'selected' : '';
            html += `<option value="${value}" ${selected}>${label}</option>`;
        }
        html += `</select>`;
        
        return html;
    }

    // Función para identificar si un elemento contiene un enlace de servicio
    function isServiceLink(element) {
        return element.find('.service-link').length > 0;
    }

    // Botón de edición principal
    $('#editLeadBtn').on('click', function() {
        const editBtn = $(this);
        
        if (!isEditMode) {
            // Entrar en modo edición
            editBtn.html('<span class="dashicons dashicons-saved"></span> Guardar');
            editBtn.addClass('save-mode');
            
            // Añadir botón de cancelar
            if ($('#cancelEditBtn').length === 0) {
                const cancelBtn = $('<button id="cancelEditBtn" class="button cancel-edit-btn"><span class="dashicons dashicons-no"></span> Cancelar</button>');
                editBtn.after(cancelBtn);
                
                // Manejar clic en botón cancelar
                cancelBtn.on('click', function() {
					
                    // Deshabilitar edición de todos los campos
                    $('[data-field]').each(function() {
                        const $this = $(this);
                        const eventoId = $this.data('evento-id');
                        const field = $this.data('field');
                        
                        // Si el campo fue agregado dinámicamente y está vacío, eliminarlo
                        if (eventoId && !originalValues[eventoId + '_' + field]) {
                            const value = $this.text().trim();
                            if (!value || value === 'No especificado' || value === '') {
                                // Eliminar la fila completa si no tiene más campos
                                const row = $this.closest('.event-row');
                                if (row.find('.event-item').length === 1) {
                                    row.remove();
                                } else {
                                    $this.closest('.event-item').remove();
                                }
                                return;
                            }
                        }
                        
                        toggleFieldEdit($this, false);
                    });
                    
                    // Restaurar servicios
                    $('.event-item.full-width .event-value').has('.service-link, .service-edit-container, .service-placeholder').each(function() {
                        const $this = $(this);
                        const eventoId = $this.closest('.evento-item').find('.evento-header').data('evento-id');
                        
                        // Si el servicio fue agregado dinámicamente y está vacío, eliminar la fila
                        if (!originalValues['servicio_url_' + eventoId] && !originalValues['servicio_titulo_' + eventoId]) {
                            const row = $this.closest('.event-row');
                            row.remove();
                            return;
                        }
                        
                        toggleServiceEdit($this, false);
                    });
                    
                    // Limpiar variables de estado
                    editingSections = {};
                    modifiedFields = {};
                    originalValues = {};
                    
                    // Restaurar visualización de nombre completo
                    $('.nombre-apellido-edit').hide();
                    $('.nombre-completo-display').show();
                    
                    // Eliminar iconos de edición y ocultar botones de sección
                    $('.edit-field-icon').remove();
                    $('.edit-section-button').remove();
                    
                    // Volver a modo visualización
                    editBtn.html('<span class="dashicons dashicons-edit"></span> Editar');
                    editBtn.removeClass('save-mode');
                    $('#cancelEditBtn').remove();
                    isEditMode = false;
                });
            }
            
            // Añadir iconos de edición
            addEditIcons();
            
            // Mostrar todos los botones de edición de evento DESPUÉS de agregarlos
            $('.edit-section-button[data-section="evento"]').show();
            
            isEditMode = true;
        } else {
            // Guardar cambios
            const formData = {
                action: 'save_lead_data',
                nonce: ltbLeadEdit.nonce,
                lead_id: ltbLeadEdit.leadId,
                eventos: {}
            };
            
            // Recoger solo los campos modificados
            for (const eventoId in modifiedFields) {
                if (eventoId === 'lead') {
                    // Campos del lead principal
                    formData.fields = {};
                    for (const field in modifiedFields[eventoId]) {
                        const element = $(`[data-field="${field}"]`).not('[data-evento-id]');
                        if (element.find('.edit-input').length) {
                            formData.fields[field] = element.find('.edit-input').val();
                        }
                    }
                } else {
                    // Campos de eventos
                    formData.eventos[eventoId] = {};
                    for (const field in modifiedFields[eventoId]) {
                        const element = $(`[data-field="${field}"][data-evento-id="${eventoId}"]`);
                        if (element.find('.edit-input').length) {
                            let value = element.find('.edit-input').val();
                            
                            // Detectar si realmente hay un cambio (incluir cambios de vacío a valor)
                            const originalValue = originalValues[eventoId + '_' + field] || '';
                            
                            // Manejar caso especial de fecha
                            if (field === 'fecha_de_evento' && value) {
                                try {
                                    // Intentar parsear fecha en formato dd/mm/yy
                                    const parts = value.split('/');
                                    if (parts.length === 3) {
                                        const day = parseInt(parts[0]);
                                        const month = parseInt(parts[1]) - 1;
                                        let year = parseInt(parts[2]);
                                        
                                        // Si el año tiene 2 dígitos, ajustar correctamente
                                        if (year < 100) {
                                            year = 2000 + year;
                                        }
                                        
                                        const date = new Date(year, month, day);
                                        value = Math.floor(date.getTime() / 1000);
                                    }
                                } catch (e) {
                                    // Error al parsear fecha
                                }
                            }
                            
                            formData.eventos[eventoId][field] = value;
                        }
                        
                        // Caso especial para servicio de interés
if (field === 'evento_servicio_de_interes') {
    const serviceInput = $(`input[name="evento_servicio_de_interes"][data-evento-id="${eventoId}"]`);
    if (serviceInput.length) {
        formData.eventos[eventoId][field] = serviceInput.val();
    }
}
                    }
                }
            }
            
            // Caso especial: Agregar nombre y apellido del bloque de edición de nombre
            if (editingSections['nombre-apellido']) {
                if (!formData.fields) {
                    formData.fields = {};
                }
                
                const nombreInput = $('.nombre-apellido-edit [data-field="lead_nombre"]').find('.edit-input');
                const apellidoInput = $('.nombre-apellido-edit [data-field="lead_apellido"]').find('.edit-input');
                
                if (nombreInput.length) {
                    formData.fields['lead_nombre'] = nombreInput.val();
                }
                
                if (apellidoInput.length) {
                    formData.fields['lead_apellido'] = apellidoInput.val();
                }
            }
            
            // Verificar si hay campos modificados
            const hasModifiedFields = Object.keys(formData.eventos).length > 0 || 
                                    (formData.fields && Object.keys(formData.fields).length > 0);
            
            if (!hasModifiedFields) {
                alert('No se han realizado cambios');
                
                // Limpiar modo edición
                $('.edit-field-icon, .edit-section-button').remove();
                isEditMode = false;
                editBtn.html('<span class="dashicons dashicons-edit"></span> Editar');
                editBtn.removeClass('save-mode');
                $('#cancelEditBtn').remove();
                
                return;
            }
            
            
            // Enviar datos
            $.ajax({
                url: ltbLeadEdit.ajaxurl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    editBtn.prop('disabled', true).text('Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        alert('Datos actualizados correctamente');
                        // Recargar página para mostrar los datos actualizados
                        location.reload();
                    } else {
                        alert('Error al guardar los cambios: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión al guardar los cambios');
                },
                complete: function() {
                    // Volver a modo visualización
                    editBtn.html('<span class="dashicons dashicons-edit"></span> Editar');
                    editBtn.removeClass('save-mode').prop('disabled', false);
                    isEditMode = false;
                    $('#cancelEditBtn').remove();
                    
                    // Eliminar iconos de edición y ocultar botones de sección
                    $('.edit-field-icon').remove();
                    $('.edit-section-button').remove();
                    
                    // Limpiar variables de estado
                    editingSections = {};
                    modifiedFields = {};
                }
            });
        }
    });
    
    // Función para eliminar lead
    $('#deleteLeadBtn').on('click', function() {
        if (confirm('¿Estás seguro de que deseas eliminar este lead? Esta acción no se puede deshacer.')) {
            $.ajax({
                url: ltbLeadEdit.ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_lead',
                    nonce: ltbLeadEdit.nonce,
                    lead_id: ltbLeadEdit.leadId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Lead eliminado correctamente');
                        // Redirigir a la lista de leads
                        window.location.href = '/leads/';
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
    
    // Función para actualizar estado
    $('.status-update-btn').on('click', function() {
        const currentStatus = $('.status-badge').text().trim();
        
        // Crear modal para actualizar estado
        const modalHtml = `
            <div id="status-modal" class="custom-modal">
                <div class="modal-backdrop"></div>
                <div class="modal-container">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Actualizar Estado</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Selecciona el nuevo estado para este lead:</p>
                            ${createStatusSelector(currentStatus)}
                        </div>
                        <div class="modal-footer">
                            <button class="button cancel-btn">Cancelar</button>
                            <button class="button button-primary save-status-btn">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Añadir modal al DOM si no existe
        if ($('#status-modal').length === 0) {
            $('body').append(modalHtml);
        }
        
        // Mostrar modal
        $('#status-modal').addClass('active');
        
        // Controladores de eventos del modal
        $('.close-modal, .cancel-btn, .modal-backdrop').on('click', function() {
            $('#status-modal').removeClass('active');
        });
        
        // Manejar guardado de estado
        $('.save-status-btn').on('click', function() {
            const newStatus = $('#status-modal select').val();
            
            if (newStatus === currentStatus) {
                $('#status-modal').removeClass('active');
                return;
            }
            
            // Enviar actualización
            $.ajax({
                url: ltbLeadEdit.ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_lead_data',
                    nonce: ltbLeadEdit.nonce,
                    lead_id: ltbLeadEdit.leadId,
                    fields: {
                        'evento_status': newStatus
                    }
                },
                beforeSend: function() {
                    $('.save-status-btn').prop('disabled', true).text('Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        // Recargar página para mostrar los datos actualizados
                        location.reload();
                    } else {
                        alert('Error al actualizar el estado: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión al actualizar el estado');
                },
                complete: function() {
                    $('.save-status-btn').prop('disabled', false).text('Guardar');
                    $('#status-modal').removeClass('active');
                }
            });
        });
    });
    
    // Manejo del botón Agregar Evento
    $('.add-event-btn').on('click', function() {
        // Crear modal para agregar evento
        const modalHtml = `
            <div id="event-modal" class="custom-modal">
                <div class="modal-backdrop"></div>
                <div class="modal-container">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Agregar Nuevo Evento</h3>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="add_event_form" class="event-form">
                                <div class="form-group required">
                                    <label for="evento_fecha">Fecha de Evento:</label>
                                    <input type="text" id="evento_fecha" name="fecha_de_evento" class="datepicker-input" readonly required>
                                </div>
                                
                                <div class="form-group required">
                                    <label for="evento_tipo">Tipo de Evento:</label>
                                    <select id="evento_tipo" name="tipo_de_evento" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Bodas">Bodas</option>
                                        <option value="XV años">XV años</option>
                                        <option value="Cumpleaños">Cumpleaños</option>
                                        <option value="Graduaciones">Graduaciones</option>
                                        <option value="Empresarial">Empresarial</option>
                                        <option value="Otros">Otros</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="evento_asistentes">Cantidad de Invitados:</label>
                                    <input type="number" id="evento_asistentes" name="evento_asistentes" min="1">
                                </div>
                                
                               <div class="form-group">
    <label for="evento_status">Status:</label>
    <select id="evento_status" name="evento_status">
        ${createStatusOptionsHTML()}
    </select>
</div>
                                
                                <input type="hidden" id="evento_ubicacion" name="ubicacion_evento">
                                
                                <div class="form-group">
                                    <label for="evento_direccion">Dirección:</label>
                                    <input type="text" id="evento_direccion" name="direccion_evento">
                                </div>
                                
                                <div class="form-group">
                                    <label for="evento_servicio_search">Servicio de Interés:</label>
                                    <input type="text" id="evento_servicio_search" class="service-search" placeholder="Buscar servicio...">
                                    <input type="hidden" id="evento_servicio" name="evento_servicio_de_interes">
                                </div>
                                
                                <div class="form-group">
                                    <label for="evento_comentarios">Comentarios:</label>
                                    <textarea id="evento_comentarios" name="comentarios_evento" rows="4"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="button cancel-btn">Cancelar</button>
                            <button class="button button-primary save-event-btn">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Añadir modal al DOM si no existe
        if ($('#event-modal').length === 0) {
            $('body').append(modalHtml);
        }
        
        // Mostrar modal
        $('#event-modal').addClass('active');
        $('body').addClass('modal-open');
        
        // Inicializar datepicker
        $('#evento_fecha').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '-1:+10',
            closeText: 'Cerrar',
            currentText: 'Hoy',
            monthNames: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
            monthNamesShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            dayNames: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            dayNamesShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
            dayNamesMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá']
        });
        
        // Inicializar autocompletado de servicios
        if (typeof $.fn.autocomplete !== 'undefined') {
            $('#evento_servicio_search').autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ltbLeadEdit.ajaxurl,
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        action: 'search_services',
                        nonce: ltbLeadEdit.nonce,
                        term: request.term
                    },
                    success: function(data) {
                        if (data.success) {
                            response(data.data);
                        } else {
                            response([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        response([]);
                    }
                });
            },
            minLength: 2,
            delay: 300,
            appendTo: 'body',
            position: { collision: 'flip' },
            select: function(event, ui) {
                $('#evento_servicio').val(ui.item.url);
                $(this).val(ui.item.label);
                return false;
            },
        });
        }
        
        // Controladores de eventos del modal
        $('.close-modal, .cancel-btn, .modal-backdrop').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#event-modal').removeClass('active');
            $('body').removeClass('modal-open');
        });
        
        // Prevenir que el modal se cierre al hacer clic dentro del contenido
        $('.modal-content').off('click').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Manejar guardado de evento
        $('.save-event-btn').on('click', function() {
            // Validar formulario
            if (!validateEventForm()) {
                return;
            }
            
            // Enviar datos
            $.ajax({
                url: ltbLeadEdit.ajaxurl,
                type: 'POST',
                data: {
                    action: 'add_event_to_lead',
                    nonce: ltbLeadEdit.nonce,
                    lead_id: ltbLeadEdit.leadId,
                    fecha_de_evento: $('#evento_fecha').val(),
                    tipo_de_evento: $('#evento_tipo').val(),
                    evento_asistentes: $('#evento_asistentes').val(),
                    evento_status: $('#evento_status').val(),
                    ubicacion_evento: $('#evento_ubicacion').val(),
                    direccion_evento: $('#evento_direccion').val(),
                    evento_servicio_de_interes: $('#evento_servicio').val(),
                    comentarios_evento: $('#evento_comentarios').val()
                },
                beforeSend: function() {
                    $('.save-event-btn').prop('disabled', true).text('Guardando...');
                },
                success: function(response) {
                    if (response.success) {
                        alert('Evento agregado correctamente');
                        // Recargar página para mostrar el nuevo evento
                        location.reload();
                    } else {
                        alert('Error al agregar evento: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión al agregar evento');
                },
                complete: function() {
                   $('.save-event-btn').prop('disabled', false).text('Guardar');
                   $('#event-modal').removeClass('active');
                }
            });
        });
    });
   
    // Validar formulario de evento
    function validateEventForm() {
        $('.error-message').remove();
        let isValid = true;
        
        // Validar fecha
        if (!$('#evento_fecha').val()) {
            $('#evento_fecha').after('<span class="error-message">La fecha es requerida</span>');
            isValid = false;
        }
        
        // Validar tipo
        if (!$('#evento_tipo').val()) {
            $('#evento_tipo').after('<span class="error-message">El tipo de evento es requerido</span>');
            isValid = false;
        }
        
        return isValid;
    }
   
    // Aplicar estilos para modales y edición
    const editAndModalStyles = `
    <style>
        /* Estilos para modo edición */
        .edit-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .edit-input:focus {
            border-color: #0d6efd;
            outline: none;
            box-shadow: 0 0 0 2px rgba(13,110,253,.25);
        }

        .edit-lead-btn.save-mode {
            background-color: #198754;
        }

        .edit-lead-btn.save-mode:hover {
            background-color: #157347;
        }
        
        /* Estilos para botón cancelar */
        .cancel-edit-btn {
            margin-left: 10px;
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .cancel-edit-btn:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: white;
        }
        
        .datepicker-field {
            background-color: white !important;
            cursor: pointer;
        }
        
        .service-edit-container {
            width: 100%;
        }
        
        /* Iconos de edición */
        .edit-field-icon {
            margin-left: 5px;
            cursor: pointer;
            opacity: 0.7;
            display: inline-block;
            vertical-align: middle;
        }
        .edit-field-icon:hover {
            opacity: 1;
        }
        
        /* Botones de edición por sección */
        .edit-section-button {
            margin-bottom: 10px;
            padding: 6px 12px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            display: inline-block;
        }
        .edit-section-button:hover {
            background-color: #e9ecef;
        }
        .edit-section-button .dashicons {
            margin-right: 5px;
            font-size: 16px;
            line-height: 1.3;
        }

        /* Estilos para modal */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
        }

        .custom-modal.active {
            display: block;
        }

        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 600px;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .event-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .event-form .form-group.required label:after {
            content: " *";
            color: #dc3545;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        @media (max-width: 767px) {
            .event-form {
                grid-template-columns: 1fr;
            }
        }
        
        /* Estilos para autocomplete */
        .ui-autocomplete {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            background: white !important;
            border: 1px solid #ddd !important;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 999999 !important;
            padding: 0;
            list-style: none;
            position: absolute !important;
        }

        .ui-autocomplete .ui-menu-item {
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            margin: 0;
        }

        .ui-autocomplete .ui-menu-item:hover {
            background-color: #f8f9fa;
        }

        .ui-autocomplete .ui-state-active,
        .ui-autocomplete .ui-widget-content .ui-state-active {
            background: #e9ecef;
            border: none;
            margin: 0;
            font-weight: normal;
        }

        .service-search {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Estilos para selects */
        .edit-input.datepicker-field, 
        .edit-input.service-search,
        select.edit-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        
        /* Estilos para el botón de editar nombre */
        .name-edit-button {
            margin-bottom: 5px;
            font-size: 12px;
            padding: 3px 8px;
        }

        /* Estilos para los campos de nombre/apellido en modo edición */
        .nombre-apellido-edit {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .nombre-apellido-edit [data-field] {
            flex: 1;
            min-width: 0;
        }

        /* Estilos para el botón de editar evento */
        .evento-content .edit-section-button {
            margin-bottom: 15px;
            display: inline-block;
        }
    </style>
    `;
   
    // Añadir estilos al DOM
    $('head').append(editAndModalStyles);
});