<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
    wp_redirect(home_url());
    exit;
}

$lead_data = $GLOBALS['ltb_lead_data'] ?? null;

if (!$lead_data) {
    echo '<div class="error-message">No se encontraron datos del lead</div>';
    return;
}

$is_admin = current_user_can('manage_options');
$nombre_completo = trim($lead_data->lead_nombre . ' ' . $lead_data->lead_apellido);
$initial = strtoupper(substr($nombre_completo, 0, 1));
$has_eventos = !empty($lead_data->eventos) && count($lead_data->eventos) > 0;
$telefono_limpio = preg_replace('/[^0-9]/', '', $lead_data->lead_celular);
?>

<div class="lead-single-container">
	
    <!-- Header con botones de acción principales -->
    <div class="lead-header">
        <div class="lead-title">
            <a href="/leads/" class="back-button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <span>Volver a Leads</span>
            </a>
            <h1>Información del Lead</h1>
        </div>
        <?php if ($is_admin): ?>
            <div class="lead-actions">
                <button class="button edit-lead-btn" id="editLeadBtn">
                    <span class="dashicons dashicons-edit"></span>
                    Editar
                </button>
                <button class="button delete-lead-btn" id="deleteLeadBtn">
                    <span class="dashicons dashicons-trash"></span>
                    Eliminar
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenido principal en 3 columnas -->
    <div class="lead-main-content">
        <!-- Columna izquierda: Información del lead -->
        <div class="lead-column lead-info-column">
            <div class="lead-profile-card">
                <div class="lead-avatar"><?php echo $initial; ?></div>
                <div class="lead-profile-info">
                    <h2 class="lead-name">
                        <span class="nombre-completo-display"><?php echo esc_html($nombre_completo); ?></span>
                        <span class="nombre-apellido-edit" style="display:none;">
                            <span data-field="lead_nombre"><?php echo esc_html($lead_data->lead_nombre); ?></span> 
                            <span data-field="lead_apellido"><?php echo esc_html($lead_data->lead_apellido); ?></span>
                        </span>
                    </h2>
                    <?php if (!empty($lead_data->lead_razon_social)): ?>
                        <div class="lead-company" data-field="lead_razon_social"><?php echo esc_html($lead_data->lead_razon_social); ?></div>
                    <?php endif; ?>
                    <div class="lead-creation-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span>Registrado: <?php echo esc_html($lead_data->fecha_solicitud); ?></span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <h3 class="card-title">Datos de contacto</h3>
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-label">
                            <span class="dashicons dashicons-phone"></span>
                            <span>Teléfono:</span>
                        </div>
                        <div class="contact-value" data-field="lead_celular"><?php echo esc_html($lead_data->lead_celular); ?></div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-label">
                            <span class="dashicons dashicons-email"></span>
                            <span>Email:</span>
                        </div>
                        <div class="contact-value" data-field="lead_e_mail"><?php echo esc_html($lead_data->lead_e_mail); ?></div>
                    </div>
                </div>
                <div class="contact-actions">
    <a href="tel:<?php echo esc_attr($lead_data->lead_celular); ?>" class="contact-action-btn phone-btn" title="Llamar">
        <span class="dashicons dashicons-phone"></span>
    </a>
    <?php
    // Obtener el nombre del usuario actual de WordPress
    $current_user = wp_get_current_user();
    $planner_name = $current_user->display_name;
    
    // Verificar si hay un solo evento
    $evento_count = !empty($lead_data->eventos) && is_array($lead_data->eventos) ? count($lead_data->eventos) : 0;
    
    if ($evento_count === 1) {
        // Si hay un solo evento, crear un mensaje con ese evento
        $evento = reset($lead_data->eventos);
        $servicio_titulo = !empty($evento->servicio_titulo) ? $evento->servicio_titulo : 'tu servicio';
        
        // Construir el mensaje
        $mensaje = "Hola, que tal {$lead_data->lead_nombre}, ¿Cómo estás? soy {$planner_name} integrante del equipo de Planner's de Reservas.Events me reporto para saludarte y ayudarte con el servicio de {$servicio_titulo}";
        
        // Codificar el mensaje para URL
        $mensaje_codificado = urlencode($mensaje);
        
        // Construir el enlace de WhatsApp
        $whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $lead_data->lead_celular) . "?text=" . $mensaje_codificado;
    } else {
        // Si hay múltiples eventos o ninguno, usar un enlace básico
        $whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $lead_data->lead_celular);
    }
    ?>
    <a href="<?php echo esc_url($whatsapp_url); ?>" 
       class="contact-action-btn whatsapp-btn" 
       target="_blank" title="<?php echo $evento_count === 1 ? 'Enviar WhatsApp sobre ' . esc_attr($servicio_titulo) : 'Enviar WhatsApp'; ?>">
        <span class="dashicons dashicons-whatsapp"></span>
    </a>
    <a href="mailto:<?php echo esc_attr($lead_data->lead_e_mail); ?>" class="contact-action-btn email-btn" title="Email">
        <span class="dashicons dashicons-email"></span>
    </a>
