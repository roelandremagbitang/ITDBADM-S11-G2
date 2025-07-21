<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Call stored procedure
$stmt = $pdo->prepare("CALL CancelUserOrders(?)");
$stmt->execute([$user_id]);

// Optionally, clear session cart too
unset($_SESSION['cart']);

header('Location: products.php');
exit;
?>
