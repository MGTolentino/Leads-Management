<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar permisos
if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
    wp_redirect(home_url());
    exit;
}
?>

<div class="pipeline-container">
    <!-- Barra de herramientas superior -->
    <div class="pipeline-toolbar">
        <button id="add_lead_btn" class="btn-primary">+ Agregar Lead</button>
        
        <!-- Filtros estilo Excel en línea -->
        <div class="inline-filters">
            <input type="text" id="quick_search" placeholder="Buscar..." class="filter-input">
            
            <select id="period_filter" class="filter-select">
                <option value="">Todos los períodos</option>
                <option value="today">Hoy</option>
                <option value="this_week">Esta semana</option>
                <option value="this_month">Este mes</option>
                <option value="this_year">Este año</option>
                <option value="specific_month">Mes/Año específico</option>
                <option value="custom">Rango personalizado</option>
            </select>
            
            <div id="custom_date_range" style="display:none;">
                <input type="text" id="date_range" class="filter-input" title="Rango de fechas" placeholder="Seleccionar rango de fechas">
            </div>
            
            <div id="specific_month_range" style="display:none;">
                <select id="mes_evento_basic" class="filter-select">
                    <option value="">Seleccionar mes</option>
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
                
                <select id="anio_evento" class="filter-select">
                    <option value="">Seleccionar año</option>
                    <?php
                    $current_year = date('Y');
                    for ($year = 2000; $year <= ($current_year + 5); $year++) {
                        echo '<option value="' . $year . '">' . $year . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <select id="event_type_filter" class="filter-select">
                <option value="">Todos los tipos</option>
                <!-- Los tipos se cargarán dinámicamente -->
            </select>
            
            <select id="priority_filter" class="filter-select">
                <option value="">Todas las prioridades</option>
                <option value="alta">Alta</option>
                <option value="media">Media</option>
                <option value="baja">Baja</option>
            </select>
            
            <button id="apply_filters" class="btn-secondary">Filtrar</button>
            <button id="clear_filters" class="btn-text">Limpiar</button>
        </div>
        
        <div class="pipeline-stats">
            Total: <span id="total_leads">0</span>
        </div>
    </div>

    <!-- Vista Pipeline -->
    <div class="pipeline-board">
        <?php
        $status_options = LTB_Leads_Status_Utils::get_active_status_options();
        foreach ($status_options as $status_value => $status_label) : ?>
            <div class="pipeline-column" data-status="<?php echo esc_attr($status_value); ?>">
                <div class="column-header">
                    <h3><?php echo esc_html($status_label); ?></h3>
                    <span class="count">0</span>
                </div>
                <div class="column-content" id="<?php echo esc_attr($status_value); ?>-cards">
                    <!-- Las tarjetas se cargarán aquí -->
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal simplificado para agregar lead -->
    <div id="lead_modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Agregar Lead</h2>
                <button class="close-modal">&times;</button>
            </div>
            
            <form id="lead_form" class="simple-form">
                <!-- Información básica del lead -->
                <div class="form-row">
                    <div class="form-field">
                        <label>Nombre *</label>
                        <input type="text" name="lead_nombre" required>
                    </div>
                    <div class="form-field">
                        <label>Apellido *</label>
                        <input type="text" name="lead_apellido" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label>Teléfono *</label>
                        <input type="tel" name="lead_celular" required>
                    </div>
                    <div class="form-field">
                        <label>Email *</label>
                        <input type="email" name="lead_e_mail" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-field">
                        <label>Razón Social</label>
                        <input type="text" name="lead_razon_social">
                    </div>
                </div>
                
                <!-- Información del evento (opcional) -->
                <div class="form-section">
                    <label class="checkbox-label">
                        <input type="checkbox" id="include_event"> Incluir información de evento
                    </label>
                </div>
                
                <div id="event_fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-field">
                            <label>Fecha del Evento</label>
                            <input type="date" id="evento_fecha" name="fecha_de_evento">
                        </div>
                        <div class="form-field">
                            <label>Tipo de Evento</label>
                            <select id="evento_tipo" name="tipo_de_evento">
                                <option value="">Seleccionar...</option>
                                <option value="Bodas">Bodas</option>
                                <option value="XV años">XV años</option>
                                <option value="Empresarial">Empresarial</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label>Número de Invitados</label>
                            <input type="number" id="evento_asistentes" name="evento_asistentes" min="1">
                        </div>
                        <div class="form-field">
                            <label>Status</label>
                            <select id="evento_status" name="evento_status">
                                <?php foreach ($status_options as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label>Dirección del Evento</label>
                            <input type="text" id="evento_direccion" name="direccion_evento" placeholder="Dirección completa del evento">
                        </div>
                        <div class="form-field">
                            <label>Servicio de Interés</label>
                            <input type="text" id="evento_servicio_search" placeholder="Buscar servicio...">
                            <input type="hidden" id="evento_servicio" name="evento_servicio_de_interes">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label>Comentarios Adicionales</label>
                            <textarea id="evento_comentarios" name="comentarios_evento" rows="3" placeholder="Notas o comentarios sobre el evento..."></textarea>
                        </div>
                    </div>
                    
                    <input type="hidden" id="evento_ubicacion" name="evento_ubicacion">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-text close-modal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>