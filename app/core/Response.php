<?php

namespace Core;


/**
 * Response handler class
 * 
 * Handles HTTP responses and provides methods to send different types of responses
 */
class Response
{

    /**
     * @var array Application configuration
     */
    private $appConfig;

    /**
     * @var int HTTP status code
     */
    private $statusCode = 200;

    /**
     * @var array Response headers
     */
    private $headers = [];

    /**
     * @var mixed Response content
     */
    private $content = '';

    /**
     * @var string Content type
     */
    private $contentType = 'text/html';

    /**
     * @var string Character set
     */
    private $charset = 'UTF-8';

    /**
     * @var array HTTP status codes and messages
     */
    private static $statusTexts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required'
    ];

    /**
     * @var mixed Response data
     */
    private $data;

    /**
     * Constructor
     */
    public function __construct($appConfig = null)
    {
        $this->appConfig = $appConfig;
        // Set default headers
        $this->headers = [
            'Content-Type' => $this->contentType . '; charset=' . $this->charset
        ];
    }

    /**
     * Set HTTP status code
     * 
     * @param int $statusCode HTTP status code
     * @return $this For method chaining
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = (int) $statusCode;
        return $this;
    }

    /**
     * Get HTTP status code
     * 
     * @return int HTTP status code
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Get HTTP status text for a status code
     * 
     * @param int $statusCode HTTP status code
     * @return string HTTP status text
     */
    public static function getStatusText($statusCode)
    {
        return self::$statusTexts[$statusCode] ?? 'Unknown Status';
    }

    /**
     * Set response content
     * 
     * @param mixed $content Response content
     * @return $this For method chaining
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response content
     * 
     * @return mixed Response content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set a response header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @param bool $replace Whether to replace existing header
     * @return $this For method chaining
     */
    public function setHeader($name, $value, $replace = true)
    {
        if ($replace || !isset($this->headers[$name])) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    /**
     * Set multiple response headers
     * 
     * @param array $headers Headers to set
     * @param bool $replace Whether to replace existing headers
     * @return $this For method chaining
     */
    public function setHeaders(array $headers, $replace = true)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value, $replace);
        }

        return $this;
    }

    /**
     * Get a response header
     * 
     * @param string $name Header name
     * @param mixed $default Default value if header not found
     * @return mixed Header value or default
     */
    public function getHeader($name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get all response headers
     * 
     * @return array All headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Remove a response header
     * 
     * @param string $name Header name
     * @return $this For method chaining
     */
    public function removeHeader($name)
    {
        unset($this->headers[$name]);
        return $this;
    }

    /**
     * Set content type
     * 
     * @param string $contentType Content type
     * @return $this For method chaining
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
        $this->setHeader('Content-Type', $contentType . '; charset=' . $this->charset);

        return $this;
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
     * Set character set
     * 
     * @param string $charset Character set
     * @return $this For method chaining
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
        $this->setHeader('Content-Type', $this->contentType . '; charset=' . $charset);

        return $this;
    }

    /**
     * Get character set
     * 
     * @return string Character set
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Send HTTP headers
     * 
     * @return $this For method chaining
     */
    public function sendHeaders()
    {

        // Send HTTP status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        return $this;
    }

    /**
     * Send response content
     * 
     * @return $this For method chaining
     */
    public function sendContent()
    {
        echo $this->prepareContent();
        return $this;
    }

    /**
     * Prepare content for sending
     * 
     * @return string Prepared content
     */
    private function prepareContent()
    {
        if (is_array($this->content) || is_object($this->content)) {
            if ($this->contentType === 'application/json') {
                return json_encode($this->content);
            } else {
                return print_r($this->content, true);
            }
        }

        return (string) $this->content;
    }

    /**
     * Send the response (headers and content)
     * 
     * @return $this For method chaining
     */
    public function send()
    {
        $this->sendHeaders();
        $this->sendContent();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        return $this;
    }

    /**
     * Create a JSON response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return $this For method chaining
     */
    public function json($data, $statusCode = 200, array $headers = [])
    {
        $this->setContentType('application/json');
        // $this->setStatusCode($statusCode);
        $this->setHeaders($headers);
        $this->setContent($data);

        return $this;
    }

    /**
     * Create a successful JSON response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     * @return $this For method chaining
     */
    public function success($data = null, $message = 'Success', $statusCode = 200)
    {
        $response = [
            'status_code' => $statusCode,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $this->json($response, $statusCode);
    }

    /**
     * Create an error JSON response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param mixed $errors Validation errors
     * @return $this For method chaining
     */
    public function error($message = 'Error', $statusCode = 400, $errors = null)
    {
        $response = [
            'status_code' => $statusCode,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $this->json($response, $statusCode);
    }

    /**
     * Create a method not allowed response
     * 
     * @param mixed $message Error message or data
     * @return $this For method chaining
     */
    public function methodNotAllowed($message = 'Method Not Allowed')
    {
        if (is_array($message) && isset($message['message'])) {
            return $this->error($message['message'], 405, $message);
        }
        return $this->error($message, 405);
    }

    /**
     * Create a validation error response
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function validationError(array $errors, $message = 'Validation failed')
    {
        if ($this->appConfig['environment'] === 'development') {
            // In development mode, return detailed error message
            return $this->error($message, 422, $errors);
        }
        return $this->error($message, 422);
    }

    /**
     * Create a bad request response
     * 
     * @param mixed $message Error message or data
     * @return $this For method chaining
     */
    public function badRequest($message = 'Bad Request')
    {
        if (is_array($message) && isset($message['message'])) {
            return $this->error($message['message'], 400, $message);
        }
        return $this->error($message, 400);
    }

    /**
     * Create a not found response
     * 
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function notFound($message = 'Resource not found')
    {
        return $this->error($message, 404);
    }

    /**
     * Create an unauthorized response
     * 
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }

    /**
     * Create a forbidden response
     * 
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function forbidden($message = 'Forbidden')
    {
        return $this->error($message, 403);
    }

    /**
     * Create a server error response
     * 
     * @param string $message Error message
     * @return $this For method chaining
     */
    public function serverError($message = 'Internal Server Error')
    {
        return $this->error($message, 500);
    }

    /**
     * Create a created response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return $this For method chaining
     */
    public function created($data = null, $message = 'Resource created successfully')
    {
        return $this->success($data, $message, 200);
    }

    /**
     * Create a no content response
     * 
     * @return $this For method chaining
     */
    public function noContent()
    {
        return $this->setStatusCode(204)->setContent('');
    }

    /**
     * Create a redirect response
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code
     * @return $this For method chaining
     */
    public function redirect($url, $statusCode = 302)
    {
        $this->setHeader('Location', $url);
        $this->setStatusCode($statusCode);
        $this->setContent('');

        return $this;
    }

    /**
     * Create a permanent redirect response
     * 
     * @param string $url URL to redirect to
     * @return $this For method chaining
     */
    public function permanentRedirect($url)
    {
        return $this->redirect($url, 301);
    }

    /**
     * Create a file download response
     * 
     * @param string $filePath Path to file
     * @param string $fileName Download file name
     * @param string $contentType Content type
     * @return $this For method chaining
     */
    public function download($filePath, $fileName = null, $contentType = null)
    {
        if (!file_exists($filePath)) {
            return $this->notFound('File not found');
        }

        $fileName = $fileName ?: basename($filePath);
        $contentType = $contentType ?: mime_content_type($filePath) ?: 'application/octet-stream';

        $this->setHeader('Content-Type', $contentType);
        $this->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $this->setHeader('Content-Length', filesize($filePath));
        $this->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->setHeader('Pragma', 'public');

        $this->setContent(file_get_contents($filePath));

        return $this;
    }

    /**
     * Create a file response (inline)
     * 
     * @param string $filePath Path to file
     * @param string $contentType Content type
     * @return $this For method chaining
     */
    public function file($filePath, $contentType = null)
    {
        if (!file_exists($filePath)) {
            return $this->notFound('File not found');
        }

        $contentType = $contentType ?: mime_content_type($filePath) ?: 'application/octet-stream';

        $this->setHeader('Content-Type', $contentType);
        $this->setHeader('Content-Length', filesize($filePath));

        $this->setContent(file_get_contents($filePath));

        return $this;
    }

    /**
     * Create an HTML response
     * 
     * @param string $html HTML content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return $this For method chaining
     */
    public function html($html, $statusCode = 200, array $headers = [])
    {
        $this->setContentType('text/html');
        // $this->setStatusCode($statusCode);
        $this->setHeaders($headers);
        $this->setContent($html);

        return $this;
    }

    /**
     * Create a plain text response
     * 
     * @param string $text Text content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return $this For method chaining
     */
    public function text($text, $statusCode = 200, array $headers = [])
    {
        $this->setContentType('text/plain');
        // $this->setStatusCode($statusCode);
        $this->setHeaders($headers);
        $this->setContent($text);

        return $this;
    }

    /**
     * Create an XML response
     * 
     * @param string $xml XML content
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     * @return $this For method chaining
     */
    public function xml($xml, $statusCode = 200, array $headers = [])
    {
        $this->setContentType('application/xml');
        // $this->setStatusCode($statusCode);
        $this->setHeaders($headers);
        $this->setContent($xml);

        return $this;
    }

    /**
     * Set a cookie
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expire Expiration time
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Whether cookie is secure
     * @param bool $httpOnly Whether cookie is HTTP only
     * @param string $sameSite SameSite attribute (None, Lax, Strict)
     * @return $this For method chaining
     */
    public function setCookie($name, $value, $expire = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true, $sameSite = 'Lax')
    {
        $cookieOptions = [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly
        ];

        if (PHP_VERSION_ID >= 70300) {
            $cookieOptions['samesite'] = $sameSite;
        } else {
            // For PHP < 7.3, add SameSite attribute to the cookie string
            $cookieOptions['path'] = $path . '; SameSite=' . $sameSite;
        }

        setcookie($name, $value, $cookieOptions);

        return $this;
    }

    /**
     * Delete a cookie
     * 
     * @param string $name Cookie name
     * @param string $path Cookie path
     * @param string $domain Cookie domain
     * @param bool $secure Whether cookie is secure
     * @param bool $httpOnly Whether cookie is HTTP only
     * @return $this For method chaining
     */
    public function deleteCookie($name, $path = '/', $domain = '', $secure = false, $httpOnly = true)
    {
        return $this->setCookie($name, '', time() - 3600, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Enable CORS (Cross-Origin Resource Sharing)
     * 
     * @param string $origin Allowed origin
     * @param array $methods Allowed methods
     * @param array $headers Allowed headers
     * @param int $maxAge Max age
     * @param bool $credentials Whether to allow credentials
     * @return $this For method chaining
     */
    public function enableCors($origin = '*', array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], array $headers = ['Content-Type', 'Authorization'], $maxAge = 86400, $credentials = true)
    {
        $this->setHeader('Access-Control-Allow-Origin', $origin);

        if ($credentials) {
            $this->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        if (!empty($methods)) {
            $this->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));
        }

        if (!empty($headers)) {
            $this->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));
        }

        if ($maxAge > 0) {
            $this->setHeader('Access-Control-Max-Age', $maxAge);
        }

        return $this;
    }

    /**
     * Handle preflight OPTIONS request
     * 
     * @param string $origin Allowed origin
     * @param array $methods Allowed methods
     * @param array $headers Allowed headers
     * @param int $maxAge Max age
     * @param bool $credentials Whether to allow credentials
     * @return $this For method chaining
     */
    public function handlePreflightRequest($origin = '*', array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], array $headers = ['Content-Type', 'Authorization'], $maxAge = 86400, $credentials = true)
    {
        $this->enableCors($origin, $methods, $headers, $maxAge, $credentials);
        // $this->setStatusCode(204);
        $this->setContent('');

        return $this;
    }

    /**
     * Set cache control headers
     * 
     * @param int $maxAge Max age in seconds
     * @param bool $public Whether cache is public
     * @param bool $mustRevalidate Whether cache must revalidate
     * @return $this For method chaining
     */
    public function setCache($maxAge = 0, $public = true, $mustRevalidate = true)
    {
        $cacheControl = $public ? 'public' : 'private';

        if ($maxAge > 0) {
            $cacheControl .= ', max-age=' . $maxAge;
        } else {
            $cacheControl .= ', no-cache, no-store';
        }

        if ($mustRevalidate) {
            $cacheControl .= ', must-revalidate';
        }

        $this->setHeader('Cache-Control', $cacheControl);

        if ($maxAge > 0) {
            $this->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        } else {
            $this->setHeader('Expires', '0');
        }

        return $this;
    }

    /**
     * Disable caching
     * 
     * @return $this For method chaining
     */
    public function noCache()
    {
        return $this->setCache(0, false, true);
    }

    /**
     * Set ETag header
     * 
     * @param string $etag ETag value
     * @param bool $weak Whether ETag is weak
     * @return $this For method chaining
     */
    public function setEtag($etag, $weak = false)
    {
        $this->setHeader('ETag', ($weak ? 'W/' : '') . '"' . $etag . '"');
        return $this;
    }

    /**
     * Set Last-Modified header
     * 
     * @param int|string $time Timestamp or date string
     * @return $this For method chaining
     */
    public function setLastModified($time)
    {
        if (is_string($time)) {
            $time = strtotime($time);
        }

        $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', $time) . ' GMT');
        return $this;
    }

    /**
     * Create a paginated response
     * 
     * @param array $items Paginated items
     * @param int $total Total number of items
     * @param int $perPage Items per page
     * @param int $currentPage Current page
     * @param array $meta Additional metadata
     * @return $this For method chaining
     */
    public function paginate(array $items, $total, $perPage, $currentPage, array $meta = [])
    {
        $lastPage = max(1, ceil($total / $perPage));

        $response = [
            'data' => $items,
            'meta' => array_merge([
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total
            ], $meta)
        ];

        // Add pagination links
        $response['links'] = [
            'first' => $this->getPaginationUrl(1, $perPage),
            'last' => $this->getPaginationUrl($lastPage, $perPage),
            'prev' => $currentPage > 1 ? $this->getPaginationUrl($currentPage - 1, $perPage) : null,
            'next' => $currentPage < $lastPage ? $this->getPaginationUrl($currentPage + 1, $perPage) : null
        ];

        return $this->json($response);
    }

    /**
     * Get pagination URL
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return string Pagination URL
     */
    private function getPaginationUrl($page, $perPage)
    {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = preg_replace('/(\?|&)page=\d+/', '', $uri);
        $uri = preg_replace('/(\?|&)limit=\d+/', '', $uri);

        $separator = strpos($uri, '?') === false ? '?' : '&';

        return $uri . $separator . 'page=' . $page . '&limit=' . $perPage;
    }

    /**
     * Create a stream response
     * 
     * @param callable $callback Callback function to generate content
     * @param int $bufferSize Buffer size
     * @return $this For method chaining
     */
    public function stream(callable $callback, $bufferSize = 4096)
    {
        $this->sendHeaders();

        // Disable output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Disable time limit
        set_time_limit(0);

        // Flush content
        flush();

        // Call the callback to generate content
        $callback($bufferSize);

        return $this;
    }

    /**
     * Create a chunked response
     * 
     * @param callable $callback Callback function to generate chunks
     * @return $this For method chaining
     */
    public function chunked(callable $callback)
    {
        $this->setHeader('Transfer-Encoding', 'chunked');

        return $this->stream(function () use ($callback) {
            $callback(function ($data) {
                echo dechex(strlen($data)) . "\r\n";
                echo $data . "\r\n";
                flush();
            });

            echo "0\r\n\r\n";
        });
    }

    /**
     * Create a server-sent events response
     * 
     * @param callable $callback Callback function to generate events
     * @return $this For method chaining
     */
    public function sse(callable $callback)
    {
        $this->setContentType('text/event-stream');
        $this->setHeader('Cache-Control', 'no-cache');
        $this->setHeader('Connection', 'keep-alive');

        return $this->stream(function () use ($callback) {
            $callback(function ($data, $event = null, $id = null, $retry = null) {
                if ($id) {
                    echo "id: {$id}\n";
                }

                if ($event) {
                    echo "event: {$event}\n";
                }

                if ($retry) {
                    echo "retry: {$retry}\n";
                }

                echo "data: " . str_replace("\n", "\ndata: ", $data) . "\n\n";
                flush();
            });
        });
    }

    public function getData()
    {
        // Assuming $this->data holds the response data
        return $this->data;
    }

    public function setData($data)
    {
        // Assuming $this->data holds the response data
        $this->data = $data;
    }
}
