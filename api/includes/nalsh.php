<?php
// منع الوصول المباشر للملف عبر المتصفح
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("HTTP/1.1 403 Forbidden");
    die("Access Denied."); // أو يمكنك إعادة توجيهه للصفحة الرئيسية: header("Location: /"); exit;
}

// ... باقي كود الإعدادات الخاص بك (DB_HOST, DB_USER, etc) ...
// إعدادات قاعدة البيانات TiDB Cloud
define('DB_HOST', 'gateway01.eu-central-1.prod.aws.tidbcloud.com'); 
define('DB_PORT', '4000'); 
define('DB_USER', '4WSCPbQrZ9Fd23S.root');
define('DB_PASS', 'BLeOeNOU6woQJXB1');
define('DB_NAME', 'github_sample');

define('UPLOAD_DIR', 'uploads/');
define('APP_SECRET_KEY', 'Nalsh_App_!@#$_Secret_Key_2026_778899');
define('FIREBASE_URL', 'https://nalsh-store-default-rtdb.europe-west1.firebasedatabase.app/');
define('FIREBASE_SECRET', 'abWaY3WlYFKCeC72RKg8w8pU67IEGKvnrPGUyY0N');
define('MACRO_DEVICE_ID', '0d8f9740-a59a-4828-97a3-65cf42aaae9e');
define('MACRO_WEBHOOK_NAME', 'send_otp');
define('GITHUB_TOKEN', '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'); 
define('GITHUB_OWNER', 'nalshi'); 
define('GITHUB_REPO', 'Nynn'); 
define('IMGBB_KEYS', ['a534bbb07829f6aa214b55253ecea58d']);

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt'
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    $pdo = null;
    error_log("DB Fail: " . $e->getMessage());
    die(json_encode(['status' => 'error', 'message' => 'خطأ اتصال']));
}
