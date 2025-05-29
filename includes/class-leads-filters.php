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

    // Recopilar todos los parámetros de filtro
    $args = array(
        // Fechas de ingreso (ahora con rango)
        'fecha_inicio' => isset($_POST['fecha_ingreso_inicio']) ? sanitize_text_field($_POST['fecha_ingreso_inicio']) : '',
        'fecha_fin' => isset($_POST['fecha_ingreso_fin']) ? sanitize_text_field($_POST['fecha_ingreso_fin']) : '',
        
        // Fechas de evento
        'fecha_evento_inicio' => isset($_POST['fecha_evento_inicio']) ? sanitize_text_field($_POST['fecha_evento_inicio']) : '',
        'fecha_evento_fin' => isset($_POST['fecha_evento_fin']) ? sanitize_text_field($_POST['fecha_evento_fin']) : '',
        
        // Búsqueda general
        'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
        
        // Ordenamiento
        'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'fecha_solicitud',
        'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        
        // Paginación
        'paged' => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
        'per_page' => isset($_POST['per_page']) ? absint($_POST['per_page']) : 20,
        
        // Nuevos filtros
        'tipo_evento' => isset($_POST['tipo_evento']) && is_array($_POST['tipo_evento']) ? 
            array_map('sanitize_text_field', $_POST['tipo_evento']) : array(),
        'status' => isset($_POST['status']) && is_array($_POST['status']) ? 
            array_map('sanitize_text_field', $_POST['status']) : array(),
        'invitados' => isset($_POST['invitados']) ? sanitize_text_field($_POST['invitados']) : ''
    );

    try {
        $results = $this->query_handler->get_leads($args);
        $total = $this->query_handler->get_total_leads($args);

        wp_send_json_success(array(
            'data' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'applied_filters' => $this->get_applied_filters_summary($args)
        ));
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

/**
 * Genera un resumen de los filtros aplicados para mostrar al usuario
 */
private function get_applied_filters_summary($args) {
    $summary = array();
    
    // Fecha de evento
    if (!empty($args['fecha_evento_inicio']) || !empty($args['fecha_evento_fin'])) {
        $fecha_texto = '';
        if (!empty($args['fecha_evento_inicio']) && !empty($args['fecha_evento_fin'])) {
            if ($args['fecha_evento_inicio'] === $args['fecha_evento_fin']) {
                $fecha_texto = 'Evento el ' . $this->format_date_display($args['fecha_evento_inicio']);
            } else {
                $fecha_texto = 'Evento del ' . $this->format_date_display($args['fecha_evento_inicio']) . 
                            ' al ' . $this->format_date_display($args['fecha_evento_fin']);
            }
        } elseif (!empty($args['fecha_evento_inicio'])) {
            $fecha_texto = 'Evento desde ' . $this->format_date_display($args['fecha_evento_inicio']);
        } elseif (!empty($args['fecha_evento_fin'])) {
            $fecha_texto = 'Evento hasta ' . $this->format_date_display($args['fecha_evento_fin']);
        }
        
        if (!empty($fecha_texto)) {
            $summary[] = array(
                'type' => 'fecha_evento',
                'label' => $fecha_texto,
                'value' => array(
                    'inicio' => $args['fecha_evento_inicio'],
                    'fin' => $args['fecha_evento_fin']
                )
            );
        }
    }
    
    // Fecha de ingreso
    if (!empty($args['fecha_inicio']) || !empty($args['fecha_fin'])) {
        $fecha_texto = '';
        if (!empty($args['fecha_inicio']) && !empty($args['fecha_fin'])) {
            if ($args['fecha_inicio'] === $args['fecha_fin']) {
                $fecha_texto = 'Ingreso el ' . $this->format_date_display($args['fecha_inicio']);
            } else {
                $fecha_texto = 'Ingreso del ' . $this->format_date_display($args['fecha_inicio']) . 
                            ' al ' . $this->format_date_display($args['fecha_fin']);
            }
        } elseif (!empty($args['fecha_inicio'])) {
            $fecha_texto = 'Ingreso desde ' . $this->format_date_display($args['fecha_inicio']);
        } elseif (!empty($args['fecha_fin'])) {
            $fecha_texto = 'Ingreso hasta ' . $this->format_date_display($args['fecha_fin']);
        }
        
        if (!empty($fecha_texto)) {
            $summary[] = array(
                'type' => 'fecha_ingreso',
                'label' => $fecha_texto,
                'value' => array(
                    'inicio' => $args['fecha_inicio'],
                    'fin' => $args['fecha_fin']
                )
            );
        }
    }
    
    // Tipo de evento
    if (!empty($args['tipo_evento'])) {
        $summary[] = array(
            'type' => 'tipo_evento',
            'label' => 'Tipo: ' . implode(', ', $args['tipo_evento']),
            'value' => $args['tipo_evento']
        );
    }
    
    // Status
    if (!empty($args['status'])) {
        $status_labels = array();
        if (class_exists('LTB_Leads_Status_Utils')) {
            $status_options = LTB_Leads_Status_Utils::get_status_options();
            foreach ($args['status'] as $status_value) {
                if (isset($status_options[$status_value])) {
                    $status_labels[] = $status_options[$status_value];
                } else {
                    $status_labels[] = $status_value;
                }
            }
        } else {
            $status_labels = $args['status'];
        }
        
        $summary[] = array(
            'type' => 'status',
            'label' => 'Status: ' . implode(', ', $status_labels),
            'value' => $args['status']
        );
    }
    
    // Invitados
    if (!empty($args['invitados'])) {
        $label = 'Invitados: ';
        switch ($args['invitados']) {
            case '1-50': $label .= 'Menos de 50'; break;
            case '51-100': $label .= '51 - 100'; break;
            case '101-200': $label .= '101 - 200'; break;
            case '201-500': $label .= '201 - 500'; break;
            case '501+': $label .= 'Más de 500'; break;
            default: $label .= $args['invitados'];
        }
        
        $summary[] = array(
            'type' => 'invitados',
            'label' => $label,
            'value' => $args['invitados']
        );
    }
    
    // Búsqueda
    if (!empty($args['search'])) {
        $summary[] = array(
            'type' => 'search',
            'label' => 'Búsqueda: ' . $args['search'],
            'value' => $args['search']
        );
    }
    
    return $summary;
}

/**
 * Formatea una fecha para mostrar al usuario
 */
private function format_date_display($date) {
    // Si tiene formato YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $timestamp = strtotime($date);
        return date('d/m/Y', $timestamp);
    }
    
    // Si tiene formato YYYY-MM (mes)
    if (preg_match('/^\d{4}-\d{2}$/', $date)) {
        $partes = explode('-', $date);
        $meses = array(
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        );
        return $meses[$partes[1]] . ' ' . $partes[0];
    }
    
    // Si tiene formato YYYY (año)
    if (preg_match('/^\d{4}$/', $date)) {
        return 'Año ' . $date;
    }
    
    return $date;
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
                <span id="active_filters_count" class="filter-count">0</span>
            </button>
        </div>
        
        <div class="filters-container" id="filters_container">
            <!-- Filtros básicos siempre visibles -->
            <div class="filters-section">
                <div class="filters-grid">
                    <!-- Filtros de fecha -->
                    <div class="filter-group date-range-group">
                        <label>Fecha de Evento</label>
                        <div class="date-range-inputs">
                            <input type="text" id="fecha_evento_inicio" class="datepicker-input" placeholder="Desde..." readonly>
                            <span class="date-range-separator">➔</span>
                            <input type="text" id="fecha_evento_fin" class="datepicker-input" placeholder="Hasta..." readonly>
                        </div>
                        <div class="date-presets">
                            <button type="button" id="btn_hoy" class="date-preset-btn">Hoy</button>
                            <button type="button" id="btn_semana" class="date-preset-btn">Esta semana</button>
                            <button type="button" id="btn_mes" class="date-preset-btn">Este mes</button>
                            <button type="button" id="btn_anio" class="date-preset-btn">Este año</button>
                        </div>
                        <div class="date-display">
                            <span id="fecha_evento_display" class="date-friendly-format"></span>
                        </div>
                    </div>
                    
                    <!-- Búsqueda -->
                    <div class="filter-group">
                        <label for="search_leads">Buscar</label>
                        <div class="search-input-wrapper">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" id="search_leads" placeholder="Nombre, email, teléfono...">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros avanzados (colapsables) -->
            <div class="filters-section collapsible">
                <div class="section-header" data-target="advanced-filters">
                    <h4>Filtros Avanzados</h4>
                    <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                </div>
                
                <div id="advanced-filters" class="section-content">
                    <div class="filters-grid">
                        <!-- Tipo de evento -->
                        <div class="filter-group">
                            <label for="tipo_evento_filter">Tipo de Evento</label>
                            <select id="tipo_evento_filter" multiple="multiple" class="select2-multi">
                                <option value="Bodas">Bodas</option>
                                <option value="XV años">XV años</option>
                                <option value="Empresarial">Empresarial</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        
                        <!-- Status -->
                        <div class="filter-group">
                            <label for="status_filter">Status</label>
                            <select id="status_filter" multiple="multiple" class="select2-multi">
                                <?php
                                // Obtener opciones de status desde el archivo de utilidades
                                if (class_exists('LTB_Leads_Status_Utils')) {
                                    $status_options = LTB_Leads_Status_Utils::get_status_options();
                                    foreach ($status_options as $value => $label) {
                                        echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <!-- Fecha de ingreso -->
                        <div class="filter-group">
                            <label for="fecha_ingreso">Fecha de Ingreso</label>
                            <div class="date-range-inputs">
                                <input type="text" id="fecha_ingreso_inicio" class="datepicker-input" placeholder="Desde..." readonly>
                                <span class="date-range-separator">➔</span>
                                <input type="text" id="fecha_ingreso_fin" class="datepicker-input" placeholder="Hasta..." readonly>
                            </div>
                        </div>
                        
                        <!-- Invitados (rango) -->
                        <div class="filter-group">
                            <label for="invitados_filter">Cantidad de Invitados</label>
                            <select id="invitados_filter">
                                <option value="">Cualquier cantidad</option>
                                <option value="1-50">Menos de 50</option>
                                <option value="51-100">51 - 100</option>
                                <option value="101-200">101 - 200</option>
                                <option value="201-500">201 - 500</option>
                                <option value="501+">Más de 500</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Opciones de ordenamiento -->
            <div class="filters-section">
                <div class="filters-actions-bar">
                    <div class="sorting-options">
                        <label for="ordenamiento">Ordenar por:</label>
                        <select id="ordenamiento">
                            <option value="fecha_solicitud">Fecha solicitud</option>
                            <option value="nombre">Nombre</option>
                            <option value="fecha_evento">Fecha evento</option>
                            <option value="tipo_evento">Tipo evento</option>
                            <option value="status">Status</option>
                        </select>
                        
                        <select id="orden" class="order-direction">
                            <option value="DESC">Descendente ↓</option>
                            <option value="ASC">Ascendente ↑</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button id="limpiar_filtros" class="button">
                            <span class="dashicons dashicons-dismiss"></span>
                            Limpiar
                        </button>
                        <button id="aplicar_filtros" class="button button-primary">
                            <span class="dashicons dashicons-filter"></span>
                            Aplicar Filtros
                        </button>
                    </div>
                </div>
                
                <!-- Chips para filtros activos -->
                <div id="filtros_activos" class="active-filters">
                    <!-- Se generan dinámicamente por JS -->
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
}