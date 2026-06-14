<?php
// إظهار الأخطاء للمساعدة في التتبع
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: Arial, sans-serif; direction: rtl; padding: 20px;'>";
echo "<h2>🚀 نظام فحص إشعارات Firebase (FCM)</h2>";

// 1. الاتصال بقاعدة البيانات
try {
    require_once __DIR__ . '/nalsh-user-admin-name.php';
    if (!isset($pdo) || !$pdo) {
        die("<p style='color:red;'>❌ فشل الاتصال بقاعدة البيانات. تأكد من ملف nalsh-user-admin-name.php</p></div>");
    }
    echo "<p style='color:green;'>✅ الاتصال بقاعدة البيانات ناجح.</p>";
} catch (Exception $e) {
    die("<p style='color:red;'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p></div>");
}

// 2. فحص متغيرات البيئة (Firebase Credentials من Render)
$env_json = getenv('FIREBASE_CREDENTIALS_JSON') ?: $_ENV['FIREBASE_CREDENTIALS_JSON'] ?? '';

if (empty($env_json)) {
    die("<div style='background:#ffebee; padding:15px; border-radius:5px;'>
         <h3 style='color:red;'>❌ خطأ: متغير البيئة FIREBASE_CREDENTIALS_JSON مفقود!</h3>
         <p>لم يتم العثور على بيانات Firebase في إعدادات Render (Environment Variables).</p>
         <p>يرجى نسخ محتوى ملف الـ JSON الخاص بـ Service Account ولصقه كقيمة للمتغير في Render.</p>
         </div></div>");
}

$firebase_creds = json_decode($env_json, true);
if (!$firebase_creds || !isset($firebase_creds['project_id']) || !isset($firebase_creds['private_key'])) {
    die("<p style='color:red;'>❌ خطأ: محتوى FIREBASE_CREDENTIALS_JSON غير صالح كملف JSON. تأكد من نسخه بشكل صحيح دون أي نقص.</p></div>");
}
echo "<p style='color:green;'>✅ تم العثور على مفاتيح Firebase بنجاح (Project: {$firebase_creds['project_id']}).</p>";

// 3. دالة جلب توكن الوصول (Access Token) من جوجل
function getGoogleAccessToken($credentials) {
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $payload = json_encode([
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
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
        echo "<p style='color:red;'>خطأ في الاتصال بجوجل: " . curl_error($ch) . "</p>";
    }
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

echo "<h3>⏳ جاري توليد توكن الوصول من جوجل...</h3>";
$access_token = getGoogleAccessToken($firebase_creds);

if (!$access_token) {
    die("<p style='color:red;'>❌ فشل في الحصول على Access Token من جوجل. تأكد من أن الـ Private Key صحيح والـ Service Account فعال.</p></div>");
}
echo "<p style='color:green;'>✅ تم توليد توكن الوصول بنجاح!</p>";

// 4. جلب التجار والتوكنات من قاعدة البيانات
echo "<h3>🔍 جاري البحث عن توكنات التجار في قاعدة البيانات...</h3>";
$stmt = $pdo->prepare("SELECT id, store_name, username, fcm_token FROM users WHERE role = 'merchant' AND fcm_token IS NOT NULL AND fcm_token != ''");
$stmt->execute();
$merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($merchants) === 0) {
    die("<div style='background:#fff3e0; padding:15px; border-radius:5px;'>
         <h3 style='color:orange;'>⚠️ لا يوجد توكنات!</h3>
         <p>قاعدة البيانات لا تحتوي على أي تاجر يمتلك <code>fcm_token</code>.</p>
         <p>هذا يعني أن المشكلة في <b>الواجهة (Frontend)</b>، فهي لا تقوم بطلب صلاحية الإشعارات ولا ترسل التوكن إلى <code>api.php (save_fcm_token)</code>.</p>
         </div></div>");
}

echo "<p>تم العثور على <b>" . count($merchants) . "</b> تاجر يمتلك توكن.</p>";
echo "<hr>";

// 5. إرسال الإشعارات
$project_id = $firebase_creds['project_id'];
$fcm_url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

foreach ($merchants as $merchant) {
    echo "<h4>👤 التاجر: {$merchant['store_name']} (ID: {$merchant['id']})</h4>";
    echo "<p style='font-size:12px; color:gray;'>Token: " . substr($merchant['fcm_token'], 0, 30) . "...</p>";
    
    $payload = [
        'message' => [
            'token' => $merchant['fcm_token'],
            'notification' => [
                'title' => 'اختبار النظام 🚀',
                'body' => 'مرحباً ' . $merchant['store_name'] . '، إشعارات المتجر تعمل بنجاح!'
            ]
        ]
    ];

    $ch = curl_init($fcm_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        echo "<p style='color:green;'>✅ تم الإرسال بنجاح! (الكود: 200)</p>";
    } else {
        echo "<p style='color:red;'>❌ فشل الإرسال (الكود: $http_code)</p>";
        echo "<pre style='background:#f4f4f4; padding:10px; direction:ltr;'>" . htmlspecialchars($result) . "</pre>";
        
        $error_data = json_decode($result, true);
        if (isset($error_data['error']['details'][0]['errorCode']) && $error_data['error']['details'][0]['errorCode'] === 'UNREGISTERED') {
            echo "<p style='color:orange;'>⚠️ سبب الفشل: التوكن قديم أو غير مسجل (UNREGISTERED). التاجر قام بمسح بيانات المتصفح أو التطبيق. يجب عليه تسجيل الدخول مجدداً لتوليد توكن جديد.</p>";
            // تنظيف التوكن الميت
            $pdo->prepare("UPDATE users SET fcm_token = NULL WHERE id = ?")->execute([$merchant['id']]);
            echo "<p style='font-size:12px; color:gray;'>تم حذف التوكن التالف من قاعدة البيانات تلقائياً.</p>";
        }
    }
    echo "<hr>";
}

echo "</div>";
?>
