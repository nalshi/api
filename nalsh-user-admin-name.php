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

// 2. إعدادات قاعدة البيانات (تم التأكد من صحتها عبر الاختبار)
define('DB_HOST', 'sql102.infinityfree.com'); 
define('DB_USER', 'if0_40357672');
define('DB_PASS', '3YLZzR439rfb9Fi');
define('DB_NAME', 'if0_40357672_store');

// 3. إعدادات التطبيق
// ... (بعد define('DB_NAME', ...))

// 4. إعدادات التطبيق
define('UPLOAD_DIR', 'uploads/');
// ⭐ جديد: إضافة مفتاح سري للتطبيق (لا تشاركه مع أحد)
define('APP_SECRET_KEY', 'Nalsh_App_!@#$_Secret_Key_2026_778899');
// =====================================
// إعدادات التخزين السحابي (Firebase & ImgBB)
// =====================================
// ضع هنا رابط قاعدة بيانات Firebase (Realtime Database)
define('FIREBASE_URL', 'https://nalsh-store-default-rtdb.europe-west1.firebasedatabase.app/');
// ضع هنا المفتاح السري لـ Firebase (Database Secret) لتخطي قواعد القراءة والكتابة
define('FIREBASE_SECRET', 'abWaY3WlYFKCeC72RKg8w8pU67IEGKvnrPGUyY0N');

// ضع هنا عدة مفاتيح ImgBB لتوزيع الحمل (أضف مفاتيح أكثر مستقبلاً)
define('IMGBB_KEYS', [
    'a534bbb07829f6aa214b55253ecea58d', // المفتاح الأول
    'a534bbb07829f6aa214b55253ecea58d'  // كرر أو ضع مفتاح آخر
]);
// ... (باقي الملف)

// 4. إنشاء اتصال قاعدة البيانات
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (PDOException $e) {
    // في حالة الخطأ، نجعل المتغير فارغاً ليعالجه ملف index.php
    $pdo = null;
    // للتصحيح فقط (يمكنك إزالة السطر التالي لاحقاً)
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

// دالة تهيئة الجداول (موجودة ولكن لا يتم استدعاؤها تلقائياً لتسريع الموقع)
function initialize_database($pdo) {
    // تم إيقاف التنفيذ التلقائي لأن الجداول موجودة بالفعل (كما ظهر في الفحص)
    return true;
}

