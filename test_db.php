<?php
/**
 * Test de conexión a BD - ELIMINAR DESPUÉS DE USAR
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de conexión Aquatiq</h2>";

// Cargar .env
$envFile = __DIR__ . '/.env';
echo "<p><strong>.env existe:</strong> " . (file_exists($envFile) ? 'SÍ' : 'NO') . "</p>";

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$name = $_ENV['DB_NAME'] ?? 'aquatiq_bd';
$user = $_ENV['DB_USER'] ?? 'aquatiq_usr';
$pass = $_ENV['DB_PASS'] ?? '';

echo "<p><strong>Host:</strong> $host</p>";
echo "<p><strong>BD:</strong> $name</p>";
echo "<p><strong>Usuario:</strong> $user</p>";
echo "<p><strong>Pass:</strong> " . (empty($pass) ? '(vacío)' : '****') . "</p>";

echo "<hr><h3>Intentando conexión...</h3>";

try {
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    echo "<p style='color:green;'><strong>✅ Conexión exitosa!</strong></p>";
    
    // Verificar tabla usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>Usuarios en BD:</strong> " . $result['total'] . "</p>";
    
    // Verificar usuario admin
    $stmt = $pdo->query("SELECT id, nombre, email, rol FROM usuarios WHERE email = 'admin@aquatiq.es'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        echo "<p style='color:green;'><strong>✅ Usuario admin encontrado:</strong> " . $admin['email'] . "</p>";
    } else {
        echo "<p style='color:red;'><strong>❌ Usuario admin NO encontrado. ¿Ejecutaste el schema.sql?</strong></p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>❌ Error:</strong> " . $e->getMessage() . "</p>";
}
