<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
    return;
}

$lead_id = isset($lead_data->lead_id) ? $lead_data->lead_id : 0;
$metadata = isset($lead_data->metadata) ? $lead_data->metadata : new stdClass();

// Obtener datos de usuarios para el selector de responsable
$users = get_users(array(
    'role__in' => array('administrator', 'editor', 'ejecutivo_de_ventas'),
    'orderby' => 'display_name'
));

// Obtener servicios disponibles
$servicios = array(
    'catering' => 'Catering',
    'fotografia' => 'Fotografía',
    'musica' => 'Música/DJ',
    'decoracion' => 'Decoración',
    'iluminacion' => 'Iluminación',
    'transporte' => 'Transporte',
    'coordinacion' => 'Coordinación de eventos',
    'audiovisual' => 'Equipo audiovisual',
    'otros' => 'Otros'
);

// Obtener opciones de ubicación
$ubicaciones = array(
    'cdmx' => 'Ciudad de México',
    'guadalajara' => 'Guadalajara',
    'monterrey' => 'Monterrey',
    'puebla' => 'Puebla',
    'queretaro' => 'Querétaro',
    'otra' => 'Otra'
);

// Obtener opciones de industria
$industrias = array(
    'tecnologia' => 'Tecnología',
    'finanzas' => 'Finanzas',
    'salud' => 'Salud',
    'educacion' => 'Educación',
    'manufactura' => 'Manufactura',
    'retail' => 'Retail',
    'entretenimiento' => 'Entretenimiento',
    'turismo' => 'Turismo',
    'construccion' => 'Construcción',
    'servicios' => 'Servicios profesionales',
    'otro' => 'Otro'
);

// Obtener etiquetas
$tags = array();
if (!empty($metadata->tags) && is_array($metadata->tags)) {
    foreach ($metadata->tags as $tag) {
        $tags[] = $tag->name;
    }
}
?>