</div>
            </div>
        </div>

        <!-- Columna central: Datos del evento y seguimientos -->
        <div class="lead-column lead-event-column">
            <?php if ($has_eventos): ?>
    <div class="eventos-accordion">
        <?php foreach ($lead_data->eventos as $index => $evento): 
            // Determinar si este evento debe estar expandido por defecto (el primero)
            $is_expanded = ($index === 0) ? 'true' : 'false';
            $is_active = ($index === 0) ? 'active' : '';
        ?>
        <div class="evento-item">
            <!-- Cabecera del acordeón -->
            <div class="evento-header <?php echo $is_active; ?>" data-expanded="<?php echo $is_expanded; ?>" data-evento-id="<?php echo $evento->evento_id; ?>">
                <div class="evento-header-info">
                    <div class="evento-header-main">
                        <span class="evento-fecha">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($evento->fecha_de_evento); ?>
                        </span>
                        <span class="evento-tipo">
                            <span class="dashicons dashicons-tag"></span>
                            <?php echo esc_html($evento->tipo_de_evento); ?>
                        </span>
                    </div>
                    <div class="evento-header-status">
                        <?php
							$status_class = LTB_Leads_Status_Utils::get_status_color_class($evento->evento_status);
							?>
							<span class="status-badge <?php echo esc_attr($status_class); ?>">
								<?php echo esc_html($evento->evento_status); ?>
							</span>
                    </div>
                </div>
                <div class="evento-toggle">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                </div>
            </div>
            
            <!-- Contenido del acordeón (inicialmente oculto excepto el primero) -->
            <div class="evento-content" style="<?php echo ($index === 0) ? 'display: block;' : 'display: none;'; ?>">
                <div class="event-info">
                    <div class="event-row">
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <span>Fecha:</span>
                            </div>
                            <div class="event-value" data-field="fecha_de_evento" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo esc_html($evento->fecha_de_evento); ?>
                            </div>
                        </div>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-tag"></span>
                                <span>Tipo:</span>
                            </div>
                            <div class="event-value" data-field="tipo_de_evento" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo esc_html($evento->tipo_de_evento); ?>
                            </div>
                        </div>
                    </div>
                    <div class="event-row">
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-groups"></span>
                                <span>Invitados:</span>
                            </div>
                            <div class="event-value" data-field="evento_asistentes" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo esc_html($evento->evento_asistentes ?: 'No especificado'); ?>
                            </div>
                        </div>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-flag"></span>
                                <span>Estado:</span>
                            </div>
                            <?php $status_class = LTB_Leads_Status_Utils::get_status_color_class($evento->evento_status); ?>
<div class="event-value status-badge <?php echo esc_attr($status_class); ?>" 
     data-field="evento_status" data-evento-id="<?php echo $evento->evento_id; ?>">
    <?php echo esc_html($evento->evento_status); ?>
