<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Ajax {
    private $query_handler;

    public function __construct() {
        $this->query_handler = new LTB_Leads_Query();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_save_lead_data', array($this, 'handle_lead_save'));
        add_action('wp_ajax_nopriv_save_lead_data', array($this, 'handle_lead_save'));
		add_action('wp_ajax_add_event_to_lead', array($this, 'handle_add_event_to_lead'));
        add_action('wp_ajax_nopriv_add_event_to_lead', array($this, 'handle_add_event_to_lead'));
		add_action('wp_ajax_check_existing_lead', array($this, 'check_existing_lead'));
        add_action('wp_ajax_nopriv_check_existing_lead', array($this, 'check_existing_lead'));
		add_action('wp_ajax_delete_lead', array($this, 'handle_delete_lead'));
		add_action('wp_ajax_search_services', array($this, 'handle_service_search'));
        add_action('wp_ajax_nopriv_search_services', array($this, 'handle_service_search'));
		// Añadir estos nuevos hooks
        add_action('wp_ajax_get_leads_by_status', array($this, 'get_leads_by_status'));
        add_action('wp_ajax_update_evento_status', array($this, 'update_evento_status'));
		add_action('wp_ajax_filter_leads', array($this, 'filter_leads'));
        // Nuevo hook para guardar metadatos
        add_action('wp_ajax_save_lead_metadata', array($this, 'handle_save_lead_metadata'));
    }

    public function handle_lead_save() {

    // Verificar nonce
    if (!check_ajax_referer('ltb_lead_edit_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    // Verificar permisos
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
        return;
    }

    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $fields = isset($_POST['fields']) ? $_POST['fields'] : array();
    $eventos = isset($_POST['eventos']) ? $_POST['eventos'] : array();
    
    if (!$lead_id) {
        wp_send_json_error('ID de lead inválido');
        return;
    }

    global $wpdb;
    $updated = false;
    
    // Actualizar campos del lead principal
    if (!empty($fields)) {
        $leads_update = array();
        
        // Procesar los campos del lead
        if (isset($fields['lead_status'])) $leads_update['lead_status'] = sanitize_text_field($fields['lead_status']);
        if (isset($fields['lead_celular'])) $leads_update['lead_celular'] = sanitize_text_field($fields['lead_celular']);
        if (isset($fields['lead_e_mail'])) $leads_update['lead_e_mail'] = sanitize_email($fields['lead_e_mail']);
        if (isset($fields['lead_nombre'])) $leads_update['lead_nombre'] = sanitize_text_field($fields['lead_nombre']);
        if (isset($fields['lead_apellido'])) $leads_update['lead_apellido'] = sanitize_text_field($fields['lead_apellido']);
        if (isset($fields['lead_razon_social'])) $leads_update['lead_razon_social'] = sanitize_text_field($fields['lead_razon_social']);
        
        // Actualizar en la base de datos
        if (!empty($leads_update)) {

            $result = $wpdb->update(
                $wpdb->prefix . 'jet_cct_leads',
                $leads_update,
                array('_ID' => $lead_id)
            );
            
            if ($result !== false) {
                $updated = true;
            }
        }
    }
    
    // Actualizar eventos
    if (!empty($eventos)) {
        foreach ($eventos as $evento_id => $evento_data) {
            if (empty($evento_data)) continue;
            
            $evento_id = intval($evento_id);
            if (!$evento_id) continue;
            
            $eventos_update = array();
            
            // Procesar los campos del evento
            if (isset($evento_data['fecha_de_evento'])) {
                $eventos_update['fecha_de_evento'] = $evento_data['fecha_de_evento'];
            }
            if (isset($evento_data['tipo_de_evento'])) {
                $eventos_update['tipo_de_evento'] = sanitize_text_field($evento_data['tipo_de_evento']);
            }
            if (isset($evento_data['evento_status'])) {
                $eventos_update['evento_status'] = sanitize_text_field($evento_data['evento_status']);
            }
            if (isset($evento_data['evento_asistentes'])) {
                $eventos_update['evento_asistentes'] = sanitize_text_field($evento_data['evento_asistentes']);
            }
            if (isset($evento_data['ubicacion_evento'])) {
                $eventos_update['ubicacion_evento'] = sanitize_text_field($evento_data['ubicacion_evento']);
            }
            if (isset($evento_data['direccion_evento'])) {
                $eventos_update['direccion_evento'] = sanitize_text_field($evento_data['direccion_evento']);
            }
            if (isset($evento_data['comentarios_evento'])) {
                $eventos_update['comentarios_evento'] = sanitize_textarea_field($evento_data['comentarios_evento']);
            }
            if (isset($evento_data['evento_servicio_de_interes'])) {
                $eventos_update['evento_servicio_de_interes'] = esc_url_raw($evento_data['evento_servicio_de_interes']);
            }
            
            // Actualizar en la base de datos
            if (!empty($eventos_update)) {

                $result = $wpdb->update(
                    $wpdb->prefix . 'jet_cct_eventos',
                    $eventos_update,
                    array('_ID' => $evento_id)
                );
                
                if ($result !== false) {
                    $updated = true;
                }
            }
        }
    }
    
    if ($updated) {
        // Obtener datos actualizados
        $updated_data = $this->query_handler->get_single_lead($lead_id);
        
        if ($updated_data) {

            wp_send_json_success(array(
                'message' => 'Datos actualizados correctamente',
                'lead' => $updated_data
            ));
        } else {

            wp_send_json_error('Error al obtener los datos actualizados');
        }
    } else {
        wp_send_json_error('No se realizaron actualizaciones en la base de datos');
    }
}
	
	/**
 * Maneja la adición de un evento a un lead existente
 */
public function handle_add_event_to_lead() {
    // Verificar nonce
    if (!check_ajax_referer('ltb_lead_edit_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }

    // Verificar permisos
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
        return;
    }

    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error('ID de lead no válido');
        return;
    }

    // Validar campos requeridos del evento
    $evento_fecha = isset($_POST['fecha_de_evento']) ? sanitize_text_field($_POST['fecha_de_evento']) : '';
    $evento_tipo = isset($_POST['tipo_de_evento']) ? sanitize_text_field($_POST['tipo_de_evento']) : '';
    
    if (empty($evento_fecha) || empty($evento_tipo)) {
        wp_send_json_error('Por favor completa los campos obligatorios del evento');
        return;
    }
    
    // Formatear fecha - asegurarse que funcione correctamente
    $fecha_timestamp = strtotime($evento_fecha);
    if ($fecha_timestamp === false) {
        wp_send_json_error('Formato de fecha inválido');
        return;
    }
    
    // Guardar el timestamp numérico en lugar de la fecha formateada
    $evento_fecha = $fecha_timestamp;
    
    // Preparar datos del evento
    $evento_data = array(
        'lead_id' => $lead_id,
        'fecha_de_evento' => $evento_fecha,
        'tipo_de_evento' => $evento_tipo,
        'evento_asistentes' => isset($_POST['evento_asistentes']) ? sanitize_text_field($_POST['evento_asistentes']) : '',
        'evento_status' => isset($_POST['evento_status']) ? sanitize_text_field($_POST['evento_status']) : 'nuevo-no-contactado',
        'ubicacion_evento' => isset($_POST['ubicacion_evento']) ? sanitize_text_field($_POST['ubicacion_evento']) : '',
        'direccion_evento' => isset($_POST['direccion_evento']) ? sanitize_text_field($_POST['direccion_evento']) : '',
        'evento_servicio_de_interes' => isset($_POST['evento_servicio_de_interes']) ? esc_url_raw($_POST['evento_servicio_de_interes']) : '',
        'comentarios_evento' => isset($_POST['comentarios_evento']) ? sanitize_textarea_field($_POST['comentarios_evento']) : '',
        'cct_status' => 'publish',
        'cct_created' => current_time('mysql'),
        'cct_modified' => current_time('mysql')
    );
    
    // Procesar URL de servicio para extraer categoría y ubicación
    if (!empty($evento_data['evento_servicio_de_interes'])) {
        // Incluir la clase para usar las funciones de extracción
        require_once LTB_LEADS_PLUGIN_DIR . 'includes/class-leads-add.php';
        $leads_add = new LTB_Leads_Add(true); // Pasar true para evitar registrar hooks
        
        // Extraer categoría
        $categoria = $leads_add->extraer_categoria_desde_url($evento_data['evento_servicio_de_interes']);
        if ($categoria) {
            $evento_data['categoria_listing_post'] = $categoria;
        }
        
        // Extraer ubicación
        $ubicacion = $leads_add->extraer_ubicacion_desde_url($evento_data['evento_servicio_de_interes']);
        if ($ubicacion) {
            $evento_data['ubicacion_evento'] = $ubicacion;
        }
    }
    
    global $wpdb;
    
    // Insertar evento
    $evento_inserted = $wpdb->insert(
        $wpdb->prefix . 'jet_cct_eventos',
        $evento_data,
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    if (!$evento_inserted) {
        wp_send_json_error('Error al guardar el evento: ' . $wpdb->last_error);
        return;
    }
    
    // Obtener ID del evento insertado
    $evento_id = $wpdb->insert_id;
    
    // Enviar respuesta exitosa
    wp_send_json_success(array(
        'lead_id' => $lead_id,
        'evento_id' => $evento_id,
        'message' => 'Evento agregado correctamente'
    ));
}
	
	public function check_existing_lead() {
    // Inicializar variables
    global $wpdb;
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $celular = isset($_POST['celular']) ? sanitize_text_field($_POST['celular']) : '';
    
    if (empty($email) && empty($celular)) {
        wp_send_json_error('Se requiere email o celular para verificar');
        return;
    }
    
    $conditions = array();
    $values = array();
    $table_name = $wpdb->prefix . 'jet_cct_leads';
    
    if (!empty($email)) {
        $conditions[] = "lead_e_mail = %s";
        $values[] = $email;
    }
    
    if (!empty($celular)) {
        // Búsqueda simple de teléfono
        $conditions[] = "lead_celular = %s";
        $values[] = $celular;
    }
    
    if (empty($conditions)) {
        wp_send_json_error('Datos inválidos para verificación');
        return;
    }
    
    $query = "SELECT _ID, lead_nombre, lead_apellido, lead_celular, lead_e_mail 
              FROM {$table_name} 
              WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
    
    try {
        $prepared_query = $wpdb->prepare($query, $values);
        $existing_lead = $wpdb->get_row($prepared_query);
        
        // Verificar si existe un usuario de WordPress con este email
        $existing_user_id = !empty($email) ? email_exists($email) : 0;
        
        wp_send_json_success(array(
            'exists' => ($existing_lead || $existing_user_id) ? true : false,
            'lead' => $existing_lead,
            'user_exists' => !empty($existing_user_id),
            'message' => $existing_lead ? 
                'Este contacto ya está registrado como lead' : 
                ($existing_user_id ? 'Este email ya está registrado como usuario' : '')
        ));
    } catch (Exception $e) {
        wp_send_json_error('Error en la consulta: ' . $e->getMessage());
    }
}
	
	/**
 * Maneja el filtrado de leads para la vista de tabla
 */
public function filter_leads() {
    check_ajax_referer('ltb_leads_filter_nonce', 'nonce');
    
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error(array('message' => 'No tienes permisos para realizar esta acción'));
        return;
    }
    
    // Procesar los filtros
    $args = array(
        'fecha_inicio' => isset($_POST['fecha_inicio']) ? sanitize_text_field($_POST['fecha_inicio']) : '',
        'fecha_fin' => isset($_POST['fecha_fin']) ? sanitize_text_field($_POST['fecha_fin']) : '',
        'fecha_evento' => isset($_POST['fecha_evento']) ? sanitize_text_field($_POST['fecha_evento']) : '',
        'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
        'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'fecha_solicitud',
        'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC',
        'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 25,
        'paged' => isset($_POST['paged']) ? intval($_POST['paged']) : 1
    );
    
    
    // Obtener datos filtrados
    $leads = $this->query_handler->get_leads($args);
    $total = $this->query_handler->get_total_leads($args);
    $pages = ceil($total / $args['per_page']);
    
    wp_send_json_success(array(
        'data' => $leads,
        'total' => $total,
        'pages' => $pages,
        'filters' => $args
    ));
}
	
	/**
 * Maneja la eliminación de un lead
 */
public function handle_delete_lead() {
    // Verificar nonce de filtro o de edición
    if (!check_ajax_referer('ltb_leads_filter_nonce', 'nonce', false) && 
        !check_ajax_referer('ltb_lead_edit_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    // Verificar permisos
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error('ID de lead no válido');
        return;
    }
    
    global $wpdb;
    
    // Primero eliminar eventos asociados
    $wpdb->delete(
        $wpdb->prefix . 'jet_cct_eventos',
        array('lead_id' => $lead_id),
        array('%d')
    );
    
    // Eliminar seguimientos asociados
    $wpdb->delete(
        $wpdb->prefix . 'jet_cct_crm',
        array('id_lead' => $lead_id),
        array('%d')
    );
    
    // Finalmente eliminar el lead
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'jet_cct_leads',
        array('_ID' => $lead_id),
        array('%d')
    );
    
    if ($deleted) {
        wp_send_json_success(array(
            'message' => 'Lead eliminado correctamente'
        ));
    } else {
        wp_send_json_error('Error al eliminar el lead: ' . $wpdb->last_error);
    }
}
	
	/**
 * Busca servicios por título
 */
public function handle_service_search() {
     // Verificar tanto el nonce de adición como el de edición
    if (!check_ajax_referer('ltb_lead_add_nonce', 'nonce', false) && 
        !check_ajax_referer('ltb_lead_edit_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    // Obtener término de búsqueda
    $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    
    if (empty($search_term)) {
        wp_send_json_error('Término de búsqueda vacío');
        return;
    }
    
    // Buscar posts
    $args = array(
        'post_type' => 'hp_listing',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        's' => $search_term,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    $query = new WP_Query($args);
    $results = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $results[] = array(
                'id' => get_the_ID(),
                'label' => get_the_title(),
                'value' => get_the_title(),
                'url' => get_permalink()
            );
        }
    }
    
    wp_reset_postdata();
    wp_send_json_success($results);
}
	

/**
 * Obtiene los leads agrupados por status para la vista pipeline
 */
public function get_leads_by_status() {
    check_ajax_referer('ltb_leads_nonce', 'nonce');
    
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error(array('message' => 'No tienes permisos para realizar esta acción'));
    }
    
    // Obtener filtros
    $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
    
    // Si los filtros usan el campo antiguo fecha_evento, convertirlo a los nuevos
    if (isset($filters['fecha_evento']) && !empty($filters['fecha_evento'])) {
        $filters['fecha_evento_inicio'] = $filters['fecha_evento'];
        $filters['fecha_evento_fin'] = $filters['fecha_evento'];
        unset($filters['fecha_evento']);
    }
    
    // Obtener datos
    $query_handler = new LTB_Leads_Query();
    $leads_by_status = $query_handler->get_leads_by_status($filters);
    
    wp_send_json_success($leads_by_status);
}

/**
 * Actualiza el status de un evento
 */
public function update_evento_status() {
    check_ajax_referer('ltb_leads_nonce', 'nonce');
    
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error(array('message' => 'No tienes permisos para realizar esta acción'));
    }
    
    // Obtener datos del formulario
    $evento_id = isset($_POST['evento_id']) ? intval($_POST['evento_id']) : 0;
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $comentarios = isset($_POST['comentarios']) ? sanitize_textarea_field($_POST['comentarios']) : '';
    
    if (!$evento_id || !$lead_id || !$new_status) {
        wp_send_json_error(array('message' => 'Datos incompletos'));
    }
    
    global $wpdb;
    $eventos_table = $wpdb->prefix . 'jet_cct_eventos';
    
    // Actualizar status
    $updated = $wpdb->update(
        $eventos_table,
        array(
            'evento_status' => $new_status,
            'comentarios_evento' => $comentarios ? $comentarios : $wpdb->get_var($wpdb->prepare(
                "SELECT comentarios_evento FROM $eventos_table WHERE _ID = %d",
                $evento_id
            ))
        ),
        array('_ID' => $evento_id)
    );
    
    if ($updated === false) {
        wp_send_json_error(array('message' => 'Error al actualizar el status'));
    }
    
    wp_send_json_success(array('message' => 'Status actualizado correctamente'));
}

/**
 * Maneja la actualización de metadatos de un lead
 */
public function handle_save_lead_metadata() {
    // Verificar nonce
    if (!check_ajax_referer('ltb_lead_metadata_nonce', 'nonce', false)) {
        wp_send_json_error('Error de seguridad');
        return;
    }
    
    // Verificar permisos
    if (!ltb_user_can_manage_leads()) {
        wp_send_json_error('No tienes permisos para realizar esta acción');
        return;
    }
    
    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    
    if (!$lead_id) {
        wp_send_json_error('ID de lead inválido');
        return;
    }
    
    // Preparar datos de metadatos
    $metadata = array(
        'lead_id' => $lead_id,
        'prioridad' => isset($_POST['prioridad']) ? sanitize_text_field($_POST['prioridad']) : null,
        'valor_potencial' => isset($_POST['valor_potencial']) ? sanitize_text_field($_POST['valor_potencial']) : null,
        'probabilidad' => isset($_POST['probabilidad']) ? sanitize_text_field($_POST['probabilidad']) : null,
        'responsable_id' => isset($_POST['responsable_id']) ? intval($_POST['responsable_id']) : null,
        'ultima_interaccion' => isset($_POST['ultima_interaccion']) && !empty($_POST['ultima_interaccion']) ? 
            date('Y-m-d H:i:s', strtotime($_POST['ultima_interaccion'])) : null,
        'proxima_accion_fecha' => isset($_POST['proxima_accion_fecha']) && !empty($_POST['proxima_accion_fecha']) ? 
            date('Y-m-d H:i:s', strtotime($_POST['proxima_accion_fecha'])) : null,
        'fuente' => isset($_POST['fuente']) ? sanitize_text_field($_POST['fuente']) : null,
        'campana' => isset($_POST['campana']) ? sanitize_text_field($_POST['campana']) : null,
        'ubicacion' => isset($_POST['ubicacion']) ? sanitize_text_field($_POST['ubicacion']) : null,
        'industria' => isset($_POST['industria']) ? sanitize_text_field($_POST['industria']) : null,
        'estado_propuesta' => isset($_POST['estado_propuesta']) ? sanitize_text_field($_POST['estado_propuesta']) : null,
        'rango_cotizacion' => isset($_POST['rango_cotizacion']) ? sanitize_text_field($_POST['rango_cotizacion']) : null,
        'temporada' => isset($_POST['temporada']) ? sanitize_text_field($_POST['temporada']) : null,
        'venue' => isset($_POST['venue']) ? sanitize_text_field($_POST['venue']) : null
    );
    
    // Procesar servicios requeridos (array)
    if (isset($_POST['servicios_requeridos']) && is_array($_POST['servicios_requeridos'])) {
        $metadata['servicios_requeridos'] = array_map('sanitize_text_field', $_POST['servicios_requeridos']);
    } else {
        $metadata['servicios_requeridos'] = null;
    }
    
    // Procesar etiquetas (array)
    $etiquetas = isset($_POST['etiquetas']) ? $_POST['etiquetas'] : array();
    if (!is_array($etiquetas)) {
        $etiquetas = array();
    }
    
    $metadata['etiquetas'] = array_map('sanitize_text_field', $etiquetas);
    
    // Verificar si existe la clase de metadatos
    if (!class_exists('LTB_Leads_Metadata')) {
        wp_send_json_error('Sistema de metadatos no disponible');
        return;
    }
    
    // Guardar metadatos
    $metadata_handler = new LTB_Leads_Metadata();
    $metadata_handler->save_lead_metadata($lead_id, $metadata);
    
    // Ejecutar acción para notificar a otros plugins
    do_action('ltb_lead_metadata_updated', $lead_id, $metadata);
    
    wp_send_json_success(array(
        'lead_id' => $lead_id,
        'message' => 'Metadatos guardados correctamente'
    ));
}
}