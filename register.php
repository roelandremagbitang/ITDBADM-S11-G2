<?php
session_start();
include 'db_connect.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = $_POST['role'] ?? 'customer';

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $error = "Email must contain '@' and a valid domain (e.g., user@domain.com).";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['customer', 'staff', 'admin'])) {
        $error = "Please select a valid role.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email address is already registered.";
        } else {
            // Hash the password for security
            $hashed_password = $password;
            
            // Insert new user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password, $role])) {
                $success = "Registration successful! You can now login.";
                // Clear form data
                $name = $email = $role = '';
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Nviridian</title>
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
        .register-container {
            position: relative;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(15px);
            border-radius: 16px;
            padding: 2rem 2.5rem;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            z-index: 1;
            animation: fadeInUp 0.6s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .register-container img {
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
        .success {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
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
        input[type="text"], input[type="email"], input[type="password"], select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: none;
            background: rgba(255,255,255,0.1);
            color: #f8fafc;
            font-size: 1rem;
            transition: background 0.3s, box-shadow 0.3s;
        }
        input[type="text"]::placeholder, input[type="email"]::placeholder, input[type="password"]::placeholder {
            color: #94a3b8;
        }
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus, select:focus {
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.4);
            outline: none;
        }
        select option {
            background: #1e293b;
            color: #f8fafc;
        }
        .password-container {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .show-password {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #cbd5e1;
        }
        .show-password input[type="checkbox"] {
            width: auto;
            margin: 0;
            padding: 0;
            accent-color: #3b82f6;
        }
        .show-password label {
            cursor: pointer;
            user-select: none;
        }
        button[type="submit"] {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.3s, background 0.3s;
            box-shadow: 0 8px 24px rgba(34,197,94,0.4);
        }
        button[type="submit"]:hover {
            transform: translateY(-4px) scale(1.02);
            background: linear-gradient(135deg, #16a34a, #15803d);
            box-shadow: 0 12px 30px rgba(34,197,94,0.5);
        }
        .login-link {
            font-size: 0.95rem;
            color: #cbd5e1;
        }
        .login-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #94a3b8;
            text-align: left;
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 6px;
        }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 2;
            user-select: none;
        }
        .toggle-password svg {
            display: block;
        }
        input[type="password"] {
            padding-right: 40px !important;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <img src="logo.jpg" alt="Logo">
        <h2>Create Account</h2>
        <p class="subtext">Join Nviridian today</p>

        <?php if (isset($error) && $error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (isset($success) && $success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required 
                   value="<?= isset($name) ? htmlspecialchars($name) : '' ?>">
            <input type="email" name="email" placeholder="Email Address" required 
                   value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
                   pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                   title="Please enter a valid email address with @ and domain (e.g., user@domain.com)">
            
            <div class="password-container" style="position:relative;">
                <input type="password" name="password" id="password" placeholder="Password" required style="padding-right:40px;">
                <span class="toggle-password" onclick="togglePassword('password', this)">
                    
                </span>
            </div>
            
            <div class="password-container" style="position:relative;">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required style="padding-right:40px;">
                
            </div>
            
            <button type="submit">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
        // Toggle password visibility with eye icon
        function togglePassword(fieldId, el) {
            const input = document.getElementById(fieldId);
            const svg = el.querySelector('svg');
            if (input.type === "password") {
                input.type = "text";
                svg.innerHTML = `
                    <path stroke="#94a3b8" stroke-width="2" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/>
                    <circle cx="12" cy="12" r="3" stroke="#94a3b8" stroke-width="2"/>
                    <line x1="4" y1="20" x2="20" y2="4" stroke="#94a3b8" stroke-width="2"/>
                `;
            } else {
                input.type = "password";
                svg.innerHTML = `
                    <path stroke="#94a3b8" stroke-width="2" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/>
                    <circle cx="12" cy="12" r="3" stroke="#94a3b8" stroke-width="2"/>
                `;
            }
        }

        // Enhanced client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            const role = document.querySelector('select[name="role"]').value;
            
            // Email validation
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address with @ and domain (e.g., user@domain.com)');
                return false;
            }
            
            // Password validation
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Role validation
            if (!role) {
                e.preventDefault();
                alert('Please select a role!');
                return false;
            }
        });

        // Real-time email validation feedback
        document.querySelector('input[name="email"]').addEventListener('input', function(e) {
            const email = e.target.value;
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            
            if (email && !emailRegex.test(email)) {
                e.target.style.borderBottom = '2px solid #ef4444';
                e.target.title = 'Email must contain @ and domain (e.g., user@domain.com)';
            } else if (email) {
                e.target.style.borderBottom = '2px solid #22c55e';
                e.target.title = 'Valid email format';
            } else {
                e.target.style.borderBottom = '';
                e.target.title = '';
            }
        });

        // Show success message and redirect after 3 seconds
        <?php if (isset($success) && $success): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
        <?php endif; ?>
    </script>

</body>
</html>
