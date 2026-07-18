<?php
/**
 * =======================================================
 * 🔁 backfill_users_to_worker.php — تشغيل لمرة واحدة فقط
 * =======================================================
 * ينسخ كل بيانات التجار/المندوبين الحالية (settings, store_type, phone...)
 * من TiDB إلى D1، حتى لا يظهر لأي تاجر أن إعداداته "اختفت" بعد تفعيل
 * get_merchant_settings / save_merchant_settings على الـ Worker.
 *
 * طريقة التشغيل (من سطر الأوامر على السيرفر، وليس عبر المتصفح):
 *   php backfill_users_to_worker.php
 *
 * ⚠️ عدّل قسم الاتصال بقاعدة البيانات أدناه ليطابق نفس بيانات الاتصال
 * المستخدمة فعلياً في مشروعك (نفس ما يُستخدم لإنشاء $pdo في باقي الملفات).
 */

// ------------------- 1) الاتصال بـ TiDB (عدّل هذا القسم) -------------------
$DB_HOST = getenv('DB_HOST') ?: '';
$DB_NAME = getenv('DB_NAME') ?: '';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '4000';

$pdo = new PDO(
    "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
    $DB_USER,
    $DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ------------------- 2) إعدادات الـ Worker -------------------
$WORKER_URL    = getenv('WORKER_API_URL') ?: 'https://api.nasermsasalsh.workers.dev';
$INTERNAL_KEY  = getenv('INTERNAL_SYNC_KEY') ?: '';

if (empty($INTERNAL_KEY)) {
    fwrite(STDERR, "❌ INTERNAL_SYNC_KEY غير معرّف في متغيرات البيئة.\n");
    exit(1);
}

// ------------------- 3) جلب كل التجار والمندوبين -------------------
$stmt = $pdo->query(
    "SELECT id, username, role, store_name, phone, store_type, settings, fcm_token,
            UNIX_TIMESTAMP(created_at) as created_at
     FROM users WHERE role IN ('merchant', 'delivery')"
);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "🔎 عدد الحسابات المطلوب نقلها: " . count($users) . "\n";

$success = 0;
$failed = 0;

foreach ($users as $u) {
    $payload = [
        'action'     => 'sync_user',
        'id'         => (string)$u['id'],
        'username'   => $u['username'],
        'role'       => $u['role'],
        'store_name' => $u['store_name'],
        'phone'      => $u['phone'],
        'store_type' => $u['store_type'],
        'settings'   => $u['settings'],
        'fcm_token'  => $u['fcm_token'],
        'created_at' => $u['created_at'] ? ((int)$u['created_at'] * 1000) : null,
    ];

    $ch = curl_init(rtrim($WORKER_URL, '/'));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Internal-Key: ' . $INTERNAL_KEY],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $success++;
        echo "✅ {$u['username']} ({$u['role']})\n";
    } else {
        $failed++;
        echo "❌ فشل: {$u['username']} — HTTP {$code} — {$res}\n";
    }

    usleep(150000); // ⏱️ 150ms بين كل طلب حتى لا نُثقل على الـ Worker/D1
}

echo "\n=== النتيجة النهائية ===\n";
echo "نجح: $success | فشل: $failed\n";
