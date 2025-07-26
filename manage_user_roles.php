<?php
session_start();
include 'db_connect.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_role') {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'];
        
        // Validate role
        $valid_roles = ['customer', 'staff', 'admin'];
        if (!in_array($new_role, $valid_roles)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role specified']);
            exit;
        }
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if current user is admin
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$admin_id]);
            $admin_check = $stmt->fetchColumn();
            
            if ($admin_check == 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Only admins can update user roles']);
                exit;
            }
            
            // Get current user info
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_user) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            $old_role = $current_user['role'];
            
            // Check if we're removing the last admin
            if ($old_role === 'admin' && $new_role !== 'admin') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
                $stmt->execute([$user_id]);
                $admin_count = $stmt->fetchColumn();
                
                if ($admin_count == 0) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Cannot remove the last admin. There must be at least one admin user.']);
                    exit;
                }
            }
            
            // Update the user role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            
            // Log the change in transaction_logs if table exists
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO transaction_logs (action_type, table_name, record_id, user_id, description, old_value, new_value, created_by)
                    VALUES ('UPDATE', 'users', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, 
                    $admin_id, 
                    "User role changed from {$old_role} to {$new_role}",
                    $old_role,
                    $new_role,
                    $_SESSION['name'] ?? 'Admin'
                ]);
            } catch (PDOException $e) {
                // If transaction_logs table doesn't exist, just continue
            }
            
            // Commit the transaction
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => "User role updated from {$old_role} to {$new_role}"]);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error updating role: ' . $e->getMessage()]);
        }
        
    } elseif ($action === 'get_user_details') {
        $user_id = intval($_POST['user_id']);
        
        try {
            // Get user details
            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Get role change history from transaction_logs (fallback to simple query if table doesn't exist)
                $history = [];
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            tl.created_at as change_date,
                            tl.description,
                            tl.old_value as old_role,
                            tl.new_value as new_role,
                            u.name as changed_by
                        FROM transaction_logs tl
                        LEFT JOIN users u ON tl.user_id = u.id
                        WHERE tl.table_name = 'users' 
                        AND tl.record_id = ? 
                        AND tl.action_type = 'UPDATE'
                        AND tl.description LIKE '%role%'
                        ORDER BY tl.created_at DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$user_id]);
                    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // If transaction_logs doesn't exist, just return empty history
                    $history = [];
                }
                
                echo json_encode([
                    'success' => true, 
                    'user' => $user, 
                    'history' => $history
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching user details: ' . $e->getMessage()]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
