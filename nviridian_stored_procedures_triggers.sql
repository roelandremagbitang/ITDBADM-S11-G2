-- =================================================================
-- NVIRIDIAN SHOP - STORED PROCEDURES AND TRIGGERS
-- Database Enhancement Script
-- Created: July 21, 2025
-- =================================================================

USE nviridian_shop;

-- =================================================================
-- CATEGORY 1: USER MANAGEMENT PROCEDURES
-- =================================================================

-- 1. Procedure to get user statistics
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

-- 2. Procedure to update user role (Admin only)
DELIMITER $$
CREATE PROCEDURE UpdateUserRole(
    IN p_user_id INT,
    IN p_new_role ENUM('customer', 'staff', 'admin'),
    IN p_admin_id INT
)
BEGIN
    DECLARE admin_check INT DEFAULT 0;
    DECLARE user_exists INT DEFAULT 0;
    
    -- Check if the person making the change is an admin
    SELECT COUNT(*) INTO admin_check 
    FROM users 
    WHERE id = p_admin_id AND role = 'admin';
    
    -- Check if target user exists
    SELECT COUNT(*) INTO user_exists 
    FROM users 
    WHERE id = p_user_id;
    
    IF admin_check = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admins can update user roles';
    ELSEIF user_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    ELSE
        UPDATE users 
        SET role = p_new_role 
        WHERE id = p_user_id;
        
        SELECT CONCAT('User role updated to ', p_new_role) as message;
    END IF;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 2: PRODUCT MANAGEMENT PROCEDURES
-- =================================================================

-- 3. Procedure to get low stock products
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

-- 4. Procedure to update product stock after sale
DELIMITER $$
CREATE PROCEDURE UpdateProductStock(
    IN p_product_id INT,
    IN p_quantity_sold INT
)
BEGIN
    DECLARE current_stock INT DEFAULT 0;
    DECLARE product_exists INT DEFAULT 0;
    
    -- Check if product exists
    SELECT COUNT(*), COALESCE(stock, 0) 
    INTO product_exists, current_stock
    FROM products 
    WHERE id = p_product_id;
    
    IF product_exists = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Product not found';
    ELSEIF current_stock < p_quantity_sold THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
    ELSE
        UPDATE products 
        SET stock = stock - p_quantity_sold 
        WHERE id = p_product_id;
        
        SELECT CONCAT('Stock updated. New stock: ', (current_stock - p_quantity_sold)) as message;
    END IF;
END$$
DELIMITER ;

-- =================================================================
-- CATEGORY 3: ORDER MANAGEMENT PROCEDURES
-- =================================================================

-- 5. Procedure to get order summary with customer details
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

-- 6. Procedure to get monthly sales report
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
-- CATEGORY 4: ANALYTICS AND REPORTING PROCEDURES
-- =================================================================

-- 7. Procedure to get top selling products
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

-- 8. Procedure to get customer purchase history
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
-- CATEGORY 5: SECURITY AND AUDIT PROCEDURES
-- =================================================================

-- 9. Procedure to log user activity
DELIMITER $$
CREATE PROCEDURE LogUserActivity(
    IN p_user_id INT,
    IN p_action VARCHAR(100),
    IN p_details TEXT
)
BEGIN
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
    
    INSERT INTO user_activity_log (user_id, action, details)
    VALUES (p_user_id, p_action, p_details);
END$$
DELIMITER ;

-- 10. Procedure to clean old session data
DELIMITER $$
CREATE PROCEDURE CleanOldData(IN p_days_old INT DEFAULT 30)
BEGIN
    DECLARE deleted_logs INT DEFAULT 0;
    
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
    
    SELECT CONCAT('Cleaned ', deleted_logs, ' old activity log records') as message;
END$$
DELIMITER ;

-- =================================================================
-- TRIGGERS FOR AUTOMATIC DATA MANAGEMENT
-- =================================================================

-- Trigger 1: Auto-update product stock when order is placed
DELIMITER $$
CREATE TRIGGER tr_update_stock_after_order
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE products 
    SET stock = stock - NEW.quantity 
    WHERE id = NEW.product_id;
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

-- =================================================================
-- UTILITY PROCEDURES FOR TESTING AND MAINTENANCE
-- =================================================================

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
-- SAMPLE DATA FOR TESTING PROCEDURES
-- =================================================================

-- You can uncomment these to test the procedures
/*
-- Test user statistics
CALL GetUserStatistics();

-- Test low stock products
CALL GetLowStockProducts(15);

-- Test monthly sales (adjust year/month as needed)
CALL GetMonthlySalesReport(2025, 7);

-- Test top selling products
CALL GetTopSellingProducts(5);

-- Test procedure verification
CALL TestAllProcedures();
*/

-- =================================================================
-- NOTES FOR IMPLEMENTATION:
-- 1. Run this script after creating your main database
-- 2. Test each procedure individually before using in production
-- 3. Some procedures create audit tables automatically
-- 4. Triggers will activate automatically after creation
-- 5. Use CALL ProcedureName(parameters) to execute procedures
-- =================================================================
