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

if (!$user || $user['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$name = htmlspecialchars($user['name']);
$role = htmlspecialchars($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Nviridian</title>
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
    </style>
</head>
<body>

    <header>
        <h1>Admin Dashboard</h1>
        <p>Welcome, <?= $name ?> (<?= ucfirst($role) ?>)</p>
    </header>

    <main>
        <div class="card">
            <h2 style="font-size: 1.5rem; font-weight: 600;">Navigations</h2>
            <div class="actions">
                 <a href="products_management.php" class="btn">🛠️ Manage Products</a>
                <a href="products.php" class="btn">📦 View Products</a>
                <a href="accounts_management.php" class="btn">👥 Manage Users</a>
                <a href="logout.php" class="btn logout-btn">🚪 Logout</a>
            </div>
        </div>
    </main>

    <footer>
        &copy; <?= date("Y") ?> Nviridian All rights reserved.
    </footer>

</body>
</html>
