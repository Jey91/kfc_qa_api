<?php
/**
 * Application Configuration
 * 
 * This file contains the main application configuration settings.
 */

return [
    // Application name
    'name' => 'Kossan',

    // Application environment
    'environment' => 'development', // development, testing, production

    // Debug mode
    'debug' => true, // true for development, false for production

    // Application URL
    'url' => 'http://localhost',

    // API version
    'api_version' => 'v1',

    // Timezone
    'timezone' => 'Asia/Kuala_Lumpur',

    // Locale
    'locale' => 'en',

    // base path
    'base_path' => '', // v3_controller, v3_controller_development

    // Platform
    'platform' => 'admin',

    // administration url
    'administration_url' => 'http://host.docker.internal:8082', //https://kpcs.kossan.com.my/admin_controller

    // Encryption key
    'key' => 'your-secret-key',

    // Secret key
    'secret_key' => '$ubWQ[f/)8C4&ul',
    
    // Autoloaded service providers
    'providers' => [
        // Add your service providers here
    ],

    // Error handling
    'error_reporting' => E_ALL, // Report all errors
    'display_errors' => true, // Display errors in development

    // Logging
    'log_level' => 'error', // debug, info, notice, warning, error, critical, alert, emergency
];