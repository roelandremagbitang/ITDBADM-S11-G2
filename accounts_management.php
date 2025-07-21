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

// Fetch all users
$stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Nviridian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #0f172a, #1e293b); color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }
        header { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 1.5rem 2rem; text-align: center; }
        header h1 { font-size: 1.8rem; font-weight: 700; }
        header p { font-size: 1rem; color: #cbd5e1; margin-top: 0.3rem; }
        .back-btn { display: inline-block; margin-top: 1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.7rem 1.3rem; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .back-btn:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-3px); }
        main { flex: 1; display: flex; justify-content: center; padding: 2rem; }
        .container { width: 95%; max-width: 900px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem; backdrop-filter: blur(15px); box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .search-bar { margin-bottom: 1rem; text-align: center; }
        .search-input { width: 80%; max-width: 400px; padding: 0.5rem; border-radius: 8px; border: none; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; color: #f1f5f9; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,255,255,0.05); color: #cbd5e1; font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.05); transition: 0.3s; }
        footer { text-align: center; padding: 1rem; font-size: 0.9rem; color: #cbd5e1; background: rgba(15, 23, 42, 0.9); border-top: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>

    <header>
        <h1>Manage Users</h1>
        <p>Welcome, <?= $name ?> (<?= ucfirst($role) ?>)</p>
        <a href="admin_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
    </header>

    <main>
        <div class="container">
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search users by name, email, or role...">
            </div>
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        &copy; <?= date("Y") ?> All rights reserved.
    </footer>

    <script>
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('usersTable');
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].innerText.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        });
    </script>

</body>
</html>
