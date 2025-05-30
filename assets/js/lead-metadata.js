jQuery(function($) {
    // Manejo de las pestañas de metadatos
    $('.metadata-tab-link').on('click', function(e) {
        e.preventDefault();
        
        // Obtener el target
        const targetId = $(this).attr('data-target');
        
        // Remover clase activa de todas las pestañas y paneles
        $('.metadata-tab-link').removeClass('active');
        $('.metadata-tab-pane').removeClass('active');
        
        // Activar la pestaña y panel seleccionados
        $(this).addClass('active');
        $('#' + targetId).addClass('active');
    });
    
    // Iniciar con la primera pestaña activa
    $('.metadata-tab-link:first').click();
    
    // Guardar metadatos
    $('#save_lead_metadata').on('click', function(e) {
        e.preventDefault();
        
        // Mostrar indicador de carga
        $(this).prop('disabled', true).text('Guardando...');
        
        // Recopilar datos del formulario
        const formData = {
            lead_id: $('#lead_id').val(),
            prioridad: $('#prioridad').val(),
            valor_potencial: $('#valor_potencial').val(),
            probabilidad: $('#probabilidad').val(),
            responsable_id: $('#responsable_id').val(),
            ultima_interaccion: $('#ultima_interaccion').val(),
            proxima_accion_fecha: $('#proxima_accion_fecha').val(),
            fuente: $('#fuente').val(),
            campana: $('#campana').val(),
            ubicacion: $('#ubicacion').val(),
            industria: $('#industria').val(),
            estado_propuesta: $('#estado_propuesta').val(),
            rango_cotizacion: $('#rango_cotizacion').val(),
            temporada: $('#temporada').val(),
            servicios_requeridos: $('#servicios_requeridos').val(),
            venue: $('#venue').val(),
            etiquetas: $('#etiquetas').val()
        };
        
        // Enviar datos al servidor
        $.ajax({
            url: ltbLeadMetadata.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_lead_metadata',
                nonce: ltbLeadMetadata.nonce,
                ...formData
            },
            success: function(response) {
                if (response.success) {
                    // Mostrar mensaje de éxito
                    const $message = $('<div class="metadata-success-message">Metadatos guardados correctamente</div>');
                    $('.metadata-form-actions').prepend($message);
                    
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(function() {
                        $message.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // Mostrar mensaje de error
                    const $message = $('<div class="metadata-error-message">Error al guardar: ' + response.data + '</div>');
                    $('.metadata-form-actions').prepend($message);
                    
                    setTimeout(function() {
                        $message.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 3000);
                }
            },
            error: function() {
                // Mostrar mensaje de error
                const $message = $('<div class="metadata-error-message">Error de conexión al guardar</div>');
                $('.metadata-form-actions').prepend($message);
                
                setTimeout(function() {
                    $message.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            },
            complete: function() {
                // Restaurar botón
                $('#save_lead_metadata').prop('disabled', false).text('Guardar Metadatos');
            }
        });
    });
    
    // Inicializar select2 para etiquetas
    if ($.fn.select2) {
        $('#etiquetas').select2({
            placeholder: 'Seleccionar o crear etiquetas',
            tags: true,
            tokenSeparators: [','],
            width: '100%'
        });
        
        // Inicializar otros campos select con select2
        $('.metadata-form-group select:not(#etiquetas)').select2({
            placeholder: 'Seleccionar...',
            width: '100%'
        });
    }
    
    // Inicializar datepicker para campos de fecha
    if ($.fn.datepicker) {
        $('.metadata-date-input').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '2000:+2'
        });
    }
});