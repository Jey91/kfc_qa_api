<?php

namespace Core;

/**
 * Request handler class
 * 
 * Handles HTTP requests and provides methods to access request data
 */
class Request
{
    /**
     * @var array Request headers
     */
    private $headers = [];

    /**
     * @var array URL parameters
     */
    private $params = [];

    /**
     * @var array Query string parameters
     */
    private $query = [];

    /**
     * @var array Request body data
     */
    private $data = [];

    /**
     * @var array Uploaded files
     */
    private $files = [];

    /**
     * @var string Request method
     */
    private $method;

    /**
     * @var string Request URI
     */
    private $uri;

    /**
     * @var string Request path
     */
    private $path;

    /**
     * @var string Content type
     */
    private $contentType;

    /**
     * @var bool Whether request is AJAX
     */
    private $isAjax = false;

    /**
     * @var bool Whether request is JSON
     */
    private $isJson = false;

    /**
     * @var bool Whether request is HTTPS
     */
    private $isSecure = false;

    /**
     * @var string Client IP address
     */
    private $clientIp;

    /**
     * @var string User agent
     */
    private $userAgent;

    /**
     * @var array Accepted content types
     */
    private $accepts = [];

    /**
     * @var string Request body raw content
     */
    private $rawContent;

    /**
     * @var mixed Authenticated user
     */
    private $user = null;

    /**
     * @var string Current route
     */
    private $route = null;

