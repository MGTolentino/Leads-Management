<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Query {
    private $wpdb;
    private $leads_table;
    private $eventos_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->leads_table = $wpdb->prefix . 'jet_cct_leads';
        $this->eventos_table = $wpdb->prefix . 'jet_cct_eventos';
    }

   public function get_leads($args = array()) {
    $defaults = array(
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'fecha_evento_inicio' => '',
        'fecha_evento_fin' => '',
        'orderby' => 'fecha_solicitud',
        'order' => 'DESC',
        'per_page' => 20,
        'paged' => 1,
        'search' => '',
        // Nuevos parámetros de filtro
        'tipo_evento' => array(),
        'status' => array(),
        'invitados' => ''
    );

    $args = wp_parse_args($args, $defaults);
    $where = array('1=1');
    $values = array();

    // Construir consulta base
    $query = "
        SELECT 
            l._ID as lead_id,
            l.cct_created as fecha_solicitud,
            l.lead_razon_social,
            l.lead_nombre,
            l.lead_apellido,
            l.lead_celular,
            l.lead_e_mail,
            e._ID as evento_id,
            e.evento_status,
            e.fecha_de_evento,
            e.tipo_de_evento,
            e.evento_servicio_de_interes,
            (SELECT COUNT(*) FROM {$this->eventos_table} WHERE lead_id = l._ID) as total_eventos
        FROM {$this->leads_table} l
        LEFT JOIN (
            SELECT e1.*
            FROM {$this->eventos_table} e1
            LEFT JOIN {$this->eventos_table} e2
            ON e1.lead_id = e2.lead_id AND e1.fecha_de_evento < e2.fecha_de_evento
            WHERE e2.lead_id IS NULL
        ) e ON e.lead_id = l._ID
    ";
    
    // Permitir que otros plugins modifiquen la consulta (como el de metadatos)
    $query = apply_filters('ltb_leads_query_select', $query, $args);

    if (!empty($args['fecha_inicio'])) {
        $where[] = "l.cct_created >= %s";
        $values[] = $args['fecha_inicio'];
    }
    if (!empty($args['fecha_fin'])) {
        $where[] = "l.cct_created <= %s";
        $values[] = $args['fecha_fin'];
    }

    if (!empty($args['fecha_evento_inicio']) && empty($args['fecha_evento_fin'])) {
        $fecha_inicio = $args['fecha_evento_inicio'];
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {

            $inicio_timestamp = strtotime($fecha_inicio . ' 00:00:00');
            $fin_timestamp = strtotime($fecha_inicio . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_inicio)) {

            $inicio_timestamp = strtotime($fecha_inicio . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_timestamp); // Obtener el último día del mes
            $fin_timestamp = strtotime($fecha_inicio . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_inicio)) {

            $inicio_timestamp = strtotime($fecha_inicio . '-01-01 00:00:00');
            $fin_timestamp = strtotime($fecha_inicio . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        else {
            $timestamp = strtotime($fecha_inicio);
            if ($timestamp !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
                $fin_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
                
                $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
                $values[] = $inicio_timestamp;
                $values[] = $fin_timestamp;
                
            }
        }
    }

    else if (empty($args['fecha_evento_inicio']) && !empty($args['fecha_evento_fin'])) {
        $fecha_fin = $args['fecha_evento_fin'];
        

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {

            $inicio_timestamp = strtotime($fecha_fin . ' 00:00:00');
            $fin_timestamp = strtotime($fecha_fin . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_fin)) {

            $inicio_timestamp = strtotime($fecha_fin . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_timestamp); // Obtener el último día del mes
            $fin_timestamp = strtotime($fecha_fin . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_fin)) {

            $inicio_timestamp = strtotime($fecha_fin . '-01-01 00:00:00');
            $fin_timestamp = strtotime($fecha_fin . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        else {
            $timestamp = strtotime($fecha_fin);
            if ($timestamp !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
                $fin_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
                
                $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
                $values[] = $inicio_timestamp;
                $values[] = $fin_timestamp;
                
            }
        }
    }

    else if (!empty($args['fecha_evento_inicio']) && !empty($args['fecha_evento_fin'])) {
        $fecha_inicio = $args['fecha_evento_inicio'];
        $fecha_fin = $args['fecha_evento_fin'];
        
        $inicio_timestamp = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . ' 00:00:00');
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . '-01 00:00:00');
        }
        elseif (preg_match('/^\d{4}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . '-01-01 00:00:00');
        }
        else {
            $ts = strtotime($fecha_inicio);
            if ($ts !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $ts) . ' 00:00:00');
            }
        }
        
        $fin_timestamp = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
            $fin_timestamp = strtotime($fecha_fin . ' 23:59:59');
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_fin)) {
            $ultimo_dia = date('t', strtotime($fecha_fin . '-01'));
            $fin_timestamp = strtotime($fecha_fin . '-' . $ultimo_dia . ' 23:59:59');
        }
        elseif (preg_match('/^\d{4}$/', $fecha_fin)) {
            $fin_timestamp = strtotime($fecha_fin . '-12-31 23:59:59');
        }
        else {
            $ts = strtotime($fecha_fin);
            if ($ts !== false) {
                $fin_timestamp = strtotime(date('Y-m-d', $ts) . ' 23:59:59');
            }
        }
        
        if ($inicio_timestamp !== null) {
            $where[] = "e.fecha_de_evento >= %d";
            $values[] = $inicio_timestamp;
        }
        
        if ($fin_timestamp !== null) {
            $where[] = "e.fecha_de_evento <= %d";
            $values[] = $fin_timestamp;
        }
    }
    // Compatibilidad con el parámetro antiguo
    else if (!empty($args['fecha_evento'])) {
        $fecha_evento = $args['fecha_evento'];
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_evento)) {
            // Es una fecha completa: YYYY-MM-DD
            $inicio_dia = strtotime($fecha_evento . ' 00:00:00');
            $fin_dia = strtotime($fecha_evento . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_dia;
            $values[] = $fin_dia;
            
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_evento)) {
            // Es un año-mes: YYYY-MM
            $inicio_mes = strtotime($fecha_evento . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_mes); // Obtener el último día del mes
            $fin_mes = strtotime($fecha_evento . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_mes;
            $values[] = $fin_mes;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_evento)) {
            // Es solo un año: YYYY
            $inicio_año = strtotime($fecha_evento . '-01-01 00:00:00');
            $fin_año = strtotime($fecha_evento . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_año;
            $values[] = $fin_año;
            
        }
        else {
            // Formato no reconocido, intentar procesar como fecha estándar
            $timestamp = strtotime($fecha_evento);
            if ($timestamp !== false) {
                $inicio_dia = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
                $fin_dia = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
                
                $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
                $values[] = $inicio_dia;
                $values[] = $fin_dia;
                
            }
        }
    }

    // Búsqueda general
    if (!empty($args['search'])) {
        
        $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
        $where[] = "(
            l.lead_nombre LIKE %s OR 
            l.lead_apellido LIKE %s OR 
            l.lead_celular LIKE %s OR 
            l.lead_e_mail LIKE %s OR 
            l.lead_razon_social LIKE %s OR
            CONCAT(l.lead_nombre, ' ', l.lead_apellido) LIKE %s
        )";
        $values = array_merge($values, array($search, $search, $search, $search, $search, $search));
    }
    
    // Filtro por tipo de evento
    if (!empty($args['tipo_evento']) && is_array($args['tipo_evento'])) {
        $tipo_evento_placeholders = array();
        foreach ($args['tipo_evento'] as $tipo) {
            $tipo_evento_placeholders[] = '%s';
            $values[] = $tipo;
        }
        if (!empty($tipo_evento_placeholders)) {
            $where[] = "e.tipo_de_evento IN (" . implode(',', $tipo_evento_placeholders) . ")";
        }
    }
    
    // Filtro por status
    if (!empty($args['status']) && is_array($args['status'])) {
        $status_placeholders = array();
        foreach ($args['status'] as $status) {
            $status_placeholders[] = '%s';
            $values[] = $status;
        }
        if (!empty($status_placeholders)) {
            $where[] = "e.evento_status IN (" . implode(',', $status_placeholders) . ")";
        }
    }
    
    // Filtro por cantidad de invitados
    if (!empty($args['invitados'])) {
        $rango = explode('-', $args['invitados']);
        if (count($rango) == 2) {
            if ($rango[1] === '+') {
                // Más de X invitados
                $where[] = "e.evento_asistentes >= %d";
                $values[] = intval($rango[0]);
            } else {
                // Rango de invitados
                $where[] = "e.evento_asistentes >= %d AND e.evento_asistentes <= %d";
                $values[] = intval($rango[0]);
                $values[] = intval($rango[1]);
            }
        }
    }

    // Aplicar filtros personalizados de Events Staff Manager y sistema de metadatos
    if (function_exists('apply_filters')) {
        $args = apply_filters('ltb_leads_query_args', $args);
        $where_custom = apply_filters('ltb_leads_query_where', '', $args);
        if (!empty($where_custom)) {
            $where[] = trim($where_custom);
        }
        
        // Permitir modificar JOINs
        $join_custom = apply_filters('ltb_leads_query_join', '', $args);
        if (!empty($join_custom)) {
            $query .= ' ' . trim($join_custom);
        }
    }

    // Agregar cláusula WHERE
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    // Ordenamiento
$order_columns = array(
    'fecha_solicitud' => 'l.cct_created',
    'nombre' => 'l.lead_nombre',
    'fecha_evento' => 'e.fecha_de_evento',
    'tipo_evento' => 'e.tipo_de_evento',
    'status' => 'e.evento_status',
    'prioridad' => 'm.prioridad',
    'valor_potencial' => 'm.valor_potencial',
    'probabilidad' => 'm.probabilidad',
    'ultima_interaccion' => 'm.ultima_interaccion'
);

