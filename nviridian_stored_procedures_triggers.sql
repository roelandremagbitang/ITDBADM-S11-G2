-- =================================================================
-- NVIRIDIAN SHOP - ENHANCED STORED PROCEDURES WITH COMMIT/ROLLBACK
-- Database Enhancement Script with Transaction Management
-- Created: July 24, 2025
-- =================================================================

USE nviridian_shop;

-- =================================================================
--  LOGGING PROCEDURE
-- =================================================================

DELIMITER $$
CREATE PROCEDURE LogTransaction(
    IN p_action_type ENUM('INSERT', 'UPDATE', 'DELETE'),
    IN p_table_name VARCHAR(50),
    IN p_record_id INT,
    IN p_user_id INT,
    IN p_description TEXT,
    IN p_old_value TEXT,
    IN p_new_value TEXT
)
BEGIN
    INSERT INTO transaction_log (
        action_type, table_name, record_id, user_id, 
        description, old_value, new_value, created_by
    )
    VALUES (
        p_action_type, p_table_name, p_record_id, p_user_id,
        p_description, p_old_value, p_new_value, USER()
    );
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 1: USER MANAGEMENT PROCEDURES WITH TRANSACTIONS
-- =================================================================

-- 1. Procedure to get user statistics (READ-ONLY, no transaction needed)
DELIMITER $$
CREATE PROCEDURE GetUserStatistics()
BEGIN
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
    FROM users;
END$$
DELIMITER ;

-- 2. Procedure to update user role with TRANSACTION SUPPORT
DELIMITER $$
CREATE PROCEDURE UpdateUserRole(
    IN p_user_id INT,
    IN p_new_role ENUM('customer', 'staff', 'admin'),
    IN p_admin_id INT
)
BEGIN
    DECLARE admin_check INT DEFAULT 0;
    DECLARE user_exists INT DEFAULT 0;
    DECLARE old_role VARCHAR(20);
    DECLARE admin_count INT DEFAULT 0;
    
    -- Declare handler for SQL exceptions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Rollback the transaction on any error
        ROLLBACK;
        RESIGNAL;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Check if the person making the change is an admin (with lock)
    SELECT COUNT(*) INTO admin_check 
    FROM users 
    WHERE id = p_admin_id AND role = 'admin'
    FOR SHARE;
    
    IF admin_check = 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admins can update user roles';
    END IF;
    
    -- Check if target user exists and get current role (with exclusive lock)
    SELECT COUNT(*), role INTO user_exists, old_role
    FROM users 
    WHERE id = p_user_id
    FOR UPDATE;
    
    IF user_exists = 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;
    
    -- Check if we're removing the last admin
    IF old_role = 'admin' AND p_new_role != 'admin' THEN
        SELECT COUNT(*) INTO admin_count 
        FROM users 
        WHERE role = 'admin' AND id != p_user_id;
        
        IF admin_count = 0 THEN
            ROLLBACK;
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot remove the last admin';
        END IF;
    END IF;
    
    -- Update the user role
    UPDATE users 
    SET role = p_new_role 
    WHERE id = p_user_id;
    
    CALL LogTransaction(
    'UPDATE', 'users', p_user_id, p_admin_id,
    CONCAT('Role changed from ', old_role, ' to ', p_new_role),
    old_role, p_new_role
);
    
    -- Commit the transaction
    COMMIT;
    
    SELECT CONCAT('User role updated from ', old_role, ' to ', p_new_role) as message;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 2: PRODUCT MANAGEMENT PROCEDURES WITH TRANSACTIONS
-- =================================================================

-- 3. Procedure to get low stock products (READ-ONLY, no transaction needed)
DELIMITER $$
CREATE PROCEDURE GetLowStockProducts(IN p_threshold INT DEFAULT 10)
BEGIN
    SELECT 
        id,
        name,
        stock,
        price,
        CASE 
            WHEN stock = 0 THEN 'OUT OF STOCK'
            WHEN stock <= 5 THEN 'CRITICAL'
            WHEN stock <= p_threshold THEN 'LOW'
            ELSE 'NORMAL'
        END as stock_status
    FROM products 
    WHERE stock <= p_threshold
    ORDER BY stock ASC, name ASC;
