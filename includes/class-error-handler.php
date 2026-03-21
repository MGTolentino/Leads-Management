<?php
/**
 * Sistema de Manejo de Errores Unificado
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Error_Handler {
    
    private static $instance = null;
    private $errors = array();
    private $error_log_enabled = true;
    private $email_notifications = false;
    private $admin_email = '';
    private $error_levels = array(
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->admin_email = get_option('admin_email');
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Capturar errores PHP
        set_error_handler(array($this, 'handle_php_error'));
        set_exception_handler(array($this, 'handle_exception'));
        register_shutdown_function(array($this, 'handle_shutdown'));
        
        // AJAX para obtener errores
        add_action('wp_ajax_ltb_get_errors', array($this, 'ajax_get_errors'));
        
        // Limpiar errores antiguos
        add_action('ltb_daily_error_cleanup', array($this, 'cleanup_old_errors'));
        
        if (!wp_next_scheduled('ltb_daily_error_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ltb_daily_error_cleanup');
        }
    }
    
    /**
     * Registrar error
     */
    public function log($message, $level = 'error', $context = array()) {
        $error = array(
            'message' => $message,
            'level' => $level,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'backtrace' => $this->get_clean_backtrace()
        );
        
        // Agregar a la lista de errores
        $this->errors[] = $error;
        
        // Guardar en base de datos si es crítico
        if ($this->get_level_priority($level) >= $this->error_levels['error']) {
            $this->save_to_database($error);
        }
        
        // Escribir en log si está habilitado
        if ($this->error_log_enabled) {
            $this->write_to_log($error);
        }
        
        // Enviar notificación si es crítico
        if ($level === 'critical' && $this->email_notifications) {
            $this->send_notification($error);
        }
        
        return true;
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = array()) {
        return $this->log($message, 'debug', $context);
    }
    
    /**
     * Log de información
     */
    public function info($message, $context = array()) {
        return $this->log($message, 'info', $context);
    }
    
    /**
     * Log de advertencia
     */
    public function warning($message, $context = array()) {
        return $this->log($message, 'warning', $context);
    }
    
    /**
     * Log de error
     */
    public function error($message, $context = array()) {
        return $this->log($message, 'error', $context);
    }
    
    /**
     * Log crítico
     */
    public function critical($message, $context = array()) {
        return $this->log($message, 'critical', $context);
    }
    
    /**
     * Manejar error PHP
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Ignorar si error_reporting está desactivado
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Ignorar errores fuera de nuestro plugin
        if (strpos($errfile, 'leads-management') === false) {
            return false;
        }
        
        $level = $this->map_error_level($errno);
        $message = sprintf('[PHP %s] %s in %s on line %d', 
            $this->get_error_type($errno), 
            $errstr, 
            $errfile, 
            $errline
        );
        
        $this->log($message, $level, array(
            'type' => 'php_error',
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline
        ));
        
        // Permitir que PHP maneje el error normalmente
        return false;
    }
    
    /**
     * Manejar excepción
     */
    public function handle_exception($exception) {
        $this->critical('Uncaught Exception: ' . $exception->getMessage(), array(
            'type' => 'exception',
            'class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ));
    }
    
    /**
     * Manejar shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->critical('Fatal Error: ' . $error['message'], array(
                'type' => 'fatal_error',
                'file' => $error['file'],
                'line' => $error['line']
            ));
        }
    }
    
    /**
     * Guardar en base de datos
     */
    private function save_to_database($error) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltb_error_logs';
        
        // Crear tabla si no existe
        $this->create_error_table();
        
        $wpdb->insert($table, array(
            'message' => $error['message'],
            'level' => $error['level'],
            'context' => json_encode($error['context']),
            'timestamp' => $error['timestamp'],
            'user_id' => $error['user_id'],
            'ip' => $error['ip'],
            'url' => $error['url']
        ));
    }
    
    /**
     * Crear tabla de errores
     */
    private function create_error_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ltb_error_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            level varchar(20) NOT NULL,
            context longtext,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20),
            ip varchar(45),
            url varchar(255),
            PRIMARY KEY (id),
            KEY level (level),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Escribir en archivo de log
     */
    private function write_to_log($error) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $log_entry = sprintf(
            "[%s] [%s] %s %s\n",
            $error['timestamp'],
            strtoupper($error['level']),
            $error['message'],
            !empty($error['context']) ? json_encode($error['context']) : ''
        );
        
        error_log($log_entry, 3, $log_file);
    }
    
    /**
     * Enviar notificación por email
     */
    private function send_notification($error) {
        $subject = sprintf('[%s] Error Crítico en Leads Management', get_bloginfo('name'));
        
        $message = "Se ha producido un error crítico:\n\n";
        $message .= "Mensaje: " . $error['message'] . "\n";
        $message .= "Fecha: " . $error['timestamp'] . "\n";
        $message .= "URL: " . $error['url'] . "\n";
        $message .= "Usuario: " . get_userdata($error['user_id'])->user_login . "\n\n";
        $message .= "Contexto:\n" . print_r($error['context'], true);
        
        wp_mail($this->admin_email, $subject, $message);
    }
    
    /**
     * Obtener backtrace limpio
     */
    private function get_clean_backtrace($limit = 5) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 2);
        
        // Remover las primeras entradas (este método y el que lo llamó)
        array_shift($backtrace);
        array_shift($backtrace);
        
        $clean = array();
        foreach ($backtrace as $trace) {
            $clean[] = sprintf(
                '%s%s%s() at %s:%d',
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function'] ?? 'unknown',
                $trace['file'] ?? 'unknown',
                $trace['line'] ?? 0
            );
        }
        
        return $clean;
    }
    
    /**
     * Mapear nivel de error PHP
     */
    private function map_error_level($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                return 'critical';
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';
                
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
                return 'info';
                
            default:
                return 'debug';
        }
    }
    
    /**
     * Obtener tipo de error
     */
    private function get_error_type($errno) {
        $types = array(
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_DEPRECATED => 'DEPRECATED'
        );
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    /**
     * Obtener prioridad del nivel
     */
    private function get_level_priority($level) {
        return $this->error_levels[$level] ?? 0;
    }
    
    /**
     * AJAX para obtener errores
     */
    public function ajax_get_errors() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'ltb_error_logs';
        
        $errors = $wpdb->get_results("
            SELECT * FROM $table 
            ORDER BY timestamp DESC 
            LIMIT 100
        ");
        
        wp_send_json_success($errors);
    }
    
    /**
     * Limpiar errores antiguos
     */
    public function cleanup_old_errors() {
        global $wpdb;
        $table = $wpdb->prefix . 'ltb_error_logs';
        
        // Eliminar errores de más de 30 días
        $wpdb->query("
            DELETE FROM $table 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * Obtener errores recientes
     */
    public function get_recent_errors($limit = 10) {
        return array_slice($this->errors, -$limit);
    }
}