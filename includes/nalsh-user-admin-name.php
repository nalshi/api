<?php
// =================================================================
// ملف الاتصال بقاعدة البيانات وإعدادات النظام الأساسية
// المسار: htdocs/includes/nalsh-user-admin-name.php
// تم التحديث ليتوافق مع قواعد بيانات Aiven.io (مع دعم SSL والبورت)
// =================================================================

// 1. بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// 2. إعدادات قاعدة البيانات الجديدة (Aiven)
define('DB_HOST', 'mysql-30bd897f-n770094456-7a25.c.aivencloud.com'); 
define('DB_PORT', '17311'); // البورت المخصص لـ Aiven
define('DB_USER', 'avnadmin');
define('DB_PASS', 'AVNS_YeyK2v-iju4-UxGfFDJ');
define('DB_NAME', 'defaultdb');

// 3. إعدادات التطبيق
define('UPLOAD_DIR', 'uploads/');
define('APP_SECRET_KEY', 'Nalsh_App_!@#$_Secret_Key_2026_778899');

// =====================================
// إعدادات التخزين السحابي (Firebase & ImgBB)
// =====================================
define('FIREBASE_URL', 'https://nalsh-store-default-rtdb.europe-west1.firebasedatabase.app/');
define('FIREBASE_SECRET', 'abWaY3WlYFKCeC72RKg8w8pU67IEGKvnrPGUyY0N');

define('IMGBB_KEYS', [
    'a534bbb07829f6aa214b55253ecea58d', 
    'a534bbb07829f6aa214b55253ecea58d'  
]);

// 4. إنشاء اتصال قاعدة البيانات
try {
    // تم إضافة الـ port في الـ DSN
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // إعداد ضروري جداً لـ Aiven للسماح بالاتصال المشفر (SSL)
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // في حالة الخطأ، نجعل المتغير فارغاً ليعالجه ملف index.php
    $pdo = null;
    error_log("Connection failed: " . $e->getMessage());
}

// 5. دوال مساعدة
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

// دالة تهيئة الجداول 
function initialize_database($pdo) {
    return true;
}
?>
