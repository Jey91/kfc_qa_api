<?php

namespace Utils;

/**
 * General helper utility functions for the application
 */
class Helper
{
    /**
     * Generate a random string
     * 
     * @param int $length Length of the string
     * @param string $keyspace Characters to use
     * @return string Random string
     */
    public static function randomString($length = 16, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if ($length < 1) {
            throw new \InvalidArgumentException("Length must be a positive integer");
        }

        $pieces = [];
        $max = mb_strlen($keyspace, '8bit') - 1;

        for ($i = 0; $i < $length; ++$i) {
            $pieces[] = $keyspace[random_int(0, $max)];
        }

        return implode('', $pieces);
    }

    /**
     * Generate a secure random token
     * 
     * @param int $length Length of the token
     * @return string Random token
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Format a date/time
     * 
     * @param mixed $datetime Date/time to format (timestamp, DateTime or string)
     * @param string $format Format string (default: Y-m-d H:i:s)
     * @return string Formatted date/time
     */
    public static function formatDate($datetime, $format = 'Y-m-d H:i:s')
    {
        if (is_numeric($datetime)) {
            // Timestamp
            return date($format, $datetime);
        } elseif ($datetime instanceof \DateTime) {
            // DateTime object
            return $datetime->format($format);
        } else {
            // String date
            return date($format, strtotime($datetime));
        }
    }

    /**
     * Get time elapsed string (e.g., "2 days ago")
     * 
     * @param mixed $datetime Date/time (timestamp, DateTime or string)
     * @param bool $full Show full date
     * @return string Time elapsed string
     */
    public static function timeElapsed($datetime, $full = false)
    {
        $now = new \DateTime;

        if (is_numeric($datetime)) {
            $datetime = date('Y-m-d H:i:s', $datetime);
        }

        if (!($datetime instanceof \DateTime)) {
            $datetime = new \DateTime($datetime);
        }

        $diff = $now->diff($datetime);
        $weeks = floor($diff->d / 7);
        $diff->d -= $weeks * 7;

        $string = [
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }

        return $string ? implode(', ', $string) . ($diff->invert ? ' ago' : ' from now') : 'just now';
    }

    /**
     * Get a simple time ago string (e.g., "2 days ago")
     * 
     * @param mixed $datetime Date/time to compare (timestamp, DateTime or string)
     * @return string Time ago string
     */
    public static function timeAgo($datetime)
    {
        $now = new \DateTime();

        if (is_numeric($datetime)) {
            $past = new \DateTime(date('Y-m-d H:i:s', $datetime));
        } elseif ($datetime instanceof \DateTime) {
            $past = $datetime;
        } else {
            $past = new \DateTime($datetime);
        }

        $diff = $now->diff($past);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }

