<?php
session_start();
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

// Use session user_id
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}


$order_id = $_GET['order_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    die('Order ID required.');
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    die('Order not found.');
}

// Fetch items
$stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $order_id ?></title>
    <meta charset="UTF-8">
    <style>
        body { font-family: monospace; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.5rem; text-align: left; }
    </style>
</head>
<body>
    <h2>Receipt - Nviridian</h2>
    <p>Order ID: <?= $order_id ?></p>
    <p>Date: <?= $order['created_at'] ?></p>
    <p>Currency: <?= $order['currency'] ?></p>
    <table>
        <tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td><?= number_format($item['price'], 2) ?></td>
                <td><?= number_format($item['price'] * $item['quantity'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr><th colspan="3">Total</th><th><?= number_format($order['total'], 2) ?></th></tr>
    </table>
    <p>Thank you for shopping with us!</p>
    <button onclick="window.print()" style="padding:0.5rem 1rem; margin-top:1rem;">Print Receipt</button>
   <button onclick="window.location.href='products.php'" style="padding:0.5rem 1rem; margin-top:1rem;">Continue Shopping</button>
</body>
</html>