<div class="metadata-container">
    <!-- Tabs para categorías -->
    <ul class="metadata-tabs">
        <li><a class="metadata-tab-link" data-target="tab-calificacion">Calificación del Lead</a></li>
        <li><a class="metadata-tab-link" data-target="tab-seguimiento">Seguimiento</a></li>
        <li><a class="metadata-tab-link" data-target="tab-origen">Origen</a></li>
        <li><a class="metadata-tab-link" data-target="tab-conversion">Estado de Conversión</a></li>
    </ul>
    
    <div class="metadata-content">
        <form id="lead_metadata_form" class="metadata-form">
            <input type="hidden" id="lead_id" name="lead_id" value="<?php echo esc_attr($lead_id); ?>">
            
            <!-- Tab: Calificación del Lead -->
            <div id="tab-calificacion" class="metadata-tab-pane">
                <div class="metadata-form-section calificacion">
                    <h3 class="metadata-form-section-title">Calificación del Lead</h3>
                    <div class="metadata-form-grid">
                        <!-- Prioridad -->
                        <div class="metadata-form-group">
                            <label for="prioridad">Prioridad</label>
                            <select id="prioridad" name="prioridad">
                                <option value="">Seleccionar prioridad</option>
                                <option value="alta" <?php selected(isset($metadata->prioridad) ? $metadata->prioridad : '', 'alta'); ?>>Alta</option>
                                <option value="media" <?php selected(isset($metadata->prioridad) ? $metadata->prioridad : '', 'media'); ?>>Media</option>
                                <option value="baja" <?php selected(isset($metadata->prioridad) ? $metadata->prioridad : '', 'baja'); ?>>Baja</option>
                            </select>
                        </div>
                        
                        <!-- Valor potencial -->
                        <div class="metadata-form-group">
                            <label for="valor_potencial">Valor Potencial</label>
                            <select id="valor_potencial" name="valor_potencial">
                                <option value="">Seleccionar valor</option>
                                <option value="bajo" <?php selected(isset($metadata->valor_potencial) ? $metadata->valor_potencial : '', 'bajo'); ?>>Bajo (&lt; $10,000)</option>
                                <option value="medio" <?php selected(isset($metadata->valor_potencial) ? $metadata->valor_potencial : '', 'medio'); ?>>Medio ($10,000 - $50,000)</option>
                                <option value="alto" <?php selected(isset($metadata->valor_potencial) ? $metadata->valor_potencial : '', 'alto'); ?>>Alto (&gt; $50,000)</option>
                            </select>
                        </div>
                        
                        <!-- Probabilidad -->
                        <div class="metadata-form-group">
                            <label for="probabilidad">Probabilidad de Conversión</label>
                            <select id="probabilidad" name="probabilidad">
                                <option value="">Seleccionar probabilidad</option>
                                <option value="alta" <?php selected(isset($metadata->probabilidad) ? $metadata->probabilidad : '', 'alta'); ?>>Alta (&gt; 70%)</option>
                                <option value="media" <?php selected(isset($metadata->probabilidad) ? $metadata->probabilidad : '', 'media'); ?>>Media (30% - 70%)</option>
                                <option value="baja" <?php selected(isset($metadata->probabilidad) ? $metadata->probabilidad : '', 'baja'); ?>>Baja (&lt; 30%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Seguimiento -->
            <div id="tab-seguimiento" class="metadata-tab-pane">
                <div class="metadata-form-section seguimiento">
                    <h3 class="metadata-form-section-title">Información de Seguimiento</h3>
                    <div class="metadata-form-grid">
                        <!-- Responsable -->
                        <div class="metadata-form-group">
                            <label for="responsable_id">Responsable</label>
                            <select id="responsable_id" name="responsable_id">
                                <option value="">Seleccionar responsable</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected(isset($metadata->responsable_id) ? $metadata->responsable_id : '', $user->ID); ?>>
                                    <?php echo esc_html($user->display_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Última interacción -->
                        <div class="metadata-form-group">
                            <label for="ultima_interaccion">Última Interacción</label>
                            <input type="text" id="ultima_interaccion" name="ultima_interaccion" class="metadata-date-input" 
                                   value="<?php echo isset($metadata->ultima_interaccion) ? esc_attr(date('Y-m-d', strtotime($metadata->ultima_interaccion))) : ''; ?>" 
                                   placeholder="YYYY-MM-DD">
                        </div>
                        
                        <!-- Próxima acción -->
                        <div class="metadata-form-group">
                            <label for="proxima_accion_fecha">Próxima Acción</label>
                            <input type="text" id="proxima_accion_fecha" name="proxima_accion_fecha" class="metadata-date-input" 
                                   value="<?php echo isset($metadata->proxima_accion_fecha) ? esc_attr(date('Y-m-d', strtotime($metadata->proxima_accion_fecha))) : ''; ?>" 
                                   placeholder="YYYY-MM-DD">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab: Origen -->
            <div id="tab-origen" class="metadata-tab-pane">
                <div class="metadata-form-section origen">
                    <h3 class="metadata-form-section-title">Información de Origen</h3>
                    <div class="metadata-form-grid">
                        <!-- Fuente -->
                        <div class="metadata-form-group">
                            <label for="fuente">Fuente</label>
                            <select id="fuente" name="fuente">
                                <option value="">Seleccionar fuente</option>
                                <option value="web" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'web'); ?>>Web</option>
                                <option value="referido" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'referido'); ?>>Referido</option>
                                <option value="redes_sociales" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'redes_sociales'); ?>>Redes Sociales</option>
                                <option value="email" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'email'); ?>>Email Marketing</option>
                                <option value="llamada" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'llamada'); ?>>Llamada</option>
                                <option value="feria" <?php selected(isset($metadata->fuente) ? $metadata->fuente : '', 'feria'); ?>>Feria o Evento</option>
                            </select>
                        </div>
                        
                        <!-- Campaña -->
                        <div class="metadata-form-group">
                            <label for="campana">Campaña</label>
                            <select id="campana" name="campana">
                                <option value="">Seleccionar campaña</option>
                                <option value="organico" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'organico'); ?>>Orgánico</option>
                                <option value="google_ads" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'google_ads'); ?>>Google Ads</option>
                                <option value="facebook_ads" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'facebook_ads'); ?>>Facebook Ads</option>
                                <option value="instagram" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'instagram'); ?>>Instagram</option>
                                <option value="promo_verano" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'promo_verano'); ?>>Promo Verano</option>
                                <option value="promo_navidad" <?php selected(isset($metadata->campana) ? $metadata->campana : '', 'promo_navidad'); ?>>Promo Navidad</option>
                            </select>
                        </div>
                        
                        <!-- Ubicación -->
                        <div class="metadata-form-group">
                            <label for="ubicacion">Ubicación del Cliente</label>
                            <select id="ubicacion" name="ubicacion">
                                <option value="">Seleccionar ubicación</option>
                                <?php foreach ($ubicaciones as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected(isset($metadata->ubicacion) ? $metadata->ubicacion : '', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Etiquetas -->
                        <div class="metadata-form-group">
                            <label for="etiquetas">Etiquetas</label>
                            <select id="etiquetas" name="etiquetas[]" multiple="multiple">
                                <?php if (!empty($tags)): ?>
                                    <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo esc_attr($tag); ?>" selected><?php echo esc_html($tag); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <option value="vip">VIP</option>
                                <option value="recurrente">Cliente Recurrente</option>
                                <option value="requiere_atencion">Requiere Atención</option>
                                <option value="presupuesto_limitado">Presupuesto Limitado</option>
                                <option value="decision_rapida">Decisión Rápida</option>
                            </select>
                            <small>Puedes seleccionar etiquetas existentes o crear nuevas escribiendo y presionando Enter.</small>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <!-- Tab: Estado de Conversión -->
            <div id="tab-conversion" class="metadata-tab-pane">
                <div class="metadata-form-section conversion">
                    <h3 class="metadata-form-section-title">Estado de Conversión</h3>
                    <div class="metadata-form-grid">
                        <!-- Estado de propuesta -->
                        <div class="metadata-form-group">
                            <label for="estado_propuesta">Estado de Propuesta</label>
                            <select id="estado_propuesta" name="estado_propuesta">
                                <option value="">Seleccionar estado</option>
                                <option value="pendiente" <?php selected(isset($metadata->estado_propuesta) ? $metadata->estado_propuesta : '', 'pendiente'); ?>>Pendiente</option>
                                <option value="enviada" <?php selected(isset($metadata->estado_propuesta) ? $metadata->estado_propuesta : '', 'enviada'); ?>>Enviada</option>
                                <option value="en_revision" <?php selected(isset($metadata->estado_propuesta) ? $metadata->estado_propuesta : '', 'en_revision'); ?>>En revisión</option>
                                <option value="aceptada" <?php selected(isset($metadata->estado_propuesta) ? $metadata->estado_propuesta : '', 'aceptada'); ?>>Aceptada</option>
                                <option value="rechazada" <?php selected(isset($metadata->estado_propuesta) ? $metadata->estado_propuesta : '', 'rechazada'); ?>>Rechazada</option>
                            </select>
                        </div>
                        
                        <!-- Rango de cotización -->
                        <div class="metadata-form-group">
                            <label for="rango_cotizacion">Rango de Cotización</label>
                            <select id="rango_cotizacion" name="rango_cotizacion">
                                <option value="">Seleccionar rango</option>
                                <option value="menos_10k" <?php selected(isset($metadata->rango_cotizacion) ? $metadata->rango_cotizacion : '', 'menos_10k'); ?>>Menos de $10,000</option>
                                <option value="10k_30k" <?php selected(isset($metadata->rango_cotizacion) ? $metadata->rango_cotizacion : '', '10k_30k'); ?>>$10,000 - $30,000</option>
                                <option value="30k_50k" <?php selected(isset($metadata->rango_cotizacion) ? $metadata->rango_cotizacion : '', '30k_50k'); ?>>$30,000 - $50,000</option>
                                <option value="50k_100k" <?php selected(isset($metadata->rango_cotizacion) ? $metadata->rango_cotizacion : '', '50k_100k'); ?>>$50,000 - $100,000</option>
                                <option value="mas_100k" <?php selected(isset($metadata->rango_cotizacion) ? $metadata->rango_cotizacion : '', 'mas_100k'); ?>>Más de $100,000</option>
                            </select>
                        </div>
                        
                        <!-- Industria -->
                        <div class="metadata-form-group">
                            <label for="industria">Industria del Cliente</label>
                            <select id="industria" name="industria">
                                <option value="">Seleccionar industria</option>
                                <?php foreach ($industrias as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected(isset($metadata->industria) ? $metadata->industria : '', $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Temporada -->
                        <div class="metadata-form-group">
                            <label for="temporada">Temporada</label>
                            <select id="temporada" name="temporada">
                                <option value="">Seleccionar temporada</option>
                                <option value="alta" <?php selected(isset($metadata->temporada) ? $metadata->temporada : '', 'alta'); ?>>Temporada alta</option>
                                <option value="baja" <?php selected(isset($metadata->temporada) ? $metadata->temporada : '', 'baja'); ?>>Temporada baja</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="metadata-form-actions">
                <button type="button" id="save_lead_metadata" class="metadata-save-btn">Guardar Metadatos</button>
            </div>
        </form>
    </div>
</div>