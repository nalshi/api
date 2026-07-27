<?php
/**
 * سكربت لمرة واحدة: ينشئ كل جداول قاعدة بيانات Nalsh على TiDB Cloud.
 * ⚠️ بعد التنفيذ الناجح، احذف هذا الملف فوراً من السيرفر لأنه يحتوي
 *    بيانات اتصال حساسة (يوزر وباسورد قاعدة البيانات).
 */

// ============ بيانات الاتصال ============
$DB_HOST     = "gateway01.eu-central-1.prod.aws.tidbcloud.com";
$DB_PORT     = 4000;
$DB_USERNAME = "G8uR7b18HrHhM4w.root";
$DB_PASSWORD = "oVaumZm0uUSrgW0z";
$DB_DATABASE = "nalsh";

// ============ لا تغيّر ما تحت هذا السطر ============
header('Content-Type: text/plain; charset=utf-8');

$tables = [];

$tables['users'] = "
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `store_name` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(30) DEFAULT NULL,
    `role` ENUM('admin','merchant','delivery') NOT NULL DEFAULT 'merchant',
    `is_active` TINYINT(1) DEFAULT 1,
    `account_status` VARCHAR(50) DEFAULT 'approved',
    `store_type` VARCHAR(100) DEFAULT NULL,
    `settings` JSON DEFAULT NULL,
    `employer_id` INT DEFAULT NULL,
    `fcm_token` TEXT DEFAULT NULL,
    `failed_login_attempts` INT DEFAULT 0,
    `lockout_until` DATETIME DEFAULT NULL,
    `password_changed_at` DATETIME DEFAULT NULL,
    `pin` VARCHAR(20) DEFAULT NULL,
    `last_location` JSON DEFAULT NULL,
    `last_active_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `role_idx` (`role`),
    INDEX `employer_idx` (`employer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['customers'] = "
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `otp_code` VARCHAR(10) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['products'] = "
CREATE TABLE IF NOT EXISTS `products` (
    `id` VARCHAR(100) PRIMARY KEY,
    `merchant_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `cost_price` DECIMAL(10,2) DEFAULT 0,
    `discount` DECIMAL(5,2) DEFAULT 0,
    `image` TEXT,
    `type` VARCHAR(100) DEFAULT 'عام',
    `options` JSON,
    `features` JSON,
    `quantity` INT DEFAULT 0,
    `quantity_type` ENUM('tracked', 'unlimited') DEFAULT 'tracked',
    `is_available` TINYINT(1) DEFAULT 1,
    `currency` VARCHAR(10) DEFAULT 'YER',
    `updated_at` BIGINT,
    `approval_status` VARCHAR(50) DEFAULT 'approved',
    `isAvailable` TINYINT(1) DEFAULT 1,
    `category_id` INT,
    `department` VARCHAR(100) DEFAULT 'عام',
    `keywords` TEXT,
    INDEX `merchant_idx` (`merchant_id`),
    INDEX `category_idx` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['categories'] = "
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `parent_id` INT DEFAULT 0,
    `user_id` INT DEFAULT NULL,
    `created_at` BIGINT,
    INDEX `parent_idx` (`parent_id`),
    INDEX `user_idx` (`user_id`),
    UNIQUE KEY `uniq_name_parent_user` (`name`, `parent_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['product_sizes'] = "
CREATE TABLE IF NOT EXISTS `product_sizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` VARCHAR(100) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `price_modifier` DECIMAL(10,2) DEFAULT 0,
    `quantity` INT DEFAULT 0,
    INDEX `product_idx` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['merchant_listings'] = "
CREATE TABLE IF NOT EXISTS `merchant_listings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `merchant_id` INT NOT NULL,
    `global_product_id` VARCHAR(100) NOT NULL,
    `merchant_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `cost_price` DECIMAL(10,2) DEFAULT 0,
    `quantity` INT DEFAULT 0,
    `quantity_type` ENUM('tracked','unlimited') DEFAULT 'tracked',
    `is_available` TINYINT(1) DEFAULT 1,
    `currency` VARCHAR(10) DEFAULT 'YER',
    `price_variables` JSON DEFAULT NULL,
    `updated_at` BIGINT,
    INDEX `merchant_idx` (`merchant_id`),
    INDEX `global_product_idx` (`global_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['live_tickets'] = "
CREATE TABLE IF NOT EXISTS `live_tickets` (
    `ticket_id` VARCHAR(100) PRIMARY KEY,
    `order_group_id` VARCHAR(100) DEFAULT NULL,
    `merchant_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `delivery_agent_id` INT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending_merchant_approval',
    `delivery_code` VARCHAR(10) DEFAULT NULL,
    `cancel_reason` TEXT DEFAULT NULL,
    `ticket_data` JSON NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `merchant_idx` (`merchant_id`),
    INDEX `customer_idx` (`customer_id`),
    INDEX `agent_idx` (`delivery_agent_id`),
    INDEX `status_idx` (`status`),
    INDEX `group_idx` (`order_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['orders_archive'] = "
CREATE TABLE IF NOT EXISTS `orders_archive` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` VARCHAR(100) NOT NULL,
    `customer_id` INT NOT NULL,
    `merchant_id` INT NOT NULL,
    `final_status` VARCHAR(50) NOT NULL,
    `total_amount` DECIMAL(10,2) DEFAULT 0,
    `archived_data` JSON NOT NULL,
    `archived_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `customer_idx` (`customer_id`),
    INDEX `merchant_idx` (`merchant_id`),
    INDEX `ticket_idx` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['orders'] = "
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `merchant_id` INT NOT NULL,
    `delivery_agent_id` INT DEFAULT NULL,
    `total_amount` DECIMAL(10,2) DEFAULT 0,
    `currency` VARCHAR(10) DEFAULT 'YER',
    `delivery_fee` DECIMAL(10,2) DEFAULT 0,
    `delivery_address_text` TEXT DEFAULT NULL,
    `delivery_gps_link` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `delivery_code` VARCHAR(10) DEFAULT NULL,
    `cancel_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `customer_idx` (`customer_id`),
    INDEX `merchant_idx` (`merchant_id`),
    INDEX `agent_idx` (`delivery_agent_id`),
    INDEX `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['order_items'] = "
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` VARCHAR(100) NOT NULL,
    `size_id` INT DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
    INDEX `order_idx` (`order_id`),
    INDEX `product_idx` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['sales_log'] = "
CREATE TABLE IF NOT EXISTS `sales_log` (
    `id` VARCHAR(100) PRIMARY KEY,
    `user_id` INT NOT NULL,
    `product_id` VARCHAR(100) NOT NULL,
    `size_id` INT DEFAULT NULL,
    `quantity` INT NOT NULL,
    `price_per_item` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'YER',
    `type` ENUM('sale','return') NOT NULL DEFAULT 'sale',
    `cost_at_sale` DECIMAL(10,2) DEFAULT 0,
    `order_id` VARCHAR(100) DEFAULT NULL,
    `original_sale_id` VARCHAR(100) DEFAULT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `user_idx` (`user_id`),
    INDEX `product_idx` (`product_id`),
    INDEX `type_idx` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['expenses'] = "
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'YER',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['delivery_agent_strikes'] = "
CREATE TABLE IF NOT EXISTS `delivery_agent_strikes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `agent_id` INT NOT NULL,
    `order_id` VARCHAR(100) NOT NULL,
    `strike_type` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `agent_idx` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['merchant_agent_links'] = "
CREATE TABLE IF NOT EXISTS `merchant_agent_links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `merchant_id` INT NOT NULL,
    `agent_id` INT NOT NULL,
    `status` ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_merchant_agent` (`merchant_id`, `agent_id`),
    INDEX `agent_idx` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['idempotency_keys'] = "
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
    `key_token` VARCHAR(191) PRIMARY KEY,
    `response_data` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['auth_tokens'] = "
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `selector` VARCHAR(64) NOT NULL UNIQUE,
    `hashed_validator` VARCHAR(255) NOT NULL,
    `user_id` INT NOT NULL,
    `expires` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['trusted_devices'] = "
CREATE TABLE IF NOT EXISTS `trusted_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `device_token` VARCHAR(191) NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME DEFAULT NULL,
    INDEX `user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['rate_limits'] = "
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `phone_number` VARCHAR(30) DEFAULT NULL,
    `request_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `ip_idx` (`ip_address`),
    INDEX `phone_idx` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['api_requests'] = "
CREATE TABLE IF NOT EXISTS `api_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `request_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `ip_idx` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['sms_queue'] = "
CREATE TABLE IF NOT EXISTS `sms_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `phone_number` VARCHAR(30) NOT NULL,
    `message` TEXT NOT NULL,
    `status` VARCHAR(30) DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `phone_idx` (`phone_number`),
    INDEX `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['settings'] = "
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` LONGTEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['user_cart'] = "
CREATE TABLE IF NOT EXISTS `user_cart` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `product_id` VARCHAR(100) NOT NULL,
    `listing_id` VARCHAR(100) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `merchant_id` INT DEFAULT NULL,
    `size_id` INT DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `customer_item_unique` (`customer_id`, `product_id`, `size_id`),
    INDEX `merchant_idx` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$tables['user_favorites'] = "
CREATE TABLE IF NOT EXISTS `user_favorites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `product_id` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_customer_product` (`customer_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ============ التنفيذ ============
try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_DATABASE};charset=utf8mb4";

    // TiDB Cloud (Serverless) يرفض أي اتصال غير مشفّر، فلازم نفعّل SSL هنا.
    // نجرب أشهر مسارات شهادات CA الموجودة عادة على السيرفرات (Linux/cPanel).
    $possibleCaBundles = [
        '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
        '/etc/pki/tls/certs/ca-bundle.crt',    // CentOS/RHEL
        '/etc/ssl/cert.pem',                   // بعض توزيعات cPanel/macOS
    ];

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ];

    foreach ($possibleCaBundles as $ca) {
        if (is_readable($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            break;
        }
    }

    if (!isset($options[PDO::MYSQL_ATTR_SSL_CA])) {
        // ما لقينا شهادة CA جاهزة على السيرفر — نفعّل SSL بدون تحقق من الشهادة
        // (يحل مشكلة "insecure transport" لكنه أقل أمانًا؛ الأفضل رفع شهادة CA حقيقية)
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $DB_USERNAME, $DB_PASSWORD, $options);
    echo "✅ تم الاتصال بنجاح (SSL) بقاعدة البيانات '{$DB_DATABASE}'.\n\n";
} catch (PDOException $e) {
    die("❌ فشل الاتصال بقاعدة البيانات:\n" . $e->getMessage() . "\n");
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$success = 0;
$failed  = 0;

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ تم إنشاء/التأكد من الجدول: {$name}\n";
        $success++;
    } catch (PDOException $e) {
        echo "❌ فشل إنشاء الجدول '{$name}': " . $e->getMessage() . "\n";
        $failed++;
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n----------------------------------------\n";
echo "النتيجة النهائية: {$success} نجح، {$failed} فشل من أصل " . count($tables) . " جدول.\n";
echo "⚠️ احذف هذا الملف من السيرفر الآن لأنه يحتوي بيانات اتصال حساسة!\n";
