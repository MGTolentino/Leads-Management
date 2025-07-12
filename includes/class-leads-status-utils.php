<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Status_Utils {
	
	
	/**
 * Obtiene solo los estados activos, sin categorías especiales
 * 
 * @return array Arreglo de estados con value => key
 */
public static function get_active_status_options() {
    $all_options = self::get_status_options(false, false);
    return $all_options;
}
    
    /**
     * Obtiene las opciones de estado disponibles desde la configuración de JetEngine
     * 
     * @param bool $include_sin_evento Incluir la categoría "sin-evento"
     * @param bool $include_otros Incluir la categoría "otros" para estados desconocidos
     * @return array Arreglo de estados con value => key
     */
    public static function get_status_options($include_sin_evento = false, $include_otros = false) {
        // Cache para mejorar rendimiento
        static $cached_options = null;
        
        if ($cached_options !== null) {
            $options = $cached_options;
        } else {
            global $wpdb;
            $meta_fields_serialized = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_fields FROM {$wpdb->prefix}jet_post_types WHERE id = %d AND slug = %s",
                71, 'eventos'
            ));
            
            $options = array();
            
            if ($meta_fields_serialized) {
                $meta_fields = maybe_unserialize($meta_fields_serialized);
                
                // Buscar el campo evento_status
                foreach ($meta_fields as $field) {
                    if (isset($field['name']) && $field['name'] === 'evento_status') {
                        if (isset($field['options']) && is_array($field['options'])) {
                            foreach ($field['options'] as $option) {
                                if (isset($option['value']) && isset($option['key'])) {
                                    $options[$option['value']] = $option['key'];
                                }
                            }
                        }
                        break;
                    }
                }
            }
            
            // Always use predefined options to ensure consistency
            // This fixes the "Con presupuesto" vs "Con Cotización" issue
            $options = array(
                'nuevo' => 'Nuevo',
                'con-presupuesto' => 'Con Cotización',
                'por-cerrar' => 'Por cerrar',
                'con-contrato' => 'Con contrato',
                'perdido' => 'Perdido'
            );
            
            // Optionally merge with database options if needed
            // if (empty($options)) {
            //     // fallback code here
            // }
            
            $cached_options = $options;
        }
        
        // Agregar categorías adicionales si se solicitan
        $result = array();
        
        if ($include_sin_evento) {
            $result['sin-evento'] = 'Sin Evento';
        }
        
        // Agregar estados activos
        $result = array_merge($result, $options);
        
        // Agregar categoría otros si se solicita
        if ($include_otros) {
            $result['otros'] = 'Otros estados';
        }
        
        return $result;
    }
    
    /**
     * Determina la categoría adecuada para un estado
     * 
     * @param string $status Estado a categorizar
     * @return string Categoría a la que pertenece
     */
    public static function get_status_category($status) {
        $known_statuses = self::get_status_options();
        
        if (empty($status)) {
            return 'sin-evento';
        }
        
        if (isset($known_statuses[$status])) {
            return $status; // Es un estado conocido
        }
        
        // Estado desconocido, buscar correspondencia
        $mapping = self::get_legacy_status_mapping();
        if (isset($mapping[$status])) {
            return $mapping[$status];
        }
        
        return 'otros';
    }
    
    /**
     * Proporciona un mapeo de estados antiguos a nuevos
     * 
     * @return array Mapeo de estados antiguos a nuevos
     */
    public static function get_legacy_status_mapping() {
        return array(
            'nuevo-no-contactado' => 'nuevo',
            'contactado' => 'con-presupuesto',
            'contactado-interesado' => 'con-presupuesto',
            'cotizacion' => 'con-presupuesto',
            'reservado' => 'por-cerrar',
            'contratado' => 'con-contrato',
            'cerrado' => 'con-contrato',
            'no-contratado' => 'perdido',
            'cancelado' => 'perdido'
        );
    }
    
    /**
     * Obtiene el color CSS para un estado
     * 
     * @param string $status Valor del estado
     * @return string Clase CSS para el color
     */
    public static function get_status_color_class($status) {
        $category = self::get_status_category($status);
        
        $color_map = array(
            'sin-evento' => 'status-sin-evento',
            'nuevo' => 'status-nuevo',
            'con-presupuesto' => 'status-con-presupuesto',
            'por-cerrar' => 'status-por-cerrar',
            'con-contrato' => 'status-con-contrato',
            'perdido' => 'status-perdido',
            'otros' => 'status-otros'
        );
        
        return isset($color_map[$category]) ? $color_map[$category] : 'status-otros';
    }
    
    /**
     * Obtiene los tipos de evento estandarizados para ambos plugins
     * 
     * @return array Arreglo de tipos de evento con value => label
     */
    public static function get_event_types() {
        return array(
            'Bodas' => 'Bodas',
            'XV años' => 'XV años', 
            'Cumpleaños' => 'Cumpleaños',
            'Graduaciones' => 'Graduaciones',
            'Empresarial' => 'Empresarial',
            'Otros' => 'Otros'
        );
    }
    
    /**
     * Normaliza un tipo de evento para que sea consistente
     * 
     * @param string $event_type Tipo de evento a normalizar
     * @return string Tipo de evento normalizado
     */
    public static function normalize_event_type($event_type) {
        if (empty($event_type)) {
            return '';
        }
        
        // Limpiar espacios y convertir a formato correcto
        $event_type = trim($event_type);
        
        // Mapear variaciones a tipos estándar
        $mapping = array(
            'boda' => 'Bodas',
            'Boda' => 'Bodas',
            'BODA' => 'Bodas',
            'bodas' => 'Bodas',
            'BODAS' => 'Bodas',
            'otro' => 'Otros',
            'Otro' => 'Otros', 
            'OTRO' => 'Otros',
            'otros' => 'Otros',
            'OTROS' => 'Otros',
            'cumpleanos' => 'Cumpleaños',
            'Cumpleanos' => 'Cumpleaños',
            'CUMPLEANOS' => 'Cumpleaños',
            'xv anos' => 'XV años',
            'XV Anos' => 'XV años',
            'xv años' => 'XV años',
            'XV AÑOS' => 'XV años',
            '15 años' => 'XV años',
            'quince años' => 'XV años',
            'graduacion' => 'Graduaciones',
            'Graduacion' => 'Graduaciones',
            'GRADUACION' => 'Graduaciones',
            'graduaciones' => 'Graduaciones',
            'GRADUACIONES' => 'Graduaciones',
            'empresarial' => 'Empresarial',
            'EMPRESARIAL' => 'Empresarial',
            'corporativo' => 'Empresarial',
            'Corporativo' => 'Empresarial',
            'CORPORATIVO' => 'Empresarial',
            'social' => 'Otros',
            'Social' => 'Otros',
            'SOCIAL' => 'Otros'
        );
        
        return isset($mapping[$event_type]) ? $mapping[$event_type] : $event_type;
    }
}