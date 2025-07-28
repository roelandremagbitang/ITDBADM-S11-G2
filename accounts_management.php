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

if (!$user || $user['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$name = htmlspecialchars($user['name']);
$role = htmlspecialchars($user['role']);

// Handle role change request
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $target_user_id = intval($_POST['user_id']);
    $new_role = $_POST['new_role'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get current user info
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_user) {
            $pdo->rollBack();
            $message = 'User not found';
            $message_type = 'error';
        } else {
            $old_role = $current_user['role'];
            
            // Check if we're removing the last admin
            if ($old_role === 'admin' && $new_role !== 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
                $stmt->execute([$target_user_id]);
                $admin_count = $stmt->fetchColumn();
                
                if ($admin_count == 0) {
                    $pdo->rollBack();
                    $message = 'Cannot remove the last admin. There must be at least one admin user.';
                    $message_type = 'error';
                } else {
                    // Update the user role
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_user_id]);
                    
                    // Log the change
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by)
                            VALUES ('UPDATE', 'users', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $target_user_id, 
                            $user_id, 
                            "User role changed from {$old_role} to {$new_role}",
                            $old_role,
                            $new_role,
                            $name
                        ]);
                    } catch (PDOException $e) {
                        // Continue even if logging fails
                    }
                    
                    $pdo->commit();
                    $message = "User role updated from {$old_role} to {$new_role}";
                    $message_type = 'success';
                }
            } else {
                // Update the user role
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $target_user_id]);
                
                // Log the change
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by)
                        VALUES ('UPDATE', 'users', ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $target_user_id, 
                        $user_id, 
                        "User role changed from {$old_role} to {$new_role}",
                        $old_role,
                        $new_role,
                        $name
                    ]);
                } catch (PDOException $e) {
                    // Continue even if logging fails
                }
                
                $pdo->commit();
                $message = "User role updated from {$old_role} to {$new_role}";
                $message_type = 'success';
            }
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = 'Error updating role: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Fetch all users with enhanced information
try {
    // Try to use the enhanced procedure first, fallback to simple query
    $stmt = $pdo->prepare("CALL GetUsersWithPrivileges()");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback to simple query with manual privilege descriptions
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            CASE 
                WHEN u.role = 'admin' THEN 'Full Access - Can manage users, products, orders'
                WHEN u.role = 'staff' THEN 'Staff Access - Can manage products, view orders, browse reports'
                WHEN u.role = 'customer' THEN 'Customer Access - Can browse and purchase products'
                ELSE 'Unknown Role'
            END as privilege_description,
            (SELECT COUNT(*) FROM transaction_log WHERE user_id = u.id) as total_orders,
            (SELECT MAX(created_at) FROM transaction_log WHERE user_id = u.id) as last_order_date
        FROM users u
        ORDER BY 
            CASE u.role 
                WHEN 'admin' THEN 1 
                WHEN 'staff' THEN 2 
                WHEN 'customer' THEN 3 
                ELSE 4 
            END, u.name
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Nviridian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #0f172a, #1e293b); color: #f8fafc; min-height: 100vh; display: flex; flex-direction: column; }
        header { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 1.5rem 2rem; text-align: center; }
        header h1 { font-size: 1.8rem; font-weight: 700; }
        header p { font-size: 1rem; color: #cbd5e1; margin-top: 0.3rem; }
        .back-btn { display: inline-block; margin-top: 1rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; padding: 0.7rem 1.3rem; border-radius: 10px; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .back-btn:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-3px); }
        main { flex: 1; display: flex; justify-content: center; padding: 2rem; }
        .container { width: 95%; max-width: 1200px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 2rem; backdrop-filter: blur(15px); box-shadow: 0 20px 40px rgba(0,0,0,0.3); animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .search-bar { margin-bottom: 1rem; text-align: center; }
        .search-input { width: 80%; max-width: 400px; padding: 0.5rem; border-radius: 8px; border: none; font-size: 1rem; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; text-align: center; font-weight: 600; }
        .message.success { background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.5); color: #22c55e; }
        .message.error { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.5); color: #ef4444; }
        table { width: 100%; border-collapse: collapse; color: #f1f5f9; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        th { background: rgba(255,255,255,0.05); color: #cbd5e1; font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.05); transition: 0.3s; }
        .role-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.875rem; font-weight: 600; text-transform: uppercase; }
        .role-admin { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .role-staff { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .role-customer { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .action-btn { padding: 0.5rem 1rem; margin: 0.25rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; font-size: 0.875rem; transition: 0.3s; }
        .btn-change-role { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .btn-change-role:hover { background: linear-gradient(135deg, #d97706, #b45309); transform: translateY(-2px); }
        .btn-view-history { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; }
        .btn-view-history:hover { background: linear-gradient(135deg, #7c3aed, #6d28d9); transform: translateY(-2px); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); }
        .modal-content { background: #1e293b; margin: 5% auto; padding: 2rem; border-radius: 16px; width: 90%; max-width: 500px; border: 1px solid rgba(255,255,255,0.1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-title { font-size: 1.5rem; font-weight: 700; }
        .close { color: #ef4444; font-size: 2rem; cursor: pointer; }
        .close:hover { color: #dc2626; }
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #cbd5e1; }
        .form-select, .form-input { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); color: #f8fafc; font-size: 1rem; }
        .form-select {
    width: 100%;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.05);
    color: #f8fafc;
    font-size: 1rem;
}
.form-select option {
    color: #0f172a; /* dark text for dropdown options */
    background: #f8fafc;
}
.form-select option[value=""] {
    color: #94a3b8; /* gray for "Select a role..." */
}
        .form-select:focus, .form-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .btn-submit { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; transition: 0.3s; }
        .btn-submit:hover { background: linear-gradient(135deg, #16a34a, #15803d); transform: translateY(-2px); }
        .history-item { padding: 1rem; margin-bottom: 0.5rem; background: rgba(255,255,255,0.05); border-radius: 8px; border-left: 4px solid #3b82f6; }
        .history-date { font-size: 0.875rem; color: #94a3b8; }
        .history-change { font-weight: 600; margin-top: 0.25rem; }
        footer { text-align: center; padding: 1rem; font-size: 0.9rem; color: #cbd5e1; background: rgba(15, 23, 42, 0.9); border-top: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body>

    <header>
        <h1>Manage Users</h1>
        <p>Welcome, <?= $name ?> (<?= ucfirst($role) ?>)</p>
        <a href="admin_dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
    </header>

    <main>
        <div class="container">
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <div class="search-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search users by name, email, or role...">
            </div>
            
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Privileges</th>
                        <th>Orders</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                <?= htmlspecialchars(ucfirst($user['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($user['privilege_description'] ?? 'Standard user privileges') ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($user['total_orders'] ?? '0') ?> orders
                            <?php if (isset($user['last_order_date']) && $user['last_order_date']): ?>
                                <br><small>Last: <?= date('M j, Y', strtotime($user['last_order_date'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="action-btn btn-change-role" onclick="openRoleModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', '<?= $user['role'] ?>')">
                                Change Role
                            </button>
                            <button class="action-btn btn-view-history" onclick="viewUserHistory(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')">
                                View History
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        &copy; <?= date("Y") ?> All rights reserved.
    </footer>

    <!-- Role Change Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Change User Role</h3>
                <span class="close" onclick="closeModal('roleModal')">&times;</span>
            </div>
            <form id="roleChangeForm">
                <input type="hidden" id="roleUserId" name="user_id">
                
                <div class="form-group">
                    <label class="form-label">User Name:</label>
                    <input type="text" id="roleUserName" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Current Role:</label>
                    <input type="text" id="roleCurrentRole" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label for="roleNewRole" class="form-label">New Role:</label>
                    <select id="roleNewRole" name="new_role" class="form-select" required>
                        <option value="">Select a role...</option>
                        <option value="customer">Customer - Can browse and purchase products</option>
                        <option value="staff">Staff - Can manage products, view orders and reports</option>
                        <option value="admin">Admin - Full access to all features including user management</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-submit">Change Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- User History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">User Role History</h3>
                <span class="close" onclick="closeModal('historyModal')">&times;</span>
            </div>
            <div id="historyContent">
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('usersTable');
        
        // Search functionality
        searchInput.addEventListener('keyup', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].innerText.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                rows[i].style.display = match ? '' : 'none';
            }
        });

        // Modal functions
        function openRoleModal(userId, userName, currentRole) {
            document.getElementById('roleUserId').value = userId;
            document.getElementById('roleUserName').value = userName;
            document.getElementById('roleCurrentRole').value = currentRole.charAt(0).toUpperCase() + currentRole.slice(1);
            document.getElementById('roleNewRole').value = '';
            document.getElementById('roleModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function viewUserHistory(userId, userName) {
            document.getElementById('historyModal').style.display = 'block';
            document.getElementById('historyContent').innerHTML = '<p>Loading history for ' + userName + '...</p>';
            
            // Fetch user history via AJAX
            fetch('manage_user_roles.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_user_details&user_id=' + userId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let historyHtml = '<h4>Role Change History for ' + userName + '</h4>';
                    
                    if (data.history && data.history.length > 0) {
                        data.history.forEach(item => {
                            historyHtml += `
                                <div class="history-item">
                                    <div class="history-date">${new Date(item.change_date).toLocaleString()}</div>
                                    <div class="history-change">${item.description}</div>
                                    ${item.changed_by ? '<div><small>Changed by: ' + item.changed_by + '</small></div>' : ''}
                                </div>
                            `;
                        });
                    } else {
                        historyHtml += '<p>No role changes found for this user.</p>';
                    }
                    
                    document.getElementById('historyContent').innerHTML = historyHtml;
                } else {
                    document.getElementById('historyContent').innerHTML = '<p>Error loading history: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('historyContent').innerHTML = '<p>Error loading history: ' + error.message + '</p>';
            });
        }

        // Role change form submission
        document.getElementById('roleChangeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'change_role');
            formData.append('user_id', document.getElementById('roleUserId').value);
            formData.append('new_role', document.getElementById('roleNewRole').value);
            
            // Disable submit button
            const submitBtn = e.target.querySelector('.btn-submit');
            submitBtn.textContent = 'Changing Role...';
            submitBtn.disabled = true;
            
            fetch('manage_user_roles.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + data.message);
                    closeModal('roleModal');
                    location.reload(); // Refresh to show changes
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            })
            .finally(() => {
                submitBtn.textContent = 'Change Role';
                submitBtn.disabled = false;
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const roleModal = document.getElementById('roleModal');
            const historyModal = document.getElementById('historyModal');
            if (event.target == roleModal) {
                roleModal.style.display = 'none';
            }
            if (event.target == historyModal) {
                historyModal.style.display = 'none';
            }
        }
    </script>

</body>
</html>