    /**
     * @var array Cookies
     */
    private $cookies = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize request data
     */
    private function initialize()
    {
        // Get request method
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Handle method override via _method parameter or X-HTTP-Method-Override header
        if ($this->method === 'POST') {
            if (isset($_POST['_method'])) {
                $this->method = strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $this->method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        // Get request URI and path
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($this->uri, PHP_URL_PATH);

        // Get query parameters
        $this->query = $_GET;

        // Get request headers
        $this->parseHeaders();

        // Get content type
        $this->contentType = $this->getHeader('Content-Type');

        // Check if request is AJAX
        $this->isAjax = $this->getHeader('X-Requested-With') === 'XMLHttpRequest';

        // Check if request is JSON
        $this->isJson = strpos($this->contentType, 'application/json') !== false;

        // Check if request is secure
        $this->isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        // Get client IP
        $this->clientIp = $this->detectClientIp();

        // Get user agent
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Parse accepted content types
        $this->parseAcceptHeader();

        // Get request body data
        $this->parseRequestData();

        // Get uploaded files
        $this->files = $_FILES;

        // Initialize cookies
        $this->cookies = $_COOKIE;
    }

    /**
     * Parse request headers
     */
    private function parseHeaders()
    {
        // Use getallheaders() if available
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            if ($headers !== false) {
                $this->headers = array_change_key_case($headers, CASE_LOWER);
                return;
            }
        }

        // Fallback to $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $this->headers[strtolower($header)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
                $this->headers[strtolower($header)] = $value;
            }
        }
    }

    /**
     * Parse Accept header
     */
    private function parseAcceptHeader()
    {
        $acceptHeader = $this->getHeader('Accept');

        if (empty($acceptHeader)) {
            $this->accepts = ['*/*'];
            return;
        }

        // Split by comma and sort by quality
        $accepts = explode(',', $acceptHeader);
        $sorted = [];

        foreach ($accepts as $accept) {
            $parts = explode(';', $accept);
            $type = trim($parts[0]);
            $quality = 1.0;

            if (isset($parts[1]) && strpos($parts[1], 'q=') !== false) {
                $quality = (float) substr($parts[1], 2);
            }

            $sorted[$type] = $quality;
        }

        arsort($sorted);
        $this->accepts = array_keys($sorted);
    }

    /**
     * Parse request data based on content type
     */
    private function parseRequestData()
    {
        // Get raw content
        $this->rawContent = file_get_contents('php://input');

        // Handle different content types
        if ($this->isJson) {
            // Parse JSON data
            $jsonData = json_decode($this->rawContent, true);
            $this->data = is_array($jsonData) ? $jsonData : [];
        } elseif (strpos($this->contentType, 'application/x-www-form-urlencoded') !== false) {
            // Parse form data
            parse_str($this->rawContent, $formData);
            $this->data = $formData;
        } elseif (strpos($this->contentType, 'multipart/form-data') !== false) {
            // Use $_POST for multipart form data
            $this->data = $_POST;
        } else {
            // Default to $_POST for other content types
            $this->data = $_POST;
        }

        // For PUT, PATCH, DELETE methods, merge data from php://input if not already parsed
        if (in_array($this->method, ['PUT', 'PATCH', 'DELETE']) && empty($this->data)) {
            parse_str($this->rawContent, $data);
            $this->data = $data;
        }
    }

    /**
     * Detect client IP address
     * 
     * @return string Client IP address
     */
    private function detectClientIp()
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get request method
     * 
     * @return string Request method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Check if request method matches
     * 
     * @param string $method Method to check
     * @return bool Whether method matches
     */
    public function isMethod($method)
    {
        return strtoupper($method) === $this->method;
    }

    /**
     * Get request URI
     * 
     * @return string Request URI
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Get request path
     * 
     * @return string Request path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get a specific header
     * 
     * @param string $name Header name
     * @param mixed $default Default value if header not found
     * @return mixed Header value or default
     */
    public function getHeader($name, $default = null)
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Check if header exists
     * 
     * @param string $name Header name
     * @return bool Whether header exists
     */
    public function hasHeader($name)
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Get all headers
     * 
     * @return array All headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get a URL parameter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter not found
     * @return mixed Parameter value or default
     */
    public function getParam($name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * Get all URL parameters
     * 
     * @return array All URL parameters
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Set URL parameters
     * 
     * @param array $params URL parameters
     * @return $this For method chaining
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Get a query parameter
     * 
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter not found
     * @return mixed Parameter value or default
     */
    public function getQuery($name, $default = null)
    {
        return $this->query[$name] ?? $default;
    }

    /**
     * Get all query parameters
     * 
     * @return array All query parameters
     */
    public function getQueryParams()
    {
        return $this->query;
    }

    /**
     * Get a request body field
     * 
     * @param string $name Field name
     * @param mixed $default Default value if field not found
     * @return mixed Field value or default
     */
    public function getData($name = null, $default = null)
    {
        if ($name === null || $name === '') {
            return $this->data;
        }

        // Check if the key exists and is not an empty string
        if (isset($this->data[$name]) && $this->data[$name] !== '') {
            return $this->data[$name];
        }

        return $default;
    }

    /**
     * Set a value in the request data
     * 
     * @param string $name Field name
     * @param mixed $value Value to set
     * @return $this For method chaining
     */
    public function setData($name, $value)
    {
        // Since $data is private, we need to get all data first
        $allData = $this->getData();

        // Modify the value
        $allData[$name] = $value;

        // Replace the entire data array
        $this->data = $allData;

        return $this;
    }

    /**
     * Get all request body data
     * 
     * @return array All request body data
     */
    public function getAllData()
    {
        return $this->data;
    }

    /**
     * Get raw request body content
     * 
     * @return string Raw content
     */
    public function getRawContent()
    {
        return $this->rawContent;
    }

    /**
     * Get an uploaded file
     * 
     * @param string $name File name
     * @return array|null File data or null if not found
     */
    public function getFile($name)
    {
        return $this->files[$name] ?? null;
    }

    /**
     * Get all uploaded files
     * 
     * @return array All uploaded files
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Check if file was uploaded
     * 
     * @param string $name File name
     * @return bool Whether file was uploaded
     */
    public function hasFile($name)
    {
        return isset($this->files[$name]) &&
            $this->files[$name]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Get content type
     * 
     * @return string Content type
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Check if request is AJAX
     * 
     * @return bool Whether request is AJAX
     */
    public function isAjax()
    {
        return $this->isAjax;
    }

    /**
     * Check if request is JSON
     * 
     * @return bool Whether request is JSON
     */
    public function isJson()
    {
        return $this->isJson;
    }

    /**
     * Check if request is secure (HTTPS)
     * 
     * @return bool Whether request is secure
     */
    public function isSecure()
    {
        return $this->isSecure;
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public function getClientIp()
    {
        return $this->clientIp;
    }
    
    /**
     * Get user agent
     * 
     * @return string User agent
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Get accepted content types
     * 
     * @return array Accepted content types
     */
    public function getAccepts()
    {
        return $this->accepts;
    }

    /**
     * Check if client accepts a content type
     * 
     * @param string $contentType Content type to check
     * @return bool Whether content type is accepted
     */
    public function accepts($contentType)
    {
        // If client accepts anything
        if (in_array('*/*', $this->accepts)) {
            return true;
        }

        // Check for exact match
        if (in_array($contentType, $this->accepts)) {
            return true;
        }

        // Check for type/*
        $type = explode('/', $contentType)[0];
        return in_array($type . '/*', $this->accepts);
    }

    /**
     * Check if request wants JSON response
     * 
     * @return bool Whether JSON is preferred
     */
    public function wantsJson()
    {
        return $this->accepts('application/json') &&
            (!$this->accepts('text/html') ||
                array_search('application/json', $this->accepts) <
                array_search('text/html', $this->accepts));
    }

    /**
     * Get request base URL
     * 
     * @return string Base URL
     */
    public function getBaseUrl()
    {
        $protocol = $this->isSecure ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * Get request full URL
     * 
     * @return string Full URL
     */
    public function getFullUrl()
    {
        return $this->getBaseUrl() . $this->uri;
    }

    /**
     * Get a value from any input source (params, query, data)
     * 
     * @param string $name Input name
     * @param mixed $default Default value if input not found
     * @return mixed Input value or default
     */
    public function input($name, $default = null)
    {
        // Check URL parameters
        if (isset($this->params[$name])) {
            return $this->params[$name];
        }

        // Check query parameters
        if (isset($this->query[$name])) {
            return $this->query[$name];
        }

        // Check request body data
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return $default;
    }

    /**
     * Get all input data (params, query, data)
     * 
     * @return array All input data
     */
    public function all()
    {
        return array_merge($this->params, $this->query, $this->data);
    }

    /**
     * Get only specified inputs
     * 
     * @param array $keys Input keys to get
     * @return array Filtered input data
     */
    public function only(array $keys)
    {
        $result = [];
        $allInputs = $this->all();

        foreach ($keys as $key) {
            if (isset($allInputs[$key])) {
                $result[$key] = $allInputs[$key];
            }
        }

        return $result;
    }

    /**
     * Get all inputs except specified ones
     * 
     * @param array $keys Input keys to exclude
     * @return array Filtered input data
     */
    public function except(array $keys)
    {
        $result = $this->all();

        foreach ($keys as $key) {
            unset($result[$key]);
        }

        return $result;
    }

    /**
     * Check if input exists
     * 
     * @param string $name Input name
     * @return bool Whether input exists
     */
    public function has($name)
    {
        $allInputs = $this->all();
        return isset($allInputs[$name]);
    }

    /**
     * Check if all inputs exist
     * 
     * @param array $names Input names
     * @return bool Whether all inputs exist
     */
    public function hasAll(array $names)
    {
        $allInputs = $this->all();

        foreach ($names as $name) {
            if (!isset($allInputs[$name])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if any of the inputs exist
     * 
     * @param array $names Input names
     * @return bool Whether any input exists
     */
    public function hasAny(array $names)
    {
        $allInputs = $this->all();

        foreach ($names as $name) {
            if (isset($allInputs[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate request data
     * 
     * @param array $rules Validation rules
     * @return array Validation errors or empty array if valid
     */
    public function validate(array $rules)
    {
        $validator = new \Utils\Validator();
        $validator->validate($this->all(), $rules);

        return $validator->getErrors();
    }

    /**
     * Get bearer token from Authorization header
     * 
     * @return string|null Bearer token or null if not found
     */
    public function getBearerToken()
    {
        $bodyData = $this->getData();

        if( array_key_exists("accessToken", $bodyData)){
            return $bodyData['accessToken'];
        }

        return null;
    }

    /**
     * Get basic auth credentials
     * 
     * @return array|null Username and password or null if not found
     */
    public function getBasicAuth()
    {
        $authHeader = $this->getHeader('Authorization');

        if (empty($authHeader) || strpos($authHeader, 'Basic ') !== 0) {
            return null;
        }

        $credentials = base64_decode(substr($authHeader, 6));

        if ($credentials === false || strpos($credentials, ':') === false) {
            return null;
        }

        list($username, $password) = explode(':', $credentials, 2);

        return [
            'username' => $username,
            'password' => $password
        ];
    }

    /**
     * Check if request is from a specific domain
     * 
     * @param string $domain Domain to check
     * @return bool Whether request is from the domain
     */
    public function isFromDomain($domain)
    {
        $referer = $this->getHeader('Referer');

        if (empty($referer)) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        return $refererHost === $domain || substr($refererHost, -strlen('.' . $domain) - 1) === '.' . $domain;
    }

    /**
     * Check if request has a specific content type
     * 
     * @param string $contentType Content type to check
     * @return bool Whether request has the content type
     */
    public function hasContentType($contentType)
    {
        return strpos($this->contentType, $contentType) !== false;
    }

    /**
     * Get pagination parameters
     * 
     * @param int $defaultPage Default page number
     * @param int $defaultLimit Default items per page
     * @param int $maxLimit Maximum items per page
     * @return array Pagination parameters
     */
    public function getPaginationParams($defaultPage = 1, $defaultLimit = 20, $maxLimit = 100)
    {
        $page = max(1, (int) $this->getQuery('page', $defaultPage));
        $limit = min($maxLimit, max(1, (int) $this->getQuery('limit', $defaultLimit)));

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit
        ];
    }

    /**
     * Get sorting parameters
     * 
     * @param string $defaultField Default sort field
     * @param string $defaultDirection Default sort direction
     * @param array $allowedFields Allowed sort fields
     * @return array Sorting parameters
     */
    public function getSortParams($defaultField = 'id', $defaultDirection = 'desc', array $allowedFields = [])
    {
        $field = $this->getQuery('sort', $defaultField);
        $direction = strtolower($this->getQuery('direction', $defaultDirection));

        // Check if field is allowed
        if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
            $field = $defaultField;
        }

        // Validate direction
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = $defaultDirection;
        }

        return [
            'field' => $field,
            'direction' => $direction
        ];
    }

    /**
     * Get filter parameters
     * 
     * @param array $allowedFilters Allowed filter fields
     * @return array Filter parameters
     */
    public function getFilterParams(array $allowedFilters = [])
    {
        $filters = [];
        $queryParams = $this->getQueryParams();

        foreach ($queryParams as $key => $value) {
            // Skip pagination and sorting parameters
            if (in_array($key, ['page', 'limit', 'sort', 'direction'])) {
                continue;
            }

            // Check if filter is allowed
            if (!empty($allowedFilters) && !in_array($key, $allowedFilters)) {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    /**
     * Get search parameter
     * 
     * @param string $paramName Search parameter name
     * @return string|null Search query or null if not found
     */
    public function getSearchQuery($paramName = 'q')
    {
        $query = $this->getQuery($paramName);

        if (empty($query)) {
            return null;
        }

        return $query;
    }

    /**
     * Set the authenticated user
     * 
     * @param mixed $user User object/data
     * @return $this For method chaining
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get the authenticated user
     * 
     * @return mixed User object/data or null if not authenticated
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the current route
     * 
     * @param string $route Route name/identifier
     * @return $this For method chaining
     */
    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Get the current route
     * 
     * @return string|null Route name/identifier or null if not set
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Check if request accepts HTML
     * 
     * @return bool Whether request accepts HTML
     */
    public function acceptsHtml()
    {
        return $this->accepts('text/html');
    }

    /**
     * Check if a cookie exists
     * 
     * @param string $name Cookie name
     * @return bool Whether cookie exists
     */
    public function hasCookie($name)
    {
        return isset($this->cookies[$name]);
    }

    /**
     * Get a cookie value
     * 
     * @param string $name Cookie name
     * @param mixed $default Default value if cookie not found
     * @return mixed Cookie value or default
     */
    public function getCookie($name, $default = null)
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * Get all cookies
     * 
     * @return array All cookies
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * Check if a query parameter exists
     * 
     * @param string $name Parameter name
     * @return bool Whether parameter exists
     */
    public function hasQuery($name)
    {
        return isset($this->query[$name]);
    }

    /**
     * Check if a request parameter exists
     * 
     * @param string $name Parameter name
     * @return bool Whether parameter exists
     */
    public function hasParam($name)
    {
        return isset($this->params[$name]);
    }
}
