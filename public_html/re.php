<?php
// إعداداتك (ضعها هنا للتجربة)
$worker_url = "https://ny.nasermsasalah.workers.dev/stores/test_user/info";
$worker_secret = "Naser_KV_Secure_998877_XyZ"; // WORKER_SECRET

$data = ["message" => "Hello KV", "time" => time()];

$ch = curl_init($worker_url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $worker_secret
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h3>نتائج الفحص:</h3>";
echo "<b>كود الاستجابة (HTTP Code):</b> " . $http_code . "<br><br>";

if ($curl_error) {
    echo "<b>خطأ في الاتصال:</b> " . $curl_error;
} else {
    echo "<b>رد سيرفر Cloudflare:</b> <br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
?>
