<?php
// install.php
require 'lib.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (!empty($username) && !empty($_POST['password'])) {
        try {
            // 1. Create Tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `username` varchar(50) NOT NULL,
                `password` varchar(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
                `setting_key` varchar(50) NOT NULL PRIMARY KEY,
                `setting_value` text DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `licenses` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `license_key` varchar(100) NOT NULL UNIQUE,
                `username` varchar(100) DEFAULT 'Unknown User',
                `device_id` varchar(100) DEFAULT NULL,
                `is_trial` int(1) DEFAULT 0, -- 🔥 New Column
                `status` enum('active','banned','blocked','disabled','expired','deleted') DEFAULT 'active',
                `duration_type` varchar(20) DEFAULT 'hours',
                `duration_value` int(11) DEFAULT 12,
                `max_devices` int(11) DEFAULT 1,
                `activated_at` bigint(20) DEFAULT 0,
                `expires_at` bigint(20) DEFAULT 0,
                `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `license_devices` (
                `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `license_id` int(11) NOT NULL,
                `device_id` varchar(100) NOT NULL,
                `device_name` varchar(100) DEFAULT NULL,
                `device_model` varchar(100) DEFAULT NULL,
                `device_version` varchar(50) DEFAULT NULL,
                `last_seen` bigint(20) DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // 2. Insert Default Settings
            $pdo->exec("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
                ('app_name', 'SADNAN PANEL'),
                ('logo_url', ''),
                ('whatsapp_url', 'https://wa.me/919999999999'),
                ('key_prefix', 'SADNAN'),
                ('trial_active', '1'),
                ('trial_duration', '5'),
                ('text_subtitle', 'Access ke liye apni key daalein'),
                ('text_btn_login', 'LOGIN'),
                ('text_btn_trial', 'START 5-MIN TRIAL'),
                ('text_contact', 'Key nahi hai? Contact admin'),
                ('maintenance_active', '0'),
                ('maintenance_msg', 'Server is under maintenance.'),
                ('update_active', '0'),
                ('update_version', '1.0.0'),
                ('update_url', '');");

            // 3. Insert Admin
            $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->execute([$username, $password]);

            $msg = "<div style='color:#10b981;'>Installation successful! <a href='login.php' style='color:#22d3ee;'>Go to Login</a>. Delete install.php now.</div>";
        } catch (Exception $e) {
            $msg = "<div style='color:#ef4444;'>Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>SADNAN PANEL - Install</title>
    <style>
        body { background: #0A0616; color: #fff; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: rgba(20,10,40,0.6); padding: 30px; border-radius: 16px; width: 300px; text-align: center; border: 1px solid rgba(168,85,247,0.3); box-shadow: 0 10px 30px rgba(168,85,247,0.2); }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: 1px solid #334155; background: #0f172a; color: #fff; box-sizing: border-box; }
        button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: linear-gradient(90deg, #A855F7, #22D3EE); color: #fff; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="card">
        <h2>SADNAN PANEL Setup</h2>
        <?= $msg ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Admin Username" required>
            <input type="password" name="password" placeholder="Admin Password" required>
            <button type="submit">Install Now</button>
        </form>
    </div>
</body>
</html>