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

$stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$name = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8');

$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item) && isset($item['quantity'])) {
            $cart_count += $item['quantity'];
        } else {
            $cart_count += 1;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Products - Nviridian</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; font-family: 'Inter', sans-serif; }
body { background: linear-gradient(135deg, #0f172a, #1e293b); color: #f8fafc; margin: 0; min-height: 100vh; display: flex; flex-direction: column; }
header { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.25); position: relative; }
header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; }
.cart-link { position: absolute; top: 1.5rem; right: 1.5rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.5rem 0.9rem; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 0.4rem; font-size: 1rem; transition: 0.3s; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4); }
.cart-link:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-3px); }
.back-btn { display: inline-block; margin-top: 1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4); }
.back-btn:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-4px); box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5); }
main { flex: 1; padding: 2rem; max-width: 1200px; margin: 0 auto; width: 95%; }
.search-bar { text-align: center; margin-bottom: 1.5rem; }
.search-input { width: 80%; max-width: 400px; padding: 0.7rem; border-radius: 10px; border: none; font-size: 1rem; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: 0.3s; backdrop-filter: blur(10px); }
.card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 12px 24px rgba(0,0,0,0.4); }
.card img { width: 100%; height: 180px; object-fit: cover; }
.card-content { padding: 1rem; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
.card h2 { font-size: 1.1rem; margin-bottom: 0.5rem; }
.card p { font-size: 0.9rem; color: #cbd5e1; margin-bottom: 0.5rem; flex-grow: 1; }
.price { color: #4ade80; font-weight: 600; font-size: 1rem; }
footer { text-align: center; padding: 1rem; font-size: 0.9rem; color: #cbd5e1; background: rgba(15, 23, 42, 0.9); border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto; }
</style>
</head>
<body>

<header>
    <h1>Available Products</h1>
    <a href="cart.php" class="cart-link">🛒 Cart (<?= $cart_count ?>)</a>
    <?php
    $dashboard = 'login.php';
    if ($user['role'] === 'admin') {
        $dashboard = 'admin_dashboard.php';
    } elseif ($user['role'] === 'staff') {
        $dashboard = 'staff_dashboard.php';
    } elseif ($user['role'] === 'customer') {
        $dashboard = 'customer_dashboard.php';
    }
    ?>
    <a href="<?= $dashboard ?>" class="back-btn">⬅ Back to Dashboard</a>
</header>

<main>
    <div class="search-bar">
        <input type="text" id="searchInput" class="search-input" placeholder="Search products...">
    </div>
    <div class="grid" id="productsGrid">
        <?php foreach ($products as $product): ?>
            <div class="card">
                <?php
                $image_name = htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8');
                $image_path = (!empty($product['image']) && file_exists("uploads/products/{$product['image']}")) 
                    ? "uploads/products/" . $image_name 
                    : "https://via.placeholder.com/400x300?text=No+Image";
                ?>
                <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="card-content">
                    <h2><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="price">₱<?= number_format($product['price'], 2) ?></div>
                    <form action="add_to_cart.php" method="POST" style="margin-top:0.5rem; text-align:center;">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="number" name="quantity" value="1" min="1" style="width:60px; text-align:center; border-radius:5px; border:none; padding:0.3rem;">
                        <button type="submit" style="margin-top:0.5rem; background:linear-gradient(135deg,#22c55e,#16a34a); color:white; border:none; padding:0.5rem 1rem; border-radius:8px; cursor:pointer; font-weight:600;">Add to Cart 🛒</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<footer>
    &copy; <?= date("Y") ?> Nviridian. All rights reserved.
</footer>

<script>
const searchInput = document.getElementById('searchInput');
const grid = document.getElementById('productsGrid');
const cards = grid.getElementsByClassName('card');

searchInput.addEventListener('keyup', function() {
    const filter = searchInput.value.toLowerCase();
    for (let card of cards) {
        const name = card.querySelector('h2').innerText.toLowerCase();
        const desc = card.querySelector('p').innerText.toLowerCase();
        card.style.display = (name.includes(filter) || desc.includes(filter)) ? '' : 'none';
    }
});
</script>

</body>
</html>