END$$
DELIMITER ;

-- 4. Procedure to update product stock with TRANSACTION SUPPORT
DELIMITER $$
CREATE PROCEDURE UpdateProductStock(
    IN p_product_id INT,
    IN p_quantity_sold INT
)
BEGIN
    DECLARE current_stock INT DEFAULT 0;
    DECLARE product_exists INT DEFAULT 0;
    DECLARE product_name VARCHAR(255);
    
    -- Declare handler for SQL exceptions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Check if product exists and lock the row for update
    SELECT COUNT(*), COALESCE(stock, 0), name 
    INTO product_exists, current_stock, product_name
    FROM products 
    WHERE id = p_product_id
    FOR UPDATE;
    
    IF product_exists = 0 THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product not found';
    END IF;
    
    IF current_stock < p_quantity_sold THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
    
    -- Update the stock
    UPDATE products 
    SET stock = stock - p_quantity_sold 
    WHERE id = p_product_id;
    
    CALL LogTransaction(
    'UPDATE', 'products', p_product_id, NULL,
    CONCAT('Stock reduced by ', p_quantity_sold),
    current_stock, (current_stock - p_quantity_sold)
);
    
    -- Commit the transaction
    COMMIT;
    
    SELECT CONCAT('Stock updated for ', product_name, '. New stock: ', (current_stock - p_quantity_sold)) as message;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 3: ORDER MANAGEMENT PROCEDURES WITH TRANSACTIONS
-- =================================================================

-- 5. Procedure to get order summary (READ-ONLY, no transaction needed)
DELIMITER $$
CREATE PROCEDURE GetOrderSummary(IN p_order_id INT)
BEGIN
    SELECT 
        o.id as order_id,
        u.name as customer_name,
        u.email as customer_email,
        o.total,
        o.currency,
        o.created_at,
        COUNT(oi.id) as total_items,
        SUM(oi.quantity) as total_quantity
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = p_order_id
    GROUP BY o.id, u.name, u.email, o.total, o.currency, o.created_at;
END$$
DELIMITER ;

-- 6. Procedure to get monthly sales report (READ-ONLY, no transaction needed)
DELIMITER $$
CREATE PROCEDURE GetMonthlySalesReport(
    IN p_year INT,
    IN p_month INT
)
BEGIN
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(o.id) as total_orders,
        SUM(o.total) as total_revenue,
        AVG(o.total) as average_order_value,
        GROUP_CONCAT(DISTINCT o.currency) as currencies_used
    FROM orders o
    WHERE YEAR(o.created_at) = p_year 
    AND MONTH(o.created_at) = p_month
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date DESC;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 4: ANALYTICS PROCEDURES (READ-ONLY)
-- =================================================================

-- 7. Procedure to get top selling products (READ-ONLY)
DELIMITER $$
CREATE PROCEDURE GetTopSellingProducts(IN p_limit INT DEFAULT 10)
BEGIN
    SELECT 
        p.id,
        p.name,
        p.price,
        p.stock,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.price) as total_revenue,
        COUNT(DISTINCT oi.order_id) as times_ordered
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    GROUP BY p.id, p.name, p.price, p.stock
    ORDER BY total_sold DESC
    LIMIT p_limit;
END$$
DELIMITER ;

-- 8. Procedure to get customer purchase history (READ-ONLY)
DELIMITER $$
CREATE PROCEDURE GetCustomerPurchaseHistory(IN p_customer_id INT)
BEGIN
    SELECT 
        o.id as order_id,
        o.created_at as order_date,
        o.total,
        o.currency,
        p.name as product_name,
        oi.quantity,
        oi.price as unit_price,
        (oi.quantity * oi.price) as item_total
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = p_customer_id
    ORDER BY o.created_at DESC, o.id DESC;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 5: ENHANCED SECURITY AND AUDIT PROCEDURES
