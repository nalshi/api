<?php
// ========================================================================
// ملف الاتصال وإعدادات البيئة (النسخة الآمنة المخصصة لـ Render)
// يمنع تخزين كلمات المرور هنا، يتم سحبها من Environment Variables
// ========================================================================

// 1. حماية الملف من الدخول المباشر عبر المتصفح
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access Denied']));
}

// 2. بدء الجلسة (Session) بشكل آمن إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // تفعيل الكوكيز الآمنة لأن Render يستخدم HTTPS دائماً
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// ========================================================================
// 3. سحب المتغيرات من منصة Render (Environment Variables)
// ========================================================================

// دالة مساعدة لجلب المتغيرات مع قيمة افتراضية في حال عدم وجودها
function get_env_value($key, $default = '') {
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

// --- إعدادات قاعدة البيانات ---
define('DB_HOST', get_env_value('DB_HOST'));
define('DB_NAME', get_env_value('DB_NAME'));
define('DB_USER', get_env_value('DB_USER'));
define('DB_PASS', get_env_value('DB_PASS'));
define('DB_PORT', get_env_value('DB_PORT', '3306')); // 3306 هو المنفذ الافتراضي

// --- مفاتيح التشفير والتطبيقات الخارجية ---
define('APP_SECRET_KEY', get_env_value('APP_SECRET_KEY', 'nalsh_fallback_secret_9988'));
define('FIREBASE_URL', get_env_value('FIREBASE_URL'));
define('FIREBASE_SECRET', get_env_value('FIREBASE_SECRET'));
define('GITHUB_OWNER', get_env_value('GITHUB_OWNER'));
define('GITHUB_REPO', get_env_value('GITHUB_REPO'));
define('GITHUB_TOKEN', get_env_value('GITHUB_TOKEN'));

// --- متغيرات الـ MacroDroid (كـ Global لأن api.php يطلبها هكذا) ---
global $MACRO_DEVICE_ID, $MACRO_WEBHOOK_NAME;
$MACRO_DEVICE_ID = get_env_value('MACRO_DEVICE_ID');
$MACRO_WEBHOOK_NAME = get_env_value('MACRO_WEBHOOK_NAME');

// --- معالجة مفاتيح رفع الصور (ImgBB) ---
// في ملف api.php أنت تستخدم: IMGBB_KEYS[array_rand(IMGBB_KEYS)]
// هذا يعني أنها يجب أن تكون مصفوفة. الكود التالي يحول النص القادم من Render إلى مصفوفة.
$imgbb_string = get_env_value('IMGBB_KEYS');
$imgbb_array = array_filter(array_map('trim', explode(',', $imgbb_string)));
if (empty($imgbb_array)) {
    $imgbb_array = ['dummy_key']; // مفتاح وهمي لمنع خطأ برمجية إذا نسيته
}
define('IMGBB_KEYS', $imgbb_array);


// ========================================================================
// 4. إنشاء الاتصال الآمن بقاعدة البيانات (PDO)
// ========================================================================
global $pdo;

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // إظهار الأخطاء كاستثناءات (مهم للتتبع)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // إرجاع البيانات كمصفوفة
        PDO::ATTR_EMULATE_PREPARES   => false,                  // حماية حقيقية ضد الـ SQL Injection
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci" // دعم كامل للغة العربية والإيموجي
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // تسجيل الخطأ الفعلي في سجلات السيرفر (Logs) لمراجعته من قبلك
    error_log("Database Connection Error: " . $e->getMessage());
    
    // إرسال رسالة نظيفة للمستخدم (بدون كشف كلمات المرور أو الأخطاء)
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode([
        'status' => 'error', 
        'message' => 'تعذر الاتصال بقاعدة البيانات. يرجى التأكد من إعدادات المتغيرات في منصة Render.'
    ]));
}
// ========================================================================
// نهاية الملف
// ========================================================================
?>
