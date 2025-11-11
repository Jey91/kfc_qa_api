<?php

/**
 * Routes Configuration - Authentication Routes Only
 * 
 * This file defines authentication routes for your RESTful API.
 */

use Core\Router;

// Require the autoloader
require_once APP_ROOT . '/vendor/autoload.php';
$appConfig = require_once APP_ROOT . '/config/app.php';

$basePath = ''; // mes_controller

$v1BasePath = $basePath . '/api/v1';

/**
 * Get the Router instance
 */
$router = new Router();

/**
 * Register Middleware
 * 
 * Define middleware that should be applied to the routes.
 */
$router->addGlobalMiddleware([
    // App\Middleware\RequestLoggerMiddleware::class,
    // App\Middleware\CorsMiddleware::class,
    // App\Middleware\SecurityHeadersMiddleware::class
]);

/**
 * Register Route Middleware
 * 
 * Define middleware that should be applied to specific routes.
 */
$router->registerMiddleware([
    'auth' => App\Middleware\AuthMiddleware::class,
    'basic' => App\Middleware\BasicAuthMiddleware::class,
    'verifyPw' => App\Middleware\VerifySecondaryPasswordMiddleware::class,
    'log' => App\Middleware\RequestLoggerMiddleware::class //before is global middleware
]);

/**
 * API Routes
 * 
 * All routes are prefixed with /api/v1
 */
$router->group(['prefix' => $v1BasePath], function ($router) {
    // $router->group(['prefix' => 'v3_controller/api/v1'], function ($router) {
    /**
     * Authentication Routes
     * 
     * Routes for user authentication.
     */
  
    $router->group(['prefix' => 'auth'], function ($router) {
        $router->post('/login', 'UserController@login')->withMiddleware(['log']);
        $router->post('/logout', 'UserController@logout')->withMiddleware(['log']);
        $router->post('/verify-platform-access-token', 'UserController@validatePlatformAccessToken')->withMiddleware(['log']);
        $router->post('/get-platform-access-token', 'UserController@generatePlatformAccessToken')->withMiddleware(['basic']);
        $router->post('/verify-redirect', 'UserController@validatePlatformAccessToken');
    });
    
     /**
     * Building Routes
     */
    $router->group(['prefix' => 'building'], function ($router) {
        $router->post('/building-list-to-select', 'PlatformController@getBuildingToSelect')->withMiddleware(['basic']);
    });

    /**
     * Site Routes
     */
    $router->group(['prefix' => 'site'], function ($router) {
        $router->post('/site-list-to-select', 'PlatformController@getSiteListToSelect')->withMiddleware(['basic']);
    });

     /**
     * Entity Routes
     */
    $router->group(['prefix' => 'entity'], function ($router) {
        $router->post('/entity-list-to-select', 'PlatformController@getEntityToSelect')->withMiddleware(['basic']);
    });

    /**
     * Plant Routes
     */
    $router->group(['prefix' => 'plant'], function ($router) {
        $router->post('/plant-list-to-select', 'PlatformController@getPlantListToSelect')->withMiddleware(['basic']);
    });

    /**
     * WMS Warehouse Routes
     */
    $router->group(['prefix' => 'warehouse'], function ($router) {
        $router->post('/all-warehouse-list-to-select', 'PlatformController@getWarehouseListToSelect');
    });

    /**
     * User Routes
     */
    $router->group(['prefix' => 'user'], function ($router) {
        $router->post('/get-platform-list', 'PlatformController@getPlatformList')->withMiddleware(['basic']);
        $router->post('/user-list-to-select', 'PlatformController@getUserListToSelect')->withMiddleware(['basic']);
        $router->post('/get-user-plant-list', 'PlatformController@getUserPlantList')->withMiddleware(['basic']);
        $router->post('/get-user-warehouse-list', 'PlatformController@getUserWarehouseList')->withMiddleware(['basic']);
        $router->post('/get-user-entity-list', 'PlatformController@getUserEntityList')->withMiddleware(['basic']);
        $router->post('/get-user-profile', 'PlatformController@getBasicProfile')->withMiddleware(['basic']);
        $router->post('/update-user-entity', 'UserController@updateUserEntity')->withMiddleware(['basic']);
    });

    /**
     * system log Routes
     */
    $router->group(['prefix' => 'system-log'], function ($router) {
        $router->post('/list', 'SystemLogHistoryController@getAllLogs')->withMiddleware(['auth']);
    });

    /**
     * Authenticated Routes
     * 
     * Routes that require authentication.
     */
    $router->group(['prefix' => 'auth', 'middleware' => 'auth'], function ($router) {
        $router->get('/me', 'AuthController@me');
    });
    
});



//$router->post('/api/v1/auth/logout', 'AuthController@logout');

/**
 * Error Handling Routes
 * 
 * Define routes for handling errors.
 */
// 404 - Not Found
$router->setNotFoundHandler(function ($request, $response) {
    return $response->setStatusCode(404)
        ->setContent(json_encode([
            'success' => false,
            'message' => 'Resource not found',
            'error' => 'Not Found',
            'status_code' => 404
        ]))
        ->setHeader('Content-Type', 'application/json');
});

/**
 * Health Check Route
 * 
 * Define a route for checking the health status of the API.
 */
$router->get('/health', function ($request, $response) {
    return $response->setStatusCode(200)
        ->setContent(json_encode([
            'status' => 'ok',
            'timestamp' => time(),
            //    'version' => env('APP_VERSION', '1.0.0')
        ]))
        ->setHeader('Content-Type', 'application/json');
});

/**
 * Return the router instance
 */
return $router;