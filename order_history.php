<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT o.id, o.total, o.currency, o.created_at 
                       FROM orders o 
                       WHERE o.user_id = ? 
                       ORDER BY o.created_at DESC");

$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order History</title>
    <style>
        body { font-family: Inter, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; border-bottom: 1px solid #334155; text-align: center; }
        th { background: #1e293b; }
        h1 { text-align: center; }
        a.back-btn {
            display: inline-block;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 0.7rem 1.2rem;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <a href="customer_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
    <h1>🧾 Order History</h1>

    <?php if ($orders): ?>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Total Amount</th>
                <th>Currency</th>
                <th>Ordered At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td>#<?= $order['id'] ?></td>
                <td><?= number_format($order['total'], 2) ?></td>
                <td><?= $order['currency'] ?></td>
                <td><?= date("F j, Y, g:i a", strtotime($order['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center;">You have no past orders.</p>
    <?php endif; ?>
</body>
</html>
