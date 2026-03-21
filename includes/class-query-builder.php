<?php
/**
 * Query Builder para construcción de queries SQL optimizadas
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Query_Builder {
    
    private $wpdb;
    private $table;
    private $select = array();
    private $joins = array();
    private $where = array();
    private $order_by = array();
    private $group_by = array();
    private $having = array();
    private $limit = null;
    private $offset = null;
    private $values = array();
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Establecer tabla principal
     */
    public function from($table) {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Campos a seleccionar
     */
    public function select($fields = '*') {
        if (is_array($fields)) {
            $this->select = array_merge($this->select, $fields);
        } else {
            $this->select[] = $fields;
        }
        return $this;
    }
    
    /**
     * Agregar JOIN
     */
    public function join($table, $condition, $type = 'LEFT') {
        $this->joins[] = "{$type} JOIN {$table} ON {$condition}";
        return $this;
    }
    
    /**
     * Agregar condición WHERE
     */
    public function where($column, $operator = '=', $value = null) {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->where[] = "{$column} {$operator} %s";
        $this->values[] = $value;
        return $this;
    }
    
    /**
     * Agregar condición WHERE IN
     */
    public function whereIn($column, array $values) {
        if (empty($values)) {
            return $this;
        }
        
        $placeholders = array_fill(0, count($values), '%s');
        $this->where[] = "{$column} IN (" . implode(',', $placeholders) . ")";
        $this->values = array_merge($this->values, $values);
        return $this;
    }
    
    /**
     * Agregar condición WHERE LIKE
     */
    public function whereLike($column, $value) {
        $this->where[] = "{$column} LIKE %s";
        $this->values[] = '%' . $this->wpdb->esc_like($value) . '%';
        return $this;
    }
    
    /**
     * Agregar condición WHERE BETWEEN
     */
    public function whereBetween($column, $start, $end) {
        $this->where[] = "{$column} BETWEEN %s AND %s";
        $this->values[] = $start;
        $this->values[] = $end;
        return $this;
    }
    
    /**
     * Agregar condición WHERE NULL
     */
    public function whereNull($column) {
        $this->where[] = "{$column} IS NULL";
        return $this;
    }
    
    /**
     * Agregar condición WHERE NOT NULL
     */
    public function whereNotNull($column) {
        $this->where[] = "{$column} IS NOT NULL";
        return $this;
    }
    
    /**
     * Agregar ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        $direction = strtoupper($direction);
        if (!in_array($direction, array('ASC', 'DESC'))) {
            $direction = 'ASC';
        }
        $this->order_by[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Agregar GROUP BY
     */
    public function groupBy($column) {
        $this->group_by[] = $column;
        return $this;
    }
    
    /**
     * Agregar HAVING
     */
    public function having($condition, $value = null) {
        if ($value !== null) {
            $this->having[] = $condition;
            $this->values[] = $value;
        } else {
            $this->having[] = $condition;
        }
        return $this;
    }
    
    /**
     * Establecer LIMIT
     */
    public function limit($limit) {
        $this->limit = intval($limit);
        return $this;
    }
    
    /**
     * Establecer OFFSET
     */
    public function offset($offset) {
        $this->offset = intval($offset);
        return $this;
    }
    
    /**
     * Construir y ejecutar query SELECT
     */
    public function get() {
        $sql = $this->buildSelect();
        
        if (!empty($this->values)) {
            $sql = $this->wpdb->prepare($sql, $this->values);
        }
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Obtener solo un registro
     */
    public function first() {
        $this->limit(1);
        $sql = $this->buildSelect();
        
        if (!empty($this->values)) {
            $sql = $this->wpdb->prepare($sql, $this->values);
        }
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Contar registros
     */
    public function count() {
        $this->select = array('COUNT(*) as total');
        $sql = $this->buildSelect();
        
        if (!empty($this->values)) {
            $sql = $this->wpdb->prepare($sql, $this->values);
        }
        
        $result = $this->wpdb->get_row($sql);
        return $result ? intval($result->total) : 0;
    }
    
    /**
     * Construir query SELECT
     */
    private function buildSelect() {
        $select = empty($this->select) ? '*' : implode(', ', $this->select);
        $sql = "SELECT {$select} FROM {$this->table}";
        
        // Agregar JOINs
        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        
        // Agregar WHERE
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }
        
        // Agregar GROUP BY
        if (!empty($this->group_by)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->group_by);
        }
        
        // Agregar HAVING
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }
        
        // Agregar ORDER BY
        if (!empty($this->order_by)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->order_by);
        }
        
        // Agregar LIMIT y OFFSET
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }
        
        return $sql;
    }
    
    /**
     * Ejecutar INSERT
     */
    public function insert(array $data) {
        return $this->wpdb->insert($this->table, $data);
    }
    
    /**
     * Ejecutar UPDATE
     */
    public function update(array $data, array $where) {
        return $this->wpdb->update($this->table, $data, $where);
    }
    
    /**
     * Ejecutar DELETE
     */
    public function delete(array $where) {
        return $this->wpdb->delete($this->table, $where);
    }
    
    /**
     * Resetear builder
     */
    public function reset() {
        $this->table = null;
        $this->select = array();
        $this->joins = array();
        $this->where = array();
        $this->order_by = array();
        $this->group_by = array();
        $this->having = array();
        $this->limit = null;
        $this->offset = null;
        $this->values = array();
        return $this;
    }
}