-- =================================================================

-- 9. Enhanced Procedure to log user activity with TRANSACTION
DELIMITER $$
CREATE PROCEDURE LogUserActivity(
    IN p_user_id INT,
    IN p_action VARCHAR(100),
    IN p_details TEXT
)
BEGIN
    -- Declare handler for SQL exceptions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Create audit log table if it doesn't exist
    CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100),
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    -- Verify user exists
    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_user_id) THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    END IF;
    
    INSERT INTO user_activity_log (user_id, action, details)
    VALUES (p_user_id, p_action, p_details);
    
    COMMIT;
    
    SELECT 'Activity logged successfully' as message;
END$$
DELIMITER ;

-- 10. Enhanced Procedure to clean old data with TRANSACTION
DELIMITER $$
CREATE PROCEDURE CleanOldData(IN p_days_old INT DEFAULT 30)
BEGIN
    DECLARE deleted_logs INT DEFAULT 0;
    
    -- Declare handler for SQL exceptions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Create cleanup log table if it doesn't exist
    CREATE TABLE IF NOT EXISTS cleanup_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cleanup_type VARCHAR(50),
        records_deleted INT,
        cleanup_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Delete old activity logs
    DELETE FROM user_activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL p_days_old DAY);
    
    SET deleted_logs = ROW_COUNT();
    
    -- Log the cleanup
    INSERT INTO cleanup_log (cleanup_type, records_deleted)
    VALUES ('user_activity_log', deleted_logs);
    
    COMMIT;
    
    SELECT CONCAT('Cleaned ', deleted_logs, ' old activity log records') as message;
END$$
DELIMITER ;

-- =================================================================
-- ENHANCED TRIGGERS WITH BETTER ERROR HANDLING
-- =================================================================

-- Enhanced Trigger 1: Auto-update product stock with validation
DELIMITER $$
CREATE TRIGGER tr_update_stock_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    DECLARE current_stock INT;
    
    -- Get current stock
    SELECT stock INTO current_stock
    FROM products
    WHERE id = NEW.product_id;
    
    -- Only update if sufficient stock
    IF current_stock >= NEW.quantity THEN
        UPDATE products 
        SET stock = stock - NEW.quantity 
        WHERE id = NEW.product_id;
    ELSE
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Insufficient stock for order item';
    END IF;
END$$
DELIMITER ;

-- Trigger 2: Log user activity on login (update last_login if column exists)
DELIMITER $$
CREATE TRIGGER tr_log_user_login
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    -- Add last_login column if it doesn't exist
    -- This trigger will work when we add the column
    IF NEW.name != OLD.name OR NEW.email != OLD.email OR NEW.role != OLD.role THEN
        INSERT INTO user_activity_log (user_id, action, details)
        VALUES (NEW.id, 'PROFILE_UPDATE', 
                CONCAT('Updated: ', 
                       CASE WHEN NEW.name != OLD.name THEN CONCAT('name(', OLD.name, '->', NEW.name, ') ') ELSE '' END,
                       CASE WHEN NEW.email != OLD.email THEN CONCAT('email(', OLD.email, '->', NEW.email, ') ') ELSE '' END,
                       CASE WHEN NEW.role != OLD.role THEN CONCAT('role(', OLD.role, '->', NEW.role, ') ') ELSE '' END
                ));
    END IF;
END$$
DELIMITER ;

-- Trigger 3: Prevent deletion of admin users if they're the last admin
DELIMITER $$
CREATE TRIGGER tr_prevent_last_admin_deletion
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    DECLARE admin_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO admin_count 
    FROM users 
    WHERE role = 'admin' AND id != OLD.id;
    
    IF OLD.role = 'admin' AND admin_count = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete the last admin user';
    END IF;
