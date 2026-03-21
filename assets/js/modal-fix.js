/**
 * Modal Fix - JavaScript para arreglar el funcionamiento del modal
 */

jQuery(document).ready(function($) {
    
    // Solo aplicar fixes de CSS, no manejar el click porque pipeline-simple.js ya lo hace
    // Asegurar que el modal tenga las clases correctas para el CSS
    const modal = $('#lead_modal');
    if (modal.length && !modal.hasClass('modal')) {
        modal.addClass('modal');
    }
    
    // Mejorar el comportamiento del modal cuando se abre/cierra
    $(document).on('modalOpen', '#lead_modal', function() {
        $(this).addClass('show');
        $('body').css('overflow', 'hidden');
    });
    
    $(document).on('modalClose', '#lead_modal', function() {
        $(this).removeClass('show active');
        $('body').css('overflow', '');
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