<?php
/**
 * Unified Error Handling System
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Error_Handler {
    
    /**
     * Error levels
     */
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const SUCCESS = 'success';
    
    /**
     * Error codes
     */
    const CODE_VALIDATION_ERROR = 'validation_error';
    const CODE_DATABASE_ERROR = 'database_error';
    const CODE_PERMISSION_ERROR = 'permission_error';
    const CODE_NOT_FOUND = 'not_found';
    const CODE_DUPLICATE_ENTRY = 'duplicate_entry';
    const CODE_INVALID_REQUEST = 'invalid_request';
    const CODE_SYSTEM_ERROR = 'system_error';
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Stored errors
     */
    private $errors = [];
    
    /**
     * Get instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Set up error handling
        add_action('init', [$this, 'setupErrorHandling']);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        add_action('wp_ajax_get_errors', [$this, 'ajaxGetErrors']);
    }
    
    /**
     * Set up error handling
     */
    public function setupErrorHandling() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // In debug mode, log all errors
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            ini_set('error_log', WP_CONTENT_DIR . '/debug.log');
        }
    }
    
    /**
     * Add an error
     * 
     * @param string $message Error message
     * @param string $code Error code
     * @param string $level Error level
     * @param array $context Additional context
     * @return void
     */
    public function addError($message, $code = null, $level = self::ERROR, $context = []) {
        $error = [
            'message' => $message,
            'code' => $code ?: self::CODE_SYSTEM_ERROR,
            'level' => $level,
            'context' => $context,
            'timestamp' => current_time('timestamp'),
            'user_id' => get_current_user_id()
        ];
        
        $this->errors[] = $error;
        
        // Log to WordPress debug log if enabled
        if (WP_DEBUG_LOG) {
            $this->logError($error);
        }
        
        // Store in transient for display
        $this->storeErrorForDisplay($error);
    }
    
    /**
     * Add a validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @param array $context Additional context
     */
    public function addValidationError($field, $message, $context = []) {
        $context['field'] = $field;
        $this->addError($message, self::CODE_VALIDATION_ERROR, self::ERROR, $context);
    }
    
    /**
     * Add a database error
     * 
     * @param string $message Error message
     * @param string $query Failed query
     */
    public function addDatabaseError($message, $query = '') {
        $context = ['query' => $query];
        $this->addError($message, self::CODE_DATABASE_ERROR, self::ERROR, $context);
    }
    
    /**
     * Add a permission error
     * 
     * @param string $message Error message
     * @param string $capability Required capability
     */
    public function addPermissionError($message, $capability = '') {
        $context = ['capability' => $capability];
        $this->addError($message, self::CODE_PERMISSION_ERROR, self::ERROR, $context);
    }
    
    /**
     * Add a warning
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public function addWarning($message, $context = []) {
        $this->addError($message, null, self::WARNING, $context);
    }
    
    /**
     * Add an info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     */
    public function addInfo($message, $context = []) {
        $this->addError($message, null, self::INFO, $context);
    }
    
    /**
     * Add a success message
     * 
     * @param string $message Success message
     * @param array $context Additional context
     */
    public function addSuccess($message, $context = []) {
        $this->addError($message, null, self::SUCCESS, $context);
    }
    
    /**
     * Check if there are errors
     * 
     * @param string $level Optional level to check
     * @return bool
     */
    public function hasErrors($level = null) {
        if ($level === null) {
            return !empty($this->errors);
        }
        
        foreach ($this->errors as $error) {
            if ($error['level'] === $level) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get all errors
     * 
     * @param string $level Optional level filter
     * @return array
     */
    public function getErrors($level = null) {
        if ($level === null) {
            return $this->errors;
        }
        
        return array_filter($this->errors, function($error) use ($level) {
            return $error['level'] === $level;
        });
    }
    
    /**
     * Get last error
     * 
     * @return array|null
     */
    public function getLastError() {
        if (empty($this->errors)) {
            return null;
        }
        
        return end($this->errors);
    }
    
    /**
     * Clear errors
     * 
     * @param string $level Optional level to clear
     */
    public function clearErrors($level = null) {
        if ($level === null) {
            $this->errors = [];
        } else {
            $this->errors = array_filter($this->errors, function($error) use ($level) {
                return $error['level'] !== $level;
            });
        }
    }
    
    /**
     * Log error to file
     * 
     * @param array $error
     */
    private function logError($error) {
        $log_message = sprintf(
            "[%s] %s - %s: %s",
            date('Y-m-d H:i:s', $error['timestamp']),
            strtoupper($error['level']),
            $error['code'],
            $error['message']
        );
        
        if (!empty($error['context'])) {
            $log_message .= ' | Context: ' . json_encode($error['context']);
        }
        
        error_log($log_message);
    }
    
    /**
     * Store error for display
     * 
     * @param array $error
     */
    private function storeErrorForDisplay($error) {
        $user_id = get_current_user_id();
        $transient_key = 'ltb_errors_' . $user_id;
        
        $stored_errors = get_transient($transient_key) ?: [];
        $stored_errors[] = $error;
        
        // Keep only last 10 errors
        $stored_errors = array_slice($stored_errors, -10);
        
        set_transient($transient_key, $stored_errors, 300); // 5 minutes
    }
    
    /**
     * Display admin notices
     */
    public function displayAdminNotices() {
        $user_id = get_current_user_id();
        $transient_key = 'ltb_errors_' . $user_id;
        
        $errors = get_transient($transient_key);
        
        if (empty($errors)) {
            return;
        }
        
        foreach ($errors as $error) {
            $class = 'notice notice-' . $this->getNoticeClass($error['level']);
            $message = esc_html($error['message']);
            
            printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
        }
        
        // Clear displayed errors
        delete_transient($transient_key);
    }
    
    /**
     * Get notice class for error level
     * 
     * @param string $level
     * @return string
     */
    private function getNoticeClass($level) {
        switch ($level) {
            case self::ERROR:
                return 'error';
            case self::WARNING:
                return 'warning';
            case self::SUCCESS:
                return 'success';
            case self::INFO:
            default:
                return 'info';
        }
    }
    
    /**
     * Handle AJAX error response
     * 
     * @param string $message
     * @param string $code
     * @param array $data
     */
    public function ajaxError($message, $code = null, $data = []) {
        wp_send_json_error([
            'message' => $message,
            'code' => $code ?: self::CODE_SYSTEM_ERROR,
            'data' => $data
        ]);
    }
    
    /**
     * Handle AJAX success response
     * 
     * @param mixed $data
     * @param string $message
     */
    public function ajaxSuccess($data, $message = '') {
        wp_send_json_success([
            'message' => $message,
            'data' => $data
        ]);
    }
    
    /**
     * AJAX endpoint to get errors
     */
    public function ajaxGetErrors() {
        check_ajax_referer('ltb_ajax_nonce', 'nonce');
        
        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : null;
        $errors = $this->getErrors($level);
        
        $this->ajaxSuccess($errors);
    }
    
    /**
     * Format error for display
     * 
     * @param array $error
     * @return string
     */
    public function formatError($error) {
        $formatted = $error['message'];
        
        if (WP_DEBUG && !empty($error['context'])) {
            $formatted .= ' (' . json_encode($error['context']) . ')';
        }
        
        return $formatted;
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public function handlePHPError($errno, $errstr, $errfile, $errline) {
        // Check if error reporting is turned off
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $level = self::ERROR;
        
        switch ($errno) {
            case E_WARNING:
            case E_USER_WARNING:
                $level = self::WARNING;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = self::INFO;
                break;
        }
        
        $context = [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ];
        
        $this->addError($errstr, 'php_error', $level, $context);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Exception $exception
     */
    public function handleException($exception) {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $this->addError(
            $exception->getMessage(),
            'uncaught_exception',
            self::ERROR,
            $context
        );
    }
    
    /**
     * Register error handlers
     */
    public function registerHandlers() {
        set_error_handler([$this, 'handlePHPError']);
        set_exception_handler([$this, 'handleException']);
    }
}