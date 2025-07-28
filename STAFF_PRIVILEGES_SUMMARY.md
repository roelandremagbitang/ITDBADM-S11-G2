# Nviridian Shop - User Role Privileges

## Role-Based Access Control System

### 🔴 **ADMIN ROLE**
**Full System Access**
- ✅ **User Management**: Can change user roles, manage accounts
- ✅ **Product Management**: Add, edit, delete products
- ✅ **Order Management**: View all orders, order history
- ✅ **System Administration**: Access all admin features
- ✅ **Reports**: View all reports and analytics
- 📁 **Dashboard**: admin_dashboard.php
- 🔗 **Navigation**: All features available

### 🔵 **STAFF ROLE** 
**Product & Order Management**
- ❌ **User Management**: Cannot change user roles
- ✅ **Product Management**: Add, edit, delete products
- ✅ **Order Management**: View orders, order history  
- ❌ **System Administration**: No admin-only features
- 📁 **Dashboard**: staff_dashboard.php
- 🔗 **Navigation**: Products, Orders, Reports

### 🟢 **CUSTOMER ROLE**
**Shopping Only**
- ❌ **User Management**: No access
- ❌ **Product Management**: Cannot manage products
- ✅ **Shopping**: Browse products, add to cart, checkout
- ✅ **Order History**: View own orders only
- ❌ **Reports**: No access to reports
- 📁 **Dashboard**: customer_dashboard.php
- 🔗 **Navigation**: Products, Cart, Orders

## Updated Files for Staff Product Management

### Files Modified:
1. **products_management.php** 
   - Changed access control from admin-only to admin + staff
   - Dashboard navigation automatically routes to correct dashboard

2. **staff_dashboard.php**
   - Added "🛠️ Manage Products" button
   - Added "📋 View Orders" button for order management

3. **accounts_management.php**
   - Updated staff privilege description
   - Updated role selection modal descriptions

### Access Control Logic:
```php
// Before (Admin Only)
if (!$user || $user['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// After (Admin + Staff)
if (!$user || !in_array($user['role'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit;
}
```

### Dashboard Navigation:
- **Admin**: Can access all features including user management
- **Staff**: Can access product management, orders, reports (no user management)
- **Customer**: Can only shop and view own orders

## Security Features Maintained:
- ✅ Session-based authentication
- ✅ Role validation on every page
- ✅ SQL injection prevention  
- ✅ Input sanitization
- ✅ Audit logging for role changes
- ✅ Last admin protection (cannot remove last admin)

## Staff Benefits:
- Full product management capabilities
- Can add new products with images
- Can edit existing products
- Can delete products
- Can manage inventory/stock levels
- Access to order viewing and reports
- Proper dashboard with all necessary tools
