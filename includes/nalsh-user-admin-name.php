<?php
// =================================================================
// ملف الاتصال بقاعدة البيانات وإعدادات النظام الأساسية
// المسار: htdocs/includes/nalsh-user-admin-name.php
// =================================================================

// 1. بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// =====================================
// 2. إعدادات قاعدة البيانات (TiDB Cloud) 
// بناءً على الصور المرفقة
// =====================================
define('DB_HOST', 'gateway01.eu-central-1.prod.aws.tidbcloud.com'); 
define('DB_PORT', '4000'); // منفذ TiDB
define('DB_USER', '3XpgEyD6kxAu7sW.root');
define('DB_PASS', 'MjuiPjY0jfG6sryz');
// ملاحظة: في الصورة اسم القاعدة sys، إذا قمت بإنشاء قاعدة باسم آخر (مثل store) قم بتغييره هنا:
define('DB_NAME', 'sys'); 

// =====================================
// 3. إعدادات التطبيق الأساسية
// =====================================
define('UPLOAD_DIR', 'uploads/');
define('APP_SECRET_KEY', 'Nalsh_App_!@#$_Secret_Key_2026_778899');

// =====================================
// إعدادات التخزين السحابي (Firebase)
// =====================================
define('FIREBASE_URL', 'https://nalsh-store-default-rtdb.europe-west1.firebasedatabase.app/');
define('FIREBASE_SECRET', 'abWaY3WlYFKCeC72RKg8w8pU67IEGKvnrPGUyY0N');

// =====================================
// إعدادات إرسال الرسائل SMS (Macrodroid)
// =====================================
define('MACRO_DEVICE_ID', '0d8f9740-a59a-4828-97a3-65cf42aaae9e');
define('MACRO_WEBHOOK_NAME', 'send_otp');

// =====================================
// إعدادات GitHub CDN (للتخزين السريع)
// =====================================
define('GITHUB_TOKEN', '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'); 
define('GITHUB_OWNER', 'nalshi'); 
define('GITHUB_REPO', 'Nynn'); 

// =====================================
// إعدادات ImgBB (رفع الصور)
// =====================================
define('IMGBB_KEYS', [
    'a534bbb07829f6aa214b55253ecea58d', 
    'a534bbb07829f6aa214b55253ecea58d'  
]);

// =====================================
// 4. إنشاء اتصال قاعدة البيانات (PDO)
// =====================================
try {
    // إضافة المنفذ (port) ضروري جداً لـ TiDB
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // إعدادات الاتصال الآمن (SSL) الإجبارية لـ TiDB Cloud
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // تخطي التحقق من الشهادة المحلية مؤقتاً لتجنب الأخطاء
    ];
    
    // إذا قمت بتحميل شهادة الـ CA كما هو مطلوب في TiDB، يمكنك تفعيل السطر التالي (بعد رفع ملف الشهادة لمجلدك):
    // $options[PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/isrgrootx1.pem';
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // في حالة الخطأ، نجعل المتغير فارغاً ليعالجه ملف index.php
    $pdo = null;
    error_log("Connection failed: " . $e->getMessage());
    
    // يمكنك تفعيل السطر التالي مؤقتاً لاكتشاف أخطاء الاتصال إذا لم يعمل الموقع:
    // die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// =====================================
// 5. دوال مساعدة
// =====================================
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

// دالة تهيئة الجداول (موجودة ولكن لا يتم استدعاؤها تلقائياً لتسريع الموقع)
function initialize_database($pdo) {
    // تم إيقاف التنفيذ التلقائي لأن الجداول موجودة بالفعل (كما ظهر في الفحص)
    return true;
}
?>