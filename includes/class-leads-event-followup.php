<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Event_Followup {
    private $wpdb;
    private $table_name;
    private $old_table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'jet_cct_event_followups';
        $this->old_table_name = $wpdb->prefix . 'jet_cct_crm';
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_save_event_followup', array($this, 'handle_save_event_followup'));
        add_action('wp_ajax_nopriv_save_event_followup', array($this, 'handle_save_event_followup'));
        add_action('wp_ajax_get_event_quotes', array($this, 'handle_get_event_quotes'));
        add_action('wp_ajax_nopriv_get_event_quotes', array($this, 'handle_get_event_quotes'));
    }

    /**
     * Renderiza el formulario de seguimiento con selector de cotizaciones
     */
    public function render_form($event_id) {
        ob_start();
        ?>
        <div class="event-followup-form-wrapper">
            <form id="eventFollowupForm" class="event-followup-form">
                <input type="hidden" id="event_id" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                
                <div class="form-group">
                    <label for="cotizacion_id">Relacionar con cotización:</label>
                    <select id="cotizacion_id" name="cotizacion_id">
                        <option value="">-- Seguimiento general --</option>
                        <?php 
                        $quotes = $this->get_event_quotes($event_id);
                        foreach ($quotes as $quote) {
                            $quote_name = !empty($quote->nombre_pdf) ? $quote->nombre_pdf : 'Cotización #' . $quote->_ID;
                            echo '<option value="' . esc_attr($quote->_ID) . '">' . esc_html($quote_name) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="seguimiento_status">Estado:</label>
                    <select id="seguimiento_status" name="seguimiento_status" required>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Realizado">Realizado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="seguimiento_fecha">Fecha:</label>
                    <input type="datetime-local" id="seguimiento_fecha" name="seguimiento_fecha" required>
                </div>

                <div class="form-group">
                    <label for="seguimiento_actividad">Actividad:</label>
                    <select id="seguimiento_actividad" name="seguimiento_actividad" required>
                        <option value="Contacto inicial">Contacto inicial</option>
                        <option value="Envio de informacion">Envío de información</option>
                        <option value="Envio de cotizacion">Envío de cotización</option>
                        <option value="Seguimiento cotizacion">Seguimiento cotización</option>
                        <option value="Cita">Cita</option>
                        <option value="Negociar">Negociar</option>
                        <option value="Cierre">Cierre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="seguimiento_notas">Notas:</label>
                    <textarea id="seguimiento_notas" name="seguimiento_notas" rows="4"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">Guardar Seguimiento</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtiene las cotizaciones de un evento
     */
    private function get_event_quotes($event_id) {
        // Por ahora retornamos array vacío ya que no tenemos tabla de cotizaciones
        // TODO: Implementar cuando exista la tabla de cotizaciones
        return array();
    }

    /**
     * Maneja el guardado de seguimiento de evento
     */
    public function handle_save_event_followup() {
        // Verificar nonce
        if (!check_ajax_referer('event_followup_nonce', 'nonce', false)) {
            wp_send_json_error('Error de seguridad');
            return;
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        
        if (!$event_id) {
            wp_send_json_error('ID de evento no válido');
            return;
        }

        // Obtener el lead_id del evento
        $event = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT lead_id FROM {$this->wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $event_id
        ));

        if (!$event) {
            wp_send_json_error('Evento no encontrado');
            return;
        }

        // Verificar campos requeridos
        $required_fields = array('status', 'fecha', 'actividad', 'notas');
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field])) {
                wp_send_json_error('Campo requerido faltante: ' . $field);
                return;
            }
        }

        // Preparar datos del seguimiento
        $seguimiento = array(
            array(
                'seguimiento-hecho-si-no' => sanitize_text_field($_POST['status']),
                'fecha' => sanitize_text_field($_POST['fecha']),
                'actividad' => sanitize_text_field($_POST['actividad']),
                'notas' => sanitize_textarea_field($_POST['notas']),
                'cotizacion_id' => isset($_POST['cotizacion_id']) ? intval($_POST['cotizacion_id']) : null
            )
        );

        $cotizacion_id = isset($_POST['cotizacion_id']) && !empty($_POST['cotizacion_id']) 
            ? intval($_POST['cotizacion_id']) 
            : null;

        // Insertar en la nueva tabla
        $inserted = $this->wpdb->insert(
            $this->table_name,
            array(
                'event_id' => $event_id,
                'lead_id' => $event->lead_id,
                'cotizacion_id' => $cotizacion_id,
                'seguimiento' => serialize($seguimiento),
                'cct_status' => 'publish',
                'cct_created' => current_time('mysql'),
                'cct_modified' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($inserted) {
            wp_send_json_success(array(
                'message' => 'Seguimiento guardado correctamente',
                'seguimiento' => $seguimiento
            ));
        } else {
            wp_send_json_error('Error al guardar el seguimiento: ' . $this->wpdb->last_error);
        }
    }

    /**
     * Verifica si un evento tiene seguimientos antiguos
     */
    public function event_has_old_followups($event_id) {
        // Obtener el lead_id del evento
        $event = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT lead_id FROM {$this->wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
            $event_id
        ));

        if (!$event) {
            return false;
        }

        // Verificar si hay seguimientos en la tabla antigua
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->old_table_name} WHERE id_lead = %d",
            $event->lead_id
        ));

        return $count > 0;
    }

    /**
     * Renderiza la lista de seguimientos del evento
     */
    public function render_event_seguimientos_list($event_id, $lead_id) {
        // Verificar si debemos usar la tabla antigua
        if ($this->event_has_old_followups($event_id)) {
            // Usar la lógica antigua
            $followup_form = new LTB_Leads_Followup_Form();
            return $followup_form->render_seguimientos_list($lead_id);
        }

        // Usar la nueva tabla
        $seguimientos = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE event_id = %d 
            ORDER BY cct_created DESC",
            $event_id
        ));
        
        ob_start();
        ?>
        <div class="event-seguimientos-list">
            <?php if (empty($seguimientos)): ?>
                <p class="no-seguimientos">No hay seguimientos registrados para este evento</p>
            <?php else: ?>
                <?php foreach ($seguimientos as $seguimiento): ?>
                    <?php 
                    $datos = maybe_unserialize($seguimiento->seguimiento);
                    if (!is_array($datos)) continue;
                    
                    foreach ($datos as $entrada):
                        if (!is_array($entrada)) continue;
                        
                        // Obtener info de la cotización si existe
                        $cotizacion_info = '';
                        if (!empty($entrada['cotizacion_id'])) {
                            $cotizacion_info = ' - Cotización #' . $entrada['cotizacion_id'];
                        }
                    ?>
                    <div class="seguimiento-item">
                        <div class="seguimiento-header">
                            <span class="seguimiento-fecha">
                                <?php echo date('d/m/Y H:i', strtotime($entrada['fecha'])); ?>
                            </span>
                            <?php if ($cotizacion_info): ?>
                            <span class="seguimiento-cotizacion">
                                <?php echo esc_html($cotizacion_info); ?>
                            </span>
                            <?php endif; ?>
                            <span class="seguimiento-status <?php echo strtolower($entrada['seguimiento-hecho-si-no']); ?>">
                                <?php echo esc_html($entrada['seguimiento-hecho-si-no']); ?>
                            </span>
                        </div>
                        <div class="seguimiento-content">
                            <p class="seguimiento-actividad">
                                <strong>Actividad:</strong> <?php echo esc_html($entrada['actividad']); ?>
                            </p>
                            <?php if (!empty($entrada['notas'])): ?>
                                <p class="seguimiento-notas">
                                    <?php echo nl2br(esc_html($entrada['notas'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                    endforeach;
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <style>
        .event-seguimientos-list {
            margin-top: 20px;
        }
        
        .seguimiento-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 10px;
            padding: 15px;
        }
        
        .seguimiento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .seguimiento-fecha {
            color: #666;
            font-size: 0.9em;
        }
        
        .seguimiento-cotizacion {
            color: #0d6efd;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .seguimiento-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .seguimiento-status.pendiente {
            background: #fff3cd;
            color: #856404;
        }
        
        .seguimiento-status.realizado {
            background: #d4edda;
            color: #155724;
        }
        
        .seguimiento-content {
            font-size: 0.95em;
        }
        
        .seguimiento-actividad {
            margin-bottom: 8px;
        }
        
        .seguimiento-notas {
            color: #666;
            margin: 0;
            white-space: pre-line;
        }
        
        .no-seguimientos {
            text-align: center;
            color: #666;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler para obtener cotizaciones de un evento
     */
    public function handle_get_event_quotes() {
        if (!check_ajax_referer('event_followup_nonce', 'nonce', false)) {
            wp_send_json_error('Error de seguridad');
            return;
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        
        if (!$event_id) {
            wp_send_json_error('ID de evento no válido');
            return;
        }

        $quotes = $this->get_event_quotes($event_id);
        
        wp_send_json_success(array(
            'quotes' => $quotes
        ));
    }
}