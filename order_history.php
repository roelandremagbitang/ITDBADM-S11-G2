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

if (!$user) {
    header('Location: login.php');
    exit;
}

// Enhanced query to get order details with items and transaction info
$stmt = $pdo->prepare("
    SELECT 
        o.id,
        o.total,
        o.currency,
        o.created_at,
        COUNT(oi.id) as item_count,
        SUM(oi.quantity) as total_items,
        GROUP_CONCAT(
            CONCAT(p.name, ' (', oi.quantity, 'x)')
            ORDER BY p.name SEPARATOR ', '
        ) as items_summary
    FROM transaction_log o 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ? 
    GROUP BY o.id, o.total, o.currency, o.created_at
    ORDER BY o.created_at DESC
");

$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total) as total_spent,
        AVG(total) as avg_order_value,
        MAX(created_at) as last_order_date,
        MIN(created_at) as first_order_date
    FROM transaction_log
    WHERE user_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Nviridian</title>
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
        }
        header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .back-btn {
            display: inline-block;
            margin-top: 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        .back-btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5);
        }
        main {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #3b82f6;
        }
        .stat-label {
            color: #cbd5e1;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        .orders-container {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        .orders-header {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .orders-header h2 {
            color: #3b82f6;
            margin-bottom: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            background: rgba(255,255,255,0.05);
            font-weight: 600;
            color: #3b82f6;
        }
        .order-id {
            font-weight: 600;
            color: #22c55e;
        }
        .amount {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .currency {
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .items-summary {
            font-size: 0.85rem;
            color: #cbd5e1;
            max-width: 300px;
        }
        .receipt-btn {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid #3b82f6;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-left: 0.5rem;
        }
        .receipt-btn:hover {
            background: rgba(59, 130, 246, 0.3);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #cbd5e1;
        }
        .empty-state h3 {
            margin-bottom: 1rem;
            color: #f8fafc;
        }
        .shop-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
            transition: 0.3s;
        }
        .shop-btn:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-2px);
        }
        .order-row {
            transition: 0.3s;
        }
        .order-row:hover {
            background: rgba(255,255,255,0.02);
        }
    </style>
</head>
<body>

    <header>
        <h1>🧾 Order History</h1>
        <p>Welcome, <?= htmlspecialchars($user['name']) ?> - Your Complete Purchase History</p>
        <a href="customer_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
    </header>

    <main>
        <?php if (!empty($orders)): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_orders'] ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₱<?= number_format($stats['total_spent'], 2) ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">₱<?= number_format($stats['avg_order_value'], 2) ?></div>
                    <div class="stat-label">Average Order</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= date('M j, Y', strtotime($stats['last_order_date'])) ?></div>
                    <div class="stat-label">Last Order</div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="orders-container">
                <div class="orders-header">
                    <h2>📦 Your Orders</h2>
                    <p>Click on any order to view transaction details and receipts</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date & Time</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Currency</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row">
                                <td>
                                    <span class="order-id">#<?= $order['id'] ?></span>
                                </td>
                                <td>
                                    <?= date("M j, Y", strtotime($order['created_at'])) ?><br>
                                    <small style="color: #cbd5e1;"><?= date("g:i A", strtotime($order['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="items-summary">
                                        <strong><?= $order['total_items'] ?> items</strong><br>
                                        <small><?= $order['items_summary'] ? htmlspecialchars($order['items_summary']) : 'No items found' ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount">₱<?= number_format($order['total'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="currency"><?= $order['currency'] ?></span>
                                </td>
                                <td>
                                    <a href="generate_receipt.php?order_id=<?= $order['id'] ?>" class="receipt-btn">
                                        🧾 Receipt
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>📦 No Orders Yet</h3>
                <p>You haven't placed any orders yet. Start shopping to see your order history here!</p>
                <a href="products.php" class="shop-btn">🛍️ Start Shopping</a>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>