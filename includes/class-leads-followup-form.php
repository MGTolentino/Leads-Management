<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Followup_Form {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_hooks();
    }

    private function init_hooks() {
    error_log('=== Inicializando hooks de followup ===');
    add_action('wp_ajax_save_followup', array($this, 'handle_save_followup'));
    add_action('wp_ajax_nopriv_save_followup', array($this, 'handle_save_followup'));
}

    public function render_form() {
        ob_start();
        ?>
        <div class="followup-form-wrapper">
            <form id="followupForm" class="followup-form">
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
                        <option value="Cita">Cita</option>
                        <option value="Negociar">Negociar</option>
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

        <style>
            .followup-form-wrapper {
                background: white;
                padding: 20px;
                border-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin-bottom: 20px;
            }

            .followup-form .form-group {
                margin-bottom: 15px;
            }

            .followup-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: 500;
                color: #444;
            }

            .followup-form select,
            .followup-form input,
            .followup-form textarea {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }

            .followup-form textarea {
                resize: vertical;
                min-height: 100px;
            }

            .followup-form .form-actions {
                margin-top: 20px;
            }

            .followup-form button {
                padding: 8px 16px;
                background: #0d6efd;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }

            .followup-form button:hover {
                background: #0b5ed7;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    public function handle_save_followup() {
    error_log('=== FOLLOWUP SAVE llamado ===');
    error_log('POST data: ' . print_r($_POST, true));

    // Verificar nonce sin die
    if (!check_ajax_referer('followup_nonce', 'nonce', false)) {
        error_log('Error de nonce');
        wp_send_json_error('Error de seguridad');
        return;
    }

    $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
    error_log('Lead ID: ' . $lead_id);
    
    if (!$lead_id) {
        error_log('ID de lead no válido');
        wp_send_json_error('ID de lead no válido');
        return;
    }

    // Verificar que todos los campos necesarios estén presentes
    $required_fields = array('status', 'fecha', 'actividad', 'notas');
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            error_log('Campo requerido faltante: ' . $field);
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
            'notas' => sanitize_textarea_field($_POST['notas'])
        )
    );

    error_log('Datos de seguimiento preparados: ' . print_r($seguimiento, true));

    // Insertar en la tabla CCT
    $inserted = $this->wpdb->insert(
        $this->wpdb->prefix . 'jet_cct_crm',
        array(
            'parent_id' => $lead_id,
            'seguimiento' => serialize($seguimiento),
            'cct_status' => 'publish',
            'cct_created' => current_time('mysql'),
            'cct_modified' => current_time('mysql'),
            'id_lead' => $lead_id
        ),
        array('%d', '%s', '%s', '%s', '%s', '%d')
    );

    if ($inserted) {
        error_log('Seguimiento guardado correctamente');
        wp_send_json_success(array(
            'message' => 'Seguimiento guardado correctamente',
            'seguimiento' => $seguimiento
        ));
    } else {
        error_log('Error al guardar seguimiento: ' . $this->wpdb->last_error);
        wp_send_json_error('Error al guardar el seguimiento: ' . $this->wpdb->last_error);
    }
}
	
	public function render_seguimientos_list($lead_id) {
    global $wpdb;
    
    $seguimientos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}jet_cct_crm 
        WHERE id_lead = %d 
        ORDER BY cct_created DESC",
        $lead_id
    ));
    
    ob_start();
    ?>
    <div class="seguimientos-list">
        <?php if (empty($seguimientos)): ?>
            <p class="no-seguimientos">No hay seguimientos registrados</p>
        <?php else: ?>
            <?php foreach ($seguimientos as $seguimiento): ?>
                <?php 
                $datos = maybe_unserialize($seguimiento->seguimiento);
                if (!is_array($datos)) continue;
                
                foreach ($datos as $entrada):
                    if (!is_array($entrada)) continue;
                ?>
                <div class="seguimiento-item">
                    <div class="seguimiento-header">
                        <span class="seguimiento-fecha">
                            <?php echo date('d/m/Y H:i', strtotime($entrada['fecha'])); ?>
                        </span>
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
    .seguimientos-list {
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
        margin-bottom: 10px;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }
    
    .seguimiento-fecha {
        color: #666;
        font-size: 0.9em;
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
	
}