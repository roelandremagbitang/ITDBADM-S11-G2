-- =================================================================
-- ADMIN PRIVILEGE MANAGEMENT FOR NVIRIDIAN SHOP
-- Additional stored procedures for role management
-- =================================================================

USE nviridian_shop;

-- Procedure to change user role (using existing UpdateUserRole procedure)
-- This procedure is already implemented in nviridian_stored_procedures_triggers.sql

-- Procedure to get user role change history
DELIMITER $$
CREATE PROCEDURE GetUserRoleHistory(IN p_user_id INT)
BEGIN
    SELECT 
        tl.created_at as change_date,
        tl.description,
        tl.old_value as old_role,
        tl.new_value as new_role,
        u.name as changed_by
    FROM transaction_logs tl
    LEFT JOIN users u ON tl.user_id = u.id
    WHERE tl.table_name = 'users' 
    AND tl.record_id = p_user_id 
    AND tl.action_type = 'UPDATE'
    AND tl.description LIKE '%role%'
    ORDER BY tl.created_at DESC;
END$$
DELIMITER ;

-- Procedure to validate admin privileges before role change
DELIMITER $$
CREATE PROCEDURE ValidateAdminPrivileges(
    IN p_admin_id INT,
    OUT p_is_admin BOOLEAN,
    OUT p_admin_name VARCHAR(255)
)
BEGIN
    DECLARE admin_count INT DEFAULT 0;
    
    SELECT COUNT(*), MAX(name) 
    INTO admin_count, p_admin_name
    FROM users 
    WHERE id = p_admin_id AND role = 'admin';
    
    SET p_is_admin = (admin_count > 0);
END$$
DELIMITER ;

-- Procedure to get all users with their privilege summary
DELIMITER $$
CREATE PROCEDURE GetUsersWithPrivileges()
BEGIN
    SELECT 
        u.id,
        u.name,
        u.email,
        u.role,
        CASE 
            WHEN u.role = 'admin' THEN 'Full Access - Can manage users, products, orders'
            WHEN u.role = 'staff' THEN 'Limited Access - Can manage products and view orders'
            WHEN u.role = 'customer' THEN 'Customer Access - Can browse and purchase products'
            ELSE 'Unknown Role'
        END as privilege_description,
        (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
        (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
    FROM users u
    ORDER BY 
        CASE u.role 
            WHEN 'admin' THEN 1 
            WHEN 'staff' THEN 2 
            WHEN 'customer' THEN 3 
            ELSE 4 
        END, u.name;
END$$
DELIMITER ;
