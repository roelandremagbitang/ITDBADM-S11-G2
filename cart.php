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

$cart = $_SESSION['cart'] ?? [];

$product_data = [];
$total = 0;

if ($cart) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
    $stmt->execute(array_keys($cart));
    $product_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($product_data as $product) {
        $total += $product['price'] * $cart[$product['id']];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Cart - Nviridian</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Inter, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #1e293b; }
        .btn { background: #22c55e; padding: 0.5rem 1rem; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #16a34a; }
    </style>
</head>
<body>
    <h1>Your Cart 🛒</h1>
    <?php if ($cart && $product_data): ?>
        <table>
            <tr>
                <th>Product</th>
                <th>Price (₱)</th>
                <th>Quantity</th>
                <th>Subtotal</th>
            </tr>
            <?php foreach ($product_data as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= number_format($product['price'], 2) ?></td>
                    <td><?= $cart[$product['id']] ?></td>
                    <td><?= number_format($product['price'] * $cart[$product['id']], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th colspan="3">Total (PHP)</th>
                <th>₱<?= number_format($total, 2) ?></th>
            </tr>
            <tr>
                <th colspan="3">Converted Total</th>
                <th class="converted-total">₱<?= number_format($total, 2) ?></th>
            </tr>
        </table>
        <form action="checkout.php" method="POST">
            <label for="currency">Choose currency: </label>
            <select name="currency" id="currency">
                <option value="PHP">PHP</option>
                <option value="USD">USD</option>
                <option value="KRW">KRW</option>
                <option value="YEN">YEN</option>
                <option value="THB">THB</option>
                <option value="CNY">CNY</option>
            </select>
            <button type="submit" class="btn">Proceed to Checkout</button>
        </form>
    <?php else: ?>
        <p>Your cart is empty. <a href="products.php" style="color:#3b82f6;">Browse products</a>.</p>
    <?php endif; ?><br>
    <form action="cancel_order.php" method="POST" style="margin-top:1rem;">
        <button type="submit" class="btn" style="background:#ef4444;">Cancel Order</button>
    </form>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const currencySelect = document.getElementById('currency');

        currencySelect.addEventListener('change', () => {
            const currency = currencySelect.value;

            fetch('convert_currency.php?currency=' + currency)
                .then(res => res.json())
                .then(data => {
                    document.querySelector('.converted-total').innerText = data.converted + ' ' + currency;
                })
                .catch(err => console.error("Conversion error:", err));
        });
    });
    </script>
</body>
</html>
