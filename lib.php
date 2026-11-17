<?php
// lib.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'login-dialog1234-personalgoo60-c027.c.aivencloud.com';
$port = '25195';
$db   = 'defaultdb';
$user = 'avnadmin';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

try { 
    $pdo->query("SELECT username FROM licenses LIMIT 1"); 
} catch (PDOException $e) { 
    try { $pdo->exec("ALTER TABLE licenses ADD COLUMN username VARCHAR(100) DEFAULT 'Unknown User' AFTER license_key"); } catch (Throwable $t) {} 
}

try { 
    $pdo->query("SELECT is_trial FROM licenses LIMIT 1"); 
} catch (PDOException $e) { 
    try { $pdo->exec("ALTER TABLE licenses ADD COLUMN is_trial INT(1) DEFAULT 0 AFTER device_id"); } catch (Throwable $t) {} 
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            return $val !== false ? $val : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('set_setting')) {
    function set_setting($pdo, $key, $val) {
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $val]);
        } catch (Throwable $e) {}
    }
}
?>