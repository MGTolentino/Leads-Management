jQuery(function($) {
    const followupForm = $('#followupForm');
    
    // Establecer fecha actual por defecto
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    $('#seguimiento_fecha').val(now.toISOString().slice(0,16));

    followupForm.on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'save_followup',
            nonce: ltbFollowup.nonce,
            lead_id: ltbFollowup.leadId,
            status: $('#seguimiento_status').val(),
            fecha: $('#seguimiento_fecha').val(),
            actividad: $('#seguimiento_actividad').val(),
            notas: $('#seguimiento_notas').val()
        };

        // Deshabilitar el formulario durante el envío
        const submitButton = followupForm.find('button[type="submit"]');
        const originalText = submitButton.text();
        submitButton.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: ltbFollowup.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Seguimiento guardado correctamente');
                    // Limpiar formulario
                    $('#seguimiento_status').val('Pendiente');
                    $('#seguimiento_fecha').val(now.toISOString().slice(0,16));
                    $('#seguimiento_actividad').val('Contacto inicial');
                    $('#seguimiento_notas').val('');
                    
                    // Recargar lista de seguimientos si existe
                    if (typeof updateSeguimientos === 'function') {
                        updateSeguimientos();
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error al guardar el seguimiento: ' + response.data);
                }
            },
            error: function() {
                alert('Error al guardar el seguimiento');
            },
            complete: function() {
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
});