<?php
$serverName = "db"; // SQL Server container name
$connectionOptions = array(
    "Database" => "mes",
    "Uid" => "sa",
    "PWD" => "YourStrong@Passw0rd",
    "TrustServerCertificate" => "yes" // Add this to bypass SSL verification
);

try {
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    echo "Connected to SQL Server successfully!";
    sqlsrv_close($conn);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>