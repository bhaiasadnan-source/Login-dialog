<?php
// login.php
require 'lib.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            header("Location: admin.php");
            exit;
        } else {
            $error = "Invalid Username or Password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | SADNAN PANEL</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --c1: #A855F7; /* Purple */
            --c2: #22D3EE; /* Cyan */
            --bg: #0A0616;
            --card: rgba(20, 10, 40, 0.6);
            --text: #F8F5FF;
            --muted: #B8ACD9;
            --err: #FB7185;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        
        body {
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background Orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.5;
            z-index: 0;
            animation: float 10s infinite alternate cubic-bezier(0.4, 0, 0.2, 1);
        }
        .orb-1 { width: 300px; height: 300px; background: var(--c1); top: -10%; left: -10%; }
        .orb-2 { width: 250px; height: 250px; background: var(--c2); bottom: -10%; right: -10%; animation-delay: -5s; }
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 40px) scale(1.1); }
        }

        /* Premium Glass Card */
        .login-card {
            position: relative;
            z-index: 10;
            background: var(--card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 20px;
            padding: 40px 30px;
            width: 90%;
            max-width: 380px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 0 0 20px rgba(34, 211, 238, 0.1);
            animation: cardIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(168,85,247,0.2), rgba(34,211,238,0.2));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.3);
        }
        .logo-icon svg { width: 32px; height: 32px; stroke: var(--c2); }

        h2 {
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 8px;
            background: linear-gradient(90deg, var(--c1), #fff, var(--c2));
            -webkit-background-clip: text;
            color: transparent;
        }
        p.subtitle { text-align: center; color: var(--muted); font-size: 13px; margin-bottom: 30px; }

        .input-group { margin-bottom: 20px; position: relative; }
        .input-group svg {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; stroke: var(--muted); fill: none;
        }
        input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.4);
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }
        input:focus {
            border-color: var(--c1);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.2);
        }
        input:focus + svg { stroke: var(--c1); }

        button {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(90deg, var(--c1), var(--c2));
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 20px rgba(34, 211, 238, 0.3);
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(168, 85, 247, 0.4);
        }
        button:active { transform: scale(0.98); }

        .error-msg {
            background: rgba(251, 113, 133, 0.1);
            border: 1px solid rgba(251, 113, 133, 0.3);
            color: var(--err);
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="login-card">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        
        <h2>SADNAN PANEL</h2>
        <p class="subtitle">Enter credentials to access</p>
        <p style="font-size:11px; color:#6b7280; text-align:center; margin-top:-18px; margin-bottom:16px;">Developed by <span style="color:#A855F7; font-weight:700;">SADNAN</span></p>

        <?php if(!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <input type="text" name="username" placeholder="Admin Username" required autocomplete="off">
            </div>
            
            <div class="input-group">
                <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">LOGIN SECURELY</button>
        </form>
    </div>

</body>
</html>