jQuery(function($) {
    // Verificar dependencias

    // Variables globales
    const addLeadBtn = $('#add_lead_btn');
    const modalContainer = $('#lead_add_modal');
    const leadForm = $('#lead_add_form');
    const eventForm = $('#event_add_form');
    const leadFormToggle = $('#lead_only_toggle');
    const eventFormToggle = $('#event_toggle');
    const leadFormSubmit = $('#submit_lead_only');
    const fullFormSubmit = $('#submit_full_form');
	
	
	// Inicializar autocompletado de servicios
// Verificar si el elemento existe

if ($('#evento_servicio_search').length > 0 && typeof $.fn.autocomplete !== 'undefined') {
    $('#evento_servicio_search').autocomplete({
    source: function(request, response) {

        $.ajax({
            url: ltbLeadAdd.ajaxurl,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'search_services',
                nonce: ltbLeadAdd.nonce,
                term: request.term
            },
            success: function(data) {

                if (data.success && Array.isArray(data.data)) {
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
    open: function() {
    },
    close: function() {
    }
    });
} else {
}
    
    // Inicializar datepicker
    $('.datepicker-input').datepicker({
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
	
	// Verificación en tiempo real de lead existente
function setupRealTimeValidation() {
    const emailInput = $('#lead_e_mail');
    const phoneInput = $('#lead_celular');
    let emailTimer = null;
    let phoneTimer = null;
    const validationDelay = 500; // milisegundos
    
    // Limpiar mensajes de verificación existentes
    function clearExistingMessages() {
        $('.lead-exists-message').remove();
    }
    
    // Validar email cuando el usuario deja de escribir
    emailInput.on('input', function() {
        clearTimeout(emailTimer);
        clearExistingMessages();
        
        const email = $(this).val().trim();
        if (email && validateEmailFormat(email)) {
            emailTimer = setTimeout(function() {
                checkExistingLead(email, '');
            }, validationDelay);
        }
    });
    
    // Validar teléfono cuando el usuario deja de escribir
    phoneInput.on('input', function() {
        clearTimeout(phoneTimer);
        clearExistingMessages();
        
        const phone = $(this).val().trim();
        if (phone && phone.length >= 10) {
            phoneTimer = setTimeout(function() {
                checkExistingLead('', phone);
            }, validationDelay);
        }
    });
    
    // Verificar formato de email
    function validateEmailFormat(email) {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailPattern.test(email);
    }
}

// Función para verificar lead existente
function checkExistingLead(email, celular) {
    $.ajax({
        url: ltbLeadAdd.ajaxurl,
        type: 'POST',
        data: {
            action: 'check_existing_lead',
            nonce: ltbLeadAdd.nonce,
            email: email,
            celular: celular
        },
        success: function(response) {
            if (response.success && response.data.exists) {
                showExistingLeadMessage(response.data);
            }
        }
    });
}

// Mostrar mensaje de lead existente
function showExistingLeadMessage(data) {
    clearExistingMessages();
    
    let messageHtml = `
        <div class="lead-exists-message">
            <div class="alert-warning">
                <p><strong>Contacto encontrado:</strong> ${data.message}</p>
            `;
    
    if (data.lead) {
        messageHtml += `
            <p><strong>Nombre:</strong> ${data.lead.lead_nombre} ${data.lead.lead_apellido}</p>
            <p><strong>Email:</strong> ${data.lead.lead_e_mail}</p>
            <p><strong>Teléfono:</strong> ${data.lead.lead_celular}</p>
            
            <div class="existing-lead-actions">
                <button type="button" class="button view-existing-lead" 
                        data-lead-id="${data.lead._ID}">
                    Ver detalles del lead
                </button>
                <button type="button" class="button update-existing-lead"
                        data-lead-id="${data.lead._ID}">
                    Continuar con este lead
                </button>
            </div>
        `;
    }
    
    messageHtml += `
            </div>
        </div>
    `;
    
    // Insertar mensaje después del campo que generó la coincidencia
    if (!$('.lead-exists-message').length) {
        $('#lead_e_mail').parent().after(messageHtml);
        
        // Agregar handlers para los botones
        $('.view-existing-lead').on('click', function() {
            const leadId = $(this).data('lead-id');
            window.location.href = `/lead-details/lead-${leadId}`;
        });
        
        $('.update-existing-lead').on('click', function() {
            const leadId = $(this).data('lead-id');
            switchToExistingLeadMode(leadId, data.lead);
        });
    }
}

// Cambiar a modo de lead existente
function switchToExistingLeadMode(leadId, leadData) {
    // Ocultar campos de lead y mostrar solo campos de evento
    $('#lead_only_toggle').click();
    $('#event_toggle').click();
    
    // Pre-completar campos de lead (solo visual, no se enviarán)
    $('#lead_nombre').val(leadData.lead_nombre).prop('disabled', true);
    $('#lead_apellido').val(leadData.lead_apellido).prop('disabled', true);
    $('#lead_celular').val(leadData.lead_celular).prop('disabled', true);
    $('#lead_e_mail').val(leadData.lead_e_mail).prop('disabled', true);
    
    // Cambiar texto de botones
    $('#submit_full_form').text('Agregar evento a lead existente').data('lead-id', leadId);
    
    // Eliminar mensaje de lead existente
    $('.lead-exists-message').remove();
    
    // Añadir clase para indicar que estamos en modo de lead existente
    $('.modal-content').addClass('existing-lead-mode');
    
    // Cambiar el título del modal
    $('.modal-header h2').text('Agregar evento a lead existente');
}

// Función para limpiar mensajes de verificación
function clearExistingMessages() {
    $('.lead-exists-message').remove();
}
    
    // Abrir modal
    addLeadBtn.on('click', function() {
        modalContainer.addClass('active');
        $('body').addClass('modal-open');
    });
    
    // Cerrar modal con X o backdrop
    $('.modal-close, .modal-backdrop').on('click', function() {
        closeModal();
    });
    
    // Cerrar modal con Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modalContainer.hasClass('active')) {
            closeModal();
        }
    });
    
    // Prevenir cierre al hacer clic dentro del modal
    $('.modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Toggle entre formularios
    leadFormToggle.on('click', function() {
        $(this).addClass('active');
        eventFormToggle.removeClass('active');
        eventForm.slideUp(300);
        fullFormSubmit.hide();
        leadFormSubmit.show();
    });
    
    eventFormToggle.on('click', function() {
        $(this).addClass('active');
        leadFormToggle.removeClass('active');
        eventForm.slideDown(300);
        leadFormSubmit.hide();
        fullFormSubmit.show();
    });
    
    // Manejar envío de solo lead
leadFormSubmit.on('click', function(e) {
    e.preventDefault();
    
    if (!validateLeadForm()) {
        return;
    }
    
    const leadData = {
        action: 'add_new_lead',
        nonce: ltbLeadAdd.nonce,
        form_type: 'lead_only',
        lead_razon_social: $('#lead_razon_social').val(),
        lead_nombre: $('#lead_nombre').val(),
        lead_apellido: $('#lead_apellido').val(),
        lead_celular: $('#lead_celular').val(),
        lead_e_mail: $('#lead_e_mail').val()
    };
    
    submitForm(leadData);
});
    
    // Manejar envío de lead + evento
    fullFormSubmit.on('click', function(e) {
        e.preventDefault();
        
        if (!validateLeadForm() || !validateEventForm()) {
            return;
        }
        
        const formData = {
            action: 'add_new_lead',
            nonce: ltbLeadAdd.nonce,
            form_type: 'lead_and_event',
            // Datos del lead
			lead_razon_social: $('#lead_razon_social').val(),
            lead_nombre: $('#lead_nombre').val(),
            lead_apellido: $('#lead_apellido').val(),
            lead_celular: $('#lead_celular').val(),
            lead_e_mail: $('#lead_e_mail').val(),
            // Datos del evento
            fecha_de_evento: $('#evento_fecha').val(),
            tipo_de_evento: $('#evento_tipo').val(),
            evento_asistentes: $('#evento_asistentes').val(),
            evento_status: $('#evento_status').val(),
            ubicacion_evento: $('#evento_ubicacion').val(),
            direccion_evento: $('#evento_direccion').val(),
            comentarios_evento: $('#evento_comentarios').val(),
            evento_servicio_de_interes: $('#evento_servicio').val()
        };
        
        // DEBUG: Log form data being sent
        
        submitForm(formData);
    });
    
    // Funciones auxiliares
    function closeModal() {
        modalContainer.removeClass('active');
        $('body').removeClass('modal-open');
        resetForm();
    }
    
    function resetForm() {
    leadForm[0].reset();
    eventForm[0].reset();
    eventForm.hide();
    leadFormToggle.addClass('active');
    eventFormToggle.removeClass('active');
    leadFormSubmit.show();
    fullFormSubmit.hide();
    $('.error-message').remove();
    $('.lead-exists-message').remove();
    
    // Restablecer campos deshabilitados
    $('#lead_nombre, #lead_apellido, #lead_celular, #lead_e_mail').prop('disabled', false);
    
    // Restablecer títulos y clases
    $('.modal-header h2').text('Agregar Nuevo Lead');
    $('.modal-content').removeClass('existing-lead-mode');
    $('#submit_full_form').text('Guardar Lead y Evento').removeData('lead-id');
}
    
    function validateLeadForm() {
        $('.error-message').remove();
        let isValid = true;
        
        // Validar nombre
        if (!$('#lead_nombre').val().trim()) {
            $('#lead_nombre').after('<span class="error-message">El nombre es requerido</span>');
            isValid = false;
        }
        
        // Validar apellido
        if (!$('#lead_apellido').val().trim()) {
            $('#lead_apellido').after('<span class="error-message">El apellido es requerido</span>');
            isValid = false;
        }
        
        // Validar celular
        const phonePattern = /^\d{10,15}$/;
        if (!phonePattern.test($('#lead_celular').val().replace(/\D/g, ''))) {
            $('#lead_celular').after('<span class="error-message">Ingrese un número de teléfono válido</span>');
            isValid = false;
        }
        
        // Validar email
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test($('#lead_e_mail').val())) {
            $('#lead_e_mail').after('<span class="error-message">Ingrese un email válido</span>');
            isValid = false;
        }
        
        return isValid;
    }
    
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
    
    function submitForm(formData) {
    // Verificar si estamos en modo de lead existente
    if ($('.modal-content').hasClass('existing-lead-mode')) {
        const leadId = $('#submit_full_form').data('lead-id');
        if (leadId) {
            // Enviar solo datos del evento para lead existente
            const eventData = {
                action: 'add_event_to_lead',
                nonce: ltbLeadAdd.nonce,
                lead_id: leadId,
                fecha_de_evento: $('#evento_fecha').val(),
                tipo_de_evento: $('#evento_tipo').val(),
                evento_asistentes: $('#evento_asistentes').val(), 
                evento_status: $('#evento_status').val(),
                ubicacion_evento: $('#evento_ubicacion').val(),
                direccion_evento: $('#evento_direccion').val(),
                comentarios_evento: $('#evento_comentarios').val(),
                evento_servicio_de_interes: $('#evento_servicio').val()
            };
            
            // DEBUG: Log event data for existing lead
            
            submitEventToExistingLead(eventData);
            return;
        }
    }
    
    // Continuar con el envío normal si no estamos en modo de lead existente
    const submitBtn = formData.form_type === 'lead_only' ? leadFormSubmit : fullFormSubmit;
    const originalText = submitBtn.text();
    
    submitBtn.prop('disabled', true).text('Guardando...');
    
    $.ajax({
        url: ltbLeadAdd.ajaxurl,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                alert('Lead guardado correctamente');
                closeModal();
                
                // Recargar tabla
                if (typeof updateTable === 'function') {
                    updateTable();
                } else {
                    location.reload();
                }
            } else if (response.data && response.data.code === 'existing_lead') {
                // Lead ya existe - mostrar opciones
                handleExistingLead(response.data);
            } else {
                alert('Error al guardar: ' + response.data);
            }
        },
        error: function() {
            alert('Error de conexión al guardar');
        },
        complete: function() {
            submitBtn.prop('disabled', false).text(originalText);
        }
    });
}

// Nueva función para manejar leads existentes
function handleExistingLead(data) {
    // Ocultar formulario actual
    leadForm.hide();
    eventForm.hide();
    leadFormSubmit.hide();
    fullFormSubmit.hide();
    
    // Crear y mostrar opciones para lead existente
    const existingLeadContent = `
        <div class="existing-lead-info">
            <div class="alert alert-info">
                <p>El correo ingresado ya está registrado.</p>
                <p>¿Qué deseas hacer?</p>
            </div>
            <div class="existing-lead-actions">
                <button id="viewExistingLead" class="button button-secondary">
                    Ver detalles del lead
                </button>
                <button id="addEventToLead" class="button button-primary">
                    Agregar evento a este lead
                </button>
            </div>
        </div>
    `;
    
    // Insertar contenido en el modal
    $('.modal-body').append(existingLeadContent);
    
    // Manejar acciones
    $('#viewExistingLead').on('click', function() {
        window.location.href = '/lead-details/lead-' + data.lead_id;
    });
    
    $('#addEventToLead').on('click', function() {
        // Ocultar opciones y mostrar formulario de evento
        $('.existing-lead-info').remove();
        leadForm.slideUp(300);
        eventForm.slideDown(300);
        
        // Configurar para enviar solo la parte de evento
        const addEventBtn = $('<button id="submitEventOnly" class="button button-primary">Guardar Evento</button>');
        $('.modal-footer').append(addEventBtn);
        
        // Manejar envío de solo evento
        addEventBtn.on('click', function(e) {
            e.preventDefault();
            
            if (!validateEventForm()) {
                return;
            }
            
            const eventData = {
                action: 'add_event_to_lead',
                nonce: ltbLeadAdd.nonce,
                lead_id: data.lead_id,
                fecha_de_evento: $('#evento_fecha').val(),
                tipo_de_evento: $('#evento_tipo').val(),
                evento_asistentes: $('#evento_asistentes').val(),
                evento_status: $('#evento_status').val(),
                ubicacion_evento: $('#evento_ubicacion').val(),
                direccion_evento: $('#evento_direccion').val(),
                comentarios_evento: $('#evento_comentarios').val(),
                evento_servicio_de_interes: $('#evento_servicio').val()
            };
            
            // Enviar datos del evento
            submitEventToExistingLead(eventData);
        });
    });
}

// Nueva función para enviar evento a lead existente
function submitEventToExistingLead(eventData) {
    const submitBtn = $('#submitEventOnly');
    const originalText = submitBtn.text();
    
    submitBtn.prop('disabled', true).text('Guardando...');
    
    $.ajax({
        url: ltbLeadAdd.ajaxurl,
        type: 'POST',
        data: eventData,
        success: function(response) {
            if (response.success) {
                alert('Evento agregado correctamente');
                closeModal();
                
                // Recargar tabla
                if (typeof updateTable === 'function') {
                    updateTable();
                } else {
                    location.reload();
                }
            } else {
                alert('Error al guardar evento: ' + response.data);
            }
        },
        error: function() {
            alert('Error de conexión al guardar evento');
        },
        complete: function() {
            submitBtn.prop('disabled', false).text(originalText);
        }
    });
}
	// Agregar después de otras inicializaciones
setupRealTimeValidation();

    // Añadir estilos CSS para el autocomplete
    const autocompleteStyles = `
    <style id="autocomplete-styles">
        .ui-autocomplete {
            max-height: 200px !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            background: white !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
            z-index: 999999 !important;
            padding: 0 !important;
            list-style: none !important;
            position: absolute !important;
            font-family: inherit !important;
        }

        .ui-autocomplete .ui-menu-item {
            padding: 8px 12px !important;
            cursor: pointer !important;
            transition: background-color 0.2s !important;
            border: none !important;
            margin: 0 !important;
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .ui-autocomplete .ui-menu-item:hover,
        .ui-autocomplete .ui-menu-item.ui-state-focus {
            background-color: #f8f9fa !important;
            color: inherit !important;
        }

        .ui-autocomplete .ui-state-active,
        .ui-autocomplete .ui-widget-content .ui-state-active {
            background: #e9ecef !important;
            border: none !important;
            margin: 0 !important;
            font-weight: normal !important;
            color: inherit !important;
        }

        .ui-autocomplete .ui-menu-item-wrapper {
            padding: 0 !important;
            display: block !important;
            width: 100% !important;
        }
        
        /* Asegurar que el input sea visible */
        #evento_servicio_search {
            width: 100% !important;
            padding: 8px 10px !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            font-size: 14px !important;
            background: white !important;
        }
    </style>
    `;
    
    // Añadir estilos al DOM si no existen
    if (!$('#autocomplete-styles').length) {
        $('head').append(autocompleteStyles);
    }
	
});