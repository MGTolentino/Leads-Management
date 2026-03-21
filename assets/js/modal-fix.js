/**
 * Modal Fix - JavaScript para arreglar el funcionamiento del modal
 */

jQuery(document).ready(function($) {
    
    // Arreglar el botón de agregar lead
    $('#add_lead_btn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Buscar el modal con el ID correcto
        const modal = $('#lead_modal');
        
        if (modal.length) {
            // Asegurar que el modal esté en la posición correcta
            if (!modal.hasClass('modal-overlay')) {
                modal.addClass('modal-overlay');
            }
            
            // Mostrar el modal
            modal.show().addClass('show active');
            modal.fadeIn(300);
            
            // Prevenir scroll del body
            $('body').css('overflow', 'hidden');
        }
    });
    
    // Cerrar modal con el botón X
    $('.close-modal, .modal-close').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });
    
    // Cerrar modal al hacer click fuera
    $('#lead_modal').on('click', function(e) {
        if ($(e.target).is('#lead_modal')) {
            closeModal();
        }
    });
    
    // Cerrar modal con ESC
    $(document).on('keyup', function(e) {
        if (e.key === "Escape" && $('#lead_modal').is(':visible')) {
            closeModal();
        }
    });
    
    // Función para cerrar el modal
    function closeModal() {
        $('#lead_modal').fadeOut(300).removeClass('show active');
        $('body').css('overflow', '');
    }
    
    // Prevenir que el formulario dentro del modal propague el click
    $('.modal-container, .modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Asegurar que el modal no esté visible al cargar la página
    $(window).on('load', function() {
        $('#lead_modal').hide().removeClass('show active');
    });
    
    // También manejar el submit del formulario
    $('#lead_form').on('submit', function(e) {
        e.preventDefault();
        // El pipeline-simple.js manejará el submit
    });
});

// Fix para asegurar que el modal esté oculto inicialmente
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('lead_modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show', 'active');
    }
});