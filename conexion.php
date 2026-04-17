<?php
// conexion.php
$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (!file_exists($configPath)) {
    die("Falta config.local.php. Copia config.example.php y completa la configuracion.");
}

$config = require $configPath;
$dbConfig = $config['db'] ?? [];

$host = $dbConfig['host'] ?? '127.0.0.1';
$user = $dbConfig['user'] ?? '';
$pass = $dbConfig['pass'] ?? '';
$db   = $dbConfig['name'] ?? '';

$con = mysqli_connect($host, $user, $pass, $db);

if (!$con) {
    die("❌ Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Configurar charset a UTF-8
mysqli_set_charset($con, "utf8mb4");
?>