$orderby = isset($order_columns[$args['orderby']]) ? $order_columns[$args['orderby']] : 'l.cct_created';
$order = in_array(strtoupper($args['order']), array('ASC', 'DESC')) ? strtoupper($args['order']) : 'DESC';

// Modificar la consulta para ordenar primero por leads sin evento o con estado obsoleto
$query .= " ORDER BY CASE 
                WHEN e._ID IS NULL THEN 1 
                ELSE 0 
            END DESC, {$orderby} {$order}";

    // Paginación
    $offset = ($args['paged'] - 1) * $args['per_page'];
    $query .= " LIMIT %d OFFSET %d";
    $values[] = $args['per_page'];
    $values[] = $offset;

    $prepared_query = !empty($values) ? $this->wpdb->prepare($query, $values) : $query;
    $results = $this->wpdb->get_results($prepared_query);

foreach ($results as &$row) {

    $row->fecha_solicitud = $this->format_friendly_date($row->fecha_solicitud);
    $row->fecha_de_evento = $this->format_friendly_date($row->fecha_de_evento);
    
    if (!empty($row->evento_servicio_de_interes)) {
        $service_info = $this->format_service_title($row->evento_servicio_de_interes);
        $row->servicio_titulo = $service_info['title'];
        $row->servicio_url = $service_info['url'];
    }
    
    if ($row->total_eventos == 0 || empty($row->evento_id)) {
        $row->is_obsolete_status = true;
    } else {

        $row->is_obsolete_status = false;
        
        $active_status = LTB_Leads_Status_Utils::get_active_status_options();
        if (!empty($row->evento_status) && !isset($active_status[$row->evento_status])) {
            $row->is_obsolete_status = true;
        }
    }
}

    return $results;
}

    private function get_service_title($url) {
        // Extraer el slug del servicio de la URL
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename(trim($path, '/'));
        
        $post = get_page_by_path($slug, OBJECT, 'post');
        
        if ($post) {
            return $post->post_title;
        }
        
        return $url; // Retornar la URL si no se encuentra el título
    }

    public function get_total_leads($args = array()) {
        $query = "SELECT COUNT(DISTINCT l._ID) FROM {$this->leads_table} l";
        $where = array('1=1');
        $values = array();

        if (!empty($args['search'])) {
            $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where[] = "(
                l.lead_nombre LIKE %s OR 
                l.lead_apellido LIKE %s OR 
                l.lead_celular LIKE %s OR 
                l.lead_e_mail LIKE %s
            )";
            $values = array_merge($values, array($search, $search, $search, $search));
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        $prepared_query = !empty($values) ? $this->wpdb->prepare($query, $values) : $query;
        return $this->wpdb->get_var($prepared_query);
    }
	
	private function format_service_title($url) {
    $patterns = [
        'contrata-el-servicio-de/',
        'book-service-for/'
    ];
    
    foreach ($patterns as $pattern) {
        if (strpos($url, $pattern) !== false) {
            $parts = explode($pattern, $url);
            if (isset($parts[1])) {
                $slug = rtrim($parts[1], '/');
                // Convertir slug a título
                $title = ucwords(str_replace('-', ' ', $slug));
                return [
                    'title' => $title,
                    'url' => $url
                ];
            }
        }
    }
    
    return [
        'title' => $url,
        'url' => $url
    ];
}
	
	private function format_date($date) {
    if (empty($date)) return '';
    
    try {
        // Si es timestamp
        if (is_numeric($date)) {
            return $this->format_friendly_date($date);
        } else {
            return $this->format_friendly_date($date);
        }
    } catch (Exception $e) {
        return 'Fecha inválida';
    }
}

	
	public function get_single_lead($lead_id) {
    
    $query_lead = $this->wpdb->prepare(
        "SELECT 
            l._ID as lead_id,
            l.cct_created as fecha_solicitud,
            l.lead_razon_social,
            l.lead_nombre,
            l.lead_apellido,
            l.lead_celular,
            l.lead_e_mail
        FROM {$this->leads_table} l
        WHERE l._ID = %d",
        $lead_id
    );
    
    $lead = $this->wpdb->get_row($query_lead);
    
    if (!$lead) {
        return null;
    }
    
    $lead->fecha_solicitud = $this->format_friendly_date($lead->fecha_solicitud, true);
    
    $query_eventos = $this->wpdb->prepare(
        "SELECT 
            e._ID as evento_id,
            e.evento_status,
            e.fecha_de_evento,
            e.tipo_de_evento,
            e.evento_asistentes,
            e.evento_servicio_de_interes,
            e.direccion_evento,
            e.ubicacion_evento,
            e.comentarios_evento,
            e.categoria_listing_post
        FROM {$this->eventos_table} e
        WHERE e.lead_id = %d
        ORDER BY e.fecha_de_evento DESC",
        $lead_id
    );
    
    $eventos = $this->wpdb->get_results($query_eventos);
    
    foreach ($eventos as &$evento) {

        $evento->fecha_de_evento = $this->format_friendly_date($evento->fecha_de_evento);
        
        if (!empty($evento->evento_servicio_de_interes)) {
            $service_info = $this->format_service_title($evento->evento_servicio_de_interes);
            $evento->servicio_titulo = $service_info['title'];
            $evento->servicio_url = $service_info['url'];
        }
    }
    
    $lead->eventos = $eventos;
    
    // Obtener metadatos si existe la clase
    if (class_exists('LTB_Leads_Metadata')) {
        $metadata = new LTB_Leads_Metadata();
        $lead_metadata = $metadata->get_lead_metadata($lead_id);
        $lead->metadata = $lead_metadata;
    }
    
    return $lead;
}
	
