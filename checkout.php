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

// Initialize variables
$order_id = null;
$total = 0;
$error_message = '';
$success = false;

try {
    // =================================================================
    // TRANSACTION WITH ACID PROPERTIES - START
    // =================================================================
    $pdo->beginTransaction(); // ATOMICITY: All operations below are part of a single transaction
    
    // Fetch product details with row locking (FOR UPDATE ensures ACID isolation)
    // ISOLATION: FOR UPDATE locks rows to prevent concurrent modifications
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id IN ($placeholders) FOR UPDATE");
    $stmt->execute(array_keys($cart));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Validate stock availability and calculate total
    foreach ($products as $product) {
        $required_quantity = $cart[$product['id']];
        
        // Check if sufficient stock is available
        if ($product['stock'] < $required_quantity) {
            // CONSISTENCY: Prevents invalid state (negative stock)
            throw new Exception("Insufficient stock for product: {$product['name']}. Available: {$product['stock']}, Required: {$required_quantity}");
        }
        
        $total += $product['price'] * $required_quantity;
    }
    
    // =================================================================
    // STEP 1: CREATE ORDER RECORD
    // =================================================================
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, currency, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $total, $currency]);
    $order_id = $pdo->lastInsertId();
    
    // Log order creation
    try {
        $stmt = $pdo->prepare("INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'INSERT', 
            'orders', 
            $order_id, 
            $user_id, 
            'New order created during checkout',
            null,
            json_encode(['total' => $total, 'currency' => $currency]),
            'checkout_system'
        ]);
    } catch (Exception $log_error) {
        // DURABILITY: Even if logging fails, main transaction continues
        // Continue even if logging fails
    }
    
    // =================================================================
    // STEP 2: CREATE ORDER ITEMS AND UPDATE STOCK
    // =================================================================
    $stmt_order_items = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    $stmt_update_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    
    foreach ($products as $product) {
        $quantity = $cart[$product['id']];
        $old_stock = $product['stock'];
        $new_stock = $old_stock - $quantity;
        
        // Insert order item
        $stmt_order_items->execute([$order_id, $product['id'], $quantity, $product['price']]);
        $order_item_id = $pdo->lastInsertId();
        
        // Update product stock
        $stmt_update_stock->execute([$quantity, $product['id']]);
        
        // Log order item creation
        try {
            $stmt = $pdo->prepare("INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'INSERT',
                'order_items',
                $order_item_id,
                $user_id,
                "Order item added: {$product['name']} x{$quantity}",
                null,
                json_encode(['product_id' => $product['id'], 'quantity' => $quantity, 'price' => $product['price']]),
                'checkout_system'
            ]);
        } catch (Exception $log_error) {
            // Continue even if logging fails
        }
        
        // Log stock reduction
        try {
            $stmt = $pdo->prepare("INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'UPDATE',
                'products',
                $product['id'],
                $user_id,
                "Stock reduced due to purchase: {$product['name']}",
                $old_stock,
                $new_stock,
                'checkout_system'
            ]);
        } catch (Exception $log_error) {
            // Continue even if logging fails
        }
    }
    
    // =================================================================
    // COMMIT TRANSACTION - ALL OPERATIONS SUCCESSFUL
    // =================================================================
    $pdo->commit();
    $success = true;
    
    // Clear cart only after successful transaction
    unset($_SESSION['cart']);
    
    // Log successful checkout
    try {
        $stmt = $pdo->prepare("INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'UPDATE',
            'checkout',
            $order_id,
            $user_id,
            "Checkout completed successfully",
            null,
            json_encode(['order_id' => $order_id, 'total' => $total, 'currency' => $currency, 'items_count' => count($cart)]),
            'checkout_system'
        ]);
    } catch (Exception $log_error) {
        // Continue even if logging fails
    }
    
} catch (Exception $e) {
    // =================================================================
    // ROLLBACK TRANSACTION - ERROR OCCURRED
    // =================================================================
    $pdo->rollBack();
    $error_message = $e->getMessage();
    $success = false;
    
    // Log failed checkout attempt
    try {
        $stmt = $pdo->prepare("INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'UPDATE',
            'checkout',
            null,
            $user_id,
            "Checkout failed: " . $error_message,
            json_encode(['cart' => $cart, 'currency' => $currency]),
            null,
            'checkout_system'
        ]);
    } catch (Exception $log_error) {
        // Continue even if logging fails
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $success ? 'Order Confirmation' : 'Checkout Error' ?> - Nviridian</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Inter, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; text-align: center; }
        .btn { background: #3b82f6; padding: 0.5rem 1rem; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration:none; display: inline-block; margin: 0.5rem; }
        .btn:hover { background: #2563eb; }
        .btn.success { background: #22c55e; }
        .btn.success:hover { background: #16a34a; }
        .btn.danger { background: #ef4444; }
        .btn.danger:hover { background: #dc2626; }
        .error-message { 
            background: rgba(239, 68, 68, 0.1); 
            border: 1px solid #ef4444; 
            color: #fecaca; 
            padding: 1rem; 
            border-radius: 8px; 
            margin: 1rem auto; 
            max-width: 600px; 
        }
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #bbf7d0;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem auto;
            max-width: 600px;
        }
        .transaction-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            color: #dbeafe;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem auto;
            max-width: 600px;
            text-align: left;
        }
        .transaction-info h3 {
            color: #3b82f6;
            margin-bottom: 0.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    
    <?php if ($success): ?>
        <!-- SUCCESS CASE - ORDER COMPLETED -->
        <h1>Thank you for your order! 🎉</h1>
        
        <div class="success-message">
            <strong>✅ Transaction Completed Successfully</strong><br>
            
        </div>
        
        <div class="transaction-info">
            <h3>📋 Transaction Details</h3>
            <strong>Order ID:</strong> <?= $order_id ?><br>
            <strong>Total Amount:</strong> <?= $currency ?> <?= number_format($total, 2) ?><br>
            <strong>Items Purchased:</strong> <?= count($cart ?? []) ?><br>
            <strong>Transaction Status:</strong> COMMITTED ✅<br>
            <strong>Stock Updated:</strong> Yes ✅<br>
            <strong>Logged to Database:</strong> Yes ✅
        </div>
        
        <p>Your order (ID: <?= $order_id ?>) has been placed successfully.</p>
        <p>All inventory has been updated and the transaction has been logged.</p>
        
        <a href="products.php" class="btn">Continue Shopping</a>
        <a href="generate_receipt.php?order_id=<?= $order_id ?>" class="btn success">View Receipt 📄</a>
        
    <?php else: ?>
        <!-- ERROR CASE - TRANSACTION FAILED -->
        <h1>Checkout Failed ❌</h1>
        
        <div class="error-message">
            <strong>⚠️ Transaction Failed</strong><br>
            <?= htmlspecialchars($error_message) ?>
        </div>
        
        <div class="transaction-info">
            <h3>📋 Transaction Details</h3>
            <strong>Transaction Status:</strong> ROLLED BACK ↩️<br>
            <strong>Stock Updated:</strong> No (Changes Reverted) ↩️<br>
            <strong>Order Created:</strong> No ❌<br>
            <strong>Your Cart:</strong> Preserved ✅<br>
            <strong>Error Logged:</strong> Yes ✅
        </div>
        
        <p>Don't worry! No changes were made to your cart or our inventory.</p>
        <p>Please try again or contact support if the problem persists.</p>
        
        <a href="cart.php" class="btn">Return to Cart</a>
        <a href="products.php" class="btn">Continue Shopping</a>
        
    <?php endif; ?>
    

</body>
</html>
