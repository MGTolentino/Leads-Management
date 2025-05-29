jQuery(function($) {
    // Toggle para mostrar/ocultar filtros avanzados
    $('#toggle_advanced_filters').on('click', function() {
        const $advancedContainer = $('#advanced_filters_container');
        
        $(this).toggleClass('active');
        
        if ($advancedContainer.is(':visible')) {
            $advancedContainer.slideUp(300);
        } else {
            $advancedContainer.slideDown(300);
        }
    });
    
    // Inicialización - mantener ocultos los filtros avanzados
    $(document).ready(function() {
        $('#advanced_filters_container').hide();
    });
    
    // Ajuste para Select2 para mejor visualización de texto
    $('.select2-selection__rendered').css({
        'text-overflow': 'ellipsis',
        'white-space': 'nowrap',
        'overflow': 'hidden',
        'display': 'block'
    });
    
    // Ajuste de altura para selects que muestran texto truncado
    $('.select2-container--default .select2-selection--single').css({
        'height': 'auto',
        'min-height': '30px'
    });
});