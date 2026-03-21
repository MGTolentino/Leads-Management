<?php
/**
 * Interface for Leads Repository
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface LTB_Leads_Repository_Interface {
    /**
     * Find a lead by ID
     * 
     * @param int $id Lead ID
     * @return object|null Lead object or null
     */
    public function find($id);
    
    /**
     * Find leads with filters
     * 
     * @param array $filters Array of filters
     * @param int $limit Number of results
     * @param int $offset Offset for pagination
     * @return array Array of lead objects
     */
    public function findWithFilters(array $filters, $limit = 100, $offset = 0);
    
    /**
     * Save a lead
     * 
     * @param array $data Lead data
     * @return int|false Lead ID or false on failure
     */
    public function save(array $data);
    
    /**
     * Update a lead
     * 
     * @param int $id Lead ID
     * @param array $data Lead data to update
     * @return bool Success or failure
     */
    public function update($id, array $data);
    
    /**
     * Delete a lead
     * 
     * @param int $id Lead ID
     * @return bool Success or failure
     */
    public function delete($id);
    
    /**
     * Count leads with filters
     * 
     * @param array $filters Array of filters
     * @return int Number of leads
     */
    public function count(array $filters = []);
}