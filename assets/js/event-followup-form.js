jQuery(function($) {
    const eventFollowupForm = $('#eventFollowupForm');
    
    // Establecer fecha actual por defecto
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#seguimiento_fecha').val(now.toISOString().slice(0,16));

    // Manejar envío del formulario
    eventFollowupForm.on('submit', function(e) {
        e.preventDefault();
        
        // Obtener el event_id del formulario
        const eventId = $('#event_id').val();
        
        const formData = {
            action: 'save_event_followup',
            nonce: ltbEventFollowup.nonce,
            event_id: eventId,
            cotizacion_id: $('#cotizacion_id').val(),
            status: $('#seguimiento_status').val(),
            fecha: $('#seguimiento_fecha').val(),
            actividad: $('#seguimiento_actividad').val(),
            notas: $('#seguimiento_notas').val()
        };

        // Deshabilitar el formulario durante el envío
        const submitButton = eventFollowupForm.find('button[type="submit"]');
        const originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: ltbEventFollowup.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Mostrar mensaje de éxito
                    showNotification('Seguimiento guardado correctamente', 'success');
                    
                    // Limpiar formulario
                    $('#seguimiento_status').val('Pendiente');
                    $('#seguimiento_fecha').val(now.toISOString().slice(0,16));
                    $('#seguimiento_actividad').val('Contacto inicial');
                    $('#seguimiento_notas').val('');
                    $('#cotizacion_id').val('');
                    
                    // Recargar lista de seguimientos
                    reloadFollowupsList();
                    
                    // Ocultar formulario
                    $('.followup-form-container').slideUp(300);
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error al guardar el seguimiento: ' + error, 'error');
            },
            complete: function() {
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });

    // Función para recargar la lista de seguimientos
    function reloadFollowupsList() {
        const eventId = $('#event_id').val();
        const container = $('.followups-timeline');
        
        // Mostrar loading
        container.html('<div class="loading-spinner">Cargando seguimientos...</div>');
        
        // Recargar la página para actualizar la lista
        // En una versión futura, esto podría ser una llamada AJAX
        setTimeout(function() {
            location.reload();
        }, 500);
    }

    // Función para mostrar notificaciones
    function showNotification(message, type) {
        // Remover notificaciones anteriores
        $('.event-notification').remove();
        
        const notification = $('<div class="event-notification ' + type + '">' + message + '</div>');
        
        // Agregar notificación al DOM
        $('.event-single-container').prepend(notification);
        
        // Animar entrada
        notification.hide().fadeIn(300);
        
        // Auto-ocultar después de 3 segundos
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Actualizar lista de cotizaciones cuando cambie el evento (para futuras implementaciones)
    $('#event_id').on('change', function() {
        updateQuotesList($(this).val());
    });

    // Función para actualizar lista de cotizaciones
    function updateQuotesList(eventId) {
        const select = $('#cotizacion_id');
        
        // Limpiar opciones actuales
        select.html('<option value="">-- Cargando cotizaciones... --</option>');
        
        $.ajax({
            url: ltbEventFollowup.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_event_quotes',
                nonce: ltbEventFollowup.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success && response.data.quotes) {
                    select.html('<option value="">-- Seguimiento general --</option>');
                    
                    response.data.quotes.forEach(function(quote) {
                        const quoteName = quote.nombre_pdf || 'Cotización #' + quote._ID;
                        select.append('<option value="' + quote._ID + '">' + quoteName + '</option>');
                    });
                }
            },
            error: function() {
                select.html('<option value="">-- Error al cargar cotizaciones --</option>');
            }
        });
    }
});

// Estilos CSS para notificaciones
jQuery(document).ready(function($) {
    if ($('.event-notification').length === 0) {
        $('head').append(`
            <style>
                .event-notification {
                    padding: 15px 20px;
                    margin: 10px 0;
                    border-radius: 4px;
                    font-weight: 500;
                    animation: slideDown 0.3s ease-out;
                }
                
                .event-notification.success {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }
                
                .event-notification.error {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }
                
                .loading-spinner {
                    text-align: center;
                    padding: 20px;
                    color: #666;
                }
                
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            </style>
        `);
    }
});