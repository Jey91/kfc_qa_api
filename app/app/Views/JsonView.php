<?php
namespace Views;

use Core\Response;

/**
 * JsonView class
 * 
 * Handles rendering of JSON responses for the API
 */
class JsonView
{
    /**
     * @var bool Whether to pretty print JSON
     */
    private $prettyPrint = false;

    /**
     * @var int JSON encoding options
     */
    private $encodingOptions = 0;

    /**
     * @var array Response data
     */
    private $data = [];

    /**
     * @var array Response metadata
     */
    private $meta = [];

    /**
     * @var array Response links
     */
    private $links = [];

    /**
     * @var array Response errors
     */
    private $errors = [];

    /**
     * @var string Response message
     */
    private $message = '';

    /**
     * @var int HTTP status code
     */
    private $statusCode = 200;

    /**
     * @var bool Whether this is an error response
     */
    private $isError = false;

    /**
     * @var Response Response instance
     */
    private $response;

    /**
     * Constructor
     * 
     * @param Response $response Response instance
     * @param bool $prettyPrint Whether to pretty print JSON
     */
    public function __construct(Response $response, $prettyPrint = false)
    {
        $this->response = $response;
        $this->prettyPrint = $prettyPrint;

        // Set default encoding options
        $this->encodingOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($this->prettyPrint) {
            $this->encodingOptions |= JSON_PRETTY_PRINT;
        }
    }

    /**
     * Set pretty print
     * 
     * @param bool $prettyPrint Whether to pretty print JSON
     * @return $this For method chaining
     */
    public function setPrettyPrint($prettyPrint)
    {
        $this->prettyPrint = (bool) $prettyPrint;

        if ($this->prettyPrint) {
            $this->encodingOptions |= JSON_PRETTY_PRINT;
        } else {
            $this->encodingOptions &= ~JSON_PRETTY_PRINT;
        }

        return $this;
    }

    /**
     * Set encoding options
     * 
     * @param int $options JSON encoding options
     * @return $this For method chaining
     */
    public function setEncodingOptions($options)
    {
        $this->encodingOptions = $options;

        if ($this->prettyPrint) {
            $this->encodingOptions |= JSON_PRETTY_PRINT;
        }

        return $this;
    }

    /**
     * Set response data
     * 
     * @param mixed $data Response data
     * @return $this For method chaining
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Add response data
     * 
     * @param string $key Data key
     * @param mixed $value Data value
     * @return $this For method chaining
     */
    public function addData($key, $value)
    {
        if (is_array($this->data)) {
            $this->data[$key] = $value;
        } else {
            $this->data = [$key => $value];
        }

        return $this;
    }

