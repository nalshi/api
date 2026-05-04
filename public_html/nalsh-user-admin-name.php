<?php
// منع الوصول المباشر للملف
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("HTTP/1.1 403 Forbidden");
    die("Access Denied.");
}

// جلب الإعدادات من بيئة التشغيل (Render Environment Variables)
define('DB_HOST', getenv('DB_HOST')); 
define('DB_PORT', getenv('DB_PORT') ?: '4000'); 
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));

define('UPLOAD_DIR', 'uploads/');
define('APP_SECRET_KEY', getenv('APP_SECRET_KEY'));
define('FIREBASE_URL', getenv('FIREBASE_URL'));
define('FIREBASE_SECRET', getenv('FIREBASE_SECRET'));
define('MACRO_DEVICE_ID', getenv('MACRO_DEVICE_ID'));
define('MACRO_WEBHOOK_NAME', getenv('MACRO_WEBHOOK_NAME'));
define('GITHUB_TOKEN', getenv('GITHUB_TOKEN')); 
define('GITHUB_OWNER', getenv('GITHUB_OWNER')); 
define('GITHUB_REPO', getenv('GITHUB_REPO')); 

// بالنسبة للمصفوفات، نقوم بجلب النص وتحويله لمصفوفة
$imgbb_env = getenv('IMGBB_KEYS');
define('IMGBB_KEYS', $imgbb_env ? explode(',', $imgbb_env) : []);

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        // مسار شهادة SSL في Render غالباً ما يكون في هذا المسار الافتراضي للينكس
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt'
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    $pdo = null;
    error_log("DB Fail: " . $e->getMessage());
    // نصيحة: لا تظهر رسالة الخطأ الحقيقية للمستخدم في الإنتاج
    die(json_encode(['status' => 'error', 'message' => 'Internal Server Error']));
}
