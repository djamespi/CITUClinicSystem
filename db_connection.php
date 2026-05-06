<?php
// 1. Your Aiven Credentials (Keep these secret in the future!)
$host = 'citu-clinic-db-cituclinic123.l.aivencloud.com';
$port = '14423';
$dbname = 'db_citu_clinic_system';
$username = 'avnadmin';
$password = 'AVNS_ACYn6YzPQeou29H2_6J';

$ca_cert = __DIR__ . '/ca.pem';

// 3. Set up the connection string
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

// 4. Configure security and error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_CA       => $ca_cert,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
];

// 5. Attempt to connect
try {
    $pdo = new PDO($dsn, $username, $password, $options);
    // Uncomment the line below temporarily just to test if you want!
    // echo "Connection successful!";
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>