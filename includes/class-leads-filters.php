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
            
            // Filtros de categorización
            'tipo_evento' => isset($_POST['tipo_evento']) && is_array($_POST['tipo_evento']) ? 
                array_map('sanitize_text_field', $_POST['tipo_evento']) : array(),
            'status' => isset($_POST['status']) && is_array($_POST['status']) ? 
                array_map('sanitize_text_field', $_POST['status']) : array(),
            'invitados' => isset($_POST['invitados']) ? sanitize_text_field($_POST['invitados']) : '',
            
            // Nuevos filtros de calificación
            'prioridad' => isset($_POST['prioridad']) ? sanitize_text_field($_POST['prioridad']) : '',
            'valor_potencial' => isset($_POST['valor_potencial']) ? sanitize_text_field($_POST['valor_potencial']) : '',
            'probabilidad' => isset($_POST['probabilidad']) ? sanitize_text_field($_POST['probabilidad']) : '',
            
            // Nuevos filtros de seguimiento
            'responsable' => isset($_POST['responsable']) && is_array($_POST['responsable']) ? 
                array_map('sanitize_text_field', $_POST['responsable']) : array(),
            'ultima_interaccion' => isset($_POST['ultima_interaccion']) ? sanitize_text_field($_POST['ultima_interaccion']) : '',
            'tiempo_sin_actividad' => isset($_POST['tiempo_sin_actividad']) ? sanitize_text_field($_POST['tiempo_sin_actividad']) : '',
            'proxima_accion' => isset($_POST['proxima_accion']) ? sanitize_text_field($_POST['proxima_accion']) : '',
            
            // Nuevos filtros de origen
            'fuente' => isset($_POST['fuente']) && is_array($_POST['fuente']) ? 
                array_map('sanitize_text_field', $_POST['fuente']) : array(),
            'campana' => isset($_POST['campana']) && is_array($_POST['campana']) ? 
                array_map('sanitize_text_field', $_POST['campana']) : array(),
            
            // Nuevos filtros demográficos
            'ubicacion' => isset($_POST['ubicacion']) && is_array($_POST['ubicacion']) ? 
                array_map('sanitize_text_field', $_POST['ubicacion']) : array(),
            'industria' => isset($_POST['industria']) && is_array($_POST['industria']) ? 
                array_map('sanitize_text_field', $_POST['industria']) : array(),
            
            // Nuevos filtros de conversión
            'estado_propuesta' => isset($_POST['estado_propuesta']) ? sanitize_text_field($_POST['estado_propuesta']) : '',
            'rango_cotizacion' => isset($_POST['rango_cotizacion']) ? sanitize_text_field($_POST['rango_cotizacion']) : '',
            
            // Nuevos filtros de eventos específicos
            'temporada' => isset($_POST['temporada']) ? sanitize_text_field($_POST['temporada']) : '',
            'servicios_requeridos' => isset($_POST['servicios_requeridos']) && is_array($_POST['servicios_requeridos']) ? 
                array_map('sanitize_text_field', $_POST['servicios_requeridos']) : array(),
            'venue' => isset($_POST['venue']) && is_array($_POST['venue']) ? 
                array_map('sanitize_text_field', $_POST['venue']) : array(),
            
            // Nuevos filtros de etiquetas
            'etiquetas' => isset($_POST['etiquetas']) && is_array($_POST['etiquetas']) ? 
                array_map('sanitize_text_field', $_POST['etiquetas']) : array(),
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
        
        // Prioridad
        if (!empty($args['prioridad'])) {
            $prioridad_labels = array(
                'alta' => 'Alta',
                'media' => 'Media',
                'baja' => 'Baja'
            );
            
            $label = isset($prioridad_labels[$args['prioridad']]) ? $prioridad_labels[$args['prioridad']] : $args['prioridad'];
            
            $summary[] = array(
                'type' => 'prioridad',
                'label' => 'Prioridad: ' . $label,
                'value' => $args['prioridad']
            );
        }
        
        // Valor potencial
        if (!empty($args['valor_potencial'])) {
            $valor_labels = array(
                'bajo' => 'Bajo (<$10,000)',
                'medio' => 'Medio ($10,000-$50,000)',
                'alto' => 'Alto (>$50,000)'
            );
            
            $label = isset($valor_labels[$args['valor_potencial']]) ? $valor_labels[$args['valor_potencial']] : $args['valor_potencial'];
            
            $summary[] = array(
                'type' => 'valor_potencial',
                'label' => 'Valor potencial: ' . $label,
                'value' => $args['valor_potencial']
            );
        }
        
        // Probabilidad
        if (!empty($args['probabilidad'])) {
            $prob_labels = array(
                'alta' => 'Alta (>70%)',
                'media' => 'Media (30-70%)',
                'baja' => 'Baja (<30%)'
            );
            
            $label = isset($prob_labels[$args['probabilidad']]) ? $prob_labels[$args['probabilidad']] : $args['probabilidad'];
            
            $summary[] = array(
                'type' => 'probabilidad',
                'label' => 'Probabilidad: ' . $label,
                'value' => $args['probabilidad']
            );
        }
        
        // Responsable
        if (!empty($args['responsable'])) {
            $summary[] = array(
                'type' => 'responsable',
                'label' => 'Responsable: ' . implode(', ', $args['responsable']),
                'value' => $args['responsable']
            );
        }
        
        // Última interacción
        if (!empty($args['ultima_interaccion'])) {
            $interaccion_labels = array(
                'hoy' => 'Hoy',
                'semana' => 'Esta semana',
                'mes' => 'Este mes',
                'trimestre' => 'Último trimestre',
                'mas_3_meses' => 'Más de 3 meses'
            );
            
            $label = isset($interaccion_labels[$args['ultima_interaccion']]) ? $interaccion_labels[$args['ultima_interaccion']] : $args['ultima_interaccion'];
            
            $summary[] = array(
                'type' => 'ultima_interaccion',
                'label' => 'Última interacción: ' . $label,
                'value' => $args['ultima_interaccion']
            );
        }
        
        // Tiempo sin actividad
        if (!empty($args['tiempo_sin_actividad'])) {
            $inactividad_labels = array(
                '7d' => 'Más de 7 días',
                '15d' => 'Más de 15 días',
                '30d' => 'Más de 30 días',
                '90d' => 'Más de 90 días'
            );
            
            $label = isset($inactividad_labels[$args['tiempo_sin_actividad']]) ? $inactividad_labels[$args['tiempo_sin_actividad']] : $args['tiempo_sin_actividad'];
            
            $summary[] = array(
                'type' => 'tiempo_sin_actividad',
                'label' => 'Sin actividad: ' . $label,
                'value' => $args['tiempo_sin_actividad']
            );
        }
        
        // Próxima acción
        if (!empty($args['proxima_accion'])) {
            $accion_labels = array(
                'hoy' => 'Hoy',
                'manana' => 'Mañana',
                'semana' => 'Esta semana',
                'mes' => 'Este mes',
                'sin_programar' => 'Sin programar'
            );
            
            $label = isset($accion_labels[$args['proxima_accion']]) ? $accion_labels[$args['proxima_accion']] : $args['proxima_accion'];
            
            $summary[] = array(
                'type' => 'proxima_accion',
                'label' => 'Próxima acción: ' . $label,
                'value' => $args['proxima_accion']
            );
        }
        
        // Fuente
        if (!empty($args['fuente'])) {
            $summary[] = array(
                'type' => 'fuente',
                'label' => 'Fuente: ' . implode(', ', $args['fuente']),
                'value' => $args['fuente']
            );
        }
        
        // Campaña
        if (!empty($args['campana'])) {
            $summary[] = array(
                'type' => 'campana',
                'label' => 'Campaña: ' . implode(', ', $args['campana']),
                'value' => $args['campana']
            );
        }
        
        // Ubicación
        if (!empty($args['ubicacion'])) {
            $summary[] = array(
                'type' => 'ubicacion',
                'label' => 'Ubicación: ' . implode(', ', $args['ubicacion']),
                'value' => $args['ubicacion']
            );
        }
        
        // Industria
        if (!empty($args['industria'])) {
            $summary[] = array(
                'type' => 'industria',
                'label' => 'Industria: ' . implode(', ', $args['industria']),
                'value' => $args['industria']
            );
        }
        
        // Estado de propuesta
        if (!empty($args['estado_propuesta'])) {
            $propuesta_labels = array(
                'enviada' => 'Enviada',
                'en_revision' => 'En revisión',
                'aceptada' => 'Aceptada',
                'rechazada' => 'Rechazada',
                'pendiente' => 'Pendiente'
            );
            
            $label = isset($propuesta_labels[$args['estado_propuesta']]) ? $propuesta_labels[$args['estado_propuesta']] : $args['estado_propuesta'];
            
            $summary[] = array(
                'type' => 'estado_propuesta',
                'label' => 'Propuesta: ' . $label,
                'value' => $args['estado_propuesta']
            );
        }
        
        // Rango de cotización
        if (!empty($args['rango_cotizacion'])) {
            $cotizacion_labels = array(
                'menos_10k' => 'Menos de $10,000',
                '10k_30k' => '$10,000 - $30,000',
                '30k_50k' => '$30,000 - $50,000',
                '50k_100k' => '$50,000 - $100,000',
                'mas_100k' => 'Más de $100,000'
            );
            
            $label = isset($cotizacion_labels[$args['rango_cotizacion']]) ? $cotizacion_labels[$args['rango_cotizacion']] : $args['rango_cotizacion'];
            
            $summary[] = array(
                'type' => 'rango_cotizacion',
                'label' => 'Cotización: ' . $label,
                'value' => $args['rango_cotizacion']
            );
        }
        
        // Temporada
        if (!empty($args['temporada'])) {
            $temporada_labels = array(
                'alta' => 'Temporada alta',
                'baja' => 'Temporada baja'
            );
            
            $label = isset($temporada_labels[$args['temporada']]) ? $temporada_labels[$args['temporada']] : $args['temporada'];
            
            $summary[] = array(
                'type' => 'temporada',
                'label' => 'Temporada: ' . $label,
                'value' => $args['temporada']
            );
        }
        
        // Servicios requeridos
        if (!empty($args['servicios_requeridos'])) {
            $summary[] = array(
                'type' => 'servicios_requeridos',
                'label' => 'Servicios: ' . implode(', ', $args['servicios_requeridos']),
                'value' => $args['servicios_requeridos']
            );
        }
        
        // Venue/Lugar
        if (!empty($args['venue'])) {
            $summary[] = array(
                'type' => 'venue',
                'label' => 'Venue: ' . implode(', ', $args['venue']),
                'value' => $args['venue']
            );
        }
        
        // Etiquetas
        if (!empty($args['etiquetas'])) {
            $summary[] = array(
                'type' => 'etiquetas',
                'label' => 'Etiquetas: ' . implode(', ', $args['etiquetas']),
                'value' => $args['etiquetas']
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
                <div class="filters-section basic-filters">
                    <div class="filters-grid compact">
                        <!-- Filtros de fecha de evento (versión compacta) -->
                        <div class="filter-group date-range-group compact">
                            <label>Fecha de Evento</label>
                            <div class="date-range-inputs">
                                <input type="text" id="fecha_evento_inicio" class="datepicker-input" placeholder="Desde..." readonly>
                                <span class="date-range-separator">➔</span>
                                <input type="text" id="fecha_evento_fin" class="datepicker-input" placeholder="Hasta..." readonly>
                            </div>
                            <div class="date-actions">
                                <div class="year-selector">
                                    <select id="anio_evento" class="date-selector">
                                        <option value="">Año</option>
                                        <?php
                                        $current_year = date('Y');
                                        for ($i = $current_year + 5; $i >= 2000; $i--) {
                                            echo '<option value="' . $i . '">' . $i . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="date-presets">
                                    <button type="button" id="btn_hoy" class="date-preset-btn">Hoy</button>
                                    <button type="button" id="btn_mes" class="date-preset-btn">Mes</button>
                                    <button type="button" id="btn_anio" class="date-preset-btn">Año</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Búsqueda -->
                        <div class="filter-group compact">
                            <label for="search_leads">Buscar</label>
                            <div class="search-input-wrapper">
                                <span class="dashicons dashicons-search"></span>
                                <input type="text" id="search_leads" placeholder="Nombre, email, teléfono...">
                            </div>
                        </div>
                        
                        <!-- Filtros comunes -->
                        <div class="filter-group compact">
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
                        
                        <!-- Tipo de evento -->
                        <div class="filter-group compact">
                            <label for="tipo_evento_filter">Tipo de Evento</label>
                            <select id="tipo_evento_filter" multiple="multiple" class="select2-multi">
                                <option value="Bodas">Bodas</option>
                                <option value="XV años">XV años</option>
                                <option value="Empresarial">Empresarial</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Más filtros (botón) -->
                    <div class="advanced-filters-toggle">
                        <button type="button" id="toggle_advanced_filters" class="toggle-advanced-btn">
                            <span class="toggle-text-show">Más filtros</span>
                            <span class="toggle-text-hide">Menos filtros</span>
                            <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Contenedor de filtros avanzados (inicialmente oculto) -->
                <div id="advanced_filters_container" class="advanced-filters-container">
                    <!-- Filtros de categorización -->
                    <div class="filters-section collapsible">
                        <div class="section-header" data-target="categorizacion-filters">
                            <h4>Categorización</h4>
                            <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        
                        <div id="categorizacion-filters" class="section-content">
                            <div class="filters-grid">
                                <!-- Fecha de ingreso -->
                                <div class="filter-group">
                                    <label for="fecha_ingreso">Fecha de Ingreso</label>
                                    <div class="date-range-inputs">
                                        <input type="text" id="fecha_ingreso_inicio" class="datepicker-input" placeholder="Desde..." readonly>
                                        <span class="date-range-separator">➔</span>
                                        <input type="text" id="fecha_ingreso_fin" class="datepicker-input" placeholder="Hasta..." readonly>
                                    </div>
                                    <div class="year-month-selectors">
                                        <select id="anio_ingreso" class="date-selector">
                                            <option value="">Año</option>
                                            <?php
                                            $current_year = date('Y');
                                            for ($i = $current_year; $i >= 2000; $i--) {
                                                echo '<option value="' . $i . '">' . $i . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <select id="mes_ingreso" class="date-selector">
                                            <option value="">Mes</option>
                                            <option value="01">Enero</option>
                                            <option value="02">Febrero</option>
                                            <option value="03">Marzo</option>
                                            <option value="04">Abril</option>
                                            <option value="05">Mayo</option>
                                            <option value="06">Junio</option>
                                            <option value="07">Julio</option>
                                            <option value="08">Agosto</option>
                                            <option value="09">Septiembre</option>
                                            <option value="10">Octubre</option>
                                            <option value="11">Noviembre</option>
                                            <option value="12">Diciembre</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Selectores mensuales -->
                                <div class="filter-group">
                                    <label for="mes_evento">Mes del Evento</label>
                                    <select id="mes_evento" class="date-selector full-width">
                                        <option value="">Cualquier mes</option>
                                        <option value="01">Enero</option>
                                        <option value="02">Febrero</option>
                                        <option value="03">Marzo</option>
                                        <option value="04">Abril</option>
                                        <option value="05">Mayo</option>
                                        <option value="06">Junio</option>
                                        <option value="07">Julio</option>
                                        <option value="08">Agosto</option>
                                        <option value="09">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                                
                                <!-- Invitados (rango) -->
                                <div class="filter-group">
                                    <label for="invitados_filter">Cantidad de Invitados</label>
                                    <select id="invitados_filter" class="full-width">
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
                
                <!-- Filtros de calificación -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="calificacion-filters">
                        <h4>Calificación del Lead</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="calificacion-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Prioridad -->
                            <div class="filter-group">
                                <label for="prioridad_filter">Prioridad</label>
                                <select id="prioridad_filter">
                                    <option value="">Cualquier prioridad</option>
                                    <option value="alta">Alta</option>
                                    <option value="media">Media</option>
                                    <option value="baja">Baja</option>
                                </select>
                            </div>
                            
                            <!-- Valor potencial -->
                            <div class="filter-group">
                                <label for="valor_potencial_filter">Valor Potencial</label>
                                <select id="valor_potencial_filter">
                                    <option value="">Cualquier valor</option>
                                    <option value="bajo">Bajo (< $10,000)</option>
                                    <option value="medio">Medio ($10,000 - $50,000)</option>
                                    <option value="alto">Alto (> $50,000)</option>
                                </select>
                            </div>
                            
                            <!-- Probabilidad de conversión -->
                            <div class="filter-group">
                                <label for="probabilidad_filter">Probabilidad</label>
                                <select id="probabilidad_filter">
                                    <option value="">Cualquier probabilidad</option>
                                    <option value="alta">Alta (> 70%)</option>
                                    <option value="media">Media (30% - 70%)</option>
                                    <option value="baja">Baja (< 30%)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros de seguimiento -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="seguimiento-filters">
                        <h4>Seguimiento</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="seguimiento-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Responsable -->
                            <div class="filter-group">
                                <label for="responsable_filter">Responsable</label>
                                <select id="responsable_filter" multiple="multiple" class="select2-multi">
                                    <?php
                                    // Obtener usuarios con rol de ventas
                                    $sales_users = get_users(array('role__in' => array('administrator', 'editor')));
                                    foreach ($sales_users as $user) {
                                        echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Última interacción -->
                            <div class="filter-group">
                                <label for="ultima_interaccion_filter">Última Interacción</label>
                                <select id="ultima_interaccion_filter">
                                    <option value="">Cualquier fecha</option>
                                    <option value="hoy">Hoy</option>
                                    <option value="semana">Esta semana</option>
                                    <option value="mes">Este mes</option>
                                    <option value="trimestre">Último trimestre</option>
                                    <option value="mas_3_meses">Más de 3 meses</option>
                                </select>
                            </div>
                            
                            <!-- Tiempo sin actividad -->
                            <div class="filter-group">
                                <label for="tiempo_sin_actividad_filter">Tiempo sin Actividad</label>
                                <select id="tiempo_sin_actividad_filter">
                                    <option value="">Cualquier tiempo</option>
                                    <option value="7d">Más de 7 días</option>
                                    <option value="15d">Más de 15 días</option>
                                    <option value="30d">Más de 30 días</option>
                                    <option value="90d">Más de 90 días</option>
                                </select>
                            </div>
                            
                            <!-- Próxima acción programada -->
                            <div class="filter-group">
                                <label for="proxima_accion_filter">Próxima Acción</label>
                                <select id="proxima_accion_filter">
                                    <option value="">Cualquier fecha</option>
                                    <option value="hoy">Hoy</option>
                                    <option value="manana">Mañana</option>
                                    <option value="semana">Esta semana</option>
                                    <option value="mes">Este mes</option>
                                    <option value="sin_programar">Sin programar</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros de origen -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="origen-filters">
                        <h4>Origen</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="origen-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Fuente -->
                            <div class="filter-group">
                                <label for="fuente_filter">Fuente</label>
                                <select id="fuente_filter" multiple="multiple" class="select2-multi">
                                    <option value="web">Web</option>
                                    <option value="referido">Referido</option>
                                    <option value="redes_sociales">Redes Sociales</option>
                                    <option value="email">Email Marketing</option>
                                    <option value="llamada">Llamada</option>
                                    <option value="feria">Feria o Evento</option>
                                </select>
                            </div>
                            
                            <!-- Campaña -->
                            <div class="filter-group">
                                <label for="campana_filter">Campaña</label>
                                <select id="campana_filter" multiple="multiple" class="select2-multi">
                                    <option value="organico">Orgánico</option>
                                    <option value="google_ads">Google Ads</option>
                                    <option value="facebook_ads">Facebook Ads</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="promo_verano">Promo Verano</option>
                                    <option value="promo_navidad">Promo Navidad</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros demográficos -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="demograficos-filters">
                        <h4>Datos Demográficos</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="demograficos-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Ubicación -->
                            <div class="filter-group">
                                <label for="ubicacion_filter">Ubicación</label>
                                <select id="ubicacion_filter" multiple="multiple" class="select2-multi">
                                    <option value="cdmx">Ciudad de México</option>
                                    <option value="guadalajara">Guadalajara</option>
                                    <option value="monterrey">Monterrey</option>
                                    <option value="puebla">Puebla</option>
                                    <option value="queretaro">Querétaro</option>
                                    <option value="otra">Otra</option>
                                </select>
                            </div>
                            
                            <!-- Industria (para empresariales) -->
                            <div class="filter-group">
                                <label for="industria_filter">Industria</label>
                                <select id="industria_filter" multiple="multiple" class="select2-multi">
                                    <option value="tecnologia">Tecnología</option>
                                    <option value="finanzas">Finanzas</option>
                                    <option value="salud">Salud</option>
                                    <option value="educacion">Educación</option>
                                    <option value="manufactura">Manufactura</option>
                                    <option value="retail">Retail</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros de conversión -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="conversion-filters">
                        <h4>Estado de Conversión</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="conversion-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Estado de propuesta -->
                            <div class="filter-group">
                                <label for="estado_propuesta_filter">Estado de Propuesta</label>
                                <select id="estado_propuesta_filter">
                                    <option value="">Cualquier estado</option>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="enviada">Enviada</option>
                                    <option value="en_revision">En revisión</option>
                                    <option value="aceptada">Aceptada</option>
                                    <option value="rechazada">Rechazada</option>
                                </select>
                            </div>
                            
                            <!-- Rango de cotización -->
                            <div class="filter-group">
                                <label for="rango_cotizacion_filter">Rango de Cotización</label>
                                <select id="rango_cotizacion_filter">
                                    <option value="">Cualquier monto</option>
                                    <option value="menos_10k">Menos de $10,000</option>
                                    <option value="10k_30k">$10,000 - $30,000</option>
                                    <option value="30k_50k">$30,000 - $50,000</option>
                                    <option value="50k_100k">$50,000 - $100,000</option>
                                    <option value="mas_100k">Más de $100,000</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros específicos de eventos -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="eventos-especificos-filters">
                        <h4>Características del Evento</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="eventos-especificos-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Temporada -->
                            <div class="filter-group">
                                <label for="temporada_filter">Temporada</label>
                                <select id="temporada_filter">
                                    <option value="">Cualquier temporada</option>
                                    <option value="alta">Temporada alta</option>
                                    <option value="baja">Temporada baja</option>
                                </select>
                            </div>
                            
                            <!-- Servicios requeridos -->
                            <div class="filter-group">
                                <label for="servicios_requeridos_filter">Servicios Requeridos</label>
                                <select id="servicios_requeridos_filter" multiple="multiple" class="select2-multi">
                                    <option value="catering">Catering</option>
                                    <option value="fotografia">Fotografía</option>
                                    <option value="musica">Música/DJ</option>
                                    <option value="decoracion">Decoración</option>
                                    <option value="iluminacion">Iluminación</option>
                                    <option value="transporte">Transporte</option>
                                </select>
                            </div>
                            
                            <!-- Venue/Lugar -->
                            <div class="filter-group">
                                <label for="venue_filter">Venue</label>
                                <select id="venue_filter" multiple="multiple" class="select2-multi">
                                    <option value="salon_principal">Salón Principal</option>
                                    <option value="jardin">Jardín</option>
                                    <option value="terraza">Terraza</option>
                                    <option value="playa">Playa</option>
                                    <option value="hacienda">Hacienda</option>
                                    <option value="otro_venue">Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Etiquetas personalizadas -->
                <div class="filters-section collapsible">
                    <div class="section-header" data-target="etiquetas-filters">
                        <h4>Etiquetas</h4>
                        <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </div>
                    
                    <div id="etiquetas-filters" class="section-content">
                        <div class="filters-grid">
                            <!-- Etiquetas -->
                            <div class="filter-group">
                                <label for="etiquetas_filter">Etiquetas</label>
                                <select id="etiquetas_filter" multiple="multiple" class="select2-multi select2-tags">
                                    <option value="vip">VIP</option>
                                    <option value="recurrente">Cliente Recurrente</option>
                                    <option value="requiere_atencion">Requiere Atención</option>
                                    <option value="presupuesto_limitado">Presupuesto Limitado</option>
                                    <option value="decision_rapida">Decisión Rápida</option>
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
                                <option value="prioridad">Prioridad</option>
                                <option value="valor_potencial">Valor potencial</option>
                                <option value="ultima_interaccion">Última interacción</option>
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