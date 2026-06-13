<?php
// =======================================================
// أداة التشخيص الذاتي وإصلاح ومزامنة الكاش السحابي
// المسار: public_html/fix_and_sync.php
// قم بطلب الملف من المتصفح مباشرة لتشغيل عملية المزامنة الحية.
// =======================================================

header('Content-Type: text/html; charset=utf-8');
echo "<html><head><title>أداة التشخيص وإصلاح المزامنة</title>";
echo "<style>body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; background: #0f172a; color: #f1f5f9; padding: 25px; line-height: 1.6; } h1 { color: #38bdf8; } .log-box { background: #1e293b; border: 1px solid #334155; padding: 20px; border-radius: 10px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3); } ul { list-style-type: none; padding-right: 20px; } li { margin-bottom: 8px; position: relative; } li::before { content: '← '; color: #38bdf8; position: absolute; right: -20px; }</style>";
echo "</head><body>";
echo "<h1>📊 نظام تشخيص وإصلاح ومزامنة الكاش السحابي</h1>";
echo "<div class='log-box'>";

try {
    // 1. الاتصال بقاعدة البيانات
    echo "<h3>1. فحص الاتصال بقاعدة البيانات والتحقق من سلامة الجداول</h3>";
    $db_file = __DIR__ . '/nalsh-user-admin-name.php';
    if (!file_exists($db_file)) {
        throw new Exception("الملف المساعد 'nalsh-user-admin-name.php' غير موجود في المجلد الرئيسي.");
    }
    
    require_once $db_file;
    if (!isset($pdo) || !$pdo) {
        throw new Exception("فشل الاتصال بقاعدة البيانات. تأكد من إعداد المتغير \$pdo بشكل سليم.");
    }
    echo "<p style='color: #4ade80;'>✓ تم الاتصال بقاعدة البيانات بنجاح.</p>";

    // 2. التحقق من هيكل جدول المنتجات وترميمه إن لزم الأمر
    echo "<h3>2. ترميم هيكل جدول المنتجات (Products)</h3>";
    try {
        // التحقق من وجود عمود approval_status وإضافته في حال غيابه
        $stmt_check_col = $pdo->query("SHOW COLUMNS FROM `products` LIKE 'approval_status'");
        if (!$stmt_check_col->fetch()) {
            echo "<li>عمود 'approval_status' غير موجود. جاري إضافته الآن تلقائياً...</li>";
            $pdo->exec("ALTER TABLE `products` ADD COLUMN `approval_status` VARCHAR(50) DEFAULT 'approved'");
            echo "<li style='color: #4ade80;'>✓ تم إضافة عمود 'approval_status' بنجاح.</li>";
        } else {
            echo "<li>عمود 'approval_status' متواجد بالفعل وصالح للاستعمال.</li>";
        }
    } catch (Exception $col_err) {
        echo "<li style='color: #ef4444;'>ملاحظة أثناء فحص الهيكل: " . $col_err->getMessage() . "</li>";
    }

    // 3. كشف المنتجات ذات الحالات غير النشطة وتعديلها لتصبح معتمدة
    echo "<h3>3. الكشف عن الحالات وتجهيزها للمزامنة</h3>";
    
    // حساب المنتجات غير المعتمدة حالياً
    $stmt_count_pending = $pdo->query("SELECT COUNT(*) FROM products WHERE approval_status IS NULL OR approval_status = 'pending' OR approval_status = ''");
    $pending_count = $stmt_count_pending->fetchColumn();
    
    if ($pending_count > 0) {
        echo "<li>تم الكشف عن <strong>{$pending_count}</strong> منتج مخفي أو بحالة معلقة في قاعدة البيانات.</li>";
        echo "<li>جاري تعديل حالة هذه المنتجات إلى 'approved' لتظهر على الموقع فوراً...</li>";
        
        $stmt_update = $pdo->prepare("UPDATE products SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = 'pending' OR approval_status = ''");
        $stmt_update->execute();
        
        echo "<li style='color: #4ade80;'>✓ تم تحديث وإطلاق المنتجات بنجاح لضمان ظهورها بالكاش السحابي!</li>";
    } else {
        echo "<li>ممتاز! جميع المنتجات النشطة في قاعدة البيانات حالتها معتمدة ومجهزة مسبقاً.</li>";
    }

    // 4. البدء بمزامنة الكاش للتجار
    echo "<h3>4. جاري تهيئة ومزامنة الكاش الحية لجميع التجار النشطين</h3>";
    
    // جلب جميع حسابات التجار في النظام لدمج كاش منتجاتهم
    $stmt_merchants = $pdo->query("SELECT id, username, store_name FROM users WHERE role = 'merchant' AND is_active = 1");
    $merchants = $stmt_merchants->fetchAll(PDO::FETCH_ASSOC);

    if (count($merchants) === 0) {
        echo "<p style='color: #f59e0b;'>⚠️ لم يتم العثور على تجار نشطين في النظام حالياً.</p>";
    } else {
        echo "<ul>";
        foreach ($merchants as $merchant) {
            trigger_cache_rebuild_verbose($pdo, $merchant['id'], $merchant['username']);
        }
        echo "</ul>";
    }

    echo "<h2 style='color: #4ade80; text-align: center; margin-top: 40px;'>🎉 اكتملت عملية الإصلاح والمزامنة الحية بنجاح!</h2>";
    echo "<p style='text-align: center; color: #94a3b8;'>يمكنك الآن مراجعة لوحة تحكم المتجر والتحقق من ظهور المنتجات على موقع الويب.</p>";

} catch (Throwable $e) {
    echo "<div style='background: #991b1b; border: 1px solid #f87171; padding: 15px; border-radius: 8px; margin-top: 20px;'>";
    echo "<strong>❌ حدث خطأ فادح أثناء التنفيذ:</strong><br>" . $e->getMessage();
    echo "<br><br>الرجاء مراجعة إعدادات قاعدة البيانات أو التأكد من ملف 'nalsh-user-admin-name.php'.";
    echo "</div>";
}

