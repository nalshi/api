<?php
// إظهار الأخطاء
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

// ⚠️ 1. ضع التوكن الذي نسخته من الأداة الأولى هنا
$device_token = "ej6CbVQule5Ls_96aavfr7:APA91bFVOMf2Djwcj7YgMejNaQixWw3anuG_HHEJN9ILVx7zTEpuURAuNK6MdM-uuFA51RnOuh85PAzR_AhA2BYxBd0q8LbChejXF7rIo4AAeL6lrkp7MPg";

echo "=== فحص إرسال الإشعارات عبر Firebase V1 API ===\n\n";

// ⚠️ 2. قراءة ملف الاعتماد (Service Account JSON)
// تأكد أن المسار صحيح لملف firebase-credentials.json في سيرفرك
$key_path = __DIR__ . '/firebase-credentials.json';

if (!file_exists($key_path)) {
    // حاول قراءته من متغيرات البيئة إذا كنت تستخدمها في Render
    $env_json = getenv('FIREBASE_CREDENTIALS_JSON');
    if (empty($env_json)) {
        die("❌ خطأ: ملف firebase-credentials.json غير موجود، ولم يتم العثور على المتغير في البيئة.\n");
    }
    $key_data = json_decode($env_json, true);
} else {
    $key_data = json_decode(file_get_contents($key_path), true);
}

if (!$key_data || !isset($key_data['project_id']) || !isset($key_data['private_key'])) {
    die("❌ خطأ: محتوى ملف الاعتمادات غير صالح (تأكد من أنه ملف JSON صحيح من جوجل).\n");
}

$project_id = $key_data['project_id'];
echo "✅ تم العثور على ملف الاعتمادات لمشروع: " . $project_id . "\n";

// 3. دالة توليد Access Token
function get_test_access_token($key_data) {
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $key_data['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $key_data['private_key'], OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $response = curl_exec($ch);
    if(curl_errno($ch)){
        die("❌ خطأ في الاتصال بجوجل للحصول على توكن: " . curl_error($ch) . "\n");
    }
    curl_close($ch);
    return json_decode($response, true);
}

echo "جاري طلب Access Token من جوجل...\n";
$token_response = get_test_access_token($key_data);

if (!isset($token_response['access_token'])) {
    die("❌ خطأ: لم يتمكن السيرفر من الحصول على Access Token.\nرد جوجل:\n" . print_r($token_response, true));
}

$access_token = $token_response['access_token'];
echo "✅ تم الحصول على Access Token بنجاح!\n\n";

// 4. إرسال الإشعار
$payload = [
    'message' => [
        'token' => $device_token,
        'data' => [
            'action' => 'new_order',
            'order_id' => '12345',
            'title' => 'اختبار إشعار من السيرفر! 🚀',
            'body' => 'إذا وصلك هذا، فالسيرفر يعمل 100%'
        ]
    ]
];

echo "جاري إرسال الإشعار إلى الهاتف...\n";
$ch = curl_init('https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "=== النتيجة ===\n";
echo "كود الاستجابة: " . $http_code . "\n";
echo "رد سيرفر جوجل:\n";
echo $response . "\n";

if ($http_code == 200) {
    echo "\n✅✅ تم إرسال الإشعار بنجاح! تفقد هاتفك/متصفحك.";
} else {
    echo "\n❌❌ فشل إرسال الإشعار! اقرأ رسالة الخطأ أعلاه لمعرفة السبب.";
}
?>