    /**
     * Set response metadata
     * 
     * @param array $meta Response metadata
     * @return $this For method chaining
     */
    public function setMeta(array $meta)
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Add response metadata
     * 
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return $this For method chaining
     */
    public function addMeta($key, $value)
    {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Set response links
     * 
     * @param array $links Response links
     * @return $this For method chaining
     */
    public function setLinks(array $links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Add response link
     * 
     * @param string $key Link key
     * @param string $url Link URL
     * @return $this For method chaining
     */
    public function addLink($key, $url)
    {
        $this->links[$key] = $url;
        return $this;
    }

    /**
     * Set pagination links
     * 
     * @param string $baseUrl Base URL
     * @param array $pagination Pagination data
     * @return $this For method chaining
     */
    public function setPaginationLinks($baseUrl, array $pagination)
    {
        $links = [];
        $baseUrl = rtrim($baseUrl, '?&');
        $queryChar = parse_url($baseUrl, PHP_URL_QUERY) ? '&' : '?';

        // Current page
        $currentPage = $pagination['current_page'] ?? 1;

        // Last page
        $lastPage = $pagination['last_page'] ?? 1;

        // Items per page
        $perPage = $pagination['per_page'] ?? 15;

        // First page link
        $links['first'] = "{$baseUrl}{$queryChar}page=1&per_page={$perPage}";

        // Previous page link
        if ($currentPage > 1) {
            $links['prev'] = "{$baseUrl}{$queryChar}page=" . ($currentPage - 1) . "&per_page={$perPage}";
        }

        // Next page link
        if ($currentPage < $lastPage) {
            $links['next'] = "{$baseUrl}{$queryChar}page=" . ($currentPage + 1) . "&per_page={$perPage}";
        }

        // Last page link
        $links['last'] = "{$baseUrl}{$queryChar}page={$lastPage}&per_page={$perPage}";

        $this->links = array_merge($this->links, $links);

        // Add pagination metadata
        $this->addMeta('pagination', [
            'total' => $pagination['total'] ?? 0,
            'count' => $pagination['count'] ?? 0,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $lastPage
        ]);

        return $this;
    }

    /**
     * Set response errors
     * 
     * @param array $errors Response errors
     * @return $this For method chaining
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        $this->isError = !empty($errors);
        return $this;
    }

    /**
     * Add response error
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

        if (is_array($message)) {
            $this->errors[$field] = array_merge($this->errors[$field], $message);
        } else {
            $this->errors[$field][] = $message;
        }

        $this->isError = true;

        return $this;
    }

    /**
     * Set response message
     * 
     * @param string $message Response message
     * @return $this For method chaining
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set HTTP status code
     * 
     * @param int $statusCode HTTP status code
     * @return $this For method chaining
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Set as error response
     * 
     * @param bool $isError Whether this is an error response
     * @return $this For method chaining
     */
    public function setIsError($isError)
    {
        $this->isError = (bool) $isError;
        return $this;
    }

    /**
     * Create a success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     * @return $this For method chaining
     */
    public function success($data = null, $message = 'Success', $statusCode = 200)
    {
        $this->data = $data;
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->isError = false;
        $this->errors = [];

        return $this;
    }

    /**
     * Create an error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Validation errors
     * @return $this For method chaining
     */
    public function error($message = 'Error', $statusCode = 400, array $errors = [])
    {
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->isError = true;
        $this->errors = $errors;
        $this->data = null;

        return $this;
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
        return $this->error($message, 422, $errors);
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
     * Render the response
     * 
     * @return string JSON response
     */
    public function render()
    {
        $response = [];

        // Add success/error status
        $response['success'] = !$this->isError;

        // Add message if present
        if (!empty($this->message)) {
            $response['message'] = $this->message;
        }

        // Add data if present and not an error
        if (!$this->isError && $this->data !== null) {
            $response['data'] = $this->data;
        }

        // Add errors if present
        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        // Add metadata if present
        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        // Add links if present
        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        // Add timestamp
        $response['timestamp'] = date('Y-m-d\TH:i:s.v\Z');

        // Encode response
        $json = json_encode($response, $this->encodingOptions);

        // Check for JSON encoding errors
        if ($json === false) {
            // Handle JSON encoding error
            $errorMessage = json_last_error_msg();
            $errorResponse = [
                'success' => false,
                'message' => 'JSON encoding error: ' . $errorMessage,
                'timestamp' => date('Y-m-d\TH:i:s.v\Z')
            ];

            $json = json_encode($errorResponse, $this->encodingOptions);
            $this->statusCode = 500;
        }

        return $json;
    }

    /**
     * Send the response
     * 
     * @return Response Response instance
     */
    public function send()
    {
        // Set content type
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');

        // Set status code
        $this->response->setStatusCode($this->statusCode);

        // Set content
        $this->response->setContent($this->render());

        // Send response
        return $this->response->send();
    }

    /**
     * Create a JSON response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     * @return Response Response instance
     */
    public function response($data = null, $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;

        // Set headers
        foreach ($headers as $name => $value) {
            $this->response->setHeader($name, $value);
        }

        return $this->send();
    }

    /**
     * Create a JSON response with data transformation
     * 
     * @param mixed $data Response data
     * @param callable $transformer Data transformer
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     * @return Response Response instance
     */
    public function responseWithTransformer($data, callable $transformer, $statusCode = 200, array $headers = [])
    {
        // Transform data
        $transformedData = $transformer($data);

        return $this->response($transformedData, $statusCode, $headers);
    }

    /**
     * Create a JSON response for a collection with pagination
     * 
     * @param array $data Collection data
     * @param array $pagination Pagination data
     * @param string $baseUrl Base URL for pagination links
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     * @return Response Response instance
     */
    public function responseWithPagination(array $data, array $pagination, $baseUrl, $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;

        // Set pagination links
        $this->setPaginationLinks($baseUrl, $pagination);

        // Set headers
        foreach ($headers as $name => $value) {
            $this->response->setHeader($name, $value);
        }

        return $this->send();
    }

    /**
     * Create a JSON response for a collection with pagination and data transformation
     * 
     * @param array $data Collection data
     * @param array $pagination Pagination data
     * @param string $baseUrl Base URL for pagination links
     * @param callable $transformer Data transformer
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     * @return Response Response instance
     */
    public function responseWithPaginationAndTransformer(array $data, array $pagination, $baseUrl, callable $transformer, $statusCode = 200, array $headers = [])
    {
        // Transform data
        $transformedData = array_map($transformer, $data);

        return $this->responseWithPagination($transformedData, $pagination, $baseUrl, $statusCode, $headers);
    }
}