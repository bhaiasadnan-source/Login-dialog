<?php
// api.php
header('Content-Type: application/json');
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch (PDOException $e) { die(json_encode(["error" => "DB Error"])); }

$action = $_GET['action'] ?? '';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if (empty($user_agent) || preg_match('/curl|python|wget|postman|scrapy|httpclient|java\//i', $user_agent)) {
    http_response_code(403); die(json_encode(["success" => false, "message" => "Access Denied"]));
}

function get_setting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

// 1. CONFIG API
if ($action == 'config') {
    echo json_encode([
        "branding" => [
            "app_name" => get_setting($pdo, 'app_name', 'SADNAN PANEL'), 
            "logo_url" => get_setting($pdo, 'logo_url'),
            "whatsapp_url" => get_setting($pdo, 'whatsapp_url'),
            "text_subtitle" => get_setting($pdo, 'text_subtitle'),
            "text_btn_login" => get_setting($pdo, 'text_btn_login'),
            "text_btn_trial" => get_setting($pdo, 'text_btn_trial'),
            "text_contact" => get_setting($pdo, 'text_contact')
        ],
        "trial" => [
            "active" => get_setting($pdo, 'trial_active') == '1',
            "duration" => (int)get_setting($pdo, 'trial_duration', 5)
        ],
        "maintenance" => [
            "active" => get_setting($pdo, 'maintenance_active') == '1', 
            "message" => get_setting($pdo, 'maintenance_msg')
        ],
        "update" => [
            "active" => get_setting($pdo, 'update_active') == '1', 
            "version" => get_setting($pdo, 'update_version'), 
            "url" => get_setting($pdo, 'update_url')
        ]
    ]);
    exit;
}

// 2. START TRIAL API
if ($action == 'start_trial') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(["success" => false]));
    if (get_setting($pdo, 'trial_active') != '1') die(json_encode(["success" => false, "message" => "Trial is disabled by admin"]));
    
    $device_id = trim($_POST['device_id'] ?? '');
    if (empty($device_id)) die(json_encode(["success" => false]));

    // Check if device already took a trial
    $stmt = $pdo->prepare("SELECT l.id FROM licenses l JOIN license_devices d ON l.id = d.license_id WHERE l.is_trial = 1 AND d.device_id = ?");
    $stmt->execute([$device_id]);
    if ($stmt->fetch()) die(json_encode(["success" => false, "message" => "Trial already used on this device"]));

    $trial_mins = (int)get_setting($pdo, 'trial_duration', 5);
    $new_key = "TRIAL-" . strtoupper(bin2hex(random_bytes(2)));
    $now_ms = round(microtime(true) * 1000);
    $expires_at = $now_ms + ($trial_mins * 60 * 1000); 
    
    $pdo->prepare("INSERT INTO licenses (license_key, status, is_trial, duration_type, duration_value, max_devices, activated_at, expires_at) VALUES (?, 'active', 1, 'minutes', ?, 1, ?, ?)")
        ->execute([$new_key, $trial_mins, $now_ms, $expires_at]);
    $license_id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO license_devices (license_id, device_id, device_name, device_model, device_version, last_seen) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$license_id, $device_id, $_POST['device_name'] ?? 'Unknown', $_POST['device_model'] ?? 'Unknown', $_POST['device_version'] ?? 'Unknown', $now_ms]);

    echo json_encode(["success" => true, "license_key" => $new_key, "expires_at" => $expires_at]);
    exit;
}

// 3. VERIFY API
if ($action == 'verify') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') die(json_encode(["success" => false]));
    $device_id = trim($_POST['device_id'] ?? '');
    $license_key = trim($_POST['license_key'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$license_key]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$license) die(json_encode(["success" => false, "message" => "INVALID LICENSE KEY"])); 
    if ($license['status'] !== 'active') die(json_encode(["success" => false, "status" => $license['status'], "message" => "LICENSE IS " . strtoupper($license['status'])])); 

    $now_ms = round(microtime(true) * 1000);
    $expires_at = (int)$license['expires_at'];

    if ($expires_at > 0 && $now_ms > $expires_at) {
        $pdo->prepare("UPDATE licenses SET status = 'expired' WHERE id = ?")->execute([$license['id']]);
        die(json_encode(["success" => false, "status" => "expired", "message" => "LICENSE EXPIRED"])); 
    }

    $stmtDev = $pdo->prepare("SELECT * FROM license_devices WHERE license_id = ? AND device_id = ?");
    $stmtDev->execute([$license['id'], $device_id]);
    $current_device = $stmtDev->fetch(PDO::FETCH_ASSOC);

    if (!$current_device) {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM license_devices WHERE license_id = ?");
        $stmtCount->execute([$license['id']]);
        if ($stmtCount->fetchColumn() >= $license['max_devices']) die(json_encode(["success" => false, "message" => "MAX DEVICES REACHED"])); 
        
        $pdo->prepare("INSERT INTO license_devices (license_id, device_id, device_name, device_model, device_version, last_seen) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$license['id'], $device_id, $_POST['device_name'] ?? 'Unknown', $_POST['device_model'] ?? 'Unknown', $_POST['device_version'] ?? 'Unknown', $now_ms]);
    } else {
        $pdo->prepare("UPDATE license_devices SET last_seen = ? WHERE id = ?")->execute([$now_ms, $current_device['id']]);
    }

    if ($license['activated_at'] == 0) {
        $dur_type = $license['duration_type']; $dur_val = (int)$license['duration_value']; $new_exp = 0;
        if ($dur_type == 'hours') $new_exp = $now_ms + ($dur_val * 3600000);
        elseif ($dur_type == 'days') $new_exp = $now_ms + ($dur_val * 86400000);
        elseif ($dur_type == 'months') $new_exp = $now_ms + ($dur_val * 2592000000);
        elseif ($dur_type == 'years') $new_exp = $now_ms + ($dur_val * 31536000000);
        $pdo->prepare("UPDATE licenses SET activated_at = ?, expires_at = ? WHERE id = ?")->execute([$now_ms, $new_exp, $license['id']]);
        $expires_at = $new_exp;
    }

    echo json_encode(["success" => true, "status" => "active", "expires_at" => $expires_at]);
    exit;
}
http_response_code(400); echo json_encode(["error" => "Invalid Action"]);
?>