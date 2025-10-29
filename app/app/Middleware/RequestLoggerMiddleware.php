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
        // Process the request through the next middleware/handler
        $response = $next($request, $response);
        
        // Only log successful requests (status codes 2xx)
        // $statusCode = $response->getStatusCode();
        
        // To get response status code; if not error code also will insert
        $reflection = new ReflectionClass($response); // Create reflection of the object
        $property = $reflection->getProperty('content'); // Get the 'content' property
        $property->setAccessible(true); // Make it accessible
        $content = $property->getValue($response); // Get its value
        
        // Only log successful requests (status codes 2xx)
        $statusCode = $content['status_code'];
        if ($statusCode == 200) {
            $this->logRequest($request, $response);
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
    private function logRequest(Request $request, Response $response)
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

            // Try to decode JSON content if it's a string
            if (is_string($responseContent)) {
                $decodedContent = json_decode($responseContent, true);
                if (is_array($decodedContent) && isset($decodedContent['message'])) {
                    $responseMessage = $decodedContent['message'];
                }
            }
            // If content is already an array, check for message
            elseif (is_array($responseContent) && isset($responseContent['message'])) {
                $responseMessage = $responseContent['message'];
            }

            // Try getData method as fallback
            if (empty($responseMessage)) {
                $responseData = $response->getData();
                if (is_array($responseData) && isset($responseData['message'])) {
                    $responseMessage = $responseData['message'];
                }
            }

            // Prepare log data
            $logData = [
                'slh_lp_plant_db_code' => $plantCode,
                'slh_ip_address' => $request->getClientIp(),
                'slh_subject' => $this->determineSubject($path),
                'slh_content' => $responseMessage ?: 'No message provided',
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

}
