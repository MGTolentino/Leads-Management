/**
 * Modal Fix - JavaScript para arreglar el funcionamiento del modal
 */

jQuery(document).ready(function($) {
    
    // Arreglar el botón de agregar lead
    $('#add_lead_btn').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Buscar el modal y mostrarlo
        const modal = $('#add_lead_modal');
        
        if (modal.length) {
            // Asegurar que el modal esté en la posición correcta
            if (!modal.hasClass('modal-overlay')) {
                modal.addClass('modal-overlay');
            }
            
            // Mostrar el modal
            modal.fadeIn(300).addClass('show active');
            
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
    $('#add_lead_modal').on('click', function(e) {
        if ($(e.target).is('#add_lead_modal')) {
            closeModal();
        }
    });
    
    // Cerrar modal con ESC
    $(document).on('keyup', function(e) {
        if (e.key === "Escape" && $('#add_lead_modal').is(':visible')) {
            closeModal();
        }
    });
    
    // Función para cerrar el modal
    function closeModal() {
        $('#add_lead_modal').fadeOut(300).removeClass('show active');
        $('body').css('overflow', '');
    }
    
    // Prevenir que el formulario dentro del modal propague el click
    $('.modal-container, .modal-content').on('click', function(e) {
        e.stopPropagation();
    });
    
    // Asegurar que el modal no esté visible al cargar la página
    $(window).on('load', function() {
        $('#add_lead_modal').hide().removeClass('show active');
    });
});

// Fix para asegurar que el modal esté oculto inicialmente
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('add_lead_modal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show', 'active');
    }
});