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

<div class="leads-wrapper">
	
	
    <!-- Botón Agregar Lead (Único) -->
    <div class="leads-actions">
        <button id="add_lead_btn" class="button button-primary">
            <span class="dashicons dashicons-plus-alt"></span>
            Agregar Nuevo Lead
        </button>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Selector de vista unificado -->
    <div class="view-selector">
        <button type="button" data-view="table" class="view-btn">
            <span class="dashicons dashicons-list-view"></span>
            Tabla
        </button>
        <button type="button" data-view="cards" class="view-btn">
            <span class="dashicons dashicons-grid-view"></span>
            Tarjetas
        </button>
        <button type="button" data-view="pipeline" class="view-btn">
            <span class="dashicons dashicons-kanban"></span>
            Pipeline
        </button>
    </div>
    
    <!-- Vista tabla -->
    <div class="leads-table-container view-container" data-view="table">
        <table class="leads-table">
            <thead>
                <tr>
                    <th data-sort="fecha_solicitud">Solicitud Recibida</th>
                    <th data-sort="nombre">Nombre Completo</th>
                    <th data-sort="tipo_evento">Tipo de Evento</th>
                    <th data-sort="fecha_evento">Fecha de Evento</th>
                    <th data-sort="status">Status</th>
                    <th>Celular</th>
                    <th>Correo</th>
                    <th>Servicio de Interés</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <!-- Los datos se cargarán dinámicamente -->
            </tbody>
        </table>
    </div>
    
    <!-- Vista tarjetas -->
    <div class="leads-cards-container view-container" data-view="cards">
        <div class="leads-cards">
            <!-- Las tarjetas se cargarán dinámicamente -->
        </div>
    </div>
    
    <!-- Vista pipeline -->
    <div class="leads-pipeline-container view-container" data-view="pipeline">
        <div class="pipeline-wrapper">
            <div class="pipeline-scrollable">
              <div class="pipeline-columns">
    <?php
    // Obtener SOLO estados activos (sin sin-evento ni otros)
    $status_options = LTB_Leads_Status_Utils::get_active_status_options();
    
    // Generar columna para cada estado
    foreach ($status_options as $status_value => $status_label) :
        // Convertir el valor a un ID válido para HTML
        $column_id = sanitize_html_class($status_value) . '-cards';
    ?>
    <div class="pipeline-column" data-status="<?php echo esc_attr($status_value); ?>">
        <div class="pipeline-column-header">
            <h3><?php echo esc_html($status_label); ?></h3>
            <span class="lead-count">0</span>
        </div>
        <div class="pipeline-cards" id="<?php echo esc_attr($column_id); ?>">
            <!-- Las tarjetas se cargarán dinámicamente -->
        </div>
    </div>
    <?php endforeach; ?>
