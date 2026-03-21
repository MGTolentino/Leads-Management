<?php
/**
 * Script para normalizar tipos de evento existentes en la base de datos
 * 
 * Este script limpia y estandariza los tipos de evento inconsistentes
 * que pueden haber sido creados antes de la implementación de la normalización
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Event_Type_Normalizer {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Ejecuta la normalización de tipos de evento
     * 
     * @return array Estadísticas de la normalización
     */
    public function normalize_event_types() {
        $stats = array(
            'total_events' => 0,
            'normalized_events' => 0,
            'changes' => array()
        );
        
        // Obtener todos los eventos con tipos
        $events = $this->wpdb->get_results("
            SELECT _ID, tipo_de_evento 
            FROM {$this->wpdb->prefix}jet_cct_eventos 
            WHERE tipo_de_evento IS NOT NULL 
            AND tipo_de_evento != ''
        ");
        
        $stats['total_events'] = count($events);
        
        foreach ($events as $event) {
            $original_type = $event->tipo_de_evento;
            $normalized_type = LTB_Leads_Status_Utils::normalize_event_type($original_type);
            
            // Solo actualizar si hay cambio
            if ($original_type !== $normalized_type) {
                $result = $this->wpdb->update(
                    $this->wpdb->prefix . 'jet_cct_eventos',
                    array('tipo_de_evento' => $normalized_type),
                    array('_ID' => $event->_ID),
                    array('%s'),
                    array('%d')
                );
                
                if ($result !== false) {
                    $stats['normalized_events']++;
                    $stats['changes'][] = array(
                        'event_id' => $event->_ID,
                        'from' => $original_type,
                        'to' => $normalized_type
                    );
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Obtiene estadísticas de tipos de evento antes de normalizar
     * 
     * @return array Estadísticas de tipos de evento
     */
    public function get_event_type_stats() {
        $types = $this->wpdb->get_results("
            SELECT tipo_de_evento, COUNT(*) as count 
            FROM {$this->wpdb->prefix}jet_cct_eventos 
            WHERE tipo_de_evento IS NOT NULL 
            AND tipo_de_evento != ''
            GROUP BY tipo_de_evento
            ORDER BY count DESC
        ");
        
        $stats = array(
            'unique_types' => count($types),
            'types' => array()
        );
        
        foreach ($types as $type) {
            $normalized = LTB_Leads_Status_Utils::normalize_event_type($type->tipo_de_evento);
            $stats['types'][] = array(
                'original' => $type->tipo_de_evento,
                'normalized' => $normalized,
                'count' => $type->count,
                'needs_change' => $type->tipo_de_evento !== $normalized
            );
        }
        
        return $stats;
    }
    
    /**
     * Genera un reporte de la normalización
     * 
     * @param array $stats Estadísticas de la normalización
     * @return string Reporte formateado
     */
    public function generate_report($stats) {
        $report = "=== REPORTE DE NORMALIZACIÓN DE TIPOS DE EVENTO ===\n\n";
        $report .= "Total de eventos procesados: " . $stats['total_events'] . "\n";
        $report .= "Eventos normalizados: " . $stats['normalized_events'] . "\n";
        $report .= "Eventos sin cambios: " . ($stats['total_events'] - $stats['normalized_events']) . "\n\n";
        
        if (!empty($stats['changes'])) {
            $report .= "CAMBIOS REALIZADOS:\n";
            $report .= str_repeat("-", 50) . "\n";
            
            foreach ($stats['changes'] as $change) {
                $report .= "Evento ID {$change['event_id']}: '{$change['from']}' → '{$change['to']}'\n";
            }
        } else {
            $report .= "No se realizaron cambios - todos los tipos ya estaban normalizados.\n";
        }
        
        return $report;
    }
}

// Función de utilidad para ejecutar desde WordPress admin
function ltb_normalize_event_types_manual() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para ejecutar esta acción.');
    }
    
    $normalizer = new LTB_Event_Type_Normalizer();
    
    // Mostrar estadísticas antes
    echo "<h2>Estadísticas ANTES de la normalización:</h2>";
    $pre_stats = $normalizer->get_event_type_stats();
    echo "<p>Tipos únicos encontrados: " . $pre_stats['unique_types'] . "</p>";
    echo "<table border='1'>";
    echo "<tr><th>Tipo Original</th><th>Será Normalizado A</th><th>Cantidad</th><th>Necesita Cambio</th></tr>";
    foreach ($pre_stats['types'] as $type) {
        $needs_change = $type['needs_change'] ? 'SÍ' : 'NO';
        echo "<tr>";
        echo "<td>" . esc_html($type['original']) . "</td>";
        echo "<td>" . esc_html($type['normalized']) . "</td>";
        echo "<td>" . $type['count'] . "</td>";
        echo "<td>" . $needs_change . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Ejecutar normalización
    echo "<h2>Ejecutando normalización...</h2>";
    $stats = $normalizer->normalize_event_types();
    
    // Mostrar reporte
    echo "<h2>Resultado de la normalización:</h2>";
    echo "<pre>" . esc_html($normalizer->generate_report($stats)) . "</pre>";
    
    // Mostrar estadísticas después
    echo "<h2>Estadísticas DESPUÉS de la normalización:</h2>";
    $post_stats = $normalizer->get_event_type_stats();
    echo "<p>Tipos únicos finales: " . $post_stats['unique_types'] . "</p>";
    echo "<table border='1'>";
    echo "<tr><th>Tipo</th><th>Cantidad</th></tr>";
    foreach ($post_stats['types'] as $type) {
        echo "<tr>";
        echo "<td>" . esc_html($type['original']) . "</td>";
        echo "<td>" . $type['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}