<?php

/**
 * API Entry Point
 */

// Define the application root directory
define('APP_ROOT', dirname(__DIR__));

// Require the autoloader
require_once APP_ROOT . '/vendor/autoload.php';
$appConfig = require_once APP_ROOT . '/config/app.php';

// Initialize Logger
$logger = new \Utils\Logger();

// Log the incoming request
$logger->info('Request received', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

// Ensure log directory exists
$logDir = APP_ROOT . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

ini_set('display_errors', $appConfig['display_errors']); // or 0 (boolean)
error_reporting($appConfig['error_reporting']);    // Keep capturing all errors
ini_set('error_log', APP_ROOT . '/logs/error.log'); // Log errors to a file

// Custom error handler to capture warnings
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($appConfig, $logger) {
    // Log all errors including warnings
    $errorType = match ($errno) {
        E_WARNING, E_USER_WARNING => 'WARNING',
        E_NOTICE, E_USER_NOTICE => 'NOTICE',
        E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
        default => 'ERROR'
    };

    $errorMessage = "$errstr in $errfile on line $errline";
    $logger->log($errorType, $errorMessage);

    // For warnings, we typically don't want to halt execution
    // Return true to prevent the standard PHP error handler from running
    return true;
});

try {
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        $logger->info('OPTIONS request handled');
        http_response_code(200);
        exit;
    }

    // Set timezone
    date_default_timezone_set($config['timezone'] ?? 'Asia/Kuala_Lumpur');

    // Initialize request and response
    $request = new \Core\Request();
    $response = new \Core\Response($appConfig);

    // Debug the path
    // header('Content-Type: application/json');
    // echo json_encode([
    //    'requested_path' => $request->getPath(),
    //    'method' => $request->getMethod()
    // ]);
    // exit;

    // Load routes
    $router = require APP_ROOT . '/config/routes.php';
    $appConfig = require APP_ROOT . '/config/app.php';

    // Dispatch the request
    $result = $router->dispatch($request, $response);

    // Log successful request completion
    $logger->info('Request completed successfully', [
        'path' => $request->getPath(),
        'method' => $request->getMethod()
    ]);

    // If result is a Response object, send it
    if ($result instanceof \Core\Response) {
        $result->send();
    } else {
        // Otherwise, send a basic response
        $response->setContent($result)
            ->send();
    }
} catch (\Throwable $e) {  // Using Throwable instead of Exception to catch more error types
    // Log the exception with safe string conversion
    $logger->error('Exception caught: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString() // Use string instead of array
    ]);

    // Simple error handling
    header('Content-Type: application/json');
    http_response_code(500);

    $debug = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ];

    $response = [
        'status_code' => 500,
        'message' => 'An error occurred while processing your request.',
        'debug' => $debug
    ];

    if (!$appConfig['debug']) {
        unset($response['debug']);
    }

    // Log the error
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode($response);
}

// Restore the previous error handler when done
restore_error_handler();