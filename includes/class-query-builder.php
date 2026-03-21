<?php
/**
 * Query Builder for database operations
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Query_Builder {
    
    private $select = [];
    private $from = '';
    private $joins = [];
    private $where = [];
    private $group_by = [];
    private $having = [];
    private $order_by = [];
    private $limit_value = null;
    private $offset_value = null;
    
    /**
     * @var wpdb
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Set SELECT clause
     * 
     * @param array|string $columns
     * @return $this
     */
    public function select($columns = ['*']) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $this->select = $columns;
        return $this;
    }
    
    /**
     * Set FROM clause
     * 
     * @param string $table
     * @param string $alias
     * @return $this
     */
    public function from($table, $alias = '') {
        $this->from = $alias ? "{$table} {$alias}" : $table;
        return $this;
    }
    
    /**
     * Add JOIN clause
     * 
     * @param string $table
     * @param string $alias
     * @param string $condition
     * @param string $type
     * @return $this
     */
    public function join($table, $alias, $condition, $type = 'INNER') {
        $this->joins[] = "{$type} JOIN {$table} {$alias} ON {$condition}";
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     * 
     * @param string $table
     * @param string $alias
     * @param string $condition
     * @return $this
     */
    public function leftJoin($table, $alias, $condition) {
        return $this->join($table, $alias, $condition, 'LEFT');
    }
    
    /**
     * Add WHERE condition
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @param string $logic
     * @return $this
     */
    public function where($column, $operator, $value, $logic = 'AND') {
        $condition = $this->prepareCondition($column, $operator, $value);
        
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $this->where[] = "{$logic} {$condition}";
        }
        
        return $this;
    }
    
    /**
     * Add OR WHERE condition
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere($column, $operator, $value) {
        return $this->where($column, $operator, $value, 'OR');
    }
    
    /**
     * Add WHERE IN condition
     * 
     * @param string $column
     * @param array $values
     * @param string $logic
     * @return $this
     */
    public function whereIn($column, array $values, $logic = 'AND') {
        if (empty($values)) {
            return $this;
        }
        
        $placeholders = implode(',', array_fill(0, count($values), '%s'));
        $condition = $this->db->prepare("{$column} IN ({$placeholders})", $values);
        
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $this->where[] = "{$logic} {$condition}";
        }
        
        return $this;
    }
    
    /**
     * Add WHERE LIKE condition
     * 
     * @param string $column
     * @param string $value
     * @param string $logic
     * @return $this
     */
    public function whereLike($column, $value, $logic = 'AND') {
        $condition = $this->db->prepare("{$column} LIKE %s", $value);
        
        if (empty($this->where)) {
            $this->where[] = $condition;
        } else {
            $this->where[] = "{$logic} {$condition}";
        }
        
        return $this;
    }
    
    /**
     * Add OR WHERE LIKE condition
     * 
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function orWhereLike($column, $value) {
        return $this->whereLike($column, $value, 'OR');
    }
    
    /**
     * Add grouped WHERE conditions
     * 
     * @param callable $callback
     * @param string $logic
     * @return $this
     */
    public function whereGroup($callback, $logic = 'AND') {
        $group_builder = new self();
        call_user_func($callback, $group_builder);
        
        if (!empty($group_builder->where)) {
            $grouped = '(' . implode(' ', $group_builder->where) . ')';
            
            if (empty($this->where)) {
                $this->where[] = $grouped;
            } else {
                $this->where[] = "{$logic} {$grouped}";
            }
        }
        
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     * 
     * @param string|array $columns
     * @return $this
     */
    public function groupBy($columns) {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $this->group_by = array_merge($this->group_by, $columns);
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     * 
     * @param string $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'ASC') {
        $this->order_by[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Set LIMIT clause
     * 
     * @param int $limit
     * @return $this
     */
    public function limit($limit) {
        $this->limit_value = intval($limit);
        return $this;
    }
    
    /**
     * Set OFFSET clause
     * 
     * @param int $offset
     * @return $this
     */
    public function offset($offset) {
        $this->offset_value = intval($offset);
        return $this;
    }
    
    /**
     * Build the SQL query
     * 
     * @return string
     */
    public function build() {
        $sql = [];
        
        // SELECT
        $sql[] = 'SELECT ' . implode(', ', $this->select);
        
        // FROM
        $sql[] = 'FROM ' . $this->from;
        
        // JOIN
        if (!empty($this->joins)) {
            $sql[] = implode(' ', $this->joins);
        }
        
        // WHERE
        if (!empty($this->where)) {
            $sql[] = 'WHERE ' . implode(' ', $this->where);
        }
        
        // GROUP BY
        if (!empty($this->group_by)) {
            $sql[] = 'GROUP BY ' . implode(', ', $this->group_by);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $sql[] = 'HAVING ' . implode(' ', $this->having);
        }
        
        // ORDER BY
        if (!empty($this->order_by)) {
            $sql[] = 'ORDER BY ' . implode(', ', $this->order_by);
        }
        
        // LIMIT & OFFSET
        if ($this->limit_value !== null) {
            $limit_clause = "LIMIT {$this->limit_value}";
            if ($this->offset_value !== null) {
                $limit_clause .= " OFFSET {$this->offset_value}";
            }
            $sql[] = $limit_clause;
        }
        
        return implode(' ', $sql);
    }
    
    /**
     * Reset the builder
     * 
     * @return $this
     */
    public function reset() {
        $this->select = [];
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->group_by = [];
        $this->having = [];
        $this->order_by = [];
        $this->limit_value = null;
        $this->offset_value = null;
        
        return $this;
    }
    
    /**
     * Prepare a condition with proper escaping
     * 
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return string
     */
    private function prepareCondition($column, $operator, $value) {
        if ($value === null) {
            if ($operator === '=') {
                return "{$column} IS NULL";
            } elseif ($operator === '!=') {
                return "{$column} IS NOT NULL";
            }
        }
        
        $placeholder = is_numeric($value) ? '%d' : '%s';
        return $this->db->prepare("{$column} {$operator} {$placeholder}", $value);
    }
}