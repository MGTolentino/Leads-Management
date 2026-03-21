<?php
/**
 * Centralized nonce management
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Nonce_Manager {
    
    /**
     * Nonce action names
     */
    const LEADS_NONCE = 'ltb_leads_nonce';
    const EDIT_NONCE = 'ltb_lead_edit_nonce';
    const ADD_NONCE = 'ltb_lead_add_nonce';
    const DELETE_NONCE = 'ltb_lead_delete_nonce';
    const FILTER_NONCE = 'ltb_leads_filter_nonce';
    const FOLLOWUP_NONCE = 'ltb_followup_nonce';
    const METADATA_NONCE = 'ltb_lead_metadata_nonce';
    const AJAX_NONCE = 'ltb_ajax_nonce';
    
    /**
     * Create a nonce
     * 
     * @param string $action Nonce action
     * @return string Nonce value
     */
    public static function create($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify a nonce
     * 
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool Valid or not
     */
    public static function verify($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Verify a nonce from request
     * 
     * @param string $action Nonce action
     * @param string $nonce_field Field name in request
     * @param string $method Request method (POST or GET)
     * @return bool Valid or not
     */
    public static function verifyRequest($action, $nonce_field = 'nonce', $method = 'POST') {
        $request_data = $method === 'POST' ? $_POST : $_GET;
        
        if (!isset($request_data[$nonce_field])) {
            return false;
        }
        
        return self::verify($request_data[$nonce_field], $action);
    }
    
    /**
     * Verify AJAX nonce
     * 
     * @param string $action Nonce action
     * @return bool Valid or not
     */
    public static function verifyAjax($action = null) {
        if ($action === null) {
            $action = self::AJAX_NONCE;
        }
        
        return self::verifyRequest($action, 'nonce', 'POST');
    }
    
    /**
     * Create nonce field for forms
     * 
     * @param string $action Nonce action
     * @param string $name Field name
     * @param bool $referer Include referer field
     * @param bool $echo Echo or return
     * @return string HTML field
     */
    public static function field($action, $name = '_wpnonce', $referer = true, $echo = true) {
        return wp_nonce_field($action, $name, $referer, $echo);
    }
    
    /**
     * Create nonce URL
     * 
     * @param string $url Base URL
     * @param string $action Nonce action
     * @param string $name Query parameter name
     * @return string URL with nonce
     */
    public static function url($url, $action, $name = '_wpnonce') {
        return wp_nonce_url($url, $action, $name);
    }
    
    /**
     * Check admin referer with nonce
     * 
     * @param string $action Nonce action
     * @param string $query_arg Query parameter name
     * @return bool|int False if invalid, 1 if valid and generated 0-12 hours ago, 2 if valid and generated 12-24 hours ago
     */
    public static function checkAdminReferer($action, $query_arg = '_wpnonce') {
        return check_admin_referer($action, $query_arg);
    }
    
    /**
     * Check AJAX referer with nonce
     * 
     * @param string $action Nonce action
     * @param string $query_arg Query parameter name
     * @param bool $die Whether to die if invalid
     * @return bool|int False if invalid, 1 if valid and generated 0-12 hours ago, 2 if valid and generated 12-24 hours ago
     */
    public static function checkAjaxReferer($action, $query_arg = false, $die = true) {
        return check_ajax_referer($action, $query_arg, $die);
    }
    
    /**
     * Get all nonce actions as array
     * 
     * @return array
     */
    public static function getAllActions() {
        return [
            'leads' => self::LEADS_NONCE,
            'edit' => self::EDIT_NONCE,
            'add' => self::ADD_NONCE,
            'delete' => self::DELETE_NONCE,
            'filter' => self::FILTER_NONCE,
            'followup' => self::FOLLOWUP_NONCE,
            'metadata' => self::METADATA_NONCE,
            'ajax' => self::AJAX_NONCE,
        ];
    }
    
    /**
     * Generate nonces for JavaScript
     * 
     * @return array
     */
    public static function generateForJs() {
        $nonces = [];
        
        foreach (self::getAllActions() as $key => $action) {
            $nonces[$key] = self::create($action);
        }
        
        return $nonces;
    }
}