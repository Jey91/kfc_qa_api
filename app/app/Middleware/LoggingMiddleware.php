<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Models\SystemLogHistory;

class LoggingMiddleware
{

    private $logModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logModel = new SystemLogHistory();
    }

    /**
     * Handle the incoming request
     *
     * @param Request $request The request object
     * @param Response $response The response object
     * @param callable $next The next middleware or route handler
     * @return Response
     */
    public function handle(Request $request, Response $response, callable $next)
    {
        // Capture request start time
        $startTime = microtime(true);

        // Process the request
        $response = $next($request, $response);
        $duration = microtime(true) - $startTime;

        // Check if the request was successful (status code 2xx)
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            // Only log successful requests
            $this->logRequest($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Log request to database
     *
     * @param Request $request The request object
     * @param Response $response The response object
     * @param float $duration Request duration in seconds
     * @return void
     */
    private function logRequest(Request $request, Response $response, float $duration)
    {
        try {
            // Get user information
            $user = $request->getUser();
            $userId = $user ? $user->id ?? $user['id'] ?? 'anonymous' : 'anonymous';

            // Determine module from route
            $path = $request->getPath();
            $module = $this->determineModule($path);

            // Get plant ID
            $plantId = $request->getData('plant_id');

            // Prepare log content
            $content = [
                'method' => $request->getMethod(),
                'path' => $path,
                'query' => $request->getQueryParams(),
                'body' => $this->sanitizeRequestData($request->getAllData()),
                'status' => $response->getStatusCode(),
                'duration' => round($duration * 1000, 2) . 'ms',
                'user_agent' => $request->getUserAgent()
            ];

            // Create log entry
            $query = "INSERT INTO system_log_history (
                slh_lp_plant_db_code, 
                slh_ip_address, 
                slh_subject, 
                slh_content, 
                slh_module, 
                slh_created_datetime, 
                slh_created_by
            ) VALUES (?, ?, ?, ?, ?, GETDATE(), ?)";

            $params = [
                $plantId,
                $request->getClientIp(),
                $this->generateSubject($request),
                json_encode($content),
                $module,
                $userId
            ];

            // $this->db->execute($query, $params);
        } catch (\Exception $e) {
            // Log to error file instead of failing the request
            error_log('Error logging request: ' . $e->getMessage());
        }
    }

    /**
     * Generate a subject line for the log entry
     *
     * @param Request $request The request object
     * @return string
     */
    private function generateSubject(Request $request): string
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Extract the main action from the path
        $pathParts = explode('/', trim($path, '/'));
        $action = end($pathParts);

        // For RESTful actions, add context
        if (in_array($action, ['create', 'update', 'delete', 'list', 'get-by-code'])) {
            // Find the resource name (usually the part before the action)
            $resourceIndex = count($pathParts) - 2;
            $resource = $resourceIndex >= 0 ? $pathParts[$resourceIndex] : '';

            return ucfirst($method) . ' ' . $resource . ' ' . $action;
        }

        return ucfirst($method) . ' ' . $path;
    }

    /**
     * Determine the module from the request path
     *
     * @param string $path The request path
     * @return string
     */
    private function determineModule(string $path): string
    {
        // Extract module from path (e.g., /api/v1/auth/login -> auth)
        $pathParts = explode('/', trim($path, '/'));

        // Skip api/v1 prefix if present
        $startIndex = 0;
        if (count($pathParts) > 2 && $pathParts[0] === 'api' && $pathParts[1] === 'v1') {
            $startIndex = 2;
        }

        // Get module name (first segment after prefix)
        return $startIndex < count($pathParts) ? $pathParts[$startIndex] : 'unknown';
    }

    /**
     * Get plant ID from request or user
     *
     * @param Request $request The request object
     * @return string
     */
    private function getPlantId(Request $request): string
    {
        // Try to get from request data
        $plantId = $request->getData('plant_id');
        if ($plantId) {
            return substr($plantId, 0, 2); // Ensure it's only 2 chars
        }

        // Try to get from user data
        $user = $request->getUser();
        if ($user) {
            $userPlantId = $user->plant_id ?? $user['plant_id'] ?? null;
            if ($userPlantId) {
                return substr($userPlantId, 0, 2);
            }
        }

        // Default plant ID
        return '00';
    }

    /**
     * Sanitize request data to remove sensitive information
     *
     * @param array $data Request data
     * @return array
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'credit_card'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }

        return $data;
    }
}