</div>
                        </div>
                    </div>
                    <?php if (!empty($evento->ubicacion_evento) || !empty($evento->direccion_evento)): ?>
                    <div class="event-row">
                        <?php if (!empty($evento->ubicacion_evento)): ?>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-location"></span>
                                <span>Ubicación:</span>
                            </div>
                            <div class="event-value" data-field="ubicacion_evento" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo esc_html($evento->ubicacion_evento); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($evento->direccion_evento)): ?>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-location-alt"></span>
                                <span>Dirección:</span>
                            </div>
                            <div class="event-value" data-field="direccion_evento" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo esc_html($evento->direccion_evento); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($evento->servicio_url)): ?>
                    <div class="event-row">
                        <div class="event-item full-width">
                            <div class="event-label">
                                <span class="dashicons dashicons-admin-links"></span>
                                <span>Servicio de interés:</span>
                            </div>
                            <div class="event-value">
                                <a href="<?php echo esc_url($evento->servicio_url); ?>" target="_blank" class="service-link">
                                    <?php echo esc_html($evento->servicio_titulo ?: 'Ver servicio'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($evento->comentarios_evento)): ?>
                    <div class="event-row">
                        <div class="event-item full-width">
                            <div class="event-label">
                                <span class="dashicons dashicons-admin-comments"></span>
                                <span>Comentarios:</span>
                            </div>
                            <div class="event-value event-comments" data-field="comentarios_evento" data-evento-id="<?php echo $evento->evento_id; ?>">
                                <?php echo nl2br(esc_html($evento->comentarios_evento)); ?>
                            </div>
                        </div>
                    </div>
			
                    <?php endif; ?>
					
										<!-- Botón para ver detalles del evento -->
<div class="event-actions">
    <a href="/event-details/event-<?php echo esc_attr($evento->evento_id); ?>" class="event-action-btn view-event-btn">
        <span class="dashicons dashicons-visibility"></span>
        Ver detalles del evento
    </a>
</div>
                    
                    <?php if ($evento_count > 1): // Solo mostrar cuando hay más de un evento ?>
                    <div class="event-actions">
                        <?php
                        // Obtener título del servicio para este evento específico
                        $servicio_titulo = !empty($evento->servicio_titulo) ? $evento->servicio_titulo : 'este servicio';
                        
                        // Construir mensaje específico para este evento
                        $mensaje_evento = "Hola, que tal {$lead_data->lead_nombre}, ¿Cómo estás? soy {$planner_name} integrante del equipo de Planner's de Reservas.Events me reporto para saludarte y ayudarte con el servicio de {$servicio_titulo}";
                        
                        // Codificar mensaje
                        $mensaje_evento_codificado = urlencode($mensaje_evento);
                        
                        // URL de WhatsApp específica para este evento
                        $whatsapp_evento_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $lead_data->lead_celular) . "?text=" . $mensaje_evento_codificado;
                        ?>
                        <a href="<?php echo esc_url($whatsapp_evento_url); ?>" class="event-action-btn whatsapp-event-btn" target="_blank" title="Contactar por este servicio">
                            <span class="dashicons dashicons-whatsapp"></span> Contactar sobre este servicio
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="info-card eventos-summary">
        <div class="card-header">
            <h3 class="card-title">Resumen de Eventos</h3>
        </div>
        <div class="eventos-stats">
            <div class="stat-item">
                <div class="stat-value"><?php echo count($lead_data->eventos); ?></div>
                <div class="stat-label">Total de eventos</div>
            </div>
            <?php 
            // Contar eventos por estado
            $estados = array();
            foreach ($lead_data->eventos as $evento) {
                $status = $evento->evento_status;
                if (!isset($estados[$status])) {
                    $estados[$status] = 0;
                }
                $estados[$status]++;
            }
            
            // Mostrar contadores de estado
            foreach ($estados as $status => $count): 
            ?>
            <div class="stat-item">
                <div class="stat-value"><?php echo $count; ?></div>
<?php $status_class = LTB_Leads_Status_Utils::get_status_color_class($status); ?>
<div class="stat-label status-badge <?php echo esc_attr($status_class); ?>"><?php echo $status; ?></div>            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
<div class="info-card">
    <div class="empty-state">
        <span class="dashicons dashicons-calendar-alt empty-icon"></span>
        <h3>No hay eventos registrados</h3>
        <p>Este lead no tiene eventos asociados. Puedes agregar un evento usando el botón correspondiente.</p>
    </div>
