<?php
/**
 * Input validation and sanitization class
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Input_Validator {
    
    /**
     * Validate and sanitize email
     * 
     * @param string $email
     * @return string|false
     */
    public static function validateEmail($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }
    
    /**
     * Validate and sanitize phone number
     * 
     * @param string $phone
     * @return string|false
     */
    public static function validatePhone($phone) {
        // Remove all non-numeric characters except + and -
        $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $phone);
        
        // Check if it's a valid phone format (basic validation)
        if (strlen($phone) < 7 || strlen($phone) > 20) {
            return false;
        }
        
        return sanitize_text_field($phone);
    }
    
    /**
     * Validate and sanitize date
     * 
     * @param string $date
     * @param string $format
     * @return string|false
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        
        if ($d && $d->format($format) === $date) {
            return $date;
        }
        
        return false;
    }
    
    /**
     * Validate date range
     * 
     * @param string $start_date
     * @param string $end_date
     * @param string $format
     * @return array|false
     */
    public static function validateDateRange($start_date, $end_date, $format = 'Y-m-d') {
        $start = self::validateDate($start_date, $format);
        $end = self::validateDate($end_date, $format);
        
        if (!$start || !$end) {
            return false;
        }
        
        // Check if end date is after or equal to start date
        if (strtotime($end) < strtotime($start)) {
            return false;
        }
        
        return [
            'start' => $start,
            'end' => $end
        ];
    }
    
    /**
     * Validate and sanitize text input
     * 
     * @param string $text
     * @param int $max_length
     * @return string|false
     */
    public static function validateText($text, $max_length = 255) {
        $text = sanitize_text_field($text);
        
        if (strlen($text) > $max_length) {
            return false;
        }
        
        return $text;
    }
    
    /**
     * Validate and sanitize textarea input
     * 
     * @param string $text
     * @param int $max_length
     * @return string|false
     */
    public static function validateTextarea($text, $max_length = 5000) {
        $text = sanitize_textarea_field($text);
        
        if (strlen($text) > $max_length) {
            return false;
        }
        
        return $text;
    }
    
    /**
     * Validate numeric input
     * 
     * @param mixed $number
     * @param float $min
     * @param float $max
     * @return float|false
     */
    public static function validateNumber($number, $min = null, $max = null) {
        if (!is_numeric($number)) {
            return false;
        }
        
        $number = floatval($number);
        
        if ($min !== null && $number < $min) {
            return false;
        }
        
        if ($max !== null && $number > $max) {
            return false;
        }
        
        return $number;
    }
    
    /**
     * Validate integer input
     * 
     * @param mixed $integer
     * @param int $min
     * @param int $max
     * @return int|false
     */
    public static function validateInteger($integer, $min = null, $max = null) {
        $number = self::validateNumber($integer, $min, $max);
        
        if ($number === false) {
            return false;
        }
        
        return intval($number);
    }
    
    /**
     * Validate array of IDs
     * 
     * @param array $ids
     * @return array|false
     */
    public static function validateIds(array $ids) {
        $validated = [];
        
        foreach ($ids as $id) {
            $validated_id = self::validateInteger($id, 1);
            if ($validated_id === false) {
                return false;
            }
            $validated[] = $validated_id;
        }
        
        return $validated;
    }
    
    /**
     * Validate status value
     * 
     * @param string $status
     * @param array $allowed_statuses
     * @return string|false
     */
    public static function validateStatus($status, array $allowed_statuses) {
        $status = sanitize_text_field($status);
        
        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }
        
        return $status;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url
     * @return string|false
     */
    public static function validateUrl($url) {
        $url = esc_url_raw($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        return $url;
    }
    
    /**
     * Validate and sanitize array of data
     * 
     * @param array $data
     * @param array $rules
     * @return array|false
     */
    public static function validateData(array $data, array $rules) {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            // Check if required
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = 'Campo requerido';
                continue;
            }
            
            // Skip if not required and empty
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Validate based on type
            $type = isset($rule['type']) ? $rule['type'] : 'text';
            $validated_value = false;
            
            switch ($type) {
                case 'email':
                    $validated_value = self::validateEmail($value);
                    break;
                    
                case 'phone':
                    $validated_value = self::validatePhone($value);
                    break;
                    
                case 'date':
                    $format = isset($rule['format']) ? $rule['format'] : 'Y-m-d';
                    $validated_value = self::validateDate($value, $format);
                    break;
                    
                case 'number':
                    $min = isset($rule['min']) ? $rule['min'] : null;
                    $max = isset($rule['max']) ? $rule['max'] : null;
                    $validated_value = self::validateNumber($value, $min, $max);
                    break;
                    
                case 'integer':
                    $min = isset($rule['min']) ? $rule['min'] : null;
                    $max = isset($rule['max']) ? $rule['max'] : null;
                    $validated_value = self::validateInteger($value, $min, $max);
                    break;
                    
                case 'textarea':
                    $max_length = isset($rule['max_length']) ? $rule['max_length'] : 5000;
                    $validated_value = self::validateTextarea($value, $max_length);
                    break;
                    
                case 'url':
                    $validated_value = self::validateUrl($value);
                    break;
                    
                case 'status':
                    $allowed = isset($rule['allowed']) ? $rule['allowed'] : [];
                    $validated_value = self::validateStatus($value, $allowed);
                    break;
                    
                default:
                    $max_length = isset($rule['max_length']) ? $rule['max_length'] : 255;
                    $validated_value = self::validateText($value, $max_length);
            }
            
            if ($validated_value === false) {
                $errors[$field] = 'Formato inválido';
            } else {
                $validated[$field] = $validated_value;
            }
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return ['success' => true, 'data' => $validated];
    }
}