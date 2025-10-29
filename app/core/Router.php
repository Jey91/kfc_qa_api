<?php
namespace Core;

/**
 * Router class
 * 
 * Handles routing of HTTP requests to appropriate controllers and actions
 */
class Router
{
    /**
     * @var array Registered routes
     */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'OPTIONS' => []
    ];

    /**
     * @var array Named routes
     */
    private $namedRoutes = [];

    /**
     * @var array Route groups
     */
    private $groups = [];

    /**
     * @var string Current group prefix
     */
    private $currentGroupPrefix = '';

    /**
     * @var array Current group middleware
     */
    private $currentGroupMiddleware = [];

    /**
     * @var array Global middleware
     */
    private $globalMiddleware = [];

    /**
     * @var array Route middleware
     */
    private $routeMiddleware = [];

    /**
     * @var array Route parameters
     */
    private $params = [];

    /**
     * @var string Base path
     */
    private $basePath = '';

    /**
     * @var array Route patterns
     */
    private $patterns = [
        ':any' => '[^/]+',
        ':id' => '[0-9]+',
        ':slug' => '[a-z0-9-]+',
        ':uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        ':alpha' => '[a-zA-Z]+',
        ':alphanumeric' => '[a-zA-Z0-9]+',
        ':number' => '[0-9]+(\.[0-9]+)?'
    ];

    /**
     * @var array HTTP methods
     */
    private $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @var string|callable Not found handler
     */
    private $notFoundHandler;

    /**
     * @var string|callable Method not allowed handler
     */
    private $methodNotAllowedHandler;

    /**
     * @var string Last added HTTP method
     */
    private $lastMethod;

    /**
     * Constructor
     * 
     * @param string $basePath Base path
     */
    public function __construct($basePath = '')
    {
        $this->basePath = $basePath;

        // Set default handlers
        $this->notFoundHandler = function (Request $request, Response $response) {
            return $response->notFound('Route not found: ' . $request->getPath());
        };

        $this->methodNotAllowedHandler = function (Request $request, Response $response, array $allowedMethods) {
            $response->setHeader('Allow', implode(', ', $allowedMethods));
            return $response->error('Method not allowed: ' . $request->getMethod(), 405);
        };
    }

    /**
     * Add a GET route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function get($route, $handler, $name = null)
    {
        return $this->addRoute('GET', $route, $handler, $name);
    }

    /**
     * Add a POST route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function post($route, $handler, $name = null)
    {
        return $this->addRoute('POST', $route, $handler, $name);
    }

    /**
     * Add a PUT route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function put($route, $handler, $name = null)
    {
        return $this->addRoute('PUT', $route, $handler, $name);
    }

    /**
     * Add a PATCH route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function patch($route, $handler, $name = null)
    {
        return $this->addRoute('PATCH', $route, $handler, $name);
    }

    /**
     * Add a DELETE route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function delete($route, $handler, $name = null)
    {
        return $this->addRoute('DELETE', $route, $handler, $name);
    }

    /**
     * Add an OPTIONS route
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function options($route, $handler, $name = null)
    {
        return $this->addRoute('OPTIONS', $route, $handler, $name);
    }

    /**
     * Add a route that responds to multiple HTTP methods
     * 
     * @param array $methods HTTP methods
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function map(array $methods, $route, $handler, $name = null)
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $route, $handler, $name);
        }

        return $this;
    }

    /**
     * Add a route that responds to all HTTP methods
     * 
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    public function any($route, $handler, $name = null)
    {
        return $this->map($this->methods, $route, $handler, $name);
    }

    /**
     * Add a resource route (RESTful controller)
     * 
     * @param string $name Resource name
     * @param string $controller Controller class
     * @param array $options Resource options
     * @return $this For method chaining
     */
    public function resource($name, $controller, array $options = [])
    {
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;

        $actions = [
            'index' => ['GET', "/{$name}", 'index'],
            'create' => ['GET', "/{$name}/create", 'create'],
            'store' => ['POST', "/{$name}", 'store'],
            'show' => ['GET', "/{$name}/:id", 'show'],
            'edit' => ['GET', "/{$name}/:id/edit", 'edit'],
            'update' => ['PUT', "/{$name}/:id", 'update'],
            'destroy' => ['DELETE', "/{$name}/:id", 'destroy']
        ];

        foreach ($actions as $action => $data) {
            if (($only && !in_array($action, $only)) || ($except && in_array($action, $except))) {
                continue;
            }

            list($method, $route, $handler) = $data;
            $this->addRoute($method, $route, "{$controller}@{$handler}", "{$name}.{$action}");
        }

        return $this;
    }

    /**
     * Add an API resource route (RESTful API controller)
     * 
     * @param string $name Resource name
     * @param string $controller Controller class
     * @param array $options Resource options
     * @return $this For method chaining
     */
    public function apiResource($name, $controller, array $options = [])
    {
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;

        $actions = [
            'index' => ['GET', "/{$name}", 'index'],
            'store' => ['POST', "/{$name}", 'store'],
            'show' => ['GET', "/{$name}/:id", 'show'],
            'update' => ['PUT', "/{$name}/:id", 'update'],
            'destroy' => ['DELETE', "/{$name}/:id", 'destroy']
        ];

        foreach ($actions as $action => $data) {
            if (($only && !in_array($action, $only)) || ($except && in_array($action, $except))) {
                continue;
            }

            list($method, $route, $handler) = $data;
            $this->addRoute($method, $route, "{$controller}@{$handler}", "{$name}.{$action}");
        }

        return $this;
    }

    /**
     * Create a route group
     * 
     * @param array $attributes Group attributes
     * @param callable $callback Group definition callback
     * @return $this For method chaining
     */
    public function group(array $attributes, callable $callback)
    {
        // Save current group state
        $previousGroupPrefix = $this->currentGroupPrefix;
        $previousGroupMiddleware = $this->currentGroupMiddleware;

        // Set new group state
        $prefix = $attributes['prefix'] ?? '';
        $this->currentGroupPrefix .= '/' . trim($prefix, '/');

        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->currentGroupMiddleware = array_merge($this->currentGroupMiddleware, $middleware);
        }

        // Execute the callback
        $callback($this);

        // Restore previous group state
        $this->currentGroupPrefix = $previousGroupPrefix;
        $this->currentGroupMiddleware = $previousGroupMiddleware;

        return $this;
    }

    /**
     * Apply middleware to the last added route
     * 
     * @param string|array $middleware Middleware to apply
     * @return $this For method chaining
     */
    public function withMiddleware($middleware)
    {   
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        if (isset($this->lastMethod) && !empty($this->routes[$this->lastMethod])) {
            $routes = &$this->routes[$this->lastMethod];
            $lastRouteIndex = count($routes) - 1;

            if ($lastRouteIndex >= 0) {
                $routes[$lastRouteIndex]['middleware'] = array_merge(
                    $routes[$lastRouteIndex]['middleware'],
                    $middleware
                );
            }
        }

        return $this;
    }

    /**
     * Add a route pattern
     * 
     * @param string $name Pattern name
     * @param string $pattern Pattern regex
     * @return $this For method chaining
     */
    public function pattern($name, $pattern)
    {
        $this->patterns[$name] = $pattern;
        return $this;
    }

    /**
     * Add multiple route patterns
     * 
     * @param array $patterns Patterns to add
     * @return $this For method chaining
     */
    public function patterns(array $patterns)
    {
        $this->patterns = array_merge($this->patterns, $patterns);
        return $this;
    }

    /**
     * Add global middleware
     * 
     * @param string|array $middleware Middleware to add
     * @return $this For method chaining
     */
    public function addGlobalMiddleware($middleware)
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);

        return $this;
    }

    /**
     * Register route middleware
     * 
     * @param array $middleware Middleware to register
     * @return $this For method chaining
     */
    public function registerMiddleware(array $middleware)
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);
        return $this;
    }

    /**
     * Set not found handler
     * 
     * @param string|callable $handler Not found handler
     * @return $this For method chaining
     */
    public function setNotFoundHandler($handler)
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Set method not allowed handler
     * 
     * @param string|callable $handler Method not allowed handler
     * @return $this For method chaining
     */
    public function setMethodNotAllowedHandler($handler)
    {
        $this->methodNotAllowedHandler = $handler;
        return $this;
    }

    /**
     * Set base path
     * 
     * @param string $basePath Base path
     * @return $this For method chaining
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * Get URL for a named route
     * 
     * @param string $name Route name
     * @param array $params Route parameters
     * @return string Route URL
     * @throws \Exception If route not found
     */
    public function url($name, array $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Route not found: {$name}");
        }

        $route = $this->namedRoutes[$name]['route'];

        // Replace named parameters
        foreach ($params as $key => $value) {
            $route = str_replace(":{$key}", $value, $route);
        }

        // Replace remaining parameter patterns
        $route = preg_replace('/:([^\/]+)/', '', $route);

        // Remove optional segments
        $route = preg_replace('/\[\/(.+?)\]/', '', $route);

        return $this->basePath . $route;
    }

    /**
     * Dispatch the request
     * 
     * @param Request $request Request object
     * @param Response $response Response object
     * @return Response Response object
     */
    public function dispatch(Request $request, Response $response)
    {
        $method = $request->getMethod();
        $path = $this->normalizePath($request->getPath());

        // Handle OPTIONS requests automatically
        if ($method === 'OPTIONS') {
            return $this->handleOptionsRequest($request, $response, $path);
        }

        // Find matching route
        $route = $this->findRoute($method, $path);

        if ($route) {
            // Set route parameters
            $request->setParams($route['params']);

            // Execute middleware
            $middleware = array_merge(
                $this->globalMiddleware,
                $route['middleware']
            );

            return $this->executeMiddleware($middleware, $request, $response, function ($request, $response) use ($route) {
                return $this->executeHandler($route['handler'], $request, $response);
            });
        }

        // Check if route exists for another method
        $allowedMethods = $this->getAllowedMethods($path);

        if (!empty($allowedMethods)) {
            // Method not allowed
            return $this->executeHandler($this->methodNotAllowedHandler, $request, $response, [$allowedMethods]);
        }

        // Route not found
        return $this->executeHandler($this->notFoundHandler, $request, $response);
    }

    /**
     * Handle OPTIONS request
     * 
     * @param Request $request Request object
     * @param Response $response Response object
     * @param string $path Request path
     * @return Response Response object
     */
    private function handleOptionsRequest(Request $request, Response $response, $path)
    {
        $allowedMethods = $this->getAllowedMethods($path);

        if (empty($allowedMethods)) {
            // Route not found
            return $this->executeHandler($this->notFoundHandler, $request, $response);
        }

        // Add OPTIONS to allowed methods
        if (!in_array('OPTIONS', $allowedMethods)) {
            $allowedMethods[] = 'OPTIONS';
        }

        // Return response with Allow header
        return $response->handlePreflightRequest(
            $request->getHeader('Origin', '*'),
            $allowedMethods
        );
    }

    /**
     * Get allowed methods for a path
     * 
     * @param string $path Request path
     * @return array Allowed methods
     */
    private function getAllowedMethods($path)
    {
        $allowedMethods = [];

        foreach ($this->methods as $method) {
            if ($method === 'OPTIONS') {
                continue;
            }

            if ($this->findRoute($method, $path, false)) {
                $allowedMethods[] = $method;
            }
        }

        return $allowedMethods;
    }

    /**
     * Find a matching route
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @param bool $withParams Whether to extract parameters
     * @return array|false Route data or false if not found
     */
    private function findRoute($method, $path, $withParams = true)
    {
        if (!isset($this->routes[$method])) {
            return false;
        }

        foreach ($this->routes[$method] as $route) {
            $pattern = $route['pattern'];

            if (preg_match($pattern, $path, $matches)) {
                if (!$withParams) {
                    return $route;
                }

                // Extract parameters
                $params = [];

                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                $route['params'] = $params;
                return $route;
            }
        }

        return false;
    }

    /**
     * Execute route handler
     * 
     * @param string|callable $handler Route handler
     * @param Request $request Request object
     * @param Response $response Response object
     * @param array $args Additional arguments
     * @return Response Response object
     */
    private function executeHandler($handler, Request $request, Response $response, array $args = [])
    {
        if (is_callable($handler)) {
            // Callable handler
            return call_user_func_array($handler, array_merge([$request, $response], $args));
        } elseif (is_string($handler)) {
            // Controller@action handler
            list($controller, $action) = explode('@', $handler);

            // Add namespace if not present
            if (strpos($controller, '\\') === false) {
                $controller = 'App\\Controllers\\' . $controller;
            }

            if (!class_exists($controller)) {
                throw new \Exception("Controller not found: {$controller}");
            }

            $instance = new $controller();

            if (!method_exists($instance, $action)) {
                throw new \Exception("Action not found: {$action} in {$controller}");
            }

            return call_user_func_array([$instance, $action], array_merge([$request, $response], $args));
        }

        throw new \Exception("Invalid route handler");
    }

    /**
     * Execute middleware
     * 
     * @param array $middleware Middleware to execute
     * @param Request $request Request object
     * @param Response $response Response object
     * @param callable $next Next middleware or route handler
     * @return Response Response object
     */
    private function executeMiddleware(array $middleware, Request $request, Response $response, callable $next)
    {
        if (empty($middleware)) {
            return $next($request, $response);
        }

        $current = array_shift($middleware);

        // Resolve middleware name to class
        if (is_string($current) && isset($this->routeMiddleware[$current])) {
            $current = $this->routeMiddleware[$current];
        }

        // Create middleware instance
        if (is_string($current)) {
            if (!class_exists($current)) {
                throw new \Exception("Middleware not found: {$current}");
            }

            $instance = new $current();

            if (!method_exists($instance, 'handle')) {
                throw new \Exception("Middleware must have a handle method: {$current}");
            }

            $current = [$instance, 'handle'];
        }

        if (!is_callable($current)) {
            throw new \Exception("Invalid middleware");
        }

        // Execute middleware
        return $current($request, $response, function ($request, $response) use ($middleware, $next) {
            return $this->executeMiddleware($middleware, $request, $response, $next);
        });
    }

    /**
     * Add a route
     * 
     * @param string $method HTTP method
     * @param string $route Route pattern
     * @param string|callable $handler Route handler
     * @param string $name Route name (optional)
     * @return $this For method chaining
     */
    private function addRoute($method, $route, $handler, $name = null)
    {
        // Prepend group prefix
        $route = $this->currentGroupPrefix . '/' . trim($route, '/');
        $route = $this->normalizePath($route);

        // Create regex pattern
        $pattern = $this->compileRoutePattern($route);

        // Add route
        $this->routes[$method][] = [
            'route' => $route,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $this->currentGroupMiddleware
        ];

        // Set last method for middleware chaining
        $this->lastMethod = $method;

        // Add named route
        if ($name !== null) {
            $this->namedRoutes[$name] = [
                'method' => $method,
                'route' => $route,
                'pattern' => $pattern,
                'handler' => $handler
            ];
        }

        return $this;
    }

    /**
     * Compile route pattern to regex
     * 
     * @param string $route Route pattern
     * @return string Regex pattern
     */
    private function compileRoutePattern($route)
    {
        // Handle optional segments
        $route = preg_replace_callback('/\[\/(.+?)\]/', function ($matches) {
            return '(?:/' . $matches[1] . ')?';
        }, $route);

        // Replace parameter patterns
        $route = preg_replace_callback('/:([^\/]+)/', function ($matches) {
            $name = $matches[1];

            foreach ($this->patterns as $pattern => $regex) {
                if ($name === $pattern || $name === substr($pattern, 1)) {
                    return "(?P<{$name}>{$regex})";
                }
            }

            return "(?P<{$name}>[^/]+)";
        }, $route);

        // Escape slashes
        $route = str_replace('/', '\/', $route);

        return '/^' . $route . '$/';
    }

    /**
     * Normalize path
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizePath($path)
    {
        // Remove base path
        if (!empty($this->basePath) && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        // Ensure leading slash
        $path = '/' . ltrim($path, '/');

        // Remove trailing slash
        $path = rtrim($path, '/');

        // Ensure at least a slash
        if (empty($path)) {
            $path = '/';
        }

        return $path;
    }

    /**
     * Get all routes
     * 
     * @return array All routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Get named routes
     * 
     * @return array Named routes
     */
    public function getNamedRoutes()
    {
        return $this->namedRoutes;
    }
}