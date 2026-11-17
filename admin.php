<?php
// admin.php
// 🔥 ERROR REPORTING (Debugging ke liye) 🔥
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'lib.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); 
    exit;
}

// Safe fallbacks just in case lib.php missed them
if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }
}
if (!function_exists('set_setting')) {
    function set_setting($pdo, $key, $val) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $val]);
    }
}

// Handle Actions
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Generate Key
        if (isset($_POST['generate_key'])) {
            $prefix = strtoupper(trim(get_setting($pdo, 'key_prefix', 'SADNAN')));
            $new_key = $prefix . "-" . strtoupper(bin2hex(random_bytes(2))) . "-" . strtoupper(bin2hex(random_bytes(2)));
            
            $stmt = $pdo->prepare("INSERT INTO licenses (license_key, username, duration_type, duration_value, max_devices, expires_at, activated_at) VALUES (?, ?, ?, ?, ?, 0, 0)");
            $stmt->execute([$new_key, $_POST['username'], $_POST['duration_type'], $_POST['duration_value'], $_POST['max_devices']]);
            $msg = "Key Generated: $new_key";
        }

        // 2. Edit Key
        if (isset($_POST['edit_key'])) {
            $stmt = $pdo->prepare("UPDATE licenses SET username = ?, duration_value = ?, max_devices = ? WHERE id = ?");
            $stmt->execute([$_POST['edit_username'], $_POST['edit_duration'], $_POST['edit_max_devices'], $_POST['id']]);
            $msg = "Key Updated Successfully!";
        }

        // 3. Save Settings (General Settings)
        if (isset($_POST['save_settings'])) {
            $inputs = ['app_name', 'whatsapp_url', 'key_prefix', 'text_subtitle', 'text_btn_login', 'text_btn_trial', 'text_contact', 'maintenance_msg', 'update_version', 'update_url'];
            foreach($inputs as $i) { 
                if (isset($_POST[$i])) set_setting($pdo, $i, trim($_POST[$i])); 
            }
            
            set_setting($pdo, 'maintenance_active', isset($_POST['maintenance_active']) ? '1' : '0');
            set_setting($pdo, 'update_active', isset($_POST['update_active']) ? '1' : '0');
            
            $msg = "Settings Saved Successfully!";
        }

        // 4. Save Trial Settings (New Trial Tab)
        if (isset($_POST['save_trial_settings'])) {
            set_setting($pdo, 'trial_active', isset($_POST['trial_active']) ? '1' : '0');
            set_setting($pdo, 'trial_duration', trim($_POST['trial_duration']));
            $msg = "Trial Settings Saved!";
        }

        // 5. Actions (Ban, Activate, Delete)
        if (isset($_POST['action']) && isset($_POST['id'])) {
            if ($_POST['action'] == 'delete') {
                $pdo->prepare("DELETE FROM license_devices WHERE license_id = ?")->execute([$_POST['id']]);
                $pdo->prepare("DELETE FROM licenses WHERE id = ?")->execute([$_POST['id']]);
                $msg = "Key Deleted!";
            } else {
                $pdo->prepare("UPDATE licenses SET status = ? WHERE id = ?")->execute([$_POST['action'], $_POST['id']]);
                $msg = "Status Updated to " . strtoupper($_POST['action']);
            }
        }

        // 6. Upload Logo
        if (isset($_POST['upload_logo']) && isset($_FILES['logo_file'])) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            
            $file_ext = strtolower(pathinfo($_FILES["logo_file"]["name"], PATHINFO_EXTENSION));
            $new_filename = "logo_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["logo_file"]["tmp_name"], $target_file)) {
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
                $logo_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/" . $target_file;
                set_setting($pdo, 'logo_url', $logo_url);
                $msg = "Logo Uploaded!";
            } else {
                throw new Exception("Error uploading file. Check folder permissions.");
            }
        }
    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
        $msg_type = "error";
    }
}

