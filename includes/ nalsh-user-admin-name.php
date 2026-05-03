<?php
// =================================================================
// ملف الاتصال بقاعدة البيانات وإعدادات النظام الأساسية (نسخة TiDB Cloud)
// المسار: htdocs/includes/nalsh-user-admin-name.php
// =================================================================

// 1. بدء الجلسة بأمان
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// 2. إعدادات قاعدة البيانات TiDB Cloud (البيانات الجديدة)
define('DB_HOST', 'gateway01.eu-central-1.prod.aws.tidbcloud.com'); 
define('DB_PORT', '4000'); 
define('DB_USER', '4WSCPbQrZ9Fd23S.root');
define('DB_PASS', '974rEXwOuyX4n5I11'); // <--- كلمة المرور الجديدة التي ظهرت لك
define('DB_NAME', 'github_sample');

// 3. إعدادات التطبيق والمفاتيح السرية
define('UPLOAD_DIR', 'uploads/');
define('APP_SECRET_KEY', 'Nalsh_App_!@#$_Secret_Key_2026_778899');

// =====================================
// إعدادات التخزين السحابي (Firebase & ImgBB)
// =====================================
define('FIREBASE_URL', 'https://nalsh-store-default-rtdb.europe-west1.firebasedatabase.app/');
define('FIREBASE_SECRET', 'abWaY3WlYFKCeC72RKg8w8pU67IEGKvnrPGUyY0N');

// =====================================
// إعدادات إرسال الرسائل SMS (Macrodroid)
// =====================================
define('MACRO_DEVICE_ID', '0d8f9740-a59a-4828-97a3-65cf42aaae9e');
define('MACRO_WEBHOOK_NAME', 'send_otp');

// =====================================
// إعدادات GitHub CDN (للتخزين السريع والمجاني)
// =====================================
define('GITHUB_TOKEN', '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'); 
define('GITHUB_OWNER', 'nalshi'); 
define('GITHUB_REPO', 'Nynn'); 

// مفاتيح ImgBB لتوزيع الحمل
define('IMGBB_KEYS', [
    'a534bbb07829f6aa214b55253ecea58d',
    'a534bbb07829f6aa214b55253ecea58d' 
]);

// 4. إنشاء اتصال قاعدة البيانات باستخدام PDO
try {
    // بناء نص الاتصال مع إضافة المنفذ
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
 $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        // إجبار الاتصال على استخدام SSL (مطلوب لـ TiDB Cloud على أنظمة Linux/Render)
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
    ];
    
    // ✅ تم التأكد من وجود الفاصلة هنا
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // في حالة الخطأ، يتم تسجيله ومنع تعليق الموقع
    $pdo = null;
    error_log("Connection failed: " . $e->getMessage());
}

// 5. دوال مساعدة للنظام
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

function initialize_database($pdo) {
    if (!$pdo) return false;
    return true;
}
?>