</div>
<?php endif; ?>

            <!-- Seguimientos -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title">Seguimientos</h3>
                </div>
                
                <div class="followup-form-container" style="display: none;">
                    <?php 
                    $followup_form = new LTB_Leads_Followup_Form();
                    echo $followup_form->render_form();
                    ?>
                </div>
                
                <div class="followups-timeline">
                    <?php echo $followup_form->render_seguimientos_list($lead_data->lead_id); ?>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Cotizaciones y acciones extra -->
        <div class="lead-column lead-actions-column">
            <div class="info-card">
                <h3 class="card-title">Acciones Rápidas</h3>
                <div class="quick-actions">
                    <?php if ($has_evento): ?>
                    <button class="action-btn status-update-btn">
                        <span class="dashicons dashicons-flag"></span>
                        <span>Actualizar Estado</span>
                    </button>
                    <?php endif; ?>
                    <button class="action-btn add-followup-btn">
                        <span class="dashicons dashicons-clock"></span>
                        <span>Programar Seguimiento</span>
                    </button>
                    <?php if ($has_evento): ?>
                    <button class="action-btn quote-btn disabled">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <span>Crear Cotización</span>
                    </button>
                    <?php else: ?>
                    <button class="action-btn add-event-btn">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span>Agregar Evento</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Panel de Calificación y Seguimiento -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title">Calificación y Seguimiento</h3>
                </div>
                
                <?php 
                // Cargar la plantilla de metadatos
                include_once(LTB_LEADS_PLUGIN_DIR . 'templates/lead-metadata.php');
                ?>
            </div>

            <!-- Cotizaciones -->
<div class="info-card">
    <div class="card-header">
        <h3 class="card-title">Cotizaciones</h3>
    </div>
    
    <?php
    // Obtener cotizaciones para este lead
    global $wpdb;
    $quotes = $wpdb->get_results($wpdb->prepare(
        "SELECT q.*, e.tipo_de_evento, e.fecha_de_evento 
         FROM {$wpdb->prefix}eq_quotes q 
         LEFT JOIN {$wpdb->prefix}jet_cct_eventos e ON q.event_id = e._ID
         WHERE q.lead_id = %d 
         ORDER BY q.created_at DESC 
         LIMIT 10",
        $lead_data->lead_id
    ));
    
    if (empty($quotes)): 
    ?>
    <div class="quotes-placeholder">
        <div class="empty-state">
            <span class="dashicons dashicons-media-spreadsheet empty-icon"></span>
            <p>No hay cotizaciones generadas para este lead.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="quotes-summary">
        <?php 
        // Agrupar cotizaciones por evento
        $quotes_by_event = array();
        foreach ($quotes as $quote) {
            if (!isset($quotes_by_event[$quote->event_id])) {
                $quotes_by_event[$quote->event_id] = array();
            }
            $quotes_by_event[$quote->event_id][] = $quote;
        }
        
        // Mostrar cotizaciones agrupadas
        foreach ($quotes_by_event as $event_id => $event_quotes): 
            $first_quote = reset($event_quotes);
            $quote_date = !empty($first_quote->fecha_de_evento) ? date_i18n(get_option('date_format'), intval($first_quote->fecha_de_evento)) : 'Sin fecha';
            $quote_event = !empty($first_quote->tipo_de_evento) ? $first_quote->tipo_de_evento : 'Evento sin tipo';
        ?>
        <div class="quote-event-group">
            <div class="quote-event-header">
                <h4 class="quote-event-title">
                    <?php echo esc_html($quote_event); ?> - <?php echo esc_html($quote_date); ?>
                </h4>
                <a href="/event-details/event-<?php echo esc_attr($event_id); ?>" class="view-event-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    Ver detalles
                </a>
            </div>
            <div class="quote-count">
                <?php echo count($event_quotes); ?> cotización(es)
            </div>
            <div class="quote-list-preview">
                <?php 
                // Mostrar solo la cotización más reciente
                $latest_quote = reset($event_quotes);
                ?>
                <div class="quote-item-preview">
                    <div class="quote-preview-info">
                        <div class="quote-preview-title">
                            <span class="dashicons dashicons-media-document"></span>
                            <?php echo esc_html($latest_quote->nombre_pdf ?: 'Cotización'); ?>
                        </div>
                        <div class="quote-preview-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($latest_quote->created_at)); ?>
                        </div>
                    </div>
                    <a href="<?php echo esc_url($latest_quote->pdf_url); ?>" target="_blank" class="quote-preview-action">
                        Ver PDF
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
        </div>
    </div>
</div>


<style>
/* Estilos generales del contenedor */
.lead-single-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    color: #333;
}

/* Header y navegación */
.lead-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.lead-title {
    display: flex;
    align-items: center;
    gap: 15px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    color: #666;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.2s;
}

