<?php
session_start();
include 'db_connect.php';

// Check if user is admin or staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || 
    !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit;
}

$name = htmlspecialchars($user['name']);
$role = htmlspecialchars($user['role']);

// Get filter parameters
$filter_table = $_GET['table'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Build WHERE clause
$where_conditions = [];
$params = [];

if ($filter_table) {
    $where_conditions[] = "tl.table_name = ?";
    $params[] = $filter_table;
}

if ($filter_action) {
    $where_conditions[] = "tl.action_type = ?";
    $params[] = $filter_action;
}

if ($filter_user) {
    $where_conditions[] = "tl.user_id = ?";
    $params[] = $filter_user;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Fetch transaction logs with user details
$sql = "
    SELECT 
        tl.log_id,
        tl.action_type,
        tl.table_name,
        tl.record_id,
        tl.description,
        tl.old_value,
        tl.new_value,
        tl.created_at,
        tl.created_by,
        u.name as user_name,
        u.email as user_email
    FROM transaction_logs tl
    LEFT JOIN users u ON tl.user_id = u.id
    $where_clause
    ORDER BY tl.created_at DESC, tl.log_id DESC
    LIMIT ?
";

$params[] = (int)$limit;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT table_name) as tables_affected,
        COUNT(DISTINCT user_id) as users_involved,
        SUM(CASE WHEN action_type = 'INSERT' THEN 1 ELSE 0 END) as inserts,
        SUM(CASE WHEN action_type = 'UPDATE' THEN 1 ELSE 0 END) as updates,
        SUM(CASE WHEN action_type = 'DELETE' THEN 1 ELSE 0 END) as deletes
    FROM transaction_logs tl
    $where_clause
";

$stmt = $pdo->prepare($stats_sql);
$stmt->execute(array_slice($params, 0, -1)); // Remove limit parameter
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available tables and users for filters
$tables = $pdo->query("SELECT DISTINCT table_name FROM transaction_logs ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$users_with_logs = $pdo->query("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    INNER JOIN transaction_logs tl ON u.id = tl.user_id 
    ORDER BY u.name
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Logs - Nviridian</title>
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
            color: #f8fafc;
            min-height: 100vh;
        }
        header {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .back-btn {
            display: inline-block;
            margin-top: 1rem;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4);
        }
        .back-btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.5);
        }
        main {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #3b82f6;
        }
        .stat-label {
            color: #cbd5e1;
            margin-top: 0.5rem;
        }
        .filters {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .filters h3 {
            margin-bottom: 1rem;
            color: #3b82f6;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        .filter-group select, .filter-group input {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.1);
            color: #f8fafc;
            font-size: 0.9rem;
        }
        .filter-group select option {
            background: #1e293b;
            color: #f8fafc;
        }
        .filter-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .filter-btn:hover {
            background: linear-gradient(135deg, #16a34a, #15803d);
            transform: translateY(-2px);
        }
        .logs-table {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        th {
            background: rgba(255,255,255,0.1);
            font-weight: 600;
            color: #3b82f6;
        }
        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .action-insert { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .action-update { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .action-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .table-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            background: rgba(168, 85, 247, 0.2);
            color: #a855f7;
        }
        .details-btn {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid #3b82f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: 0.3s;
        }
        .details-btn:hover {
            background: rgba(59, 130, 246, 0.3);
        }
        .details-row {
            display: none;
            background: rgba(255,255,255,0.02);
        }
        .details-content {
            padding: 1rem;
            border-left: 3px solid #3b82f6;
            margin: 0.5rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }
        .json-display {
            background: rgba(0,0,0,0.3);
            padding: 0.5rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            margin: 0.5rem 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

    <header>
        <h1>📋 Transaction Logs</h1>
        <p>Welcome, <?= $name ?> (<?= ucfirst($role) ?>) - Database Activity Monitor</p>
        <?php
        $dashboard = $role === 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php';
        ?>
        <a href="<?= $dashboard ?>" class="back-btn">⬅ Back to Dashboard</a>
    </header>

    <main>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_logs']) ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #22c55e;"><?= number_format($stats['inserts']) ?></div>
                <div class="stat-label">Inserts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #3b82f6;"><?= number_format($stats['updates']) ?></div>
                <div class="stat-label">Updates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ef4444;"><?= number_format($stats['deletes']) ?></div>
                <div class="stat-label">Deletes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['tables_affected'] ?></div>
                <div class="stat-label">Tables Affected</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['users_involved'] ?></div>
                <div class="stat-label">Users Involved</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>🔍 Filter Logs</h3>
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label>Table:</label>
                    <select name="table">
                        <option value="">All Tables</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?= $table ?>" <?= $filter_table === $table ? 'selected' : '' ?>>
                                <?= ucfirst($table) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Action:</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <option value="INSERT" <?= $filter_action === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
                        <option value="UPDATE" <?= $filter_action === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                        <option value="DELETE" <?= $filter_action === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>User:</label>
                    <select name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users_with_logs as $log_user): ?>
                            <option value="<?= $log_user['id'] ?>" <?= $filter_user == $log_user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($log_user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Limit:</label>
                    <select name="limit">
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                        <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                        <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="filter-btn">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="logs-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date/Time</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: #cbd5e1;">
                                No transaction logs found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= $log['log_id'] ?></td>
                                <td><?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <span class="action-badge action-<?= strtolower($log['action_type']) ?>">
                                        <?= $log['action_type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="table-badge"><?= $log['table_name'] ?></span>
                                </td>
                                <td><?= $log['record_id'] ?? '-' ?></td>
                                <td>
                                    <?= $log['user_name'] ? htmlspecialchars($log['user_name']) : 'System' ?>
                                    <?php if ($log['user_email']): ?>
                                        <br><small style="color: #cbd5e1;"><?= htmlspecialchars($log['user_email']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['description']) ?></td>
                                <td>
                                    <?php if ($log['old_value'] || $log['new_value']): ?>
                                        <button class="details-btn" onclick="toggleDetails(<?= $log['log_id'] ?>)">
                                            View Details
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                                <tr id="details-<?= $log['log_id'] ?>" class="details-row">
                                    <td colspan="8">
                                        <div class="details-content">
                                            <?php if ($log['old_value']): ?>
                                                <strong>🔴 Old Value:</strong>
                                                <div class="json-display"><?= htmlspecialchars($log['old_value']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($log['new_value']): ?>
                                                <strong>🟢 New Value:</strong>
                                                <div class="json-display"><?= htmlspecialchars($log['new_value']) ?></div>
                                            <?php endif; ?>
                                            <small style="color: #cbd5e1;">
                                                Created by: <?= htmlspecialchars($log['created_by']) ?>
                                            </small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function toggleDetails(logId) {
            const detailsRow = document.getElementById('details-' + logId);
            const isVisible = detailsRow.style.display === 'table-row';
            detailsRow.style.display = isVisible ? 'none' : 'table-row';
        }
    </script>

</body>
</html>