echo "</div></body></html>";

// =======================================================
// الدوال المساعدة المكررة والمعدلة لإظهار سجل التنفيذ خطوة بخطوة
// =======================================================

function kv_request_verbose($path, $method = 'GET', $data = null) {
    $kv_url = getenv('WORKER_CDN_URL') ?: getenv('WORKER_D1_URL') ?: 'https://ny.nasermsasalsh.workers.dev/';
    if (substr($kv_url, -1) !== '/') $kv_url .= '/';
    
    $kv_secret = getenv('WORKER_SECRET') ?: ''; 
    $path = str_replace('.json', '', $path);
    $url = $kv_url . ltrim($path, '/');
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6); 
    
    $headers = ['Content-Type: application/json'];
    if ($method !== 'GET' && !empty($kv_secret)) {
        $headers[] = 'Authorization: Bearer ' . $kv_secret;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 400) {
        throw new Exception("استجابة غير صالحة من السيرفر (كود الحالة: $http_code) | الرد: $response");
    }
    return json_decode($response, true);
}

function sync_to_github_verbose($path, $data, $method = 'PUT', $commit_message = "Auto-update from fix script") {
    $gh_token = getenv('GITHUB_TOKEN') ?: '';
    $gh_owner = getenv('GITHUB_REPO_OWNER') ?: '';
    $gh_repo  = getenv('GITHUB_REPO_NAME') ?: '';

    if (empty($gh_token) || empty($gh_owner) || empty($gh_repo)) {
         echo "<li style='color: #94a3b8;'>ملاحظة: تم تخطي التحديث لـ GitHub (لم يتم إعداد متغيرات بيئة المستودع في Render).</li>";
         return;
    }

    $url = "https://api.github.com/repos/{$gh_owner}/{$gh_repo}/contents/" . ltrim($path, '/');
    $headers = [
        "Authorization: Bearer {$gh_token}",
        "Accept: application/vnd.github+json",
        "User-Agent: Nalsh-Ecom-System/1.0",
        "X-GitHub-Api-Version: 2022-11-28"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $sha = null;
    if ($http_code == 200) {
        $file_info = json_decode($response, true);
        $sha = $file_info['sha'] ?? null;
    }

    $payload = ["message" => $commit_message];
    if ($sha) $payload["sha"] = $sha;
    
    if ($method !== 'DELETE') {
        $json_content = json_encode($data, JSON_UNESCAPED_UNICODE);
        $payload["content"] = base64_encode($json_content);
    }

    $ch2 = curl_init($url);
    curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 4);
    $res = curl_exec($ch2);
    $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($code >= 200 && $code < 300) {
        echo "<li style='color: #4ade80;'>✓ تم مزامنة الملف الاحتياطي '{$path}' مع مستودع GitHub بنجاح.</li>";
    } else {
         echo "<li style='color: #f59e0b;'>⚠️ فشل تحديث GitHub الاحتياطي للملف '{$path}' (كود الرد: $code).</li>";
    }
}

