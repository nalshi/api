<?php
// ==========================================
// أداة فحص النظام الشاملة (Diagnostic Tool)
// ==========================================

// ⚠️ حماية الملف: لا يمكن فتحه إلا بإضافة ?key=12345 للرابط
if (!isset($_GET['key']) || $_GET['key'] !== '12345') {
    die("<h2 style='color:red; text-align:center; font-family:tahoma;'>غير مصرح بالدخول. استخدم الرابط الصحيح.</h2>");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html dir='rtl' lang='ar'><head><meta charset='UTF-8'><title>فحص النظام</title>";
echo "<style>body{font-family:'Segoe UI', Tahoma, Arial; background:#f4f7f6; padding:20px;} .card{background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.1); margin-bottom:20px;} .success{color:#155724; background:#d4edda; padding:10px; border-radius:5px; border:1px solid #c3e6cb;} .error{color:#721c24; background:#f8d7da; padding:10px; border-radius:5px; border:1px solid #f5c6cb;} .warning{color:#856404; background:#fff3cd; padding:10px; border-radius:5px; border:1px solid #ffeeba;} pre{background:#272822; color:#f8f8f2; padding:10px; border-radius:5px; direction:ltr; text-align:left; overflow-x:auto;}</style>";
echo "</head><body>";
echo "<h1 style='text-align:center; color:#333;'>🚀 أداة الفحص الشاملة للنظام</h1>";

function printResult($title, $isSuccess, $message, $details = '') {
    $class = $isSuccess ? 'success' : 'error';
    $icon = $isSuccess ? '✅' : '❌';
    echo "<div class='card'>";
    echo "<h3>$icon $title</h3>";
    echo "<div class='$class'>$message</div>";
    if (!empty($details)) echo "<pre>$details</pre>";
    echo "</div>";
}

// ---------------------------------------------------------
// 1. فحص المتغيرات البيئية (Render Variables)
// ---------------------------------------------------------
$env_keys = [
    'FIREBASE_DB_URL',
    'FIREBASE_DB_SECRET',
    'FIREBASE_CREDENTIALS_JSON',
    'CLOUDFLARE_ACCOUNT_ID',
    'CLOUDFLARE_DATABASE_ID',
    'CLOUDFLARE_API_TOKEN',
    'WORKER_D1_URL',
    'WORKER_SECRET'
];

$env_details = "";
$env_missing = false;
foreach ($env_keys as $key) {
    $val = getenv($key) ?: $_ENV[$key] ?? null;
    if (empty($val)) {
        $env_details .= "[$key] => ❌ مفقود أو فارغ!\n";
        $env_missing = true;
    } else {
        // إخفاء جزء من القيم السرية للأمان
        $masked = strlen($val) > 15 ? substr($val, 0, 5) . '****' . substr($val, -5) : '****';
        $env_details .= "[$key] => ✅ موجود ($masked)\n";
    }
}
printResult("فحص المتغيرات البيئية (Environment Variables)", !$env_missing, $env_missing ? "بعض المتغيرات الهامة مفقودة من السيرفر!" : "جميع المتغيرات الأساسية موجودة.", $env_details);


// ---------------------------------------------------------
// 2. فحص الاتصال بقاعدة البيانات MySQL
// ---------------------------------------------------------
$pdo = null;
if (file_exists('nalsh-user-admin-name.php')) {
    require_once 'nalsh-user-admin-name.php';
    if (isset($pdo)) {
        printResult("اتصال قاعدة البيانات", true, "تم الاتصال بقاعدة البيانات بنجاح.");
    } else {
        printResult("اتصال قاعدة البيانات", false, "ملف الاتصال موجود ولكن المتغير PDO غير معرف.");
    }
} else {
    printResult("اتصال قاعدة البيانات", false, "ملف nalsh-user-admin-name.php غير موجود بجوار هذا الملف!");
}


// ---------------------------------------------------------
// 3. فحص اتصال Firebase Realtime Database (كتابة وقراءة)
// ---------------------------------------------------------
$fb_url = rtrim(getenv('FIREBASE_DB_URL') ?: $_ENV['FIREBASE_DB_URL'] ?? 'https://shiban-a2757-default-rtdb.europe-west1.firebasedatabase.app/', '/');
$fb_secret = getenv('FIREBASE_DB_SECRET') ?: $_ENV['FIREBASE_DB_SECRET'] ?? '';

if (empty($fb_secret)) {
    printResult("اتصال Firebase", false, "لا يمكن الفحص: FIREBASE_DB_SECRET مفقود.");
} else {
    $test_url = "$fb_url/test_connection.json?auth=$fb_secret";
    
    // محاولة الكتابة
    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'ok', 'time' => time()]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        // حذف التجربة
        $ch2 = curl_init($test_url);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch2);
        curl_close($ch2);
        
        printResult("اتصال Firebase", true, "تمت الكتابة والقراءة من Firebase بنجاح.", "HTTP Code: $http_code\nResponse: $response");
    } else {
        printResult("اتصال Firebase", false, "فشل الاتصال بـ Firebase أو الرابط/الرقم السري خاطئ.", "HTTP Code: $http_code\nResponse: $response");
    }
}