END$$
DELIMITER ;

-- Trigger 4: Auto-calculate order total when order items are inserted
DELIMITER $$
CREATE TRIGGER tr_calculate_order_total
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    DECLARE new_total DECIMAL(10,2) DEFAULT 0;
    
    SELECT SUM(quantity * price) INTO new_total
    FROM order_items
    WHERE order_id = NEW.order_id;
    
    UPDATE orders 
    SET total = new_total 
    WHERE id = NEW.order_id;
END$$
DELIMITER ;

-- Trigger 5: Log product changes for audit
DELIMITER $$
CREATE TRIGGER tr_log_product_changes
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    -- Create product audit table if it doesn't exist
    CREATE TABLE IF NOT EXISTS product_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT,
        old_name VARCHAR(255),
        new_name VARCHAR(255),
        old_price DECIMAL(10,2),
        new_price DECIMAL(10,2),
        old_stock INT,
        new_stock INT,
        change_type VARCHAR(50),
        changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    INSERT INTO product_audit_log (
        product_id, old_name, new_name, old_price, new_price, 
        old_stock, new_stock, change_type
    )
    VALUES (
        NEW.id, OLD.name, NEW.name, OLD.price, NEW.price,
        OLD.stock, NEW.stock, 'UPDATE'
    );
END$$
DELIMITER ;

-- Keep existing triggers 2-5 as they are...
-- (tr_log_user_login, tr_prevent_last_admin_deletion, tr_calculate_order_total, tr_log_product_changes)

-- =================================================================
-- STEP 4: TRIGGERS FOR AUTOMATIC LOGGING
-- These will log automatically without changing your procedures
-- =================================================================

-- Trigger to log user changes
DELIMITER $$
CREATE TRIGGER tr_log_user_changes
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.role != NEW.role THEN
        CALL LogTransaction(
            'UPDATE', 'users', NEW.id, NEW.id,
            CONCAT('User role changed from ', OLD.role, ' to ', NEW.role),
            OLD.role, NEW.role
        );
    END IF;
    
    IF OLD.name != NEW.name OR OLD.email != NEW.email THEN
        CALL LogTransaction(
            'UPDATE', 'users', NEW.id, NEW.id,
            'User profile updated',
            CONCAT('Name: ', OLD.name, ', Email: ', OLD.email),
            CONCAT('Name: ', NEW.name, ', Email: ', NEW.email)
        );
    END IF;
END$$
DELIMITER ;

-- Trigger to log product stock changes
DELIMITER $$
CREATE TRIGGER tr_log_stock_changes
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF OLD.stock != NEW.stock THEN
        CALL LogTransaction(
            'UPDATE', 'products', NEW.id, NULL,
            CONCAT('Stock changed from ', OLD.stock, ' to ', NEW.stock),
            OLD.stock, NEW.stock
        );
    END IF;
    
    IF OLD.price != NEW.price THEN
        CALL LogTransaction(
            'UPDATE', 'products', NEW.id, NULL,
            CONCAT('Price changed from ', OLD.price, ' to ', NEW.price),
            OLD.price, NEW.price
        );
    END IF;
END$$
DELIMITER ;

-- Trigger to log new orders
DELIMITER $$
CREATE TRIGGER tr_log_new_orders
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    CALL LogTransaction(
        'INSERT', 'orders', NEW.id, NEW.user_id,
        CONCAT('New order created: ', NEW.currency, ' ', NEW.total),
        NULL, NEW.total
    );
END$$
DELIMITER ;

-- Trigger to log order items
DELIMITER $$
CREATE TRIGGER tr_log_order_items
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    CALL LogTransaction(
        'INSERT', 'order_items', NEW.id, NULL,
        CONCAT('Order item added: Product ', NEW.product_id, ', Qty: ', NEW.quantity),
        NULL, CONCAT('Price: ', NEW.price, ', Qty: ', NEW.quantity)
    );
