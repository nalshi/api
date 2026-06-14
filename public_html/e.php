<?php
require_once __DIR__ . '/nalsh-user-admin-name.php';

ini_set('display_errors', 0); // إخفاء التحذيرات المزعجة
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: Arial, sans-serif; direction: rtl; padding: 20px;'>";
echo "<h2>🚀 نظام فحص إشعارات Firebase (FCM)</h2>";

if (!isset($pdo) || !$pdo) {
    die("<p style='color:red;'>❌ فشل الاتصال بقاعدة البيانات.</p></div>");
}

$firebase_creds = null;
$env_json = getenv('FIREBASE_CREDENTIALS_JSON') ?: $_ENV['FIREBASE_CREDENTIALS_JSON'] ?? '';

if (!empty($env_json)) {
    $firebase_creds = json_decode($env_json, true) ?: json_decode(stripslashes($env_json), true);
}

if (!$firebase_creds || !isset($firebase_creds['project_id']) || !isset($firebase_creds['private_key'])) {
    die("<div style='background:#ffebee; padding:15px; border-radius:5px;'><h3 style='color:red;'>❌ خطأ: بيانات Firebase مفقودة أو غير صالحة!</h3></div></div>");
}

// 🌟 هذا هو السطر السحري الذي يحل مشكلة الـ Private Key من Render 🌟
$firebase_creds['private_key'] = str_replace('\\n', "\n", $firebase_creds['private_key']);

echo "<p style='color:green;'>✅ تم جلب بيانات فايربيس بنجاح (Project: {$firebase_creds['project_id']}).</p>";

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
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

echo "<h3>⏳ جاري توليد توكن الوصول من جوجل...</h3>";
$access_token = getGoogleAccessToken($firebase_creds);

if (!$access_token) {
    die("<p style='color:red;'>❌ فشل الحصول على Access Token من جوجل. تأكد من أن الـ Private Key صحيح.</p></div>");
}
echo "<p style='color:green;'>✅ تم توليد توكن الوصول (Google Auth Token) بنجاح!</p>";

// جلب التجار والتوكنات من قاعدة البيانات
$stmt = $pdo->prepare("SELECT id, store_name, username, fcm_token FROM users WHERE role = 'merchant' AND fcm_token IS NOT NULL AND fcm_token != ''");
$stmt->execute();
$merchants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($merchants) === 0) {
    die("<div style='background:#fff3e0; padding:15px; border-radius:5px;'><h3 style='color:orange;'>⚠️ لا يوجد توكنات أجهزة في قاعدة البيانات!</h3></div></div>");
}

echo "<hr>";
$project_id = $firebase_creds['project_id'];
$fcm_url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

foreach ($merchants as $merchant) {
    echo "<h4>👤 التاجر: {$merchant['store_name']}</h4>";
    
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
        echo "<p style='color:green; font-weight:bold;'>✅ تم الإرسال إلى هاتف التاجر بنجاح!</p>";
    } else {
        echo "<p style='color:red;'>❌ فشل الإرسال (الكود: $http_code)</p>";
        $error_data = json_decode($result, true);
        if (isset($error_data['error']['details'][0]['errorCode']) && $error_data['error']['details'][0]['errorCode'] === 'UNREGISTERED') {
            echo "<p style='color:orange;'>⚠️ التوكن تالف. التاجر يحتاج لتسجيل الدخول من جديد.</p>";
            $pdo->prepare("UPDATE users SET fcm_token = NULL WHERE id = ?")->execute([$merchant['id']]);
        }
    }
}
echo "</div>";
?>
