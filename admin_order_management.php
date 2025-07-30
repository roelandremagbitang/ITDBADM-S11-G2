
<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM transaction_log WHERE id = ?")->execute([$delete_id]);
    header("Location: admin_order_management.php");
    exit;
}

// Handle Edit
$edit_order = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM transaction_log WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_order = $stmt->fetch(PDO::FETCH_ASSOC);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $update_id = intval($_POST['update_id']);
    $total = floatval($_POST['total']);
    $currency = $_POST['currency'];
    $stmt = $pdo->prepare("UPDATE transaction_log SET total = ?, currency = ? WHERE id = ?");
    $stmt->execute([$total, $currency, $update_id]);
    header("Location: admin_order_management.php");
    exit;
}

// Fetch all orders with user info and items
$stmt = $pdo->query("
    SELECT 
        o.id,
        o.total,
        o.currency,
        o.created_at,
        u.name AS user_name,
        u.role AS user_role,
        COUNT(oi.id) as item_count,
        SUM(oi.quantity) as total_items,
        GROUP_CONCAT(
            CONCAT(p.name, ' (', oi.quantity, 'x)')
            ORDER BY p.name SEPARATOR ', '
        ) as items_summary
    FROM transaction_log o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    GROUP BY o.id, o.total, o.currency, o.created_at, u.name, u.role
    ORDER BY o.created_at DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders - Nviridian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #0f172a, #1e293b); color: #f8fafc; min-height: 100vh; }
        header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; }
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
            vertical-align: top;
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
        .edit-btn {
            background: rgba(245, 158, 66, 0.2);
            color: #f59e42;
            border: 1px solid #f59e42;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 0.5rem;
            font-weight: 600;
            text-decoration: none;
        }
        .edit-btn:hover {
            background: rgba(245, 158, 66, 0.3);
        }
        .delete-btn {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            margin-left: 0.5rem;
            font-weight: 600;
        }
        .delete-btn:hover {
            background: rgba(239, 68, 68, 0.3);
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
        .edit-form {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            max-width: 400px;
        }
        .edit-form label { display: block; margin-bottom: 0.5rem; color: #cbd5e1; }
        .edit-form input, .edit-form select {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #1e293b;
            color: #f8fafc;
        }
        .edit-form button {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            padding: 0.5rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .edit-form button:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }
    </style>
</head>
<body>
    <header>
        <h1>🧾 All Orders</h1>
        <a href="admin_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
    </header>
    <main>
        <?php if ($edit_order): ?>
            <form class="edit-form" method="POST">
                <h2>Edit Order #<?= htmlspecialchars($edit_order['id']) ?></h2>
                <input type="hidden" name="update_id" value="<?= $edit_order['id'] ?>">
                <label>Total Amount</label>
                <input type="number" step="0.01" name="total" value="<?= htmlspecialchars($edit_order['total']) ?>" required>
                <label>Currency</label>
                <select name="currency" required>
                    <option value="PHP" <?= $edit_order['currency'] === 'PHP' ? 'selected' : '' ?>>PHP</option>
                    <option value="USD" <?= $edit_order['currency'] === 'USD' ? 'selected' : '' ?>>USD</option>
                </select>
                <button type="submit">Update Order</button>
                <a href="admin_order_management.php" class="back-btn" style="margin-left:10px;">Cancel</a>
            </form>
        <?php endif; ?>

        <?php if (!empty($orders)): ?>
            <div class="orders-container">
                <div class="orders-header">
                    <h2>📦 All Orders</h2>
                    <p>View, edit, or delete any order from any user (admin, staff, customer)</p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User<br><small style="font-weight:400;color:#3b82f6;">User Role</small></th>
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
                                    <?= htmlspecialchars($order['user_name']) ?>
                                    <br>
                                    <small style="color:#94a3b8;"><?= htmlspecialchars(ucfirst($order['user_role'])) ?></small>
                                </td>
                                <td>
                                    <?= date("M j, Y", strtotime($order['created_at'])) ?><br>
                                    <small style="color: #cbd5e1;"><?= date("g:i A", strtotime($order['created_at'])) ?></small>
                                </td>
                                <td>
                                    <strong><?= $order['total_items'] ?> item<?= $order['total_items'] > 1 ? 's' : '' ?></strong><br>
                                    <small class="items-summary"><?= $order['items_summary'] ? htmlspecialchars($order['items_summary']) : 'No items' ?></small>
                                </td>
                                <td>
                                    <span class="amount">₱<?= number_format($order['total'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="currency"><?= htmlspecialchars($order['currency']) ?></span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="generate_receipt.php?order_id=<?= $order['id'] ?>" class="receipt-btn">🧾 Receipt</a>
                                    <a href="admin_order_management.php?action=edit&id=<?= $order['id'] ?>" class="edit-btn">Edit</a>
                                    <form method="POST" action="admin_order_management.php" style="display:inline;">
                                        <input type="hidden" name="delete_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="delete-btn" onclick="return confirm('Delete this order?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>📦 No Orders Yet</h3>
                <p>No orders found in the system.</p>
            </div>
        <?php endif; ?>