<?php
// إظهار الأخطاء إن وجدت
ini_set('display_errors', 1);
error_reporting(E_ALL);

// تضمين ملف الاتصال بقاعدة البيانات (نفس الملف المستخدم في api.php)
require_once __DIR__ . '/nalsh-user-admin-name.php';

if (!isset($pdo)) {
    die("خطأ: لم يتم العثور على اتصال بقاعدة البيانات. تأكد من ملف nalsh-user-admin-name.php");
}

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>فحص توكن الإشعارات</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 20px; background: #f3f6f9; }
        .container { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: right; }
        th { background: #4f46e5; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .status-yes { color: #10b981; font-weight: bold; }
        .status-no { color: #ef4444; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 15px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px;}
    </style>
</head>
<body>
    <div class='container'>
        <h2>فحص توكن الإشعارات (FCM Token) في قاعدة البيانات</h2>
        <a href='api.php' class='btn'>العودة</a>
";

try {
    // جلب التجار والمناديب فقط
    $stmt = $pdo->query("SELECT id, username, store_name, role, fcm_token FROM users WHERE role IN ('merchant', 'delivery') ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>رقم الحساب (ID)</th><th>اسم المستخدم</th><th>المتجر</th><th>حالة التوكن</th><th>بداية التوكن (للتأكد)</th></tr>";

    foreach ($users as $user) {
        $has_token = !empty($user['fcm_token']);
        $status_class = $has_token ? 'status-yes' : 'status-no';
        $status_text = $has_token ? '✅ التوكن محفوظ' : '❌ الحقل فارغ';
        
        // إظهار أول 25 حرف فقط من التوكن لأسباب أمنية ولعدم تخريب شكل الجدول
        $preview = $has_token ? substr($user['fcm_token'], 0, 25) . '...' : 'لا يوجد توكن';

        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['store_name']}</td>";
        echo "<td class='{$status_class}'>{$status_text}</td>";
        echo "<td dir='ltr' style='text-align: left; font-family: monospace;'>{$preview}</td>";
        echo "</tr>";
    }
    
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color:red;'>خطأ في جلب البيانات: " . $e->getMessage() . "</p>";
}

echo "
    </div>
</body>
</html>";
?>