.back-button:hover {
    color: #0d6efd;
}

.back-button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.lead-header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 500;
}

.lead-actions button {
    background-color: #c8bb90;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.lead-actions button:hover {
    background-color: #b8a77e;
}

/* Layout principal de 3 columnas */
.lead-main-content {
    display: grid;
    grid-template-columns: 300px 1fr 300px;
    gap: 20px;
}

/* Estilos de tarjetas */
.info-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.card-title {
    font-size: 18px;
    font-weight: 500;
    margin-top: 0;
    margin-bottom: 16px;
    color: #333;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.card-header .card-title {
    margin: 0;
    padding: 0;
    border: none;
}

/* Perfil del lead */
.lead-profile-card {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.lead-avatar {
    width: 60px;
    height: 60px;
    background: #c8bb90;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 500;
}

.lead-profile-info {
    flex: 1;
}

.lead-name {
    font-size: 20px;
    font-weight: 500;
    margin: 0 0 5px;
}

.lead-company {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.lead-creation-date {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #777;
    font-size: 12px;
}

/* Datos de contacto */
.contact-info {
    margin-bottom: 15px;
}

.contact-item {
    margin-bottom: 12px;
}

.contact-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    margin-bottom: 3px;
    font-size: 14px;
}

.contact-value {
    font-size: 15px;
    color: #333;
    word-break: break-word;
}

.contact-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.contact-action-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px;
    border-radius: 4px;
    text-decoration: none;
    color: white;
    font-size: 13px;
    transition: opacity 0.2s;
    min-width: 80px;
}

.contact-action-btn:hover {
    opacity: 0.9;
    color: white;
}

.phone-btn {
    background: #0d6efd;
}

.whatsapp-btn {
    background: #25D366;
}

.email-btn {
    background: #6c757d;
}

/* Datos del evento */
.event-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.event-item {
    flex: 1;
}

.event-item.full-width {
    flex: 0 0 100%;
}

.event-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    margin-bottom: 5px;
    font-size: 14px;
}

.event-value {
    font-size: 15px;
    color: #333;
}

	.event-comments {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 4px;
    font-size: 14px	
	line-height: 1.5;
    white-space: pre-line;
}

.service-link {
    color: #0d6efd;
    text-decoration: none;
}

.service-link:hover {
    text-decoration: underline;
}


.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

.status-badge.status-sin-evento {
    background: #f1f3f5;
    color: #495057;
}

