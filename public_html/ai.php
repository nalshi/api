<?php
// =======================================================
// ملف فحص رادار التنبيهات (Nalsh Debugger)
// يقوم بفحص الاتصال بـ Firebase وتجربة الإرسال والاستقبال
// =======================================================
require_once 'nalsh-user-admin-name.php';

// منع التخزين المؤقت
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// 1. فحص الثوابت
$errors = [];
if (!defined('FIREBASE_URL')) $errors[] = "FIREBASE_URL غير معرف في ملف الإعدادات.";
if (!defined('FIREBASE_SECRET')) $errors[] = "FIREBASE_SECRET غير معرف في ملف الإعدادات.";

// 2. دالة تجربة الإرسال
function testFirebasePush($id) {
    $url = FIREBASE_URL . "merchant_signals/" . $id . ".json?auth=" . FIREBASE_SECRET;
    $data = ["test_signal" => ["time" => time(), "msg" => "فحص يدوي"]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    return ['res' => $response, 'err' => $error];
}

$test_res = null;
if (isset($_GET['send_test'])) {
    $test_res = testFirebasePush(999); // نستخدم ID وهمي للفحص
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاحص رادار التنبيهات 🛠️</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f0f2f5; padding: 20px; color: #333; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .log { background: #000; color: #0f0; padding: 15px; border-radius: 8px; font-family: monospace; height: 200px; overflow-y: auto; direction: ltr; text-align: left; }
        button { padding: 10px 20px; cursor: pointer; background: #4f46e5; color: white; border: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>

    <h1>🛠️ نظام فحص التنبيهات اللحظية</h1>

    <!-- المرحلة 1: فحص الملفات -->
    <div class="card">
        <h3>1. فحص الإعدادات (Backend)</h3>
        <?php if (empty($errors)): ?>
            <p class="success">✅ ملف الإعدادات سليم وروابط Firebase معرفة.</p>
            <p>الرابط: <code><?php echo FIREBASE_URL; ?></code></p>
        <?php else: ?>
            <?php foreach($errors as $e) echo "<p class='error'>❌ $e</p>"; ?>
        <?php endif; ?>
    </div>

    <!-- المرحلة 2: تجربة الإرسال -->
    <div class="card">
        <h3>2. تجربة الإرسال من السيرفر إلى Firebase</h3>
        <button onclick="window.location.href='?send_test=1'">إرسال إشارة فحص الآن</button>
        <?php if ($test_res): ?>
            <p>الرد من Firebase: <code><?php echo $test_res['res'] ?: 'لا يوجد رد'; ?></code></p>
            <?php if ($test_res['err']) echo "<p class='error'>CURL Error: ".$test_res['err']."</p>"; ?>
            <?php if ($test_res['res'] && strpos($test_res['res'], 'error') === false): ?>
                <p class="success">✅ الإرسال من السيرفر يعمل بنجاح!</p>
            <?php else: ?>
                <p class="error">❌ فشل الإرسال، تأكد من "قواعد الحماية" (Rules) في Firebase.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- المرحلة 3: تجربة الاستقبال (الرادار) -->
    <div class="card">
        <h3>3. تجربة الاستقبال في المتصفح (Frontend Radar)</h3>
        <p>سيقوم هذا المربع بعرض الإشارات التي تصل من Firebase الآن:</p>
        <div id="console-log" class="log">جاري بدء الرادار...</div>
        <p id="notif-status">فحص صلاحية الإشعارات: جاري الفحص...</p>
        <button onclick="testSound()">تجربة صوت الجرس</button>
    </div>

    <script>
        const logDiv = document.getElementById('console-log');
        const notifStatus = document.getElementById('notif-status');
        const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

        function addLog(msg) {
            logDiv.innerHTML += `> ${msg}<br>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // فحص الإشعارات
        if (!("Notification" in window)) {
            notifStatus.innerHTML = "❌ المتصفح لا يدعم الإشعارات.";
        } else {
            notifStatus.innerHTML = `حالة الإشعارات الحالية: <b>${Notification.permission}</b>`;
            if (Notification.permission !== "granted") {
                addLog("طلب إذن الإشعارات...");
                Notification.requestPermission();
            }
        }

        function testSound() {
            addLog("محاولة تشغيل الصوت...");
            audio.play().then(() => addLog("✅ الصوت يعمل!")).catch(e => addLog("❌ حظر المتصفح الصوت! انقر على الصفحة أولاً."));
        }

        // الاتصال بـ Firebase
        const fbUrl = "<?php echo FIREBASE_URL; ?>merchant_signals/999.json";
        addLog("📡 جاري الاتصال برادار Firebase على المسار 999...");

        try {
            const es = new EventSource(fbUrl);
            es.addEventListener('put', function(e) {
                const data = JSON.parse(e.data);
                addLog("📥 إشارة مستلمة: " + JSON.stringify(data));
                if (data.data) {
                    addLog("🎉 رائع! المتصفح استقبل الإشارة بنجاح.");
                    audio.play().catch(() => {});
                }
            });
            es.onerror = function() { addLog("⚠️ خطأ في الاتصال (قد يكون بسبب الـ Rules أو الرابط)."); };
        } catch (e) {
            addLog("❌ فشل تشغيل EventSource: " + e.message);
        }
    </script>
</body>
</html>