</div>
            </div>
        </div>
    </div>
    
    <!-- Paginación (para vistas tabla y tarjetas) -->
    <div class="leads-pagination">
        <!-- La paginación se cargará dinámicamente -->
    </div>
    
    <!-- Selector de elementos por página -->
    <div class="per-page-selector">
        <label for="per_page_select">Mostrar:</label>
        <select id="per_page_select" class="per-page-select">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <span>por página</span>
    </div>
    
    <!-- Modal para Agregar Lead -->
    <div id="lead_add_modal" class="modal-container">
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Agregar Nuevo Lead</h2>
                    <button class="modal-close">&times;</button>
                </div>
                
                <!-- Contenido del modal (mantiene todo el contenido original) -->
                <div class="modal-tabs">
                    <button id="lead_only_toggle" class="tab-button active">Datos del Lead</button>
                    <button id="event_toggle" class="tab-button">+ Incluir Evento</button>
                </div>
                
                <div class="modal-body">
                    <!-- Formulario de Lead -->
                    <form id="lead_add_form" class="form-section">
                        <div class="form-group">
                            <label for="lead_razon_social">Razón Social:</label>
                            <input type="text" id="lead_razon_social" name="lead_razon_social">
                        </div>
                        <div class="form-group required">
                            <label for="lead_nombre">Nombre:</label>
                            <input type="text" id="lead_nombre" name="lead_nombre" required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="lead_apellido">Apellido:</label>
                            <input type="text" id="lead_apellido" name="lead_apellido" required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="lead_celular">Teléfono:</label>
                            <input type="tel" id="lead_celular" name="lead_celular" required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="lead_e_mail">Email:</label>
                            <input type="email" id="lead_e_mail" name="lead_e_mail" required>
                        </div>
                    </form>
                    
                    <!-- Formulario de Evento (inicialmente oculto) -->
                    <form id="event_add_form" class="form-section" style="display:none;">
                        <div class="form-group required">
                            <label for="evento_fecha">Fecha de Evento:</label>
                            <input type="text" id="evento_fecha" name="fecha_de_evento" class="datepicker-input" readonly required>
                        </div>
                        
                        <div class="form-group required">
                            <label for="evento_tipo">Tipo de Evento:</label>
                            <select id="evento_tipo" name="tipo_de_evento" required>
                                <option value="">Seleccionar...</option>
                                <option value="Bodas">Bodas</option>
                                <option value="XV años">XV años</option>
                                <option value="Empresarial">Empresarial</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="evento_asistentes">Cantidad de Invitados:</label>
                            <input type="number" id="evento_asistentes" name="evento_asistentes" min="1">
                        </div>
                        
                        <div class="form-group">
    <label for="evento_status">Status:</label>
    <select id="evento_status" name="evento_status">
        <?php
        // Solo mostrar los estados activos (sin "sin-evento" ni "otros")
        $status_options = LTB_Leads_Status_Utils::get_status_options();
        foreach ($status_options as $status_value => $status_label) :
        ?>
            <option value="<?php echo esc_attr($status_value); ?>"><?php echo esc_html($status_label); ?></option>
        <?php endforeach; ?>
    </select>
</div>
                        
                        <div class="form-group">
                            <label for="evento_direccion">Dirección:</label>
                            <input type="text" id="evento_direccion" name="evento_direccion">
                        </div>
                        
                        <div class="form-group">
                            <label for="evento_servicio">Servicio de Interés:</label>
                            <input type="text" id="evento_servicio_search" class="service-search" placeholder="Buscar servicio...">
                            <input type="hidden" id="evento_servicio" name="evento_servicio_de_interes">
                        </div>
                        
                        <div class="form-group">
                            <label for="evento_comentarios">Comentarios:</label>
                            <textarea id="evento_comentarios" name="evento_comentarios" rows="4"></textarea>
                        </div>
                        
                        <input type="hidden" id="evento_ubicacion" name="evento_ubicacion">
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button modal-close-btn">Cancelar</button>
                    <button type="button" id="submit_lead_only" class="button button-primary">Guardar Lead</button>
                    <button type="button" id="submit_full_form" class="button button-primary" style="display:none;">Guardar Lead y Evento</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Actualizar Status -->
    <div id="status_update_modal" class="modal-container">
        <div class="modal-backdrop"></div>
        <div class="modal-wrapper">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Actualizar Status del Evento</h2>
                    <button class="modal-close">&times;</button>
                </div>
                
                <div class="modal-body">
                    <form id="status_update_form" class="form-section">
                        <input type="hidden" id="update_evento_id" name="evento_id">
                        <input type="hidden" id="update_lead_id" name="lead_id">
                        
                        <div class="form-group">
    <label for="update_evento_status">Nuevo Status:</label>
    <select id="update_evento_status" name="evento_status">
        <?php
        // Solo mostrar los estados activos (sin "sin-evento" ni "otros")
        $status_options = LTB_Leads_Status_Utils::get_status_options();
        foreach ($status_options as $status_value => $status_label) :
        ?>
            <option value="<?php echo esc_attr($status_value); ?>"><?php echo esc_html($status_label); ?></option>
        <?php endforeach; ?>
    </select>
</div>
                        
                        <div class="form-group">
                            <label for="update_comentarios">Comentarios:</label>
                            <textarea id="update_comentarios" name="comentarios" rows="4" placeholder="Añadir comentarios sobre este cambio..."></textarea>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button modal-close-btn">Cancelar</button>
                    <button type="button" id="submit_status_update" class="button button-primary">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>
</div>