.status-badge.status-nuevo {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-con-presupuesto {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.status-por-cerrar {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-con-contrato {
    background: #c3e6cb;
    color: #155724;
}

.status-badge.status-perdido {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.status-otros {
    background: #e9ecef;
    color: #495057;
}

/* Acciones rápidas */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px;
    border-radius: 4px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
    text-align: left;
}

.action-btn:hover {
    background: #e9ecef;
}

.action-btn.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.coming-soon {
    background: #e9ecef;
    color: #495057;
}

/* Seguimientos */
.followups-timeline {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 5px;
}

.add-followup-toggle {
    background: #0d6efd;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 10px;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.followup-form-container {
    border-bottom: 1px solid #eee;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

/* Estados vacíos */
.empty-state {
    text-align: center;
    padding: 30px 0;
    color: #666;
}

.empty-icon {
    font-size: 48px;
    width: 48px;
    height: 48px;
    margin: 0 auto 15px;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 0 0 10px;
    font-weight: 500;
}

.empty-state p {
    margin: 0 0 20px;
    font-size: 14px;
}

.add-event-btn {
    background: #0d6efd;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

/* Placeholder para cotizaciones */
.quotes-placeholder {
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Media queries para responsive */
@media (max-width: 1200px) {
    .lead-main-content {
        grid-template-columns: 250px 1fr 250px;
    }
}

@media (max-width: 991px) {
    .lead-main-content {
        grid-template-columns: 1fr;
    }
    
    .lead-info-column {
        order: 1;
    }
    
    .lead-event-column {
        order: 2;
    }
    
    .lead-actions-column {
        order: 3;
    }
    
    .event-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .contact-actions {
        flex-direction: row;
    }
    
    .quick-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .action-btn {
        flex: 1;
        min-width: 150px;
        justify-content: center;
    }
}

@media (max-width: 767px) {
    .lead-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .lead-actions {
        width: 100%;
    }
    
    .lead-actions button {
        width: 100%;
        justify-content: center;
    }
    
    .contact-actions, .quick-actions {
        flex-direction: column;
    }
}
</style>



<style>
/* Estilos para modo edición */
.edit-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.edit-input:focus {
    border-color: #0d6efd;
    outline: none;
    box-shadow: 0 0 0 2px rgba(13,110,253,.25);
}

.edit-lead-btn.save-mode {
    background-color: #198754;
}

.edit-lead-btn.save-mode:hover {
    background-color: #157347;
}


/* Estilos del formulario */
.event-form .form-group {
    margin-bottom: 15px;
}

.event-form .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.event-form .form-group input,
.event-form .form-group select,
.event-form .form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.event-form .form-group.required label:after {
    content: " *";
    color: #dc3545;
}

.error-message {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}
	
	/* Estilos para el acordeón de eventos */
.eventos-accordion {
    margin-bottom: 20px;
}

.evento-item {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 10px;
    overflow: hidden;
}

.evento-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    cursor: pointer;
    transition: background-color 0.2s;
    border-left: 4px solid #dee2e6;
}

.evento-header:hover {
    background-color: #f8f9fa;
}

.evento-header.active {
    border-left-color: #0d6efd;
    background-color: #f8f9fa;
}

.evento-header-info {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.evento-header-main {
    display: flex;
    gap: 20px;
    align-items: center;
}

.evento-fecha, .evento-tipo {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
}

.evento-toggle {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.evento-header.active .evento-toggle .dashicons {
    transform: rotate(180deg);
}

.evento-toggle .dashicons {
    transition: transform 0.3s;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.evento-content {
    padding: 0 20px 20px;
    border-top: 1px solid #f0f0f0;
}

/* Estilos para el resumen de eventos */
.eventos-summary {
    margin-top: 20px;
}

.eventos-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.stat-item {
    flex: 1;
    min-width: 100px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: 500;
    color: #0d6efd;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #495057;
}

/* Responsivo */
@media (max-width: 767px) {
    .evento-header-main {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .eventos-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .stat-item {
        min-width: 70px;
    }
}
	
	/* Estilos para cotizaciones */
.quotes-summary {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.quote-event-group {
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 15px;
    background: #f9f9f9;
}

.quote-event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.quote-event-title {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
}


.view-event-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #0d6efd;
    text-decoration: none;
}

.view-event-btn:hover {
    text-decoration: underline;
}

.quote-count {
    font-size: 13px;
    color: #666;
    margin-bottom: 10px;
}

.quote-list-preview {
    background: white;
    border: 1px solid #eee;
    border-radius: 4px;
}

.quote-item-preview {
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.quote-preview-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.quote-preview-title {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
}

.quote-preview-date {
    font-size: 12px;
    color: #666;
}

.quote-preview-action {
    background: #0d6efd;
    color: white;
    border-radius: 4px;
    padding: 5px 10px;
    font-size: 13px;
    text-decoration: none;
    transition: background-color 0.2s;
}

.quote-preview-action:hover {
    background: #0b5ed7;
    color: white;
}

/* Estilos para el botón de editar nombre */
.name-edit-button {
    margin-bottom: 5px;
    font-size: 12px;
    padding: 3px 8px;
}

/* Estilos para los campos de nombre/apellido en modo edición */
.nombre-apellido-edit {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.nombre-apellido-edit [data-field] {
    flex: 1;
    min-width: 0;
}

/* Estilos para el botón de editar evento */
.evento-content .edit-section-button {
    margin-bottom: 15px;
    display: inline-block;
}
</style>

<script>
	
jQuery(document).ready(function($) {
    // Función para manejar el acordeón de eventos
    $('.evento-header').on('click', function() {
        var $header = $(this);
        var $eventoItem = $header.parent();
        var $content = $eventoItem.find('.evento-content');
        var isExpanded = $header.attr('data-expanded') === 'true';
        
        // Alternar el estado actual
        if (isExpanded) {
            $content.slideUp(300);
            $header.attr('data-expanded', 'false');
            $header.removeClass('active');
        } else {
            $content.slideDown(300);
            $header.attr('data-expanded', 'true');
            $header.addClass('active');
        }
    });
});
</script>