<?php
// ====================================================================
// أداة الفحص الشاملة للاتصال بـ Cloudflare KV
// المسار: htdocs/public_html/test-kv.php
// ====================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ====================================================================
// ⚙️ 1. الإعدادات (قم بوضع المفتاح السري هنا يدوياً للفحص)
// ====================================================================
$worker_url = getenv('WORKER_CDN_URL') ?: 'https://ny.nasermsasalsh.workers.dev';
$worker_secret = getenv('WORKER_SECRET') ?: 'Naser_KV_Secure_998877_XyZ'; // 👈 ضع الرقم السري هنا بين علامتي التنصيص إذا كان المتغير لا يعمل

// بيانات تجريبية للإرسال
$test_path = 'stores/test_merchant_999/products';
$test_data = [
    "test_item_1" => [
        "id" => "test_1",
        "name" => "منتج فحص من السيرفر",
        "price" => 1000,
        "updated_at" => time()
    ]
];

// ====================================================================
// 🎨 تصميم واجهة الفحص
// ====================================================================
echo '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>KV Connection Tester</title>';
echo '<style>
    body { font-family: Tahoma, Arial; background: #f4f7f6; padding: 20px; color: #333; line-height: 1.6; }
    .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    h1 { color: #2c3e50; text-align: center; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    .log { padding: 15px; margin-bottom: 10px; border-radius: 5px; border-right: 5px solid #ccc; font-size: 14px; word-wrap: break-word; }
    .info { background: #e8f4f8; border-color: #3498db; }
    .success { background: #e8f8f5; border-color: #2ecc71; color: #1e8449; font-weight: bold;}
    .error { background: #fdeced; border-color: #e74c3c; color: #c0392b; font-weight: bold;}
    .warning { background: #fcf3cf; border-color: #f1c40f; color: #9c640c;}
    pre { background: #272822; color: #f8f8f2; padding: 10px; border-radius: 5px; overflow-x: auto; text-align: left; direction: ltr; }
</style></head><body><div class="container">';
echo '<h1>🔍 أداة فحص Cloudflare KV Worker</h1>';

function printLog($msg, $type = 'info') {
    echo "<div class='log {$type}'>{$msg}</div>";
}

// ====================================================================
// 🧪 بدء الفحوصات
// ====================================================================

// فحص 1: مكتبة cURL
if (!function_exists('curl_init')) {
    printLog("مكتبة cURL غير مفعلة في هذه الاستضافة! لا يمكن للسيرفر الاتصال بـ Cloudflare.", 'error');
    exit;
} else {
    printLog("مكتبة cURL مفعلة (النسخة: " . curl_version()['version'] . ").", 'success');
}

// فحص 2: المتغيرات
if (empty($worker_secret)) {
    printLog("⚠️ مفتاح WORKER_SECRET غير موجود أو فارغ! تأكد من وضعه في الكود أو في متغيرات البيئة. (الرفع سيفشل حتماً)", 'warning');
} else {
    printLog("تم العثور على مفتاح WORKER_SECRET (طوله: " . strlen($worker_secret) . " حرف).", 'success');
}
printLog("رابط الـ Worker المستخدم: <code>{$worker_url}</code>", 'info');


// ====================================================================
// 🚀 فحص 3: محاولة الرفع (PUT Request)
// ====================================================================
printLog("جاري محاولة إرسال طلب PUT إلى المسار: <code>/{$test_path}</code>...", 'info');

$url = rtrim($worker_url, '/') . '/' . $test_path;
$json_payload = json_encode($test_data, JSON_UNESCAPED_UNICODE);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // لتجاوز مشاكل الشهادات المجانية
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

// تسجيل الهيدرز المرسلة
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $worker_secret
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// تشغيل cURL
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

curl_close($ch);

// ====================================================================
// 📊 4. تحليل النتائج
// ====================================================================

echo "<h3>نتائج الاتصال:</h3>";
if ($curl_error) {
    printLog("فشل الاتصال بسب خطأ في السيرفر أو الشبكة (cURL Error):<br><code>{$curl_error}</code>", 'error');
} else {
    printLog("تم الاتصال بنجاح في {$total_time} ثانية.", 'success');
    
    echo "<b>كود الاستجابة (HTTP Code):</b> {$http_code}<br>";
    
    if ($http_code >= 200 && $http_code < 300) {
        printLog("✅ تم رفع البيانات بنجاح! كود الاستجابة: {$http_code}", 'success');
        echo "<b>رد السيرفر:</b> <pre>" . htmlspecialchars($response) . "</pre>";
    } 
    elseif ($http_code == 401 || $http_code == 403) {
        printLog("❌ تم رفض الطلب (Unauthorized/Forbidden). السبب المحتمل:", 'error');
        echo "<ul>
            <li>كلمة المرور (WORKER_SECRET) غير متطابقة بين كود PHP وكود الـ Worker.</li>
            <li>جدار حماية Cloudflare (WAF) يمنع الـ IP الخاص باستضافتك.</li>
            <li>كود الـ Worker مكتوب بطريقة ترفض الطلب.</li>
        </ul>";
        echo "<b>رد الـ Worker:</b> <pre>" . htmlspecialchars($response) . "</pre>";
    }
    elseif ($http_code == 405) {
        printLog("❌ تم رفض الطلب (Method Not Allowed). السبب المحتمل:", 'error');
        echo "<ul><li>الـ Worker لا يدعم استقبال طلبات PUT. تأكد من كود الـ Worker نفسه.</li></ul>";
        echo "<b>رد الـ Worker:</b> <pre>" . htmlspecialchars($response) . "</pre>";
    }
    else {
        printLog("❌ فشل الرفع. كود استجابة غير متوقع: {$http_code}", 'warning');
        echo "<b>رد الـ Worker:</b> <pre>" . htmlspecialchars($response) . "</pre>";
    }
}

// ====================================================================
// 🧹 فحص 4: محاولة قراءة البيانات (GET Request) للتأكد من الحفظ
// ====================================================================
if ($http_code >= 200 && $http_code < 300) {
    printLog("جاري محاولة قراءة البيانات للتأكد من حفظها فعلياً...", 'info');
    
    $ch_get = curl_init($url);
    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_get, CURLOPT_SSL_VERIFYPEER, false);
    $get_response = curl_exec($ch_get);
    $get_code = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
    curl_close($ch_get);

    if ($get_code == 200) {
        $decoded = json_decode($get_response, true);
        if (isset($decoded['test_item_1'])) {
            printLog("✅ تم القراءة بنجاح! البيانات موجودة في KV السحابي.", 'success');
        } else {
            printLog("⚠️ تم جلب الملف لكن لا يحتوي على البيانات التي أرسلناها للتو! قد يكون هناك تأخير في الـ KV.", 'warning');
        }
    } else {
        printLog("❌ فشل قراءة البيانات. كود الاستجابة: {$get_code}", 'error');
    }
}

echo "</div></body></html>";
?>
