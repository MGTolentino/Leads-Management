<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Filters {
    private $query_handler;

    public function __construct() {
    $this->query_handler = new LTB_Leads_Query();
    $this->init_hooks();
}
	
	private function init_hooks() {
    add_action('wp_ajax_filter_leads', array($this, 'handle_filter_leads'));
    add_action('wp_ajax_nopriv_filter_leads', array($this, 'handle_filter_leads'));
}
	
	public function handle_filter_leads() {
    check_ajax_referer('ltb_leads_filter_nonce', 'nonce');

    $args = array(
        'fecha_inicio' => isset($_POST['fecha_inicio']) ? sanitize_text_field($_POST['fecha_inicio']) : '',
        'fecha_fin' => isset($_POST['fecha_fin']) ? sanitize_text_field($_POST['fecha_fin']) : '',
        'fecha_evento_inicio' => isset($_POST['fecha_evento_inicio']) ? sanitize_text_field($_POST['fecha_evento_inicio']) : '',
        'fecha_evento_fin' => isset($_POST['fecha_evento_fin']) ? sanitize_text_field($_POST['fecha_evento_fin']) : '',
        'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
        'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'fecha_solicitud',
        'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        'paged' => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
        'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20
    );

    try {
        $results = $this->query_handler->get_leads($args);
        $total = $this->query_handler->get_total_leads($args);

        wp_send_json_success(array(
            'data' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page'])
        ));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}
	
	

    /**
     * Renderizar panel de filtros
     */
   public function render_filters() {
    ob_start();
    ?>
    <div class="leads-filters">
    <div class="filters-toggle">
        <button type="button" id="toggle_filters_btn" class="toggle-filters-btn">
            <span class="dashicons dashicons-filter"></span>
            Filtros
        </button>
    </div>
    
    <div class="filters-container" id="filters_container">
        <div class="filters-row">
            <div class="filter-group">
                <label for="fecha_ingreso">Ingreso:</label>
                <input type="text" id="fecha_ingreso" class="datepicker-input" placeholder="Fecha..." readonly>
            </div>
            
          <div class="filter-group date-range-group">
    <label>Evento:</label>
    <div class="date-range-inputs">
        <input type="text" id="fecha_evento_inicio" class="datepicker-input" placeholder="Desde..." readonly>
        <span class="date-range-separator">-</span>
        <input type="text" id="fecha_evento_fin" class="datepicker-input" placeholder="Hasta..." readonly>
        <div class="date-format-buttons">
            <button type="button" id="btn_mes_completo" class="date-format-btn">Mes</button>
            <button type="button" id="btn_anio_completo" class="date-format-btn">Año</button>
        </div>
    </div>
    <div class="date-display">
        <span id="fecha_evento_display" class="date-friendly-format"></span>
    </div>
</div>
            
            <div class="filter-group">
                <label for="search_leads">Buscar:</label>
                <input type="text" id="search_leads" placeholder="Nombre, email...">
            </div>
        </div>
        
        <div class="filters-row">
            <div class="filter-group">
                <label for="ordenamiento">Ordenar:</label>
                <select id="ordenamiento">
                    <option value="fecha_solicitud">F. solicitud</option>
                    <option value="nombre">Nombre</option>
                    <option value="fecha_evento">F. evento</option>
                    <option value="tipo_evento">Tipo evento</option>
                    <option value="status">Status</option>
                </select>
                
                <select id="orden">
                    <option value="DESC">↓</option>
                    <option value="ASC">↑</option>
                </select>
            </div>
            
            <div class="filter-actions">
                <button id="aplicar_filtros" class="button button-primary">
                    <span class="dashicons dashicons-filter"></span>
                    Filtrar
                </button>
                <button id="limpiar_filtros" class="button">
                    <span class="dashicons dashicons-dismiss"></span>
                    Limpiar
                </button>
            </div>
        </div>
    </div>
</div>
    <?php
    return ob_get_clean();
}
}