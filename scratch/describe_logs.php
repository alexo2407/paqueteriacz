<?php
$host = 'localhost';
$db   = 'paquetes_apppack';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
try {
     $pdo = new PDO($dsn, $user, $pass);
     $stmt = $pdo->query("DESCRIBE forwarding_log");
     print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (\PDOException $e) {
     echo "Error: " . $e->getMessage();
}
