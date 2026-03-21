<?php
/**
 * Sistema de Cache Avanzado con Estrategias de Invalidación
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Cache {
    
    private static $instance = null;
    private $cache_group = 'ltb_leads';
    private $cache_keys = array();
    private $ttl_default = 300; // 5 minutos
    private $ttl_long = 3600; // 1 hora
    private $ttl_short = 60; // 1 minuto
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Invalidar cache cuando se modifica un lead
        add_action('ltb_lead_created', array($this, 'invalidate_lead_cache'));
        add_action('ltb_lead_updated', array($this, 'invalidate_lead_cache'));
        add_action('ltb_lead_deleted', array($this, 'invalidate_lead_cache'));
        add_action('ltb_event_created', array($this, 'invalidate_event_cache'));
        add_action('ltb_event_updated', array($this, 'invalidate_event_cache'));
        
        // Limpiar cache periódicamente
        add_action('ltb_hourly_cache_cleanup', array($this, 'cleanup_expired'));
        
        // Programar limpieza si no existe
        if (!wp_next_scheduled('ltb_hourly_cache_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'ltb_hourly_cache_cleanup');
        }
    }
    
    /**
     * Obtener valor del cache
     */
    public function get($key, $group = null) {
        $group = $group ?: $this->cache_group;
        $value = wp_cache_get($key, $group);
        
        if (false !== $value) {
            $this->track_hit($key, true);
            return $value;
        }
        
        $this->track_hit($key, false);
        return false;
    }
    
    /**
     * Guardar en cache con TTL específico
     */
    public function set($key, $value, $ttl = null, $group = null) {
        $group = $group ?: $this->cache_group;
        $ttl = $ttl ?: $this->ttl_default;
        
        // Guardar metadatos del cache
        $this->cache_keys[$group][$key] = array(
            'expires' => time() + $ttl,
            'size' => strlen(serialize($value))
        );
        
        return wp_cache_set($key, $value, $group, $ttl);
    }
    
    /**
     * Eliminar del cache
     */
    public function delete($key, $group = null) {
        $group = $group ?: $this->cache_group;
        unset($this->cache_keys[$group][$key]);
        return wp_cache_delete($key, $group);
    }
    
    /**
     * Limpiar grupo completo
     */
    public function flush_group($group = null) {
        $group = $group ?: $this->cache_group;
        
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($group);
        } else {
            // Fallback para sistemas sin soporte de flush_group
            if (isset($this->cache_keys[$group])) {
                foreach ($this->cache_keys[$group] as $key => $meta) {
                    wp_cache_delete($key, $group);
                }
            }
        }
        
        unset($this->cache_keys[$group]);
    }
    
    /**
     * Remember pattern - Obtener o generar y guardar
     */
    public function remember($key, $callback, $ttl = null, $group = null) {
        $value = $this->get($key, $group);
        
        if (false === $value) {
            $value = call_user_func($callback);
            if (null !== $value) {
                $this->set($key, $value, $ttl, $group);
            }
        }
        
        return $value;
    }
    
    /**
     * Invalidar cache de leads
     */
    public function invalidate_lead_cache($lead_id = null) {
        // Invalidar cache específico del lead
        if ($lead_id) {
            $this->delete('lead_' . $lead_id);
            $this->delete('lead_events_' . $lead_id);
        }
        
        // Invalidar caches de listados
        $this->invalidate_pattern('leads_list_*');
        $this->invalidate_pattern('leads_count_*');
        $this->invalidate_pattern('pipeline_*');
    }
    
    /**
     * Invalidar cache de eventos
     */
    public function invalidate_event_cache($event_id = null) {
        if ($event_id) {
            $this->delete('event_' . $event_id);
        }
        
        $this->invalidate_pattern('events_*');
        $this->invalidate_pattern('pipeline_*');
    }
    
    /**
     * Invalidar por patrón
     */
    public function invalidate_pattern($pattern, $group = null) {
        $group = $group ?: $this->cache_group;
        
        if (!isset($this->cache_keys[$group])) {
            return;
        }
        
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/^' . $pattern . '$/';
        
        foreach ($this->cache_keys[$group] as $key => $meta) {
            if (preg_match($pattern, $key)) {
                $this->delete($key, $group);
            }
        }
    }
    
    /**
     * Limpiar cache expirado
     */
    public function cleanup_expired() {
        $now = time();
        
        foreach ($this->cache_keys as $group => $keys) {
            foreach ($keys as $key => $meta) {
                if (isset($meta['expires']) && $meta['expires'] < $now) {
                    $this->delete($key, $group);
                }
            }
        }
    }
    
    /**
     * Obtener estadísticas del cache
     */
    public function get_stats() {
        $stats = array(
            'groups' => count($this->cache_keys),
            'total_keys' => 0,
            'total_size' => 0,
            'hit_rate' => $this->calculate_hit_rate()
        );
        
        foreach ($this->cache_keys as $group => $keys) {
            $stats['total_keys'] += count($keys);
            foreach ($keys as $meta) {
                $stats['total_size'] += $meta['size'] ?? 0;
            }
        }
        
        $stats['total_size_mb'] = round($stats['total_size'] / 1048576, 2);
        
        return $stats;
    }
    
    /**
     * Rastrear hits y misses para estadísticas
     */
    private function track_hit($key, $is_hit) {
        static $hits = 0;
        static $total = 0;
        
        $total++;
        if ($is_hit) {
            $hits++;
        }
        
        // Guardar estadísticas cada 100 requests
        if ($total % 100 === 0) {
            update_option('ltb_cache_hit_rate', $hits / $total, false);
        }
    }
    
    /**
     * Calcular tasa de aciertos
     */
    private function calculate_hit_rate() {
        return get_option('ltb_cache_hit_rate', 0) * 100;
    }
    
    /**
     * Precargar cache común
     */
    public function warm_cache() {
        // Precargar estados
        $this->remember('status_options', function() {
            return LTB_Leads_Status_Utils::get_status_options();
        }, $this->ttl_long);
        
        // Precargar tipos de evento
        $this->remember('event_types', function() {
            return LTB_Leads_Status_Utils::get_event_types();
        }, $this->ttl_long);
        
        // Precargar conteos básicos
        if (class_exists('LTB_Leads_Repository')) {
            $repository = new LTB_Leads_Repository();
            
            $this->remember('total_leads_count', function() use ($repository) {
                return $repository->count();
            }, $this->ttl_short);
            
            // Precargar leads recientes
            $this->remember('recent_leads_10', function() use ($repository) {
                return $repository->getRecentLeads(10);
            }, $this->ttl_short);
        }
    }
}