<?php
/**
 * Repository Interface for Leads
 * Define el contrato para acceso a datos
 */

if (!defined('ABSPATH')) {
    exit;
}

interface ILeadsRepository {
    public function find($id);
    public function findAll($filters = array());
    public function findByStatus($status);
    public function create($data);
    public function update($id, $data);
    public function delete($id);
    public function count($filters = array());
    public function getByDateRange($start_date, $end_date);
    public function getRecentLeads($limit = 10);
}