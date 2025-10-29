<?php

/**
 * API Entry Point
 */

// Define the application root directory
define('APP_ROOT', dirname(__DIR__));

// Require the autoloader
require_once APP_ROOT . '/vendor/autoload.php';
$appConfig = require_once APP_ROOT . '/config/app.php';

ini_set('display_errors', $appConfig['display_errors']); // or 0 (boolean)
error_reporting($appConfig['error_reporting']);        // Keep capturing all errors
ini_set('error_log', APP_ROOT . '/logs/error.log'); // Log errors to a file

try {

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Return early with a 200 OK status
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
    //     'requested_path' => $request->getPath(),
    //     'method' => $request->getMethod()
    // ]);
    // exit;


    // Load routes
    $router = require APP_ROOT . '/config/routes.php';
    $appConfig = require APP_ROOT . '/config/app.php';


    // Dispatch the request
    $result = $router->dispatch($request, $response);

    // If result is a Response object, send it
    if ($result instanceof \Core\Response) {
        $result->send();
    } else {
        // Otherwise, send a basic response
        $response->setContent($result)
            ->send();
    }
} catch (\Throwable $e) {
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
