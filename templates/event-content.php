<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
    wp_redirect(home_url());
    exit;
}

$event_data = $GLOBALS['ltb_event_data'] ?? null;
$lead_data = $GLOBALS['ltb_lead_data'] ?? null;
$quotes = $GLOBALS['ltb_event_quotes'] ?? array();

if (!$event_data || !$lead_data) {
    echo '<div class="error-message">No se encontraron datos del evento</div>';
    return;
}

$is_admin = current_user_can('manage_options');
$nombre_completo = trim($lead_data->lead_nombre . ' ' . $lead_data->lead_apellido);
$fecha_evento = !empty($event_data->fecha_de_evento) ? date_i18n(get_option('date_format'), intval($event_data->fecha_de_evento)) : 'Sin fecha';
$tipo_evento = !empty($event_data->tipo_de_evento) ? $event_data->tipo_de_evento : 'Sin tipo';
$telefono_limpio = preg_replace('/[^0-9]/', '', $lead_data->lead_celular);
?>

<div class="event-single-container">
    <!-- Header con botones de acción principales -->
    <div class="event-header">
        <div class="event-title">
            <a href="/lead-details/lead-<?php echo esc_attr($lead_data->_ID); ?>" class="back-button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <span>Volver al Lead</span>
            </a>
            <h1>Detalles del Evento</h1>
        </div>
    </div>

    <!-- Contenido principal en 2 columnas -->
    <div class="event-main-content">
        <!-- Columna izquierda: Información del evento y lead -->
        <div class="event-column event-info-column">
            <div class="info-card">
                <h3 class="card-title">Datos del Evento</h3>
                <div class="event-info">
                    <div class="event-row">
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <span>Fecha:</span>
                            </div>
                            <div class="event-value">
                                <?php echo esc_html($fecha_evento); ?>
                            </div>
                        </div>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-tag"></span>
                                <span>Tipo:</span>
                            </div>
                            <div class="event-value">
                                <?php echo esc_html($tipo_evento); ?>
                            </div>
                        </div>
                    </div>
                    <div class="event-row">
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-groups"></span>
                                <span>Invitados:</span>
                            </div>
                            <div class="event-value">
                                <?php echo esc_html($event_data->evento_asistentes ?: 'No especificado'); ?>
                            </div>
                        </div>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-flag"></span>
                                <span>Estado:</span>
                            </div>
                            <div class="event-value status-badge <?php echo sanitize_html_class($event_data->evento_status); ?>">
                                <?php echo esc_html($event_data->evento_status); ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($event_data->ubicacion_evento) || !empty($event_data->direccion_evento)): ?>
                    <div class="event-row">
                        <?php if (!empty($event_data->ubicacion_evento)): ?>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-location"></span>
                                <span>Ubicación:</span>
                            </div>
                            <div class="event-value">
                                <?php echo esc_html($event_data->ubicacion_evento); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($event_data->direccion_evento)): ?>
                        <div class="event-item">
                            <div class="event-label">
                                <span class="dashicons dashicons-location-alt"></span>
                                <span>Dirección:</span>
                            </div>
                            <div class="event-value">
                                <?php echo esc_html($event_data->direccion_evento); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event_data->servicio_url)): ?>
                    <div class="event-row">
                        <div class="event-item full-width">
                            <div class="event-label">
                                <span class="dashicons dashicons-admin-links"></span>
                                <span>Servicio de interés:</span>
                            </div>
                            <div class="event-value">
                                <a href="<?php echo esc_url($event_data->servicio_url); ?>" target="_blank" class="service-link">
                                    <?php echo esc_html($event_data->servicio_titulo ?: 'Ver servicio'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($event_data->comentarios_evento)): ?>
                    <div class="event-row">
                        <div class="event-item full-width">
                            <div class="event-label">
                                <span class="dashicons dashicons-admin-comments"></span>
                                <span>Comentarios:</span>
                            </div>
                            <div class="event-value event-comments">
                                <?php echo nl2br(esc_html($event_data->comentarios_evento)); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-card">
                <h3 class="card-title">Datos del Cliente</h3>
                <div class="lead-info">
                    <div class="lead-profile">
                        <div class="lead-name">
                            <?php echo esc_html($nombre_completo); ?>
                            <?php if (!empty($lead_data->lead_razon_social)): ?>
                                <div class="lead-company"><?php echo esc_html($lead_data->lead_razon_social); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="contact-info">
                        <div class="contact-item">
                            <div class="contact-label">
                                <span class="dashicons dashicons-phone"></span>
                                <span>Teléfono:</span>
                            </div>
                            <div class="contact-value">
                                <?php echo esc_html($lead_data->lead_celular); ?>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-label">
                                <span class="dashicons dashicons-email"></span>
                                <span>Email:</span>
                            </div>
                            <div class="contact-value">
                                <?php echo esc_html($lead_data->lead_e_mail); ?>
                            </div>
                        </div>
                    </div>
                    <div class="contact-actions">
                        <a href="tel:<?php echo esc_attr($telefono_limpio); ?>" class="contact-action-btn phone-btn" title="Llamar">
                            <span class="dashicons dashicons-phone"></span>
                        </a>
                        <?php
                        // Obtener el nombre del usuario actual
                        $current_user = wp_get_current_user();
                        $planner_name = $current_user->display_name;
                        
                        // Construir el mensaje
                        $servicio_titulo = !empty($event_data->servicio_titulo) ? $event_data->servicio_titulo : 'tu servicio';
                        $mensaje = "Hola, que tal {$lead_data->lead_nombre}, ¿Cómo estás? soy {$planner_name} integrante del equipo de Planner's de Reservas.Events me reporto para saludarte y ayudarte con el servicio de {$servicio_titulo}";
                        
                        // Codificar el mensaje para URL
                        $mensaje_codificado = urlencode($mensaje);
                        
                        // Construir el enlace de WhatsApp
                        $whatsapp_url = "https://wa.me/" . $telefono_limpio . "?text=" . $mensaje_codificado;
                        ?>
                        <a href="<?php echo esc_url($whatsapp_url); ?>" 
                           class="contact-action-btn whatsapp-btn" 
                           target="_blank" title="Enviar WhatsApp">
                            <span class="dashicons dashicons-whatsapp"></span>
                        </a>
                        <a href="mailto:<?php echo esc_attr($lead_data->lead_e_mail); ?>" class="contact-action-btn email-btn" title="Email">
                            <span class="dashicons dashicons-email"></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Cotizaciones -->
        <div class="event-column event-quotes-column">
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title">Cotizaciones del Evento</h3>
                    <button class="action-btn create-quote-btn">
                        <span class="dashicons dashicons-plus"></span>
                        <span>Nueva Cotización</span>
                    </button>
                </div>
                
                <?php if (empty($quotes)): ?>
                <div class="quotes-empty-state">
                    <span class="dashicons dashicons-media-spreadsheet empty-icon"></span>
                    <p>No hay cotizaciones generadas para este evento.</p>
                    <p>Puedes crear una nueva cotización usando el botón "Nueva Cotización".</p>
                </div>
                <?php else: ?>
                <div class="quotes-list">
                    <?php foreach ($quotes as $quote): ?>
                    <div class="quote-item">
                        <div class="quote-header">
                            <div class="quote-title">
                                <span class="dashicons dashicons-media-document"></span>
                                <span><?php echo esc_html($quote->nombre_pdf ?: 'Cotización'); ?></span>
                            </div>
                            <div class="quote-date">
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($quote->created_at)); ?>
                            </div>
                        </div>
                        <div class="quote-actions">
                            <a href="<?php echo esc_url($quote->pdf_url); ?>" target="_blank" class="quote-action-btn view-btn">
                                <span class="dashicons dashicons-visibility"></span>
                                <span>Ver PDF</span>
                            </a>
                            <a href="<?php echo esc_url($quote->pdf_url); ?>" download class="quote-action-btn download-btn">
                                <span class="dashicons dashicons-download"></span>
                                <span>Descargar</span>
                            </a>
                            <button class="quote-action-btn share-btn" data-url="<?php echo esc_url($quote->pdf_url); ?>">
                                <span class="dashicons dashicons-share"></span>
                                <span>Compartir</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Seguimientos específicos para este evento -->
            <div class="info-card">
                <div class="card-header">
                    <h3 class="card-title">Seguimientos</h3>
                    <button class="button add-followup-toggle">
                        <span class="dashicons dashicons-plus"></span>
                        Nuevo Seguimiento
                    </button>
                </div>
                
                <div class="followup-form-container" style="display: none;">
                    <?php 
                    // Usar la nueva clase para seguimientos de eventos
                    if (class_exists('LTB_Leads_Event_Followup')) {
                        $event_followup = new LTB_Leads_Event_Followup();
                        echo $event_followup->render_form($event_data->_ID);
                    } else {
                        // Fallback a la clase antigua si no existe la nueva
                        $followup_form = new LTB_Leads_Followup_Form();
                        echo $followup_form->render_form();
                    }
                    ?>
                </div>
                
                <div class="followups-timeline">
                    <?php 
                    // Usar la nueva clase para mostrar seguimientos
                    if (class_exists('LTB_Leads_Event_Followup')) {
                        $event_followup = new LTB_Leads_Event_Followup();
                        echo $event_followup->render_event_seguimientos_list($event_data->_ID, $lead_data->_ID);
                    } else {
                        // Fallback a la clase antigua
                        $followup_form = new LTB_Leads_Followup_Form();
                        if (method_exists($followup_form, 'render_seguimientos_list')) {
                            echo $followup_form->render_seguimientos_list($lead_data->_ID);
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos generales */
.event-single-container {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    color: #333;
}

/* Header y navegación */
.event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.event-title {
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

.event-header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 500;
}

/* Layout principal de 2 columnas */
.event-main-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
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
    font-size: 14px;
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

.status-badge.nuevo-no-contactado {
    background: #fff3cd;
    color: #856404;
}

.status-badge.contactado-interesado {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.reservado {
    background: #d4edda;
    color: #155724;
}

.status-badge.cerrado {
    background: #c3e6cb;
    color: #155724;
}

.status-badge.cancelado {
    background: #f8d7da;
    color: #721c24;
}

/* Datos del Lead */
.lead-profile {
    margin-bottom: 15px;
}

.lead-name {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 5px;
}

.lead-company {
    font-size: 14px;
    color: #666;
}

.contact-info {
    margin-bottom: 15px;
}

.contact-item {
    margin-bottom: 10px;
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
}

.contact-actions {
    display: flex;
    gap: 10px;
}

.contact-action-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px;
    border-radius: 4px;
    text-decoration: none;
    color: white;
    transition: opacity 0.2s;
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

/* Cotizaciones */
.quotes-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quote-item {
    border: 1px solid #eee;
    border-radius: 6px;
    padding: 15px;
    background: #f9f9f9;
}

.quote-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.quote-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
}

.quote-date {
    font-size: 13px;
    color: #666;
}

.quote-actions {
    display: flex;
    gap: 10px;
}

.quote-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
    transition: background-color 0.2s;
    border: none;
    cursor: pointer;
}

.view-btn {
    background: #6c757d;
    color: white;
}

.download-btn {
   background: #0d6efd;
   color: white;
}

.share-btn {
   background: #198754;
   color: white;
}

.quote-action-btn:hover {
   opacity: 0.9;
}

/* Botones de acción */
.action-btn {
   display: inline-flex;
   align-items: center;
   gap: 8px;
   padding: 8px 16px;
   border-radius: 4px;
   background: #0d6efd;
   border: none;
   color: white;
   cursor: pointer;
   transition: background-color 0.2s;
   font-size: 14px;
}

.action-btn:hover {
   background: #0b5ed7;
}

.create-quote-btn {
   background: #198754;
}

.create-quote-btn:hover {
   background: #157347;
}

/* Estado vacío */
.quotes-empty-state {
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

.quotes-empty-state p {
   margin: 0 0 10px;
   font-size: 14px;
}

/* Seguimientos */
.followups-timeline {
   max-height: 400px;
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

/* Media queries */
@media (max-width: 991px) {
   .event-main-content {
       grid-template-columns: 1fr;
   }
   
   .event-info-column {
       order: 1;
   }
   
   .event-quotes-column {
       order: 2;
   }
   
   .event-row {
       flex-direction: column;
       gap: 10px;
   }
   
   .contact-actions {
       flex-direction: row;
   }
   
   .quote-actions {
       flex-direction: column;
   }
   
   .quote-action-btn {
       justify-content: center;
   }
}

@media (max-width: 767px) {
   .event-header {
       flex-direction: column;
       align-items: flex-start;
       gap: 15px;
   }
   
   .contact-actions {
       flex-direction: column;
   }
}
</style>

<script>
// Localizar datos para el script de seguimientos de evento
var ltbEventFollowup = {
    ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('event_followup_nonce'); ?>',
    eventId: <?php echo esc_js($event_data->_ID); ?>
};
</script>
<?php
// Encolar el script de seguimientos de evento si existe la nueva clase
if (class_exists('LTB_Leads_Event_Followup')) {
    wp_enqueue_script('event-followup-form', plugin_dir_url(dirname(__FILE__)) . 'assets/js/event-followup-form.js', array('jquery'), '1.0.0', true);
}
?>
<script>
jQuery(document).ready(function($) {
   // Toggle para el formulario de seguimiento
   $('.add-followup-toggle').on('click', function() {
       $('.followup-form-container').slideToggle(300);
   });
   
   // Función para compartir cotización
   $('.share-btn').on('click', function() {
       var pdfUrl = $(this).data('url');
       
       // Si el navegador soporta la API de compartir
       if (navigator.share) {
           navigator.share({
               title: 'Cotización para evento',
               text: 'Aquí está la cotización para tu evento',
               url: pdfUrl
           })
           .catch(console.error);
       } else {
           // Fallback: copiar al portapapeles
           var tempInput = document.createElement('input');
           document.body.appendChild(tempInput);
           tempInput.value = pdfUrl;
           tempInput.select();
           document.execCommand('copy');
           document.body.removeChild(tempInput);
           
           alert('URL de la cotización copiada al portapapeles');
       }
   });
   
   // Botón crear cotización
   $('.create-quote-btn').on('click', function() {
       // Redirigir a página con Context Panel activado para este evento y lead
       var eventId = '<?php echo esc_js($event_data->_ID); ?>';
       var leadId = '<?php echo esc_js($lead_data->_ID); ?>';
       
       // Almacenar IDs en localStorage para que el Context Panel los recoja
       localStorage.setItem('eq_selected_lead', leadId);
       localStorage.setItem('eq_selected_event', eventId);
       
       // Redirigir a la página principal de cotización
       window.location.href = '/cotizador-de-eventos/';
   });
});
</script>