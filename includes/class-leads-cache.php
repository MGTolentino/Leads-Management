<?php
/**
 * Cache management for Leads Management
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Cache {
    
    /**
     * Cache group name
     */
    const CACHE_GROUP = 'ltb_leads';
    
    /**
     * Default expiration time (1 hour)
     */
    const DEFAULT_EXPIRATION = 3600;
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @return mixed Cached value or false
     */
    public function get($key) {
        return wp_cache_get($key, self::CACHE_GROUP);
    }
    
    /**
     * Set cache value
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Expiration time in seconds
     * @return bool Success
     */
    public function set($key, $value, $expiration = null) {
        if ($expiration === null) {
            $expiration = self::DEFAULT_EXPIRATION;
        }
        
        return wp_cache_set($key, $value, self::CACHE_GROUP, $expiration);
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key) {
        return wp_cache_delete($key, self::CACHE_GROUP);
    }
    
    /**
     * Delete all cached values in a group
     * 
     * @param string $group Group name
     * @return bool Success
     */
    public function delete_group($group) {
        // WordPress doesn't have a native way to delete by group
        // This would require an object cache plugin like Redis
        // For now, we'll increment the cache version to invalidate
        $version_key = 'cache_version_' . $group;
        $version = $this->get($version_key);
        
        if ($version === false) {
            $version = 1;
        } else {
            $version++;
        }
        
        return $this->set($version_key, $version, 86400); // 24 hours
    }
    
    /**
     * Flush all cache
     * 
     * @return bool Success
     */
    public function flush() {
        return wp_cache_flush();
    }
    
    /**
     * Remember a value in cache
     * 
     * @param string $key Cache key
     * @param callable $callback Function to get value if not cached
     * @param int $expiration Expiration time
     * @return mixed
     */
    public function remember($key, $callback, $expiration = null) {
        $value = $this->get($key);
        
        if ($value === false) {
            $value = call_user_func($callback);
            $this->set($key, $value, $expiration);
        }
        
        return $value;
    }
    
    /**
     * Get multiple cached values
     * 
     * @param array $keys Array of cache keys
     * @return array Array of values
     */
    public function get_multiple(array $keys) {
        $values = [];
        
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }
        
        return $values;
    }
    
    /**
     * Set multiple cache values
     * 
     * @param array $items Array of key => value pairs
     * @param int $expiration Expiration time
     * @return bool Success
     */
    public function set_multiple(array $items, $expiration = null) {
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Generate cache key from parameters
     * 
     * @param string $prefix Key prefix
     * @param mixed $params Parameters to include in key
     * @return string Cache key
     */
    public function make_key($prefix, $params = null) {
        if ($params === null) {
            return $prefix;
        }
        
        if (is_array($params) || is_object($params)) {
            return $prefix . '_' . md5(serialize($params));
        }
        
        return $prefix . '_' . $params;
    }
}