public function format_friendly_date($date, $include_time = false) {
    if (empty($date)) {
        return '';
    }
    
    if (is_numeric($date)) {
        $timestamp = intval($date);
    } else {

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 'Fecha inválida';
        }
    }
    
    $meses = array(
        1 => 'enero', 
        2 => 'febrero', 
        3 => 'marzo', 
        4 => 'abril',
        5 => 'mayo', 
        6 => 'junio', 
        7 => 'julio', 
        8 => 'agosto',
        9 => 'septiembre', 
        10 => 'octubre', 
        11 => 'noviembre', 
        12 => 'diciembre'
    );
    
    $dia = date('j', $timestamp);
    $mes = date('n', $timestamp);
    $año = date('Y', $timestamp);
    
    $fecha_formateada = $dia . ' de ' . $meses[$mes] . ' del ' . $año;
    
    if ($include_time) {
        $hora = date('H:i', $timestamp);
        $fecha_formateada .= ' a las ' . $hora;
    }
    
    return $fecha_formateada;
}
	
public function get_leads_by_status($args = array()) {
    $defaults = array(
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'fecha_evento_inicio' => '',
        'fecha_evento_fin' => '',
        'search' => ''
    );

    $args = wp_parse_args($args, $defaults);
    $where = array('1=1');
    $values = array();

    $query = "
        SELECT 
            l._ID as lead_id,
            l.cct_created as fecha_solicitud,
            l.lead_razon_social,
            l.lead_nombre,
            l.lead_apellido,
            l.lead_celular,
            l.lead_e_mail,
            e._ID as evento_id,
            e.evento_status,
            e.fecha_de_evento,
            e.tipo_de_evento,
            e.evento_servicio_de_interes
        FROM {$this->leads_table} l
        LEFT JOIN {$this->eventos_table} e ON e.lead_id = l._ID
    ";
    
    // Permitir que otros plugins modifiquen la consulta (como el de metadatos)
    $query = apply_filters('ltb_leads_query_select', $query, $args);

    if (!empty($args['fecha_inicio'])) {
        $where[] = "l.cct_created >= %s";
        $values[] = $args['fecha_inicio'];
    }
    if (!empty($args['fecha_fin'])) {
        $where[] = "l.cct_created <= %s";
        $values[] = $args['fecha_fin'];
    }

    if (!empty($args['fecha_evento_inicio']) && empty($args['fecha_evento_fin'])) {
        $fecha_inicio = $args['fecha_evento_inicio'];
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {

            $inicio_timestamp = strtotime($fecha_inicio . ' 00:00:00');
            $fin_timestamp = strtotime($fecha_inicio . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_inicio)) {
            // Es un año-mes: YYYY-MM
            $inicio_timestamp = strtotime($fecha_inicio . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_timestamp); // Obtener el último día del mes
            $fin_timestamp = strtotime($fecha_inicio . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_inicio)) {
            // Es solo un año: YYYY
            $inicio_timestamp = strtotime($fecha_inicio . '-01-01 00:00:00');
            $fin_timestamp = strtotime($fecha_inicio . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        else {
            $timestamp = strtotime($fecha_inicio);
            if ($timestamp !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
                $fin_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
                
                $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
                $values[] = $inicio_timestamp;
                $values[] = $fin_timestamp;
                
            }
        }
    }
    // Si hay fecha de fin pero no fecha de inicio
    else if (empty($args['fecha_evento_inicio']) && !empty($args['fecha_evento_fin'])) {
        $fecha_fin = $args['fecha_evento_fin'];
        
        // Verificar formato y convertir a timestamp
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
            // Es una fecha completa: YYYY-MM-DD
            $inicio_timestamp = strtotime($fecha_fin . ' 00:00:00');
            $fin_timestamp = strtotime($fecha_fin . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_fin)) {
            // Es un año-mes: YYYY-MM
            $inicio_timestamp = strtotime($fecha_fin . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_timestamp); // Obtener el último día del mes
            $fin_timestamp = strtotime($fecha_fin . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_fin)) {
            // Es solo un año: YYYY
            $inicio_timestamp = strtotime($fecha_fin . '-01-01 00:00:00');
            $fin_timestamp = strtotime($fecha_fin . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_timestamp;
            $values[] = $fin_timestamp;
            
        }
        else {
            $timestamp = strtotime($fecha_fin);
            if ($timestamp !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
                $fin_timestamp = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
                
                $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
                $values[] = $inicio_timestamp;
                $values[] = $fin_timestamp;
                
            }
        }
    }
    // Si hay ambas fechas (rango completo)
    else if (!empty($args['fecha_evento_inicio']) && !empty($args['fecha_evento_fin'])) {
        $fecha_inicio = $args['fecha_evento_inicio'];
        $fecha_fin = $args['fecha_evento_fin'];
        
        // Procesar fecha de inicio
        $inicio_timestamp = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . ' 00:00:00');
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . '-01 00:00:00');
        }
        elseif (preg_match('/^\d{4}$/', $fecha_inicio)) {
            $inicio_timestamp = strtotime($fecha_inicio . '-01-01 00:00:00');
        }
        else {
            $ts = strtotime($fecha_inicio);
            if ($ts !== false) {
                $inicio_timestamp = strtotime(date('Y-m-d', $ts) . ' 00:00:00');
            }
        }
        
        // Procesar fecha de fin
        $fin_timestamp = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
            $fin_timestamp = strtotime($fecha_fin . ' 23:59:59');
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_fin)) {
            $ultimo_dia = date('t', strtotime($fecha_fin . '-01'));
            $fin_timestamp = strtotime($fecha_fin . '-' . $ultimo_dia . ' 23:59:59');
        }
        elseif (preg_match('/^\d{4}$/', $fecha_fin)) {
            $fin_timestamp = strtotime($fecha_fin . '-12-31 23:59:59');
        }
        else {
            $ts = strtotime($fecha_fin);
            if ($ts !== false) {
                $fin_timestamp = strtotime(date('Y-m-d', $ts) . ' 23:59:59');
            }
        }
        
        // Añadir condiciones SQL
        if ($inicio_timestamp !== null) {
            $where[] = "e.fecha_de_evento >= %d";
            $values[] = $inicio_timestamp;
        }
        
        if ($fin_timestamp !== null) {
            $where[] = "e.fecha_de_evento <= %d";
            $values[] = $fin_timestamp;
        }
    }
    // Compatibilidad con el parámetro antiguo
    else if (!empty($args['fecha_evento'])) {
        $fecha_evento = $args['fecha_evento'];
        
        // Verificar el formato de la fecha para determinar si es año-mes, año o fecha completa
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_evento)) {
            // Es una fecha completa: YYYY-MM-DD
            $inicio_dia = strtotime($fecha_evento . ' 00:00:00');
            $fin_dia = strtotime($fecha_evento . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_dia;
            $values[] = $fin_dia;
            
        } 
        elseif (preg_match('/^\d{4}-\d{2}$/', $fecha_evento)) {
            // Es un año-mes: YYYY-MM
            $inicio_mes = strtotime($fecha_evento . '-01 00:00:00');
            $ultimo_dia = date('t', $inicio_mes); // Obtener el último día del mes
            $fin_mes = strtotime($fecha_evento . '-' . $ultimo_dia . ' 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
            $values[] = $inicio_mes;
            $values[] = $fin_mes;
            
        }
        elseif (preg_match('/^\d{4}$/', $fecha_evento)) {
            // Es solo un año: YYYY
            $inicio_año = strtotime($fecha_evento . '-01-01 00:00:00');
            $fin_año = strtotime($fecha_evento . '-12-31 23:59:59');
            
            $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
           $values[] = $inicio_año;
           $values[] = $fin_año;
           
       }
       else {
           // Formato no reconocido, intentar procesar como fecha estándar
           $timestamp = strtotime($fecha_evento);
           if ($timestamp !== false) {
               $inicio_dia = strtotime(date('Y-m-d', $timestamp) . ' 00:00:00');
               $fin_dia = strtotime(date('Y-m-d', $timestamp) . ' 23:59:59');
               
               $where[] = "e.fecha_de_evento >= %d AND e.fecha_de_evento <= %d";
               $values[] = $inicio_dia;
               $values[] = $fin_dia;
               }
       }
   }

   // Búsqueda general
   if (!empty($args['search'])) {
       $search = '%' . $this->wpdb->esc_like($args['search']) . '%';
       $where[] = "(
           l.lead_nombre LIKE %s OR 
           l.lead_apellido LIKE %s OR 
           l.lead_celular LIKE %s OR 
           l.lead_e_mail LIKE %s OR 
           l.lead_razon_social LIKE %s
       )";
       $values = array_merge($values, array($search, $search, $search, $search, $search));
   }
   
   // Filtro por tipo de evento
   if (!empty($args['tipo_evento']) && is_array($args['tipo_evento'])) {
       $tipo_evento_placeholders = array();
       foreach ($args['tipo_evento'] as $tipo) {
           $tipo_evento_placeholders[] = '%s';
           $values[] = $tipo;
       }
       if (!empty($tipo_evento_placeholders)) {
           $where[] = "e.tipo_de_evento IN (" . implode(',', $tipo_evento_placeholders) . ")";
       }
   }
   
   // Filtro por status
   if (!empty($args['status']) && is_array($args['status'])) {
       $status_placeholders = array();
       foreach ($args['status'] as $status) {
           $status_placeholders[] = '%s';
           $values[] = $status;
       }
       if (!empty($status_placeholders)) {
           $where[] = "e.evento_status IN (" . implode(',', $status_placeholders) . ")";
       }
   }
   
   // Filtro por cantidad de invitados
   if (!empty($args['invitados'])) {
       $rango = explode('-', $args['invitados']);
       if (count($rango) == 2) {
           if ($rango[1] === '+') {
               // Más de X invitados
               $where[] = "e.evento_asistentes >= %d";
               $values[] = intval($rango[0]);
           } else {
               // Rango de invitados
               $where[] = "e.evento_asistentes >= %d AND e.evento_asistentes <= %d";
               $values[] = intval($rango[0]);
               $values[] = intval($rango[1]);
           }
       }
   }

   // Aplicar filtros personalizados de Events Staff Manager y sistema de metadatos
   if (function_exists('apply_filters')) {
       $args = apply_filters('ltb_leads_query_args', $args);
       $where_custom = apply_filters('ltb_leads_query_where', '', $args);
       if (!empty($where_custom)) {
           $where[] = trim($where_custom);
       }
       
       // Permitir modificar JOINs
       $join_custom = apply_filters('ltb_leads_query_join', '', $args);
       if (!empty($join_custom)) {
           $query .= ' ' . trim($join_custom);
       }
   }

   // Agregar cláusula WHERE
   if (!empty($where)) {
       $query .= " WHERE " . implode(' AND ', $where);
   }

   // Ordenamiento por fecha de solicitud (los más recientes primero)
   $query .= " ORDER BY l.cct_created DESC";

   // Preparar y ejecutar la consulta
   $prepared_query = !empty($values) ? $this->wpdb->prepare($query, $values) : $query;
   $results = $this->wpdb->get_results($prepared_query);

   // Para la vista pipeline solo necesitamos estados activos, pero para los datos
   $active_status_options = LTB_Leads_Status_Utils::get_active_status_options();
   $grouped_leads = array();

   // Inicializar los grupos para estados activos
   foreach ($active_status_options as $status_value => $status_label) {
       $grouped_leads[$status_value] = array();
   }

   // También inicializar grupos para categorías especiales
   $grouped_leads['sin-evento'] = array();
   $grouped_leads['otros'] = array();

   foreach ($results as $row) {
       // Crear un nuevo objeto de lead+evento
       $lead_event = new stdClass();
       $lead_event->lead_id = $row->lead_id;
       $lead_event->fecha_solicitud = $this->format_friendly_date($row->fecha_solicitud);
       $lead_event->nombre_completo = trim($row->lead_nombre . ' ' . $row->lead_apellido);
       $lead_event->lead_razon_social = $row->lead_razon_social;
       $lead_event->lead_celular = $row->lead_celular;
       $lead_event->lead_e_mail = $row->lead_e_mail;
       
       // Información del evento
       $lead_event->evento_id = $row->evento_id;
       $lead_event->evento_status = $row->evento_status;
       $lead_event->fecha_evento = $this->format_friendly_date($row->fecha_de_evento);
       $lead_event->tipo_evento = $row->tipo_de_evento;
       
       // Procesar URL del servicio
       if (!empty($row->evento_servicio_de_interes)) {
           $service_info = $this->format_service_title($row->evento_servicio_de_interes);
           $lead_event->servicio_titulo = $service_info['title'];
           $lead_event->servicio_url = $service_info['url'];
       }

       // Si tiene un evento, añadirlo al grupo correspondiente según su status
       if (!empty($row->evento_id)) {
           $status_category = LTB_Leads_Status_Utils::get_status_category($row->evento_status);
           $grouped_leads[$status_category][] = $lead_event;
       } else {
           // Si no tiene evento, añadirlo al grupo "sin-evento"
           $grouped_leads['sin-evento'][] = $lead_event;
       }
   }

   return $grouped_leads;
}
	
/**
 * Formatea los datos de un evento desde la consulta de leads por status
 */
private function format_event_data($row) {
    $event = new stdClass();
    $event->evento_id = $row->evento_id;
    $event->status = $row->evento_status;
    $event->fecha = $this->format_friendly_date($row->fecha_de_evento);
    $event->tipo = $row->tipo_de_evento;
    
    // Procesar URL del servicio
    if (!empty($row->evento_servicio_de_interes)) {
        $service_info = $this->format_service_title($row->evento_servicio_de_interes);
        $event->servicio_titulo = $service_info['title'];
        $event->servicio_url = $service_info['url'];
    }
    
    return $event;
}

}