// Fetch Data
try {
    $keys = $pdo->query("SELECT * FROM licenses WHERE is_trial = 0 ORDER BY id DESC")->fetchAll();
    $total_keys = count($keys);
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status='active' AND is_trial = 0");
    $active_keys = $stmt->fetchColumn();

    // Fetch Trial Data (New)
    $trials = $pdo->query("
        SELECT l.*, d.device_id, d.device_name, d.device_model 
        FROM licenses l 
        LEFT JOIN license_devices d ON l.id = d.license_id 
        WHERE l.is_trial = 1 
        ORDER BY l.id DESC
    ")->fetchAll();
    $total_trials = count($trials);
    
    $now_ms = round(microtime(true) * 1000);
    $active_trials = 0;
    foreach($trials as $t) {
        if ($t['status'] == 'active' && ($t['expires_at'] > $now_ms || $t['expires_at'] == 0)) {
            $active_trials++;
        }
    }

} catch (Exception $e) {
    die("Database Error. Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SADNAN Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg: #0A0616; 
            --card: rgba(20,10,40,0.6); 
            --c1: #A855F7; 
            --c2: #22D3EE; 
            --err: #FB7185; 
            --success: #10b981; 
            --text: #F8F5FF;
            --muted: #B8ACD9;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background: var(--bg); color: var(--text); padding-bottom: 80px; overflow-x: hidden; }
        
        /* Background Animation */
        .bg-anim { position: fixed; inset: 0; z-index: -1; overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.35; }
        .orb1 { width: 300px; height: 300px; background: var(--c1); top: -50px; left: -50px; animation: float 10s infinite alternate; }
        .orb2 { width: 250px; height: 250px; background: var(--c2); bottom: -50px; right: -50px; animation: float 12s infinite alternate-reverse; }
        @keyframes float { 0% { transform: translate(0,0); } 100% { transform: translate(30px, 30px); } }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; padding: 20px; position: sticky; top: 0; background: rgba(10,6,22,0.8); backdrop-filter: blur(10px); z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .header h1 { font-size: 20px; font-weight: 800; background: linear-gradient(90deg, var(--c1), var(--c2)); -webkit-background-clip: text; color: transparent; }
        .logout-btn { background: rgba(251,113,133,0.1); color: var(--err); padding: 8px 12px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; border: 1px solid rgba(251,113,133,0.3); }

        .container { padding: 15px; max-width: 600px; margin: 0 auto; }
        
        /* Tabs */
        .tab-content { display: none; animation: fadeUp 0.4s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Glass Cards */
        .glass { background: var(--card); backdrop-filter: blur(16px); border: 1px solid rgba(168,85,247,0.2); border-radius: 20px; padding: 20px; margin-bottom: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        h3 { color: var(--c2); margin-bottom: 15px; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Inputs */
        input[type="text"], input[type="number"], select { width: 100%; padding: 14px; margin-bottom: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.4); color: #fff; font-size: 15px; outline: none; transition: 0.3s; }
        input:focus, select:focus { border-color: var(--c1); box-shadow: 0 0 10px rgba(168,85,247,0.2); }
        
        /* Buttons */
        button, .btn { width: 100%; padding: 14px; border: none; border-radius: 12px; background: linear-gradient(90deg, var(--c1), var(--c2)); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; display: block; text-align: center; }
        button:active { transform: scale(0.98); }
        .btn-sm { padding: 10px 15px; width: auto; border-radius: 8px; font-size: 13px; font-weight: 600; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .stat-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 20px 15px; text-align: center; }
        .stat-box b { display: block; font-size: 26px; color: var(--c1); margin-bottom: 5px; }
        .stat-box span { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Key & Trial Cards */
        .key-card { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 15px; margin-bottom: 15px; position: relative; overflow: hidden; }
        .key-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--c1); }
        .key-card.trial::before { background: var(--c2); } /* Cyan for trials */
        
        .key-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .key-name { font-size: 13px; color: var(--muted); margin-bottom: 4px; display: block; }
        .key-text { font-family: monospace; font-size: 15px; font-weight: 700; color: #fff; letter-spacing: 1px; }
        .device-info { font-size: 11px; color: #888; margin-top: 4px; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 4px; display: inline-block; }
        
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .badge.active { background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
        .badge.banned { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .badge.expired { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }

        .action-row { display: flex; gap: 8px; margin-top: 15px; }
        .action-row form { flex: 1; }
        .action-row button { width: 100%; padding: 10px; font-size: 13px; border-radius: 10px; }
        .btn-ban { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .btn-activate { background: rgba(16,185,129,0.2); color: var(--success); border: 1px solid rgba(16,185,129,0.3); }
        .btn-delete { background: rgba(255,255,255,0.05); color: var(--muted); border: 1px solid rgba(255,255,255,0.1); }

        /* Custom Toggle Switch */
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #2a303c; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--c1); }
        input:checked + .slider:before { transform: translateX(22px); }
        
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .setting-row:last-child { border-bottom: none; }

        /* Form Grids */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 400px) { .grid-2 { grid-template-columns: 1fr; } }

        /* Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; display: flex; justify-content: space-around; background: rgba(10,6,22,0.9); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.08); z-index: 100; padding-bottom: env(safe-area-inset-bottom); }
        .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 12px 0; color: var(--muted); font-size: 11px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .nav-item.active { color: var(--c1); }
        .nav-item svg { width: 22px; height: 22px; margin-bottom: 4px; fill: currentColor; }

        /* Alert */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: center; }
        .alert.success { background: rgba(16,185,129,0.15); color: var(--success); border: 1px solid var(--success); }
        .alert.error { background: rgba(251,113,133,0.15); color: var(--err); border: 1px solid var(--err); }
        
        /* Details/Summary */
        details { margin-top: 15px; background: rgba(0,0,0,0.2); border-radius: 10px; padding: 10px; }
        summary { cursor: pointer; color: var(--c2); font-size: 13px; font-weight: 600; outline: none; }
        
        .logo-preview { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--c1); display: block; margin-bottom: 10px; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); font-size: 14px; }
    </style>
</head>
<body>

<div class="bg-anim"><div class="orb orb1"></div><div class="orb orb2"></div></div>

<!-- HEADER -->
<div class="header">
    <h1>SADNAN PANEL</h1>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<div class="container">
    <?php if(!empty($msg)): ?>
        <div class="alert <?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- TAB 1: DASHBOARD -->
    <div id="tab-dashboard" class="tab-content active">
        <div class="stats-grid">
            <div class="stat-box"><b><?= $total_keys ?></b><span>Total Keys</span></div>
            <div class="stat-box"><b><?= $active_keys ?></b><span>Active Keys</span></div>
            <div class="stat-box"><b><?= $total_trials ?></b><span>Total Trials</span></div>
            <div class="stat-box"><b><?= $active_trials ?></b><span>Live Trials</span></div>
        </div>

        <div class="glass">
            <h3>Generate New Key</h3>
            <form method="POST">
                <input type="text" name="username" placeholder="Customer Name" required>
                <div class="grid-2">
                    <select name="duration_type">
                        <option value="hours">Hours</option><option value="days">Days</option>
                        <option value="months">Months</option><option value="years">Years</option>
                    </select>
                    <input type="number" name="duration_value" value="1" placeholder="Duration">
                </div>
                <input type="number" name="max_devices" value="1" placeholder="Max Devices">
                <button type="submit" name="generate_key">Generate Key</button>
            </form>
        </div>
    </div>

    <!-- TAB 2: MANAGE KEYS -->
    <div id="tab-keys" class="tab-content">
        <?php if(empty($keys)): ?>
            <div class="empty-state">No paid keys generated yet.</div>
        <?php endif; ?>
        
        <?php foreach($keys as $k): ?>
            <div class="key-card">
                <div class="key-header">
                    <div>
                        <span class="key-name"><?= htmlspecialchars($k['username']) ?></span>
                        <div class="key-text"><?= htmlspecialchars($k['license_key']) ?></div>
                    </div>
                    <span class="badge <?= $k['status'] ?>"><?= $k['status'] ?></span>
                </div>
                
                <div class="action-row">
                    <?php if($k['status'] == 'active'): ?>
                        <form method="POST"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button name="action" value="banned" class="btn-ban">Ban</button></form>
                    <?php else: ?>
                        <form method="POST"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button name="action" value="active" class="btn-activate">Activate</button></form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Delete this key permanently?')">
                        <input type="hidden" name="id" value="<?= $k['id'] ?>">
                        <button name="action" value="delete" class="btn-delete">Delete</button>
                    </form>
                </div>

                <details>
                    <summary>Edit Key Details (Extend Time)</summary>
                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="id" value="<?= $k['id'] ?>">
                        <input type="text" name="edit_username" value="<?= htmlspecialchars($k['username']) ?>" placeholder="Name">
                        <div class="grid-2">
                            <input type="number" name="edit_duration" value="<?= $k['duration_value'] ?>" placeholder="Time">
                            <input type="number" name="edit_max_devices" value="<?= $k['max_devices'] ?>" placeholder="Max Devices">
                        </div>
                        <button type="submit" name="edit_key" class="btn-sm" style="width:100%; background:var(--c1);">Update Key</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAB 3: TRIAL MANAGEMENT (NEW) -->
    <div id="tab-trial" class="tab-content">
        
        <div class="glass">
            <h3>Trial Settings</h3>
            <form method="POST">
                <div class="setting-row" style="padding-top:0;">
                    <span style="font-size:15px; font-weight:600;">Enable Trial Button</span>
                    <label class="switch">
                        <input type="checkbox" name="trial_active" <?= get_setting($pdo, 'trial_active') == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <label style="font-size:12px; color:var(--muted); margin-top:10px; display:block;">Trial Time (Minutes)</label>
                <input type="number" name="trial_duration" value="<?= htmlspecialchars(get_setting($pdo, 'trial_duration', '5')) ?>">
                <button type="submit" name="save_trial_settings" style="background:var(--c1);">Save Trial Settings</button>
            </form>
        </div>

        <h3 style="color:var(--c2); margin-bottom:15px; padding-left:5px;">Recent Trials (<?= count($trials) ?>)</h3>
        
        <?php if(empty($trials)): ?>
            <div class="empty-state">No users have taken a trial yet.</div>
        <?php endif; ?>

        <?php foreach($trials as $t): ?>
            <div class="key-card trial">
                <div class="key-header">
                    <div>
                        <span class="key-name">Trial User</span>
                        <div class="key-text"><?= htmlspecialchars($t['license_key']) ?></div>
                        <?php if(!empty($t['device_id'])): ?>
                            <div class="device-info">
                                <b><?= htmlspecialchars($t['device_name'] ?? 'Unknown') ?> <?= htmlspecialchars($t['device_model'] ?? '') ?></b><br>
                                ID: <?= substr(htmlspecialchars($t['device_id']), 0, 12) ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= $t['status'] ?>"><?= $t['status'] ?></span>
                </div>
                
                <div class="action-row">
                    <?php if($t['status'] == 'active'): ?>
                        <form method="POST"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button name="action" value="banned" class="btn-ban">Block Device</button></form>
                    <?php else: ?>
                        <form method="POST"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button name="action" value="active" class="btn-activate">Unblock</button></form>
                    <?php endif; ?>
                    
                    <form method="POST" onsubmit="return confirm('Delete this trial record?')">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button name="action" value="delete" class="btn-delete">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- TAB 4: SETTINGS -->
    <div id="tab-settings" class="tab-content">
        <form method="POST" enctype="multipart/form-data">
            
            <div class="glass">
                <h3>Branding & App Info</h3>
                <div class="grid-2">
                    <div>
                        <label style="font-size:12px; color:var(--muted);">App Name</label>
                        <input type="text" name="app_name" value="<?= htmlspecialchars(get_setting($pdo, 'app_name', 'SADNAN PANEL')) ?>">
                    </div>
                    <div>
                        <label style="font-size:12px; color:var(--muted);">Key Prefix</label>
                        <input type="text" name="key_prefix" value="<?= htmlspecialchars(get_setting($pdo, 'key_prefix', 'SADNAN')) ?>">
                    </div>
                </div>
                
                <label style="font-size:12px; color:var(--muted);">WhatsApp URL</label>
                <input type="text" name="whatsapp_url" value="<?= htmlspecialchars(get_setting($pdo, 'whatsapp_url')) ?>">

                <label style="font-size:12px; color:var(--muted);">App Logo</label>
                <?php if(get_setting($pdo, 'logo_url')): ?>
                    <img src="<?= htmlspecialchars(get_setting($pdo, 'logo_url')) ?>?t=<?= time() ?>" class="logo-preview">
                <?php endif; ?>
                <input type="file" name="logo_file" accept="image/*" style="padding:10px; background:rgba(255,255,255,0.05);">
                <button type="submit" name="upload_logo" style="background:rgba(168,85,247,0.2); color:var(--c1); border:1px solid var(--c1); margin-top:10px;">Upload Logo Only</button>
            </div>

            <div class="glass">
                <h3>App UI Texts</h3>
                <input type="text" name="text_subtitle" value="<?= htmlspecialchars(get_setting($pdo, 'text_subtitle')) ?>" placeholder="Subtitle">
                <div class="grid-2">
                    <input type="text" name="text_btn_login" value="<?= htmlspecialchars(get_setting($pdo, 'text_btn_login')) ?>" placeholder="Login Button">
                    <input type="text" name="text_btn_trial" value="<?= htmlspecialchars(get_setting($pdo, 'text_btn_trial')) ?>" placeholder="Trial Button">
                </div>
                <input type="text" name="text_contact" value="<?= htmlspecialchars(get_setting($pdo, 'text_contact')) ?>" placeholder="Contact Admin Text">
            </div>

            <div class="glass">
                <h3>Maintenance & Update</h3>
                <div class="setting-row">
                    <span style="font-size:14px; font-weight:600;">Enable Maintenance</span>
                    <label class="switch">
                        <input type="checkbox" name="maintenance_active" <?= get_setting($pdo, 'maintenance_active') == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <input type="text" name="maintenance_msg" value="<?= htmlspecialchars(get_setting($pdo, 'maintenance_msg')) ?>" placeholder="Maintenance Message" style="margin-top:10px;">
                
                <div class="setting-row">
                    <span style="font-size:14px; font-weight:600;">Force Update</span>
                    <label class="switch">
                        <input type="checkbox" name="update_active" <?= get_setting($pdo, 'update_active') == '1' ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="grid-2" style="margin-top:10px;">
                    <input type="text" name="update_version" value="<?= htmlspecialchars(get_setting($pdo, 'update_version')) ?>" placeholder="Version">
                    <input type="text" name="update_url" value="<?= htmlspecialchars(get_setting($pdo, 'update_url')) ?>" placeholder="Update URL">
                </div>
            </div>

            <button type="submit" name="save_settings" style="font-size:16px; padding:16px; margin-bottom:20px;">Save All Settings</button>
        </form>
    </div>
</div>

<!-- BOTTOM NAVIGATION -->
<div class="bottom-nav">
    <div class="nav-item active" onclick="switchTab('dashboard', this)">
        <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        <span>Dashboard</span>
    </div>
    <div class="nav-item" onclick="switchTab('keys', this)">
        <svg viewBox="0 0 24 24"><path d="M12.65 10C11.83 7.67 9.61 6 7 6c-3.31 0-6 2.69-6 6s2.69 6 6 6c2.61 0 4.83-1.67 5.65-4H17v4h4v-4h2v-4H12.65zM7 14c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>
        <span>Keys</span>
    </div>
    <div class="nav-item" onclick="switchTab('trial', this)">
        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
        <span>Trial</span>
    </div>
    <div class="nav-item" onclick="switchTab('settings', this)">
        <svg viewBox="0 0 24 24"><path d="M19.14,12.94c0.04-0.3,0.06-0.61,0.06-0.94c0-0.32-0.02-0.64-0.06-0.94l2.03-1.58c0.18-0.14,0.23-0.41,0.12-0.61 l-1.92-3.32c-0.12-0.22-0.37-0.29-0.59-0.22l-2.39,0.96c-0.5-0.38-1.03-0.7-1.62-0.94L14.4,2.81c-0.04-0.24-0.24-0.41-0.48-0.41 h-3.84c-0.24,0-0.43,0.17-0.47,0.41L9.25,5.35C8.66,5.59,8.12,5.92,7.63,6.29L5.24,5.33c-0.22-0.08-0.47,0-0.59,0.22L2.73,8.87 C2.62,9.08,2.66,9.34,2.86,9.48l2.03,1.58C4.84,11.36,4.8,11.69,4.8,12s0.02,0.64,0.06,0.94l-2.03,1.58 c-0.18,0.14-0.23,0.41-0.12,0.61l1.92,3.32c0.12,0.22,0.37,0.29,0.59,0.22l2.39-0.96c0.5,0.38,1.03,0.7,1.62,0.94l0.36,2.54 c0.05,0.24,0.24,0.41,0.48,0.41h3.84c0.24,0,0.44-0.17,0.47-0.41l0.36-2.54c0.59-0.24,1.13-0.56,1.62-0.94l2.39,0.96 c0.22,0.08,0.47,0,0.59-0.22l1.92-3.32c0.12-0.22,0.07-0.49-0.12-0.61L19.14,12.94z M12,15.6c-1.98,0-3.6-1.62-3.6-3.6 s1.62-3.6,3.6-3.6s3.6,1.62,3.6,3.6S13.98,15.6,12,15.6z"/></svg>
        <span>Settings</span>
    </div>
</div>

<script>
function switchTab(tabName, element) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    // Remove active class from all nav items
    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    // Add active class to clicked nav
    element.classList.add('active');
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Auto hide alert after 4 seconds
setTimeout(() => {
    const alert = document.querySelector('.alert');
    if(alert) { alert.style.display = 'none'; }
}, 4000);
</script>

<!-- SADNAN CREDIT FOOTER -->
<div style="text-align:center; padding:16px 0 30px; font-size:12px; color:#6b7280;">
    Developed & Powered by <span style="color:#A855F7; font-weight:700;">SADNAN</span> &mdash; All Rights Reserved
</div>
</body>
</html>