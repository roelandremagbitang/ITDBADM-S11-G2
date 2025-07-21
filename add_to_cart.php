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
$product_id = $_POST['product_id'] ?? null;
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

if (!$product_id) {
    header('Location:products.php');
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id] += $quantity;
} else {
    $_SESSION['cart'][$product_id] = $quantity;
}

header('Location:products.php?added=1');
exit;
?>