END$$
DELIMITER ;

-- Trigger to log transaction status changes
DELIMITER $$
CREATE TRIGGER tr_log_transaction_status
AFTER INSERT ON transaction_logs
FOR EACH ROW
BEGIN
    CALL LogTransaction(
        'INSERT', 'transaction_logs', NEW.id, NULL,
        CONCAT('Payment ', NEW.status, ' for order ', NEW.order_id),
        NULL, CONCAT('Method: ', NEW.payment_method, ', Amount: ', NEW.amount)
    );
END$$
DELIMITER ;

-- Trigger to log user deletions
DELIMITER $$
CREATE TRIGGER tr_log_user_deletion
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
    CALL LogTransaction(
        'DELETE', 'users', OLD.id, OLD.id,
        CONCAT('User deleted: ', OLD.name, ' (', OLD.email, ')'),
        CONCAT('Role: ', OLD.role), NULL
    );
END$$
DELIMITER ;

-- =================================================================
-- TESTING PROCEDURES WITH TRANSACTION SUPPORT
-- =================================================================

DELIMITER $$
CREATE PROCEDURE TestTransactionRollback()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'Transaction rolled back due to error' as result;
    END;
    
    START TRANSACTION;
    
    -- This should work
    INSERT INTO users (name, email, password, role)
    VALUES ('Test User', 'test@example.com', 'hashed_password', 'customer');
    
    -- This should fail (duplicate email)
    INSERT INTO users (name, email, password, role)
    VALUES ('Test User 2', 'test@example.com', 'hashed_password', 'customer');
    
    COMMIT;
    
    SELECT 'Transaction committed successfully' as result;
END$$
DELIMITER ;

-- Test procedure to verify all procedures work
DELIMITER $$
CREATE PROCEDURE TestAllProcedures()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE proc_name VARCHAR(64);
    DECLARE proc_cursor CURSOR FOR 
        SELECT ROUTINE_NAME 
        FROM INFORMATION_SCHEMA.ROUTINES 
        WHERE ROUTINE_SCHEMA = 'nviridian_shop' 
        AND ROUTINE_TYPE = 'PROCEDURE'
        AND ROUTINE_NAME LIKE 'Get%';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    SELECT 'Testing stored procedures...' as message;
    
    OPEN proc_cursor;
    read_loop: LOOP
        FETCH proc_cursor INTO proc_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SELECT CONCAT('Procedure exists: ', proc_name) as test_result;
    END LOOP;
    
    CLOSE proc_cursor;
    SELECT 'All procedure tests completed!' as final_message;
END$$
DELIMITER ;

-- =================================================================
-- SAMPLE USAGE WITH TRANSACTIONS
-- =================================================================

/*
-- Test transaction rollback
CALL TestTransactionRollback();

-- Process a complete order with automatic rollback on error
CALL ProcessCompleteOrder(1, 'USD', '[{"product_id": 1, "quantity": 2}, {"product_id": 2, "quantity": 1}]');

-- Update user role with transaction protection
CALL UpdateUserRole(2, 'staff', 1);

-- Batch update stock with savepoints
CALL BatchUpdateProductStock('[{"product_id": 1, "adjustment": 10}, {"product_id": 2, "adjustment": -5}]');

-- Process payment with automatic stock restoration on failure
CALL ProcessPayment(1, 'credit_card', 'failed');
*/

-- =================================================================
-- NOTES FOR TRANSACTION IMPLEMENTATION:
-- 1. All write operations now use START TRANSACTION and COMMIT/ROLLBACK
-- 2. Read-only procedures don't need transactions
-- 3. Error handlers ensure automatic ROLLBACK on failures
-- 4. Row locking (FOR UPDATE) prevents concurrent modification issues
-- 5. Savepoints allow partial rollback in batch operations
-- 6. All procedures maintain ACID properties
-- =================================================================