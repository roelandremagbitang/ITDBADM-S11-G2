<?php
session_start();
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

include 'db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: login.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$currency = $_POST['currency'] ?? 'PHP';
$allowed_currencies = ['PHP', 'USD', 'KRW'];
if (!in_array($currency, $allowed_currencies)) {
    $currency = 'PHP';
}

$user_id = $_SESSION['user_id'];

if (!$cart) {
    header('Location: cart.php');
    exit;
}

// Fetch product details
$placeholders = implode(',', array_fill(0, count($cart), '?'));
$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
$stmt->execute(array_keys($cart));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($products as $product) {
    $total += $product['price'] * $cart[$product['id']];
}

$stmt = $pdo->prepare("INSERT INTO orders (user_id, total, currency, created_at) VALUES (?, ?, ?, NOW())");
$stmt->execute([$user_id, $total, $currency]);
$order_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
foreach ($products as $product) {
    $stmt->execute([$order_id, $product['id'], $cart[$product['id']], $product['price']]);
}

unset($_SESSION['cart']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation - Nviridian</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Inter, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; text-align: center; }
        .btn { background: #3b82f6; padding: 0.5rem 1rem; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration:none; }
        .btn:hover { background: #2563eb; }
    </style>
</head>
<body>
    <h1>Thank you for your order! 🎉</h1>
    <p>Your order (ID: <?= $order_id ?>) has been placed successfully.</p>
    <p>Total: <?= $currency ?> <?= number_format($total, 2) ?></p>
    <a href="browse_products.php" class="btn">Continue Shopping</a>
    <a href="generate_receipt.php?order_id=<?= $order_id ?>" class="btn" style="background:#22c55e; margin-left:1rem;">View Receipt 📄</a>
</body>
</html>
