<?php
session_start();
include 'db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
        $stmt->execute([$email, $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
                exit;
            } elseif ($user['role'] === 'staff') {
                header('Location: staff_dashboard.php');
                exit;
            } else {
                header('Location: customer_dashboard.php');
                exit;
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Nviridian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }
        body {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            color: #f8fafc;
        }
        body::before {
            content: "";
            position: absolute;
            inset: 0;
          background: url('https://png.pngtree.com/background/20231017/original/pngtree-office-essentials-technology-and-gadgets-illustration-featuring-laptop-printer-camera-tablet-picture-image_5591437.jpg') center/cover no-repeat;


            opacity: 0.3;
            z-index: 0;
            filter: blur(10px);
        }
        .login-container {
            position: relative;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 2rem 2.5rem;
            width: 90%;
            max-width: 380px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            z-index: 1;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-container img {
            width: 90px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
        }
        h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .subtext {
            font-size: 0.95rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        input[type="email"], input[type="password"] {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: none;
            background: rgba(255,255,255,0.1);
            color: #f8fafc;
            font-size: 1rem;
            transition: background 0.3s, box-shadow 0.3s;
        }
        input[type="email"]::placeholder, input[type="password"]::placeholder {
            color: #94a3b8;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.4);
            outline: none;
        }
        button[type="submit"] {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.3s, background 0.3s;
            box-shadow: 0 8px 24px rgba(59,130,246,0.4);
        }
        button[type="submit"]:hover {
            transform: translateY(-4px) scale(1.02);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 12px 30px rgba(59,130,246,0.5);
        }
        .register-link {
            font-size: 0.95rem;
            color: #cbd5e1;
        }
        .register-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <img src="logo.jpg" alt="Logo">
        <h2>Welcome Back</h2>
        <p class="subtext">Login to your account</p>

        <?php if (isset($error) && $error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Email" required 
                   value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>

</body>
</html>
