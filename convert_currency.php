<?php
session_start();
include 'db_connect.php';

$currency = $_GET['currency'] ?? 'PHP';
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    echo json_encode(['converted' => '0.00']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($cart), '?'));
$stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
$stmt->execute(array_keys($cart));
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$php_total = 0;
foreach ($products as $p) {
    $php_total += $p['price'] * $cart[$p['id']];
}

// Call the stored procedure
$stmt = $pdo->prepare("CALL convert_total_by_currency(?, ?, @converted, @rate)");
$stmt->execute([$php_total, $currency]);

$result = $pdo->query("SELECT @converted AS converted, @rate AS rate")->fetch();
echo json_encode([
    'converted' => number_format($result['converted'], 2),
    'rate' => $result['rate']
]);
?>
