<?php
// ========================================================================
// ملف نقل المنتجات من Cloudflare D1 إلى قاعدة بيانات TiDB Cloud (نسخة محصنة ضد تعارض الجداول)
// 🚨 هام جداً: قم بحذف هذا الملف فوراً بعد الانتهاء من عملية النقل لأسباب أمنية!
// المسار: htdocs/public_html/migrate.php
// ========================================================================

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/nalsh-user-admin-name.php'; // جلب اتصال الـ PDO الخاص بـ TiDB

// دالة جلب البيانات من Cloudflare D1 API
function fetch_from_d1($sql, $params = []) {
    $account_id  = getenv('CLOUDFLARE_ACCOUNT_ID') ?: $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? '';
    $database_id = getenv('CLOUDFLARE_DATABASE_ID') ?: $_ENV['CLOUDFLARE_DATABASE_ID'] ?? '';
    $api_token   = getenv('CLOUDFLARE_API_TOKEN') ?: $_ENV['CLOUDFLARE_API_TOKEN'] ?? '';
    
    if (empty($account_id) || empty($database_id) || empty($api_token)) {
        die("خطأ: بيانات Cloudflare D1 غير مكتملة في متغيرات البيئة على Render.\n");
    }
    
    $url = "https://api.cloudflare.com/client/v4/accounts/" . trim($account_id) . "/d1/database/" . trim($database_id) . "/query";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $payload = json_encode([
        'sql' => $sql,
        'params' => $params
    ], JSON_UNESCAPED_UNICODE);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($api_token)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        die("فشل الاتصال بـ Cloudflare D1. كود الاستجابة: $http_code\nاستجابة الخادم: $response\n");
    }
    
    $result = json_decode($response, true);
    if (!$result || !$result['success']) {
        die("خطأ من Cloudflare D1 API: " . print_r($result['errors'] ?? 'Unknown error', true) . "\n");
    }
    
    return $result['result'][0]['results'] ?? [];
}

echo "--- بدء عملية نقل المنتجات من Cloudflare D1 إلى TiDB Cloud ---\n\n";

try {
    // 1. التحقق الآمن من الجدول القديم وأخذ نسخة احتياطية منه عند الحاجة
    $column_exists = false;
    try {
        $stmt_cols = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'merchant_id'");
        if ($stmt_cols->fetch()) {
            $column_exists = true;
        }
    } catch (Exception $e) {
        // الجدول قد لا يكون موجوداً أساساً وهذا طبيعي
    }

    if (!$column_exists) {
        // التأكد من وجود الجدول قبل محاولة تغيير اسمه
        $table_exists = false;
        try {
            $pdo->query("SELECT 1 FROM `products` LIMIT 1");
            $table_exists = true;
        } catch (Exception $e) {}

        if ($table_exists) {
            $backup_name = 'products_backup_' . time();
            $pdo->exec("RENAME TABLE `products` TO `$backup_name`");
            echo "✔ تم العثور على جدول قديم غير متوافق. تم نقله احتياطياً بنجاح إلى اسم الجدول: ($backup_name)\n";
        }
    }

    // 2. إنشاء جدول المنتجات الحديث (products) الموحد في TiDB
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
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
        INDEX `merchant_idx` (`merchant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    echo "1. تم فحص وتهيئة جدول المنتجات (products) الحديث بنجاح.\n";

    // 3. جلب المنتجات من Cloudflare D1
    echo "2. جاري جلب المنتجات من Cloudflare D1...\n";
    $d1_products = fetch_from_d1("SELECT * FROM products");
    $count = count($d1_products);
    echo "-> تم العثور على ($count) منتج داخل Cloudflare D1.\n\n";

    if ($count === 0) {
        die("انتهى: لا توجد منتجات مسجلة لنقلها.\n");
    }

    // 4. كتابة المنتجات في جدول TiDB المحدث
    echo "3. بدء كتابة المنتجات في TiDB Cloud...\n";
    $stmt = $pdo->prepare("
        INSERT INTO products (
            id, merchant_id, name, description, price, cost_price, discount, image, type, options, quantity, quantity_type, is_available, currency, updated_at, approval_status, isAvailable, category_id, department, keywords
        ) VALUES (
            :id, :merchant_id, :name, :description, :price, :cost_price, :discount, :image, :type, :options, :quantity, :quantity_type, :is_available, :currency, :updated_at, :approval_status, :isAvailable, :category_id, :department, :keywords
        )
        ON DUPLICATE KEY UPDATE
            merchant_id = VALUES(merchant_id),
            name = VALUES(name),
            description = VALUES(description),
            price = VALUES(price),
            cost_price = VALUES(cost_price),
            discount = VALUES(discount),
            image = VALUES(image),
            type = VALUES(type),
            options = VALUES(options),
            quantity = VALUES(quantity),
            quantity_type = VALUES(quantity_type),
            is_available = VALUES(is_available),
            currency = VALUES(currency),
            updated_at = VALUES(updated_at),
            approval_status = VALUES(approval_status),
            isAvailable = VALUES(isAvailable),
            category_id = VALUES(category_id),
            department = VALUES(department),
            keywords = VALUES(keywords)
    ");

    $migrated_count = 0;
    foreach ($d1_products as $row) {
        $options = $row['options'] ?? '[]';
        if (is_array($options) || is_object($options)) {
            $options = json_encode($options, JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            ':id'              => $row['id'],
            ':merchant_id'     => $row['merchant_id'],
            ':name'            => $row['name'],
            ':description'     => $row['description'] ?? $row['mainDescription'] ?? '',
            ':price'           => $row['price'] ?? 0,
            ':cost_price'      => $row['cost_price'] ?? 0,
            ':discount'        => $row['discount'] ?? 0,
            ':image'           => $row['image'] ?? '',
            ':type'            => $row['type'] ?? 'عام',
            ':options'         => $options,
            ':quantity'        => $row['quantity'] ?? 0,
            ':quantity_type'   => $row['quantity_type'] ?? 'tracked',
            ':is_available'    => isset($row['is_available']) ? $row['is_available'] : (isset($row['isAvailable']) ? $row['isAvailable'] : 1),
            ':currency'        => $row['currency'] ?? 'YER',
            ':updated_at'      => $row['updated_at'] ?? time(),
            ':approval_status' => $row['approval_status'] ?? 'approved',
            ':isAvailable'     => $row['isAvailable'] ?? 1,
            ':category_id'     => $row['category_id'] ?? null,
            ':department'      => $row['department'] ?? 'عام',
            ':keywords'        => $row['keywords'] ?? ''
        ]);
        $migrated_count++;
        echo "✔ تم نقل المنتج: " . $row['name'] . "\n";
    }

    echo "\n🎉 تم اكتمال النقل بنجاح! تم نقل ($migrated_count) منتج وتخزينهم في TiDB Cloud.\n";
    echo "\n🚨 هام لأمان موقعك: يرجى مسح ملف 'migrate.php' فوراً من خادمك لتجنب أي استغلال ثانٍ.\n";

} catch (Exception $e) {
    echo "\n❌ حدث خطأ أثناء عملية النقل: " . $e->getMessage() . "\n";
}
