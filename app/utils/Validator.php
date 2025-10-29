<?php

namespace Utils;

/**
 * Data validation utility for the application
 */
class Validator
{
    /**
     * @var array Validation errors
     */
    private $errors = [];

    /**
     * @var array Custom validation rules
     */
    private $customRules = [];

    /**
     * @var array Custom validation messages
     */
    private $customMessages = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        // Initialize with empty errors
        $this->resetErrors();
    }

    /**
     * Reset validation errors
     * 
     * @return $this For method chaining
     */
    public function resetErrors()
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Get all validation errors
     * 
     * @return array Validation errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if validation has errors
     * 
     * @return bool Whether validation has errors
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Add a validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * Register a custom validation rule
     * 
     * @param string $name Rule name
     * @param callable $callback Validation callback
     * @return $this For method chaining
     */
    public function addRule($name, callable $callback)
    {
        $this->customRules[$name] = $callback;
        return $this;
    }

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool Whether validation passed
     */
    public function validate(array $data, array $rules)
    {
        $this->resetErrors();

        foreach ($rules as $field => $fieldRules) {
            // Skip if field doesn't exist and not required
            if (!isset($data[$field]) && !$this->hasRule($fieldRules, 'required')) {
                continue;
            }

            // Get field value (null if not set)
            $value = $data[$field] ?? null;

            // Apply each rule to the field
            foreach ($fieldRules as $rule => $ruleValue) {
                // If rule is numeric, it's a simple rule without parameters
                if (is_numeric($rule)) {
                    $rule = $ruleValue;
                    $ruleValue = true;
                }

                $this->applyRule($field, $value, $rule, $ruleValue, $data);
            }
        }

        return !$this->hasErrors();
    }

    /**
     * Set custom validation messages
     * 
     * @param array $messages Custom validation messages
     * @return $this For method chaining
     */
    public function setMessages(array $messages)
    {
        $this->customMessages = $messages;
        return $this;
    }

    /**
     * Apply a validation rule to a field
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param mixed $ruleValue Rule parameters
     * @param array $data All data being validated
     * @return bool Whether validation passed
     */
    private function applyRule($field, $value, $rule, $ruleValue, array $data)
    {
        // Check for custom rule
        if (isset($this->customRules[$rule])) {
            $result = call_user_func($this->customRules[$rule], $value, $ruleValue, $data);

            if ($result !== true) {
                $this->addError($field, is_string($result) ? $result : "The {$field} field is invalid.");
                return false;
            }

            return true;
        }

        // Apply built-in rules
        switch ($rule) {
            case 'required':
                if ($ruleValue && ($value === null || $value === '')) {
                    $this->addError($field, "The {$field} field is required.");
                    return false;
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !$this->isValidEmail($value)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                    return false;
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !$this->isValidUrl($value)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                    return false;
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->addError($field, "The {$field} must be numeric.");
                    return false;
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, "The {$field} must be an integer.");
                    return false;
                }
                break;

            case 'float':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_FLOAT)) {
                    $this->addError($field, "The {$field} must be a float.");
                    return false;
                }
                break;

            case 'boolean':
                if ($value !== null && $value !== '' && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
                    $this->addError($field, "The {$field} must be a boolean.");
                    return false;
                }
                break;

            case 'date':
                if ($value !== null && $value !== '' && !$this->isValidDate($value)) {
                    $this->addError($field, "The {$field} must be a valid date.");
                    return false;
                }
                break;

            case 'min':
                if ($value !== null && $value !== '') {
                    if (is_string($value) && mb_strlen($value) < $ruleValue) {
                        $this->addError($field, "The {$field} must be at least {$ruleValue} characters.");
                        return false;
                    } elseif (is_numeric($value) && $value < $ruleValue) {
                        $this->addError($field, "The {$field} must be at least {$ruleValue}.");
                        return false;
                    }
                }
                break;

            case 'max':
                if ($value !== null && $value !== '') {
                    if (is_string($value) && mb_strlen($value) > $ruleValue) {
                        $this->addError($field, "The {$field} may not be greater than {$ruleValue} characters.");
                        return false;
                    } elseif (is_numeric($value) && $value > $ruleValue) {
                        $this->addError($field, "The {$field} may not be greater than {$ruleValue}.");
                        return false;
                    }
                }
                break;

            case 'between':
                if ($value !== null && $value !== '') {
                    list($min, $max) = $ruleValue;

                    if (is_string($value)) {
                        $length = mb_strlen($value);
                        if ($length < $min || $length > $max) {
                            $this->addError($field, "The {$field} must be between {$min} and {$max} characters.");
                            return false;
                        }
                    } elseif (is_numeric($value)) {
                        if ($value < $min || $value > $max) {
                            $this->addError($field, "The {$field} must be between {$min} and {$max}.");
                            return false;
                        }
                    }
                }
                break;

            case 'length':
                if ($value !== null && $value !== '') {
                    $length = mb_strlen((string) $value);
                    list($min, $max) = $ruleValue;
                    if ($length < $min || $length > $max) {
                        $this->addError($field, "The {$field} must be between {$min} and {$max} characters.");
                        return false;
                    }
                }
                break;

            case 'in':
                if ($value !== null && $value !== '' && !in_array($value, (array) $ruleValue, true)) {
                    $allowed = is_array($ruleValue) ? implode(', ', $ruleValue) : $ruleValue;
                    $this->addError($field, "The {$field} must be one of the following: {$allowed}.");
                    return false;
                }
                break;

            case 'not_in':
                if ($value !== null && $value !== '' && in_array($value, (array) $ruleValue, true)) {
                    $disallowed = is_array($ruleValue) ? implode(', ', $ruleValue) : $ruleValue;
                    $this->addError($field, "The {$field} may not be one of the following: {$disallowed}.");
                    return false;
                }
                break;

            case 'regex':
                if ($value !== null && $value !== '' && !preg_match($ruleValue, $value)) {
                    $this->addError($field, "The {$field} format is invalid.");
                    return false;
                }
                break;

            case 'same':
                if ($value !== ($data[$ruleValue] ?? null)) {
                    $this->addError($field, "The {$field} and {$ruleValue} must match.");
                    return false;
                }
                break;

            case 'different':
                if ($value === ($data[$ruleValue] ?? null)) {
                    $this->addError($field, "The {$field} and {$ruleValue} must be different.");
                    return false;
                }
                break;

            case 'alpha':
                if ($value !== null && $value !== '' && !preg_match('/^[\pL\pM]+$/u', $value)) {
                    $this->addError($field, "The {$field} may only contain letters.");
                    return false;
                }
                break;

            case 'alpha_num':
                if ($value !== null && $value !== '' && !preg_match('/^[\pL\pM\pN]+$/u', $value)) {
                    $this->addError($field, "The {$field} may only contain letters and numbers.");
                    return false;
                }
                break;

            case 'alpha_dash':
                if ($value !== null && $value !== '' && !preg_match('/^[\pL\pM\pN_-]+$/u', $value)) {
                    $this->addError($field, "The {$field} may only contain letters, numbers, dashes and underscores.");
                    return false;
                }
                break;

            default:
                $this->addError($field, "Unknown validation rule: {$rule}.");
                return false;
        }

        return true;
    }

    /**
     * Check if rules contain a specific rule
     * 
     * @param array $rules Rules to check
     * @param string $rule Rule to find
     * @return bool Whether rule exists
     */
    private function hasRule(array $rules, $rule)
    {
        return in_array($rule, $rules) || array_key_exists($rule, $rules);
    }

    /**
     * Validate an email address
     * 
     * @param string $email Email to validate
     * @return bool Whether email is valid
     */
    public function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate a URL
     * 
     * @param string $url URL to validate
     * @return bool Whether URL is valid
     */
    public function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate a date string
     * 
     * @param string $date Date to validate
     * @param string $format Date format (optional)
     * @return bool Whether date is valid
     */
    public function isValidDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate an ID (positive integer)
     * 
     * @param mixed $id ID to validate
     * @return bool Whether ID is valid
     */
    public function isValidId($id)
    {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    /**
     * Validate a username
     * 
     * @param string $username Username to validate
     * @return bool Whether username is valid
     */
    public function isValidUsername($username)
    {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
    }

    /**
     * Validate a password strength
     * 
     * @param string $password Password to validate
     * @param int $minLength Minimum length (default: 8)
     * @param bool $requireMixed Require mixed case (default: true)
     * @param bool $requireNumbers Require numbers (default: true)
     * @param bool $requireSymbols Require symbols (default: false)
     * @return bool Whether password is valid
     */
    public function isValidPassword($password, $minLength = 8, $requireMixed = true, $requireNumbers = true, $requireSymbols = false)
    {
        // Check minimum length
        if (strlen($password) < $minLength) {
            return false;
        }

        // Check for mixed case if required
        if ($requireMixed && !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Check for numbers if required
        if ($requireNumbers && !preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Check for symbols if required
        if ($requireSymbols && !preg_match('/[^a-zA-Z0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Validate a phone number
     * 
     * @param string $phone Phone number to validate
     * @return bool Whether phone number is valid
     */
    public function isValidPhone($phone)
    {
        // Remove common formatting characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Basic validation - at least 10 digits, starting with optional +
        return preg_match('/^\+?[0-9]{10,15}$/', $phone) === 1;
    }

    /**
     * Validate an IP address
     * 
     * @param string $ip IP address to validate
     * @param int $flags FILTER_FLAG_IPV4 or FILTER_FLAG_IPV6 (optional)
     * @return bool Whether IP address is valid
     */
    public function isValidIp($ip, $flags = null)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false;
    }

    /**
     * Sanitize a string for output
     * 
     * @param string $string String to sanitize
     * @return string Sanitized string
     */
    public function sanitizeString($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize an array of data
     * 
     * @param array $data Data to sanitize
     * @param array $fields Fields to sanitize (all if empty)
     * @return array Sanitized data
     */
    public function sanitizeData(array $data, array $fields = [])
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Skip if not in fields (when fields is not empty)
            if (!empty($fields) && !in_array($key, $fields)) {
                $sanitized[$key] = $value;
                continue;
            }

            // Sanitize based on type
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
