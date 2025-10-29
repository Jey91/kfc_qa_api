<?php

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
header( 'Access-Control-Max-Age: 3600' );
header( 'Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token' );

return [
    // Default database connection
    'default' => 'sqlsrv',

    // Database connections
    'connections' => [
        // MySQL connection
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'phantom',
            'username' => 'root',
            'password' => 'rootpass',
            'charset' => 'utf8mb4',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ],

        // MSSQL connection
        // 'sqlsrv' => [
        //     'driver' => 'sqlsrv',
        //     'host' => '10.200.10.121',
        //     'port' => '1433',
        //     'database' => 'mes',
        //     'username' => 'bryan',
        //     'password' => 'kpcs@123',
        //     'options' => [
        //         \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        //         \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        // ]
        // ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => 'db',
            'port' => '1437',
            'database' => 'qa',
            'username' => 'sa',
            'password' => 'YourStrong@Passw0rd',
            'charset' => 'utf8',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'TrustServerCertificate' => true,
        ],

    ],

    // Query logging
    'log_queries' => false,
];