<?php
// ملف فحص الاتصال والأخطاء الشامل
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$report = [
    'php_version' => PHP_VERSION,
    'pdo_mysql_installed' => extension_loaded('pdo_mysql'),
    'config_file_found' => false,
    'db_connection' => 'Not Tested',
    'error' => null
];

// 1. فحص وجود ملف الإعدادات
$config_path = __DIR__ . '/../includes/nalsh-user-admin-name.php';
if (file_exists($config_path)) {
    $report['config_file_found'] = true;
    try {
        // محاولة تضمين الملف
        require_once $config_path;
        
        // 2. فحص هل متغير $pdo تم تعريفه؟
        if (isset($pdo)) {
            if ($pdo instanceof PDO) {
                // 3. محاولة إجراء استعلام بسيط
                $stmt = $pdo->query("SELECT 1");
                if ($stmt) {
                    $report['db_connection'] = 'Success! Connected to ' . DB_NAME;
                }
            } else {
                $report['db_connection'] = 'Failed: $pdo is not a valid PDO object. Check your config file.';
            }
        } else {
            $report['db_connection'] = 'Failed: $pdo variable is not defined. Check if config file has errors.';
        }
    } catch (Exception $e) {
        $report['db_connection'] = 'Failed';
        $report['error'] = $e->getMessage();
    }
} else {
    $report['error'] = "Config file NOT FOUND at: " . $config_path;
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
