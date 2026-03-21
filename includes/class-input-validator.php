<?php
/**
 * Sistema de Validación de Inputs
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Input_Validator {
    
    private static $instance = null;
    private $errors = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validar email
     */
    public function validate_email($email, $field_name = 'email') {
        if (empty($email)) {
            $this->add_error($field_name, 'El email es requerido');
            return false;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->add_error($field_name, 'El formato del email no es válido');
            return false;
        }
        
        return sanitize_email($email);
    }
    
    /**
     * Validar teléfono
     */
    public function validate_phone($phone, $field_name = 'phone') {
        if (empty($phone)) {
            $this->add_error($field_name, 'El teléfono es requerido');
            return false;
        }
        
        // Limpiar caracteres no numéricos
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Validar longitud mínima
        if (strlen($phone) < 10) {
            $this->add_error($field_name, 'El teléfono debe tener al menos 10 dígitos');
            return false;
        }
        
        return sanitize_text_field($phone);
    }
    
    /**
     * Validar texto requerido
     */
    public function validate_required($value, $field_name) {
        if (empty($value) || trim($value) === '') {
            $this->add_error($field_name, "El campo {$field_name} es requerido");
            return false;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Validar fecha
     */
    public function validate_date($date, $field_name = 'date', $format = 'Y-m-d') {
        if (empty($date)) {
            return null; // Fecha opcional
        }
        
        $d = DateTime::createFromFormat($format, $date);
        
        if (!$d || $d->format($format) !== $date) {
            $this->add_error($field_name, 'El formato de fecha no es válido');
            return false;
        }
        
        return $date;
    }
    
    /**
     * Validar número entero
     */
    public function validate_integer($value, $field_name, $min = null, $max = null) {
        if (!is_numeric($value)) {
            $this->add_error($field_name, "El campo {$field_name} debe ser un número");
            return false;
        }
        
        $value = intval($value);
        
        if ($min !== null && $value < $min) {
            $this->add_error($field_name, "El valor mínimo es {$min}");
            return false;
        }
        
        if ($max !== null && $value > $max) {
            $this->add_error($field_name, "El valor máximo es {$max}");
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validar select/opción
     */
    public function validate_option($value, $valid_options, $field_name) {
        if (!in_array($value, $valid_options, true)) {
            $this->add_error($field_name, 'La opción seleccionada no es válida');
            return false;
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Validar URL
     */
    public function validate_url($url, $field_name = 'url') {
        if (empty($url)) {
            return null; // URL opcional
        }
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->add_error($field_name, 'La URL no es válida');
            return false;
        }
        
        return esc_url_raw($url);
    }
    
    /**
     * Validar datos de lead completo
     */
    public function validate_lead_data($data) {
        $validated = array();
        
        // Nombre
        if (isset($data['lead_nombre'])) {
            $validated['lead_nombre'] = $this->validate_required($data['lead_nombre'], 'Nombre');
        }
        
        // Apellido
        if (isset($data['lead_apellido'])) {
            $validated['lead_apellido'] = $this->validate_required($data['lead_apellido'], 'Apellido');
        }
        
        // Email
        if (isset($data['lead_e_mail'])) {
            $validated['lead_e_mail'] = $this->validate_email($data['lead_e_mail']);
        }
        
        // Teléfono
        if (isset($data['lead_celular'])) {
            $validated['lead_celular'] = $this->validate_phone($data['lead_celular']);
        }
        
        // Razón social (opcional)
        if (!empty($data['lead_razon_social'])) {
            $validated['lead_razon_social'] = sanitize_text_field($data['lead_razon_social']);
        }
        
        if ($this->has_errors()) {
            return false;
        }
        
        return $validated;
    }
    
    /**
     * Validar datos de evento
     */
    public function validate_event_data($data) {
        $validated = array();
        
        // Fecha de evento
        if (isset($data['fecha_de_evento'])) {
            $validated['fecha_de_evento'] = $this->validate_date($data['fecha_de_evento'], 'Fecha del evento');
        }
        
        // Tipo de evento
        if (!empty($data['tipo_de_evento'])) {
            $event_types = LTB_Leads_Status_Utils::get_event_types();
            $valid_types = array_keys($event_types);
            $validated['tipo_de_evento'] = $this->validate_option(
                $data['tipo_de_evento'], 
                $valid_types, 
                'Tipo de evento'
            );
        }
        
        // Número de asistentes
        if (!empty($data['evento_asistentes'])) {
            $validated['evento_asistentes'] = $this->validate_integer(
                $data['evento_asistentes'], 
                'Número de invitados', 
                1, 
                10000
            );
        }
        
        // Estado del evento
        if (!empty($data['evento_status'])) {
            $status_options = LTB_Leads_Status_Utils::get_status_options();
            $valid_status = array_keys($status_options);
            $validated['evento_status'] = $this->validate_option(
                $data['evento_status'], 
                $valid_status, 
                'Estado'
            );
        }
        
        // Dirección (opcional)
        if (!empty($data['direccion_evento'])) {
            $validated['direccion_evento'] = sanitize_textarea_field($data['direccion_evento']);
        }
        
        // Comentarios (opcional)
        if (!empty($data['comentarios_evento'])) {
            $validated['comentarios_evento'] = sanitize_textarea_field($data['comentarios_evento']);
        }
        
        if ($this->has_errors()) {
            return false;
        }
        
        return $validated;
    }
    
    /**
     * Sanitizar array de datos
     */
    public function sanitize_array($data, $rules) {
        $sanitized = array();
        
        foreach ($rules as $key => $rule) {
            if (!isset($data[$key])) {
                continue;
            }
            
            switch ($rule) {
                case 'email':
                    $sanitized[$key] = sanitize_email($data[$key]);
                    break;
                    
                case 'url':
                    $sanitized[$key] = esc_url_raw($data[$key]);
                    break;
                    
                case 'int':
                    $sanitized[$key] = intval($data[$key]);
                    break;
                    
                case 'float':
                    $sanitized[$key] = floatval($data[$key]);
                    break;
                    
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($data[$key]);
                    break;
                    
                case 'html':
                    $sanitized[$key] = wp_kses_post($data[$key]);
                    break;
                    
                case 'text':
                default:
                    $sanitized[$key] = sanitize_text_field($data[$key]);
                    break;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Agregar error
     */
    private function add_error($field, $message) {
        $this->errors[$field][] = $message;
    }
    
    /**
     * Verificar si hay errores
     */
    public function has_errors() {
        return !empty($this->errors);
    }
    
    /**
     * Obtener errores
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Obtener errores como string
     */
    public function get_errors_string() {
        $messages = array();
        
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = $error;
            }
        }
        
        return implode('. ', $messages);
    }
    
    /**
     * Limpiar errores
     */
    public function clear_errors() {
        $this->errors = array();
    }
}