here<?php
// ملف فحص الاتصال وتجاوز حماية CORS
header("Access-Control-Allow-Origin: *"); // السماح للجميع مؤقتاً للفحص
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN");
header('Content-Type: application/json; charset=utf-8');

// السماح بطلبات الفحص المسبقة (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// إرجاع استجابة ناجحة مع تفاصيل الاتصال
echo json_encode([
    "status" => "success",
    "message" => "✅ الاتصال بسيرفر Render يعمل بنجاح!",
    "your_origin" => $_SERVER['HTTP_ORIGIN'] ?? 'غير معروف',
    "php_version" => phpversion()
]);
?>
