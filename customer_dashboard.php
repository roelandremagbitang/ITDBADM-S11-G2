<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// FIX: Define $name and $role here
$name = htmlspecialchars($user['name']);
$role = htmlspecialchars($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Nviridian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        body {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        header h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        header p {
            font-size: 1rem;
            color: #cbd5e1;
            margin-top: 0.3rem;
        }
        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: url('https://images.unsplash.com/photo-1605902711622-cfb43c4437f0?fit=crop&w=1920&q=80') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        main::before {
            content: "";
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
        }
        .card {
            position: relative;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
            z-index: 1;
            max-width: 400px;
            width: 90%;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 0.9rem 1.2rem;
            border: none;
            border-radius: 12px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        .btn:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5);
        }
        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
        }
        .logout-btn:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 12px 30px rgba(239, 68, 68, 0.5);
        }
        footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.9);
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: #1e293b;
            padding: 2rem;
            border-radius: 16px;
            max-width: 400px;
            margin: 5% auto;
            position: relative;
        }
        .close {
            position: absolute;
            top: 10px;
            right: 18px;
            font-size: 2rem;
            cursor: pointer;
            color: #f87171;
        }
    </style>
</head>
<body>

    <header>
        <h1>Staff Dashboard</h1>
        <p>Welcome, <?= $name ?> (<?= ucfirst($role) ?>)</p>
    </header>

    <main>
        <div class="card">
            <h2 style="font-size: 1.5rem; font-weight: 600;">Staff Navigations</h2>
            <div class="actions">
                <a href="products.php" class="btn">📦 Browse Products</a>
                
                <a href="order_history.php" class="btn">📋 View Orders</a>
                <button class="btn" onclick="document.getElementById('profileModal').style.display='block'">👤 Edit Profile</button>
                <a href="logout.php" class="btn logout-btn">🚪 Logout</a>
            </div>
        </div>
        <!-- Profile Modal -->
        <div id="profileModal" class="modal" style="display:none;position:fixed;z-index:999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);">
            <div style="background:#1e293b;padding:2rem 2rem 1rem 2rem;border-radius:16px;max-width:400px;margin:5% auto;position:relative;">
                <span onclick="document.getElementById('profileModal').style.display='none'" style="position:absolute;top:10px;right:18px;font-size:2rem;cursor:pointer;color:#f87171;">&times;</span>
                <h2 style="color:#3b82f6;margin-bottom:1rem;">Edit Profile</h2>
                <form method="post" autocomplete="off">
                    <label style="color:#cbd5e1;">Name</label>
                    <input type="text" name="new_name" value="<?= htmlspecialchars($name) ?>" required style="width:100%;padding:0.5rem;margin-bottom:1rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;">
                    <label style="color:#cbd5e1;">New Password</label>
                    <input type="password" name="new_password" placeholder="Leave blank to keep current" style="width:100%;padding:0.5rem;margin-bottom:1rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;">
                    <label style="color:#cbd5e1;">Retype Password</label>
                    <input type="password" name="retype_password" placeholder="Retype new password" style="width:100%;padding:0.5rem;margin-bottom:1.2rem;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;">
                    <button type="submit" name="update_profile" class="btn" style="width:100%;">Save Changes</button>
                </form>
            </div>
        </div>
        <script>
            // Close modal on outside click
            window.onclick = function(event) {
                var modal = document.getElementById('profileModal');
                if (event.target == modal) modal.style.display = "none";
            };
            <?php if (isset($profile_update_success) && $profile_update_success): ?>
                // Close modal and show success message (no reload)
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('profileModal').style.display = 'none';
                    alert('Profile updated successfully!');
                });
            <?php endif; ?>
        </script>
    </main>
    <?php
    // Handle profile update
    $profile_update_success = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $new_name = trim($_POST['new_name']);
        $new_password = $_POST['new_password'];
        $retype_password = $_POST['retype_password'];
        if ($new_name !== '') {
            if ($new_password !== '') {
                if ($new_password !== $retype_password) {
                    echo "<script>alert('Passwords do not match.');</script>";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
                    $stmt->execute([$new_name, $hashed, $user_id]);
                    $name = htmlspecialchars($new_name);
                    $profile_update_success = true;
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $stmt->execute([$new_name, $user_id]);
                $name = htmlspecialchars($new_name);
                $profile_update_success = true;
            }
        }
    }
    ?>

    <footer>
        &copy; <?= date("Y") ?> Nviridian All rights reserved.
    </footer>

</body>
</html>