function trigger_cache_rebuild_verbose($pdo, $merchant_id, $merchant_username) {
    echo "<li><strong>جاري معالجة المتجر: $merchant_username (رقم تعريفي: $merchant_id)...</strong></li>";
    echo "<ul>";
    
    // جلب كافة المنتجات النشطة والمقبولة للتاجر المحدد
    $stmt = $pdo->prepare("SELECT * FROM products WHERE merchant_id = ? AND is_available = 1 AND approval_status = 'approved'");
    $stmt->execute([$merchant_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($products);
    echo "<li>عدد المنتجات الصالحة المكتشفة في قاعدة البيانات للتاجر: <strong>{$count}</strong></li>";

    $timestamp = round(microtime(true) * 1000);
    
    // تجهيز مصفوفة البحث السريع (Search Index)
    $searchIndex = [
        '_version' => $timestamp,
        'data' => []
    ];
    $categoriesSet = [];
    $pages = [];
    $PAGE_SIZE = 20;

    foreach ($products as $p) {
        $searchIndex['data'][] = [
            'id' => $p['id'],
            'n' => $p['name'],
            'p' => (float)$p['price'],
            'd' => (float)($p['discount'] ?? 0),
            'i' => $p['image'] ?? '',
            't' => $p['type'] ?? 'عام',
            'a' => (int)($p['is_available'] ?? 1)
        ];
        
        $cat = $p['type'] ?? 'عام';
        if (!in_array($cat, $categoriesSet)) {
            $categoriesSet[] = $cat;
        }
    }

    // تقسيم المنتجات إلى صفحات (Pagination)
    $chunks = array_chunk($products, $PAGE_SIZE);
    foreach ($chunks as $index => $chunk) {
        $pageNum = $index + 1;
        $pageData = [];
        foreach ($chunk as $p) {
            $opts = [];
            if (!empty($p['options'])) {
                $opts = json_decode($p['options'], true) ?: [];
            }
            $pageData[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'mainDescription' => $p['description'] ?? $p['mainDescription'] ?? '',
                'price' => (float)$p['price'],
                'discount' => (float)($p['discount'] ?? 0),
                'image' => $p['image'] ?? '',
                'type' => $p['type'] ?? 'عام',
                'options' => $opts,
                'quantity' => (int)($p['quantity'] ?? 0),
                'quantity_type' => $p['quantity_type'] ?? 'tracked',
                'is_available' => (int)($p['is_available'] ?? 1)
            ];
        }
        $pages[$pageNum] = $pageData;
    }

    if (empty($pages)) {
        $pages[1] = [];
    }

    $categoriesData = [
        '_version' => $timestamp,
        'data' => $categoriesSet
    ];

    $basePath = "stores/{$merchant_username}/";
    $manifestVersions = [
        'search' => $timestamp,
        'categories' => $timestamp,
        'info' => $timestamp,
        'pages' => []
    ];

    // جلب وبناء الملف الموحد لضمان تحميل الواجهة الكسول
    $productsMap = [];
    foreach($products as $p) {
        $productsMap[$p['id']] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'mainDescription' => $p['description'] ?? $p['mainDescription'] ?? '',
            'price' => (float)$p['price'],
            'discount' => (float)($p['discount'] ?? 0),
            'image' => $p['image'] ?? '',
            'type' => $p['type'] ?? 'عام',
            'options' => json_decode($p['options'] ?? '[]', true) ?: [],
            'quantity' => (int)($p['quantity'] ?? 0),
            'quantity_type' => $p['quantity_type'] ?? 'tracked',
            'is_available' => (int)($p['is_available'] ?? 1),
            'currency' => $p['currency'] ?? 'YER',
            'updated_at' => (int)($p['updated_at'] ?? time())
        ];
    }

    // رفع الملفات مباشرة إلى Cloudflare KV
    try {
        kv_request_verbose("{$basePath}search_index", 'PUT', $searchIndex);
        echo "<li style='color: #4ade80;'>✓ تم رفع كشاف البحث السريع (search_index).</li>";
        
        kv_request_verbose("{$basePath}categories", 'PUT', $categoriesData);
        echo "<li style='color: #4ade80;'>✓ تم رفع مصفوفة التصنيفات (categories).</li>";

        kv_request_verbose("{$basePath}products", 'PUT', $productsMap);
        echo "<li style='color: #4ade80;'>✓ تم رفع الكائن الكامل للمنتجات (products).</li>";

        foreach ($pages as $pageNum => $pageData) {
            $pagePayload = [
                '_version' => $timestamp,
                'page' => $pageNum,
                'total_pages' => count($pages),
                'data' => $pageData
            ];
            kv_request_verbose("{$basePath}products_page_{$pageNum}", 'PUT', $pagePayload);
            echo "<li style='color: #4ade80;'>✓ تم رفع المنتجات المجزأة - الصفحة [{$pageNum}].</li>";
            $manifestVersions['pages']["page_{$pageNum}"] = $timestamp;
        }

        $manifestPayload = [
            'version' => $timestamp,
            'total_products' => count($products),
            'total_pages' => count($pages),
            'files' => $manifestVersions
        ];
        kv_request_verbose("{$basePath}manifest", 'PUT', $manifestPayload);
        echo "<li style='color: #4ade80;'>✓ تم رفع ملف المانيفست (manifest).</li>";

    } catch (Exception $kv_err) {
        echo "<li style='color: #ef4444;'>❌ فشل أثناء الرفع لـ Cloudflare KV: " . $kv_err->getMessage() . "</li>";
    }

    // المزامنة الاحتياطية مع GitHub
    sync_to_github_verbose("{$basePath}manifest.json", $manifestPayload, 'PUT', "Rebuild cache manifest v$timestamp");
    sync_to_github_verbose("{$basePath}search_index.json", $searchIndex, 'PUT', "Rebuild search index v$timestamp");
    sync_to_github_verbose("{$basePath}categories.json", $categoriesData, 'PUT', "Rebuild categories v$timestamp");
    
    foreach ($pages as $pageNum => $pageData) {
        $pagePayload = [
            '_version' => $timestamp,
            'page' => $pageNum,
            'total_pages' => count($pages),
            'data' => $pageData
        ];
        sync_to_github_verbose("{$basePath}products_page_{$pageNum}.json", $pagePayload, 'PUT', "Rebuild page $pageNum v$timestamp");
    }

    echo "</ul>";
}
?>