// ---------------------------------------------------------
// 4. فحص إشعارات الهاتف FCM (Push Notifications)
// ---------------------------------------------------------
$fcm_json = getenv('FIREBASE_CREDENTIALS_JSON') ?: $_ENV['FIREBASE_CREDENTIALS_JSON'] ?? '';
if (empty($fcm_json) && file_exists('firebase-credentials.json')) {
    $fcm_json = file_get_contents('firebase-credentials.json');
}

if (empty($fcm_json)) {
    printResult("إشعارات الهاتف (FCM)", false, "ملف الصلاحيات JSON مفقود! الإشعارات لن تعمل.");
} else {
    $key_data = json_decode($fcm_json, true);
    if (!$key_data || !isset($key_data['private_key'])) {
        printResult("إشعارات الهاتف (FCM)", false, "محتوى JSON غير صالح أو لا يحتوي على Private Key.", $fcm_json);
    } else {
        // محاولة استخراج التوكن
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $key_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time()
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
        curl_close($ch);
        
        $token_data = json_decode($response, true);
        if (isset($token_data['access_token'])) {
            printResult("إشعارات الهاتف (FCM)", true, "تم استخراج Access Token بنجاح. نظام الإشعارات جاهز للعمل.", "Token Length: " . strlen($token_data['access_token']));
        } else {
            printResult("إشعارات الهاتف (FCM)", false, "فشل استخراج التوكن من جوجل.", $response);
        }
    }
}

// ---------------------------------------------------------
// 5. فحص بيانات تاجر محدد لمعرفة سبب عدم وصول الطلبات له
// ---------------------------------------------------------
echo "<div class='card'><h3>🔍 فحص مسار تاجر محدد</h3>";
echo "<form method='GET'><input type='hidden' name='key' value='12345'>";
echo "<input type='text' name='merchant' placeholder='أدخل اسم المستخدم للتاجر (Username)' required style='padding:10px; width:60%;'>";
echo "<button type='submit' style='padding:10px 20px; background:#007bff; color:#fff; border:none; cursor:pointer;'>افحص</button>";
echo "</form>";

if (isset($_GET['merchant']) && $pdo) {
    $m_user = trim($_GET['merchant']);
    $stmt = $pdo->prepare("SELECT id, username, fcm_token FROM users WHERE username = ?");
    $stmt->execute([$m_user]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($merchant) {
        $app_secret = defined('APP_SECRET_KEY') ? APP_SECRET_KEY : 'nalsh_fallback_secret_9988';
        $secure_hash = md5($merchant['id'] . $app_secret . 'orders');
        
        $details = "ID التاجر: " . $merchant['id'] . "\n";
        $details .= "اسم المستخدم: " . $merchant['username'] . "\n";
        $details .= "المسار السري للطلبات في فايربيس:\nsecure_active_orders/" . $secure_hash . "\n\n";
        
        if (empty($merchant['fcm_token'])) {
            $details .= "❌ هذا التاجر لم يسمح بالإشعارات في المتصفح/التطبيق (fcm_token فارغ).\n";
        } else {
            $details .= "✅ التاجر لديه توكن إشعارات (fcm_token موجود).\n";
        }
        
        printResult("نتيجة فحص التاجر ($m_user)", true, "تم العثور على التاجر.", $details);
        
        echo "<div class='warning'><b>💡 نصيحة للإصلاح:</b><br>افتح فايربيس (Firebase Console) وقم بتوسيع المسار:<br><code>stores/{$merchant['username']}/secure_active_orders/{$secure_hash}</code><br>إذا كانت الطلبات موجودة هنا ولكن لا تظهر في لوحة التاجر، فهذا يعني أن لوحة التاجر تبحث في مسار (Hash) مختلف! يجب توحيد <code>APP_SECRET_KEY</code>.</div>";

    } else {
        printResult("نتيجة فحص التاجر ($m_user)", false, "لم يتم العثور على التاجر في قاعدة البيانات.");
    }
}
echo "</div>";

echo "</body></html>";
?>
