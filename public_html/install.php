<?php

// =================================================================
//      NALSH STORE - DATABASE INSTALLATION SCRIPT V1.0
// =================================================================
//  Purpose: To create all necessary tables for the Nalsh multi-vendor
//           e-commerce platform, based on the provided project files.
//  Usage:   Upload this file to your `public_html` directory and
//           run it once from your browser.
// =================================================================

// --- Basic Setup ---
set_time_limit(300); // 5 minutes max execution time
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><title>تثبيت قاعدة البيانات</title><style>
    body { font-family: 'Tajawal', sans-serif; background-color: #0f172a; color: #e2e8f0; line-height: 1.8; padding: 40px; }
    h1 { color: #6366f1; border-bottom: 2px solid #334155; padding-bottom: 10px; }
    .log { background-color: #1e293b; border-left: 4px solid #4f46e5; margin: 1em 0; padding: 1em; border-radius: 8px; font-family: monospace, 'Courier New'; }
    .success { color: #34d399; }
    .error { color: #f87171; }
    .warn { color: #f59e0b; }
    .final { font-size: 1.2rem; font-weight: bold; margin-top: 20px; padding: 20px; border-radius: 10px; }
</style><link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap' rel='stylesheet'></head><body>";
echo "<h1><i class='fas fa-database'></i> بدء عملية تثبيت قاعدة بيانات متجر نالش...</h1>";

try {
    // --- 1. Connect to the Database ---
    echo "<div class='log'>محاولة الاتصال بقاعدة البيانات...</div>";
    require_once __DIR__ . '/../includes/nalsh-user-admin-name.php';

    if (!$pdo) {
        throw new Exception("فشل الاتصال بقاعدة البيانات. يرجى التحقق من بيانات الاعتماد في 'nalsh-user-admin-name.php'.");
    }
    echo "<div class='log success'>تم الاتصال بقاعدة البيانات '" . DB_NAME . "' بنجاح.</div>";

    // --- 2. SQL Schema Definitions ---
    // This array holds all the CREATE TABLE statements.
    $tables = [];

    // Table for Users (Admin, Merchants, Delivery Agents)
    $tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
      `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `store_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `role` enum('admin','merchant','delivery') COLLATE utf8mb4_unicode_ci NOT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `account_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
      `settings` JSON,
      `store_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `employer_id` int(11) DEFAULT NULL,
      `last_location` JSON,
      `last_active_at` datetime DEFAULT NULL,
      `failed_login_attempts` int(11) DEFAULT 0,
      `lockout_until` datetime DEFAULT NULL,
      `password_changed_at` datetime DEFAULT NULL,
      `pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `username` (`username`),
      KEY `phone` (`phone`),
      KEY `role` (`role`),
      KEY `employer_id` (`employer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table for Customers
    $tables['customers'] = "CREATE TABLE IF NOT EXISTS `customers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
      `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
      `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `address` text COLLATE utf8mb4_unicode_ci,
      `otp_code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `is_verified` tinyint(1) DEFAULT 0,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `phone` (`phone`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    // Table for Global Products Catalog
    $tables['products'] = "CREATE TABLE IF NOT EXISTS `products` (
      `id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
      `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
      `mainDescription` text COLLATE utf8mb4_unicode_ci,
      `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `delete_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `sizes` JSON,
      `price` decimal(10,2) DEFAULT NULL,
      `base_price` decimal(10,2) DEFAULT NULL,
      `discount` decimal(5,2) DEFAULT 0.00,
      `keywords` text COLLATE utf8mb4_unicode_ci,
      `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `isAvailable` tinyint(1) DEFAULT 1,
      `approval_status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'approved',
      `product_number` int(11) DEFAULT NULL,
      `user_id` int(11) DEFAULT NULL,
      `category_id` int(11) DEFAULT NULL,
      `currency` VARCHAR(5) DEFAULT 'YER',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `category_id` (`category_id`),
      KEY `user_id` (`user_id`),
      FULLTEXT KEY `product_search` (`name`,`keywords`,`mainDescription`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    // Table for Categories
    $tables['categories'] = "CREATE TABLE IF NOT EXISTS `categories` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
      `parent_id` int(11) DEFAULT NULL,
      `user_id` INT(11) NULL,
      PRIMARY KEY (`id`),
      KEY `name` (`name`),
      KEY `parent_id` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Linking table between Merchants and Products
    $tables['merchant_listings'] = "CREATE TABLE IF NOT EXISTS `merchant_listings` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) NOT NULL,
      `global_product_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
      `merchant_price` decimal(10,2) NOT NULL,
      `cost_price` decimal(10,2) DEFAULT 0.00,
      `quantity` int(11) DEFAULT 0,
      `quantity_type` enum('tracked','unlimited') COLLATE utf8mb4_unicode_ci DEFAULT 'tracked',
      `is_available` tinyint(1) DEFAULT 1,
      `price_variables` JSON,
      `currency` VARCHAR(5) DEFAULT 'YER',
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `merchant_product` (`merchant_id`,`global_product_id`),
      KEY `global_product_id` (`global_product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // New Orders table (Live Tickets)
    $tables['live_tickets'] = "CREATE TABLE IF NOT EXISTS `live_tickets` (
      `ticket_id` varchar(64) NOT NULL,
      `order_group_id` varchar(64) DEFAULT NULL,
      `merchant_id` int(11) NOT NULL,
      `customer_id` int(11) NOT NULL,
      `delivery_agent_id` int(11) DEFAULT NULL,
      `status` varchar(50) NOT NULL,
      `delivery_code` varchar(10) DEFAULT NULL,
      `ticket_data` JSON NOT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`ticket_id`),
      KEY `merchant_id` (`merchant_id`),
      KEY `customer_id` (`customer_id`),
      KEY `delivery_agent_id` (`delivery_agent_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Archived Orders table
    $tables['orders_archive'] = "CREATE TABLE IF NOT EXISTS `orders_archive` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ticket_id` varchar(64) NOT NULL,
      `customer_id` int(11) NOT NULL,
      `merchant_id` int(11) NOT NULL,
      `final_status` varchar(50) NOT NULL,
      `total_amount` decimal(10,2) NOT NULL,
      `archived_data` JSON NOT NULL,
      `archived_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ticket_id` (`ticket_id`),
      KEY `customer_id` (`customer_id`),
      KEY `merchant_id` (`merchant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Legacy Orders tables (for compatibility)
    $tables['orders'] = "CREATE TABLE IF NOT EXISTS `orders` ( `id` INT(11) AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB;";
    $tables['order_items'] = "CREATE TABLE IF NOT EXISTS `order_items` ( `id` INT(11) AUTO_INCREMENT PRIMARY KEY ) ENGINE=InnoDB;";

    // Customer Cart and Favorites
    $tables['user_cart'] = "CREATE TABLE IF NOT EXISTS `user_cart` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_id` int(11) NOT NULL,
      `product_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
      `listing_id` INT(11) NOT NULL,
      `merchant_id` INT(11) NOT NULL,
      `user_id` INT(11) NULL,
      `size_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `quantity` int(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `customer_item_unique` (`customer_id`,`listing_id`,`size_id`),
      KEY `customer_id` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $tables['user_favorites'] = "CREATE TABLE IF NOT EXISTS `user_favorites` (`id` int(11) NOT NULL AUTO_INCREMENT, `customer_id` int(11) NOT NULL, `product_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `customer_product` (`customer_id`,`product_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // System and Security tables
    $tables['settings'] = "CREATE TABLE IF NOT EXISTS `settings` (`setting_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL, `setting_value` text COLLATE utf8mb4_unicode_ci, PRIMARY KEY (`setting_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $tables['auth_tokens'] = "CREATE TABLE IF NOT EXISTS `auth_tokens` (`id` int(11) NOT NULL AUTO_INCREMENT, `selector` varchar(255) NOT NULL, `hashed_validator` varchar(255) NOT NULL, `user_id` int(11) NOT NULL, `expires` datetime NOT NULL, PRIMARY KEY (`id`), KEY `selector` (`selector`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $tables['trusted_devices'] = "CREATE TABLE IF NOT EXISTS `trusted_devices` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `device_token` VARCHAR(128) NOT NULL, `user_agent` TEXT, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `last_used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY (`user_id`), KEY (`device_token`)) ENGINE=InnoDB;";
    $tables['api_requests'] = "CREATE TABLE IF NOT EXISTS `api_requests` (`id` INT AUTO_INCREMENT PRIMARY KEY, `ip_address` VARCHAR(45) NOT NULL, `request_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY (`ip_address`), KEY (`request_time`)) ENGINE=InnoDB;";
    $tables['idempotency_keys'] = "CREATE TABLE IF NOT EXISTS `idempotency_keys` (`id` int(11) NOT NULL AUTO_INCREMENT, `key_token` varchar(128) NOT NULL, `response_data` text NOT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `key_token` (`key_token`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $tables['sms_queue'] = "CREATE TABLE IF NOT EXISTS `sms_queue` (`id` int(11) NOT NULL AUTO_INCREMENT, `phone_number` varchar(25) NOT NULL, `message` text NOT NULL, `status` enum('pending','sent','failed') DEFAULT 'pending', `retry_count` int(11) DEFAULT 0, `last_attempt_at` timestamp NULL DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, `processed_at` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`), KEY `status_index` (`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    // Financial and Delivery tables
    $tables['sales_log'] = "CREATE TABLE IF NOT EXISTS `sales_log` (`id` varchar(64) NOT NULL, `user_id` int(11) NOT NULL, `product_id` varchar(64) NOT NULL, `size_id` varchar(50) DEFAULT NULL, `quantity` int(11) NOT NULL, `price_per_item` decimal(10,2) NOT NULL, `total_price` decimal(10,2) NOT NULL, `cost_at_sale` decimal(10,2) DEFAULT NULL, `currency` varchar(5) NOT NULL, `type` enum('sale','return') NOT NULL, `original_sale_id` varchar(64) DEFAULT NULL, `order_id` varchar(64) DEFAULT NULL, `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `type` (`type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $tables['expenses'] = "CREATE TABLE IF NOT EXISTS `expenses` (`id` int(11) NOT NULL AUTO_INCREMENT, `user_id` int(11) NOT NULL, `expense_date` date NOT NULL, `category` varchar(100) NOT NULL, `description` text, `amount` decimal(10,2) NOT NULL, `currency` varchar(5) NOT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $tables['delivery_agent_strikes'] = "CREATE TABLE IF NOT EXISTS `delivery_agent_strikes` (`id` INT AUTO_INCREMENT PRIMARY KEY, `agent_id` INT, `order_id` VARCHAR(64), `strike_type` VARCHAR(50), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;";
    $tables['merchant_agent_links'] = "CREATE TABLE IF NOT EXISTS `merchant_agent_links` (`id` INT AUTO_INCREMENT PRIMARY KEY, `merchant_id` INT, `agent_id` INT, `status` ENUM('pending', 'accepted', 'rejected'), `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;";

    // --- 3. Execute Table Creation ---
    foreach ($tables as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "<div class='log success'>جدول '{$name}' تم فحصه/إنشاؤه بنجاح.</div>";
        } catch (PDOException $e) {
            echo "<div class='log error'>فشل في إنشاء جدول '{$name}': " . $e->getMessage() . "</div>";
        }
    }

    // --- 4. Insert Default Admin User (if not exists) ---
    echo "<div class='log'>فحص وجود حساب المدير...</div>";
    $admin_user = 'admin';
    $admin_pass = 'password123';
    $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$admin_user]);
    if ($stmt->fetch()) {
        echo "<div class='log success'>حساب المدير موجود بالفعل.</div>";
    } else {
        $pdo->prepare("INSERT INTO users (username, password, role, is_active, store_name, account_status) VALUES (?, ?, 'admin', 1, 'لوحة التحكم الرئيسية', 'approved')")
             ->execute([$admin_user, $hashed_pass]);
        echo "<div class='log warn'>تم إنشاء حساب مدير افتراضي. <br>اسم المستخدم: <strong>{$admin_user}</strong> <br>كلمة المرور: <strong>{$admin_pass}</strong> <br>يرجى تغيير كلمة المرور فوراً بعد تسجيل الدخول.</div>";
    }
    
    // --- 5. Final Message ---
    echo "<div class='log final success'>🎉 اكتملت عملية التثبيت بنجاح! جميع الجداول جاهزة للعمل. يمكنك الآن حذف هذا الملف بأمان.</div>";

} catch (Exception $e) {
    echo "<div class='log final error'>❌ حدث خطأ فادح: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
