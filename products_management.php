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

if (!$user || !in_array($user['role'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit;
}

$upload_dir = __DIR__ . '/uploads/products/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $image_name = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['image']['tmp_name'];
        $original_name = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed_exts)) {
            $new_name = uniqid('product_', true) . '.' . $ext;
            move_uploaded_file($tmp_name, $upload_dir . $new_name);
            $image_name = $new_name;
        }
    }

    $stmt = $pdo->prepare("CALL add_product(?, ?, ?, ?, ?)");
    $stmt->execute([$name, $description, $price, $stock, $image_name]);



    header("Location: products_management.php");
    exit;
}

// Handle Delete Product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod && $prod['image'] && file_exists($upload_dir . $prod['image'])) {
        unlink($upload_dir . $prod['image']);
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: products_management.php");
    exit;
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['edit_name'];
    $description = $_POST['edit_description'];
    $price = $_POST['edit_price'];
    $stock = $_POST['edit_stock'];

    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_image = $prod['image'];

    if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['edit_image']['tmp_name'];
        $original_name = basename($_FILES['edit_image']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed_exts)) {
            if ($current_image && file_exists($upload_dir . $current_image)) {
                unlink($upload_dir . $current_image);
            }
            $new_name = uniqid('product_', true) . '.' . $ext;
            move_uploaded_file($tmp_name, $upload_dir . $new_name);
            $current_image = $new_name;
        }
    }

    $stmt = $pdo->prepare("CALL modify_product(?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $name, $description, $price, $stock, $current_image]);

    header("Location: products_management.php");
    exit;
}

// Fetch Products
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Products - Nviridian</title>
<style>
body { background: #0f172a; color: #f8fafc; font-family: sans-serif; padding: 2rem; }
header { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
.back-btn { display: inline-block; margin-top: 1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.75rem 1.5rem; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4); }
.back-btn:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-4px); box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5); }
header h1 { font-size: 1.8rem; font-weight: 700; }
h1 { text-align: center; }
form, table { max-width: 600px; margin: 1rem auto; background: #1e293b; padding: 1rem; border-radius: 8px; }
input, textarea { width: 100%; margin: 0.5rem 0; padding: 0.5rem; border-radius: 4px; border: none; }
button { padding: 0.5rem 1rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #1d4ed8; }
.modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.6); }
.modal-content { background: #1e293b; margin: 5% auto; padding: 1rem; border: 1px solid #334155; width: 90%; max-width: 400px; border-radius: 8px; position: relative; }
.close { color: #f87171; position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; }
table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
th, td { padding: 0.5rem; border-bottom: 1px solid #334155; text-align: center; }
a.delete { color: #f87171; text-decoration: none; }
img.product-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; }
</style>
</head>
<body>
<header>
    <h1>Available Products</h1>
    <?php
    $dashboard = 'login.php';
    if ($user['role'] === 'admin') { $dashboard = 'admin_dashboard.php'; }
    elseif ($user['role'] === 'staff') { $dashboard = 'staff_dashboard.php'; }
    elseif ($user['role'] === 'customer') { $dashboard = 'customer_dashboard.php'; }
    ?>
    <a href="<?= $dashboard ?>" class="back-btn">⬅ Back to Dashboard</a>
</header>
<h1>Manage Products</h1>

<form method="post" enctype="multipart/form-data">
    <input type="text" name="name" placeholder="Product Name" required>
    <textarea name="description" placeholder="Description" required></textarea>
    <input type="number" step="0.01" name="price" placeholder="Price (PHP)" required>
    <input type="number" name="stock" placeholder="Stock Quantity" required>
    <input type="file" name="image" accept="image/*">
    <button type="submit" name="add_product">Add Product</button>
</form>

<table>
    <thead>
        <tr><th>ID</th><th>Image</th><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product): ?>
        <tr>
            <td><?= $product['id'] ?></td>
            <td>
                <?php if ($product['image'] && file_exists($upload_dir . $product['image'])): ?>
                    <img src="uploads/products/<?= htmlspecialchars($product['image']) ?>" alt="Product" class="product-thumb">
                <?php else: ?>
                    No Image
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td>₱<?= number_format($product['price'], 2) ?></td>
            <td><?= $product['stock'] ?></td>
            <td>
                <span class="edit-btn" onclick="openModal(<?= htmlspecialchars(json_encode($product)) ?>)">✏️ Edit</span> |
                <a href="?delete=<?= $product['id'] ?>" class="delete" onclick="return confirm('Delete this product?')">🗑️ Delete</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Edit Product</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="edit_product_id">
            <input type="text" name="edit_name" id="edit_name" placeholder="Product Name" required>
            <textarea name="edit_description" id="edit_description" placeholder="Description" required></textarea>
            <input type="number" step="0.01" name="edit_price" id="edit_price" placeholder="Price (PHP)" required>
            <input type="number" name="edit_stock" id="edit_stock" placeholder="Stock Quantity" required>
            <input type="file" name="edit_image" accept="image/*">
            <button type="submit" name="update_product">Update Product</button>
        </form>
    </div>
</div>

<script>
function openModal(product) {
    document.getElementById('edit_product_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('editModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target == document.getElementById('editModal')) {
        closeModal();
    }
};
</script>

</body>
</html>
