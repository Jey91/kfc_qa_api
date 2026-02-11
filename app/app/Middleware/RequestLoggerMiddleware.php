<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Models\SystemLogHistory;
use ReflectionClass;
/**
 * RequestLoggerMiddleware
 * 
 * Logs all successful requests to the database
 */
class RequestLoggerMiddleware
{
    /**
     * Handle the request
     * 
     * @param Request $request Request object
     * @param Response $response Response object
     * @param callable $next Next middleware or route handler
     * @return Response Response object
     */
    public function handle(Request $request, Response $response, callable $next)
    {   
        // Capture request start time
        $startTime = microtime(true);
        
        // Get client IP address
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        // Process the request through the next middleware/handler
        $response = $next($request, $response);
        $duration = microtime(true) - $startTime;
        // Only log successful requests (status codes 2xx)
        // $statusCode = $response->getStatusCode();
        
        // To get response status code; if not error code also will insert
        $reflection = new ReflectionClass($response); // Create reflection of the object
        $property = $reflection->getProperty('content'); // Get the 'content' property
        $property->setAccessible(true); // Make it accessible
        $content = $property->getValue($response); // Get its value
        
        // Only log successful requests (status codes 2xx)
        $statusCode = $content['status_code'];
        if ($statusCode == 200 || $statusCode == 422) {
            $this->logRequest($request, $response, $duration, $ipAddress);
        }
        return $response;
    }
    /**
     * Log the request to the database
     * 
     * @param Request $request Request object
     * @param Response $response Response object
     * @return void
     */
    private function logRequest(Request $request, Response $response, float $duration, $ipAddress)
    {
        try {
            $plantCode = $request->getData('globalPlantDbCode');
            $logger = new SystemLogHistory();

            // Get the authenticated user (if available)
            //$authInfo = $request->getData('user'); 
            $username = "guest";
            if (str_contains($request->getPath(), 'auth/login')) {
                $username = $request->getData('username');
            }
            else if (str_contains($request->getPath(), 'auth/verify-platform-access-token')) {
                $content = $response->getContent();
                $username = $content['data']['username'];
            }
            else {
                $username = $request->getData('accessUsername');
            }
            
            // Determine the module from the route or path
            $path = $request->getPath();
            $module = $this->determineModule($path);
            // Get response content and extract message
            $responseContent = $response->getContent();
            $responseMessage = '';
            $innerStatusCode = '';
          
            // Try to decode JSON content if it's a string
            if (is_string($responseContent)) {
                $decodedContent = json_decode($responseContent, true);
                if (is_array($decodedContent) && isset($decodedContent['message']) && isset($decodedContent['status_code'])) {
                    $responseMessage = $decodedContent['message'];
                    $innerStatusCode = $decodedContent['status_code'];
                }
            }
            // If content is already an array, check for message
            elseif (is_array($responseContent) && isset($responseContent['message']) && isset($responseContent['status_code'])) {
                $responseMessage = $responseContent['message'];
                $innerStatusCode = $responseContent['status_code'];
            }

            // Try getData method as fallback
            if (empty($responseMessage)) {
                $responseData = $response->getData();
                if (is_array($responseData) && isset($responseData['message']) && isset($responseData['status_code'])) {
                    $responseMessage = $responseData['message'];
                    $innerStatusCode = $responseData['status_code'];
                }
            }

            // Prepare log content
            $content = [
                'method' => $request->getMethod(),
                'path' => $path,
                'query' => $request->getQueryParams(),
                'body' => $this->sanitizeRequestData($request->getAllData()),
                'status' => $response->getStatusCode(),
                'response_message' => $responseMessage ?: 'No message provided',
                'duration' => round($duration * 1000, 2) . 'ms',
                'user_agent' => $request->getUserAgent(),
                'ip_address' => $ipAddress
            ];

            // Prepare log data
            $logData = [
                'slh_lp_plant_db_code' => $plantCode,
                'slh_ip_address' => $request->getClientIp(),
                'slh_subject' => $this->determineSubject($path),
                'slh_content' => json_encode($content),//$responseMessage ?: 'No message provided',
                'slh_module' => $module,
                'slh_created_datetime' => date('Y-m-d H:i:s'),
                'slh_created_by' => $username
            ];
            $logger->create($logData);
        } catch (\Exception $e) {
            // Log the error but don't interrupt the response
            error_log('Error logging request: ' . $e->getMessage());
        }
    }


    /**
     * Format the log content
     * 
     * @param Request $request Request object
     * @param Response $response Response object
     * @return string Formatted content
     */
    private function formatContent(Request $request, Response $response)
    {
        $content = [
            'method' => $request->getMethod(),
            'path' => $request->getPath(),
            'query_params' => $request->getQueryParams(),
            'request_data' => $request->getAllData(),
            'status_code' => $response->getStatusCode(),
            'response_data' => $response->getData()
        ];

        return json_encode($content);
    }

    /**
     * Determine the module from the path
     * 
     * @param string $path Request path
     * @return string Module name
     */
    private function determineModule($path)
    {
        $modules = [
            'user' => 'user',
            'notification-center' => 'notification_center'
        ];
        $segments = explode('/', trim($path, '/'));
        // Search for the first matching module key in the path
        foreach ($segments as $segment) {
            if (isset($modules[$segment])) {
                return $modules[$segment];
            }
        }
        // Default module name if path is empty
        return 'general';
    }

    /**
     * Determine the subject based on the path
     * 
     * @param string $path Request path
     * @return string Subject
     */
    private function determineSubject($path)
        {
            $modules = [
                'user' => [
                    'logout' => 'Logout',
                ],
                'notification-center' => [
                    'create' => 'Create Notification',
                    'update' => 'Update Notification',
                    'delete' => 'Delete Notification'
                ],
            ];
            // the path of url is follow api route

            $segments = explode('/', trim($path, '/'));

        // Try to find the first segment that exists in $modules
        $start = 0;
        foreach ($segments as $i => $segment) {
            if (isset($modules[$segment])) {
                $start = $i;
                break;
            }
        }

        // Slice the array starting from the first match
        $relevantSegments = array_slice($segments, $start);

        // Walk the module tree
        $current = $modules;
        foreach ($relevantSegments as $segment) {
            if (is_array($current) && isset($current[$segment])) {
                $current = $current[$segment];
            } else {
                return 'General Action';
            }
        }

        return is_string($current) ? $current : 'General Action';
    }
    /**
     * Sanitize request data to remove sensitive information
     *
     * @param array $data
     * @return array
     */
    private function sanitizeRequestData(array $data): array
    {
        // Fields to mask (keep key, hide value)
        $maskFields = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'credit_card',
            'verifySecondaryPassword',
            'accessToken',
            'secondaryPassword'
        ];

        // Fields to completely remove
        $hideFields = [
            'user'
        ];

        // Mask sensitive fields
        foreach ($maskFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '******';
            }
        }

        // Remove hidden fields
        foreach ($hideFields as $field) {
            unset($data[$field]);
        }

        return $data;
    }
}
