<?php
// =======================================================
// ملف تنظيف قواعد البيانات (TiDB + Cloudflare D1)
// مخصص لمرحلة ما بعد التطوير لمسح البيانات الوهمية
// =======================================================

// 1. حماية الملف برمز سري لمنع الكوارث (تأكد من تمرير ?secret=123456 في الرابط)
$secret_key = '123456'; // يمكنك تغيير هذا الرقم لأي رقم سري تريده
if (!isset($_GET['secret']) || $_GET['secret'] !== $secret_key) {
    http_response_code(403);
    die("<h2 style='color:red; text-align:center;'>Access Denied / غير مصرح لك. يرجى كتابة الرمز السري في الرابط.</h2>");
}

// 2. الاتصال بقاعدة بيانات TiDB (MySQL)
require_once __DIR__ . '/nalsh-user-admin-name.php';
if (!isset($pdo) || !$pdo) {
    die("فشل الاتصال بقاعدة بيانات TiDB.");
}

// 3. دالة الاتصال المباشر بـ Cloudflare D1 (منسوخة من نظامك)
function d1_request_cleaner($sql, $params = []) {
    $account_id  = getenv('CLOUDFLARE_ACCOUNT_ID') ?: $_ENV['CLOUDFLARE_ACCOUNT_ID'] ?? '';
    $database_id = getenv('CLOUDFLARE_DATABASE_ID') ?: $_ENV['CLOUDFLARE_DATABASE_ID'] ?? '';
    $api_token   = getenv('CLOUDFLARE_API_TOKEN') ?: $_ENV['CLOUDFLARE_API_TOKEN'] ?? '';
    
    if (empty($account_id) || empty($database_id) || empty($api_token)) {
        return "بيانات Cloudflare D1 مفقودة في متغيرات البيئة.";
    }
    
    $url = "https://api.cloudflare.com/client/v4/accounts/" . trim($account_id) . "/d1/database/" . trim($database_id) . "/query";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $payload = json_encode(['sql' => $sql, 'params' => $params], JSON_UNESCAPED_UNICODE);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($api_token)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if (isset($result['success']) && $result['success']) {
        return true;
    }
    return "خطأ D1: " . ($result['errors'][0]['message'] ?? 'غير معروف');
}

echo "<div style='font-family: tahoma; direction: rtl; padding: 20px;'>";
echo "<h2>🧹 جاري تنظيف قواعد البيانات...</h2><hr>";

// =======================================================
// القسم الأول: تنظيف TiDB (البيانات المحلية)
// =======================================================
echo "<h3>1. تنظيف جداول TiDB (MySQL):</h3>";

// إيقاف فحص المفاتيح الأجنبية مؤقتاً لتجنب أخطاء الحذف
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// قائمة الجداول المراد تفريغها (تم استثناء users و settings للحفاظ على حسابك وإعداداتك)
$tidb_tables = [
    'customers', 
    'products', 
    'merchant_listings', 
    'categories',
    'orders', 
    'order_items', 
    'live_tickets', 
    'orders_archive',
    'sales_log', 
    'expenses', 
    'user_cart', 
    'user_favorites',
    'api_requests', 
    'trusted_devices', 
    'idempotency_keys',
    'rate_limits', 
    'sms_queue', 
    'auth_tokens', 
    'merchant_agent_links'
    
    // إذا أردت مسح المستخدمين والتجار (بما فيهم حسابك) أزل علامة // من السطرين بالأسفل
    // 'users',
    // 'settings'
];

foreach ($tidb_tables as $table) {
    try {
        // نستخدم TRUNCATE لأنه يحذف البيانات ويُصفر عداد الـ ID إلى 1
        $pdo->exec("TRUNCATE TABLE `$table`");
        echo "<p style='color: green;'>✅ تم تفريغ الجدول: <b>$table</b> بنجاح.</p>";
    } catch (Exception $e) {
        // في حال كان الجدول غير موجود (لم يتم إنشاؤه بعد) نتجاهل الخطأ
        echo "<p style='color: orange;'>⚠️ لم يتم تفريغ الجدول: <b>$table</b> (قد يكون غير موجود أو فارغاً).</p>";
    }
}

// إعادة تشغيل فحص المفاتيح الأجنبية
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// =======================================================
// القسم الثاني: تنظيف Cloudflare D1 (البيانات السحابية)
// =======================================================
echo "<hr><h3>2. تنظيف جداول Cloudflare D1:</h3>";

// D1 (SQLite) لا يدعم TRUNCATE، لذلك نستخدم DELETE ثم نصفّر عداد الـ ID
$d1_tables = ['products', 'sales_log'];

foreach ($d1_tables as $table) {
    $res = d1_request_cleaner("DELETE FROM $table");
    if ($res === true) {
        // تصفير عداد الـ ID التلقائي في SQLite
        d1_request_cleaner("DELETE FROM sqlite_sequence WHERE name='$table'");
        echo "<p style='color: green;'>✅ تم تفريغ جدول D1: <b>$table</b> بنجاح.</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ خطأ في جدول D1 (<b>$table</b>): $res</p>";
    }
}

echo "<hr><h2 style='color: blue;'>🎉 اكتملت عملية تنظيف قواعد البيانات! النظام الآن نظيف تماماً.</h2>";
echo "<p style='color: red;'><b>ملاحظة هامة:</b> يُنصح بحذف هذا الملف (`clean_db.php`) من الاستضافة بعد الانتهاء لضمان أمان النظام.</p>";
echo "</div>";
?>