        return 'just now';
    }

    /**
     * Convert bytes to human-readable format
     * 
     * @param int $bytes Bytes to convert
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Truncate a string to a specified length
     * 
     * @param string $string String to truncate
     * @param int $length Maximum length
     * @param string $append String to append if truncated
     * @return string Truncated string
     */
    public static function truncate($string, $length = 100, $append = '...')
    {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        return mb_substr($string, 0, $length) . $append;
    }

    /**
     * Convert a string to slug format
     * 
     * @param string $string String to convert
     * @param string $separator Separator (default: -)
     * @return string Slug
     */
    public static function slugify($string, $separator = '-')
    {
        // Replace non-alphanumeric characters with separator
        $slug = preg_replace('/[^a-zA-Z0-9]/', $separator, $string);
        // Replace multiple separators with a single one
        $slug = preg_replace('/' . preg_quote($separator) . '+/', $separator, $slug);
        // Trim separators from beginning and end
        $slug = trim($slug, $separator);
        // Convert to lowercase
        $slug = strtolower($slug);

        return $slug;
    }

    /**
     * Get a value from an array using dot notation
     * 
     * @param array $array Array to search
     * @param string $key Key using dot notation
     * @param mixed $default Default value if key not found
     * @return mixed Value or default
     */
    public static function arrayGet($array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a value in an array using dot notation
     * 
     * @param array &$array Array to modify
     * @param string $key Key using dot notation
     * @param mixed $value Value to set
     * @return array Modified array
     */
    public static function arraySet(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Check if a string starts with a given substring
     * 
     * @param string $haystack String to search in
     * @param string $needle Substring to search for
     * @return bool Whether string starts with substring
     */
    public static function startsWith($haystack, $needle)
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    /**
     * Check if a string ends with a given substring
     * 
     * @param string $haystack String to search in
     * @param string $needle Substring to search for
     * @return bool Whether string ends with substring
     */
    public static function endsWith($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    /**
     * Convert an object to an array
     * 
     * @param object $object Object to convert
     * @return array Converted array
     */
    public static function objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        return array_map([self::class, 'objectToArray'], (array) $object);
    }

    /**
     * Convert an array to an object
     * 
     * @param array $array Array to convert
     * @return object Converted object
     */
    public static function arrayToObject($array)
    {
        if (!is_array($array)) {
            return $array;
        }

        $object = new \stdClass();

        foreach ($array as $key => $value) {
            $object->$key = is_array($value) ? self::arrayToObject($value) : $value;
        }

        return $object;
    }

    /**
     * Get the current URL
     * 
     * @param bool $includeQuery Include query string
     * @return string Current URL
     */
    public static function currentUrl($includeQuery = true)
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!$includeQuery) {
            $uri = strtok($uri, '?');
        }

        return $protocol . '://' . $host . $uri;
    }

    /**
     * Get the base URL of the application
     * 
     * @return string Base URL
     */
    public static function baseUrl()
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * Redirect to a URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function redirect($url, $statusCode = 302)
    {
        header('Location: ' . $url, true, $statusCode);
        exit;
    }

    /**
     * Get a value from $_GET, $_POST, or $_COOKIE
     * 
     * @param string $key Key to get
     * @param mixed $default Default value if key not found
     * @return mixed Value or default
     */
    public static function input($key, $default = null)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        if (isset($_COOKIE[$key])) {
            return $_COOKIE[$key];
        }

        return $default;
    }

    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    public static function getClientIp()
    {
        $keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent string
     * 
     * @return string User agent
     */
    public static function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    /**
     * Check if request is AJAX
     * 
     * @return bool Whether request is AJAX
     */
    public static function isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Check if request is over HTTPS
     * 
     * @return bool Whether request is over HTTPS
     */
    public static function isHttps()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Get request method
     * 
     * @return string Request method
     */
    public static function getRequestMethod()
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Generate a CSRF token
     * 
     * @param string $tokenId Token ID
     * @return string CSRF token
     */
    public static function generateCsrfToken($tokenId = 'default')
    {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }

        $token = self::generateToken();
        $_SESSION['csrf_tokens'][$tokenId] = [
            'token' => $token,
            'time' => time()
        ];

        return $token;
    }

    /**
     * Validate a CSRF token
     * 
     * @param string $token Token to validate
     * @param string $tokenId Token ID
     * @param int $expireTime Token expiration time in seconds
     * @return bool Whether token is valid
     */
    public static function validateCsrfToken($token, $tokenId = 'default', $expireTime = 3600)
    {
        if (!isset($_SESSION['csrf_tokens'][$tokenId])) {
            return false;
        }

        $storedToken = $_SESSION['csrf_tokens'][$tokenId];

        // Check if token has expired
        if (time() - $storedToken['time'] > $expireTime) {
            unset($_SESSION['csrf_tokens'][$tokenId]);
            return false;
        }

        // Check if token matches
        if ($token !== $storedToken['token']) {
            return false;
        }

        // Token is valid, remove it to prevent reuse
        unset($_SESSION['csrf_tokens'][$tokenId]);

        return true;
    }

    /**
     * Escape HTML entities
     * 
     * @param string $string String to escape
     * @return string Escaped string
     */
    public static function escape($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convert a string to camelCase
     * 
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function camelCase($string)
    {
        $string = preg_replace('/[^a-zA-Z0-9]+/', ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return lcfirst($string);
    }

    /**
     * Convert a string to snake_case
     * 
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function snakeCase($string)
    {
        $string = preg_replace('/[^a-zA-Z0-9]+/', '_', $string);
        $string = preg_replace('/([a-z])([A-Z])/', '$1_$2', $string);

        return strtolower($string);
    }

    /**
     * Convert a string to kebab-case
     * 
     * @param string $string String to convert
     * @return string Converted string
     */
    public static function kebabCase($string)
    {
        $string = preg_replace('/[^a-zA-Z0-9]+/', '-', $string);
        $string = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);

        return strtolower($string);
    }

    /**
     * Get a unique identifier
     * 
     * @param bool $moreEntropy Add more entropy
     * @return string Unique identifier
     */
    public static function uuid($moreEntropy = false)
    {
        return uniqid('', $moreEntropy);
    }

    /**
     * Parse a URL and return its components
     * 
     * @param string $url URL to parse
     * @return array URL components
     */
    public static function parseUrl($url)
    {
        return parse_url($url);
    }

    /**
     * Build a URL from components
     * 
     * @param array $parts URL components
     * @return string Built URL
     */
    public static function buildUrl($parts)
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    /**
     * Get a value from environment variables
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed Environment variable value or default
     */
    public static function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }

    /**
     * Get a value from JSON file
     * 
     * @param string $file JSON file path
     * @param string $key Key using dot notation
     * @param mixed $default Default value if key not found
     * @return mixed Value or default
     */
    public static function jsonConfig($file, $key = null, $default = null)
    {
        if (!file_exists($file)) {
            return $default;
        }

        $json = json_decode(file_get_contents($file), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }

        if ($key === null) {
            return $json;
        }

        return self::arrayGet($json, $key, $default);
    }

    /**
     * Convert a value to boolean
     * 
     * @param mixed $value Value to convert
     * @return bool Converted value
     */
    public static function toBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value > 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', 'yes', 'y', '1', 'on']);
        }

        return (bool) $value;
    }


    /**
     * Generate a unique code combining timestamp and hash with customizable length
     * 
     * @param int $length Desired total length of the code (must be >= 10)
     * @return string Code of specified length
     */
    public static function generateDbCode($length = 28)
    {
        if ($length < 10) {
            throw new InvalidArgumentException("Length must be at least 10 characters for uniqueness.");
        }

        // Step 1: Get high-precision timestamp
        $microtime = microtime(true);
        $timestamp = floor($microtime);
        $microseconds = substr(($microtime - $timestamp), 2, 6); // Extract 6-digit microseconds

        // Step 2: Create a unique seed for hashing
        $pid = getmypid();
        $random = mt_rand();
        $seed = $timestamp . $microseconds . $pid . $random;

        // Step 3: Generate a hash
        $hash = md5($seed); // 32 chars
        // Optionally use SHA1 or SHA256 for longer codes:
        // $hash = hash('sha256', $seed); // 64 chars

        // Step 4: Build the final code
        $timePart = $timestamp; // e.g., 10-digit timestamp
        $remainingLength = $length - strlen($timePart);
        $code = $timePart . substr($hash, 0, $remainingLength);

        return strtoupper($code);
    }

    /**
     * Generate a "random key" combining timestamp, microseconds, process ID, and a hash
     * 
     * @param string $prefix Optional prefix to include at the start of the key
     * @param int $length Desired total length of the key (including prefix)
     * @return string Unique key of specified length
     */
    public static function generateRandomKey($prefix = '', $length = 30)
    {
        // Step 1: High-precision timestamp
        $microtime = microtime(true);
        $timestamp = floor($microtime);
        $microseconds = substr(str_replace('.', '', $microtime), -6); // microsecond part

        // Step 2: Create a unique seed
        $pid = getmypid();                // Process ID
        $rand = bin2hex(random_bytes(8)); // 16-char random hex
        $seed = $timestamp . $microseconds . $pid . $rand;

        // Step 3: Hash the seed using MD5 (or SHA1/SHA256 if more length is needed)
        $hash = md5($seed); // 32 chars

        // Step 4: Build final key
        $base = $prefix . $timestamp . $microseconds . $pid . $hash;

        // Step 5: Remove unwanted characters and trim to desired length
        $key = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $base), 0, $length));

        return $key;
    }

    /**
     * Encrypt a data with AES-256-CBC
     * 
     * @param string $data value to encrypt
     * @param string $secretKey 32-byte secret key
     * @return string Base64-encoded string containing IV and encrypted data
     */
    public static function encryptData($data, $secretKey)
    {
        // Ensure key is exactly 32 bytes
        $key = hash('sha256', $secretKey, true);

        // Generate a random IV (16 bytes for AES-256-CBC)
        $iv = random_bytes(16);

        // Encrypt
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // Return IV + encrypted data (Base64 encoded)
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a data encrypted with decryptData()
     * 
     * @param string $data Base64-encoded encrypted string
     * @param string $secretKey 32-byte secret key
     * @return string|false Decrypted data or false on failure
     */
    public static function decryptData($data, $secretKey)
    {
        $key = hash('sha256', $secretKey, true);

        // Decode the Base64 string
        $data = base64_decode($data);

        // Extract IV and encrypted data
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        // Decrypt
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }

}
