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
                <option value="">Período</option>
                <option value="today">Hoy</option>
                <option value="this_week">Esta semana</option>
                <option value="this_month">Este mes</option>
                <option value="this_year">Este año</option>
                <option value="january">Enero</option>
                <option value="february">Febrero</option>
                <option value="march">Marzo</option>
                <option value="april">Abril</option>
                <option value="may">Mayo</option>
                <option value="june">Junio</option>
                <option value="july">Julio</option>
                <option value="august">Agosto</option>
                <option value="september">Septiembre</option>
                <option value="october">Octubre</option>
                <option value="november">Noviembre</option>
                <option value="december">Diciembre</option>
                <option value="custom">Rango personalizado</option>
            </select>
            
            <div id="custom_date_range" class="date-range-inputs" style="display:none;">
                <div class="date-input-wrapper">
                    <label class="date-input-label" for="date_from">Desde:</label>
                    <input type="date" id="date_from" class="filter-input datepicker-input">
                </div>
                <div class="date-input-wrapper">
                    <label class="date-input-label" for="date_to">Hasta:</label>
                    <input type="date" id="date_to" class="filter-input datepicker-input">
                </div>
            </div>
            
            <select id="event_type_filter" class="filter-select">
                <option value="">Tipo de evento</option>
                <!-- Se cargarán dinámicamente -->
            </select>
            
            <select id="priority_filter" class="filter-select">
                <option value="">Prioridad</option>
                <option value="alta">Alta</option>
                <option value="media">Media</option>
                <option value="baja">Baja</option>
            </select>
            
            <select id="value_filter" class="filter-select">
                <option value="">Valor potencial</option>
                <option value="menos_10k">Menos $10K</option>
                <option value="10k_30k">$10K - $30K</option>
                <option value="30k_50k">$30K - $50K</option>
                <option value="50k_mas">$50K+</option>
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
                            <input type="date" name="fecha_de_evento">
                        </div>
                        <div class="form-field">
                            <label>Tipo de Evento</label>
                            <select name="evento_tipo">
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
                            <input type="number" name="evento_asistentes" min="1">
                        </div>
                        <div class="form-field">
                            <label>Status</label>
                            <select name="evento_status">
                                <?php foreach ($status_options as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label>Dirección</label>
                            <input type="text" name="evento_direccion" placeholder="Dirección del evento">
                        </div>
                        <div class="form-field">
                            <label>Servicio de Interés</label>
                            <input type="text" id="evento_servicio_search" class="service-search" placeholder="Buscar servicio...">
                            <input type="hidden" id="evento_servicio" name="evento_servicio_de_interes">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label>Comentarios</label>
                            <textarea name="evento_comentarios" rows="3" placeholder="Comentarios adicionales"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-text close-modal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>