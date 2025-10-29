<?php
// app/config/middleware.php

return [
    // Global middleware (applied to all routes)
    'global' => [
    ],

    // Named middleware (applied to specific routes)
    'route' => [
        'auth' => \App\Middleware\AuthMiddleware::class,
    ]
];