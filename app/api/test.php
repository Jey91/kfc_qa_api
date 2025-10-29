<?php

// phpinfo();
// Include necessary files
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/core/Database.php'; // Adjust the path as needed

// Test database connection
try {
    // Get database instance
    $db = \Core\Database::getInstance();

    // Try a simple query to verify connection
    $db->query("SELECT 1");

    // If we get here, connection is successful
    echo "Database connection successful!";

    // Optional: Display connection details
    echo "\nQuery count: " . $db->getQueryCount();

} catch (\PDOException $e) {
    // Connection failed
    echo "Database connection failed: " . $e->getMessage();
}