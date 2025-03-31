<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Followup {
    private $wpdb;
    private $tabla_seguimientos;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tabla_seguimientos = $wpdb->prefix . 'jet_cct_crm';
        $this->init_hooks();
    }

    private function init_hooks() {
        add_shortcode('mostrar_seguimientos', array($this, 'render_seguimientos'));
    }

    public function render_seguimientos($atts) {
        // Obtener el ID del lead de la URL
        $url_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path_parts = explode('/', trim($url_path, '/'));
        $lead_slug = end($path_parts);
        
        // Extraer ID del slug
        preg_match('/-(\d+)$/', $lead_slug, $matches);
        if (empty($matches[1])) {
            return '<div class="no-seguimientos">Lead no encontrado</div>';
        }

        $lead_id = $matches[1];
        
        // Consultar seguimientos
        $seguimientos = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tabla_seguimientos} 
            WHERE parent_id = %d 
            AND cct_status = 'publish'
            ORDER BY _ID DESC",
            $lead_id
        ));

        if (empty($seguimientos)) {
            return '<div class="no-seguimientos">No hay seguimientos registrados</div>';
        }

        // Obtener la estructura de campos del primer seguimiento
        $primer_seguimiento = maybe_unserialize($seguimientos[0]->seguimiento);
        if (!is_array($primer_seguimiento) || empty($primer_seguimiento)) {
            return '<div class="no-seguimientos">Error al procesar los seguimientos</div>';
        }

        // Obtener los campos del primer registro
        $campos = array_keys(reset($primer_seguimiento));

        // Iniciar el output
        $output = '<div class="seguimientos-wrapper">';
        $output .= '<div class="seguimientos-container">';

        foreach ($seguimientos as $seguimiento) {
            $datos = maybe_unserialize($seguimiento->seguimiento);
            
            if (!is_array($datos)) continue;

            foreach ($datos as $entrada) {
                if (!is_array($entrada)) continue;
                
                $output .= '<div class="seguimiento-item">';
                $output .= '<div class="seguimiento-grid">';
                
                foreach ($campos as $campo) {
                    $valor = isset($entrada[$campo]) ? $entrada[$campo] : '';
                    
                    // Formatear fecha
                    if ($campo === 'fecha' && $valor) {
                        $fecha = new DateTime($valor);
                        $valor = $fecha->format('d/m/Y H:i');
                    }
                    
                    // Formatear actividad
                    if ($campo === 'actividad') {
                        $valor = ucfirst(str_replace('_', ' ', $valor));
                    }
                    
                    $output .= sprintf(
                        '<div class="seguimiento-campo %s">
                            <div class="campo-label">%s</div>
                            <div class="campo-valor">%s</div>
                        </div>',
                        esc_attr($campo),
                        esc_html($this->formatear_nombre_campo($campo)),
                        esc_html($valor)
                    );
                }
                
                $output .= '</div></div>';
            }
        }

        $output .= '</div></div>';

        // Agregar estilos
        $output .= $this->get_seguimientos_styles(count($campos));

        return $output;
    }

    private function formatear_nombre_campo($campo) {
        return ucfirst(str_replace(['-', '_'], ' ', $campo));
    }

    private function get_seguimientos_styles($num_campos) {
        $grid_columns = implode(' ', array_fill(0, $num_campos, '1fr'));
        
        return '
        <style>
            .seguimientos-wrapper {
                margin: 20px 0;
                width: 100%;
            }
            
            .seguimientos-container {
                display: flex;
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }
            
            .seguimiento-item {
                width: 100%;
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                padding: 15px;
                border-radius: 4px;
                box-sizing: border-box;
            }
            
            .seguimiento-grid {
                display: grid;
                grid-template-columns: ' . $grid_columns . ';
                gap: 10px;
                align-items: start;
            }
            
            .seguimiento-campo {
                min-width: 0;
                padding: 0 10px;
            }
            
            .campo-label {
                font-weight: bold;
                color: #666;
                margin-bottom: 4px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .campo-valor {
                color: #333;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .no-seguimientos {
                padding: 15px;
                background: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 4px;
                text-align: center;
                width: 100%;
            }
            
            @media (max-width: 768px) {
                .seguimiento-grid {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
                
                .seguimiento-campo {
                    padding: 0 0 8px 0;
                    border-bottom: 1px solid #eee;
                }
                
                .seguimiento-campo:last-child {
                    border-bottom: none;
                    padding-bottom: 0;
                }
            }
        </style>';
    }
}