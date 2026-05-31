<?php
// ========================================================================
// ملف الاتصال وإعدادات البيئة (النسخة المتطورة لدعم Cloudflare D1)
// المسار: htdocs/public_html/nalsh-user-admin-name.php
// ========================================================================

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Access Denied']));
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// ========================================================================
// 1. قارئ ملفات ENV
// ========================================================================
$env_files = [__DIR__ . '/.env', __DIR__ . '/api.env.txt'];
foreach ($env_files as $file) {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'"); 
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
    }
}

function get_env_value($key, $default = '') {
    $val = getenv($key);
    if ($val === false) $val = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    return $val !== false ? $val : $default;
}

// دالة ذكية للتعامل مع المتغيرات المقصوصة في منصة الاستضافة (Render)
function get_env_by_prefix($prefix, $default = '') {
    foreach ($_ENV as $key => $val) {
        if (strpos($key, $prefix) === 0) return $val;
    }
    foreach ($_SERVER as $key => $val) {
        if (strpos($key, $prefix) === 0) return $val;
    }
    if ($prefix === 'CLOUDFLARE_AC') {
        $val = get_env_value('CLOUDFLARE_ACCOUNT_ID');
        if ($val) return $val;
    }
    if ($prefix === 'CLOUDFLARE_AP') {
        $val = get_env_value('CLOUDFLARE_API_TOKEN');
        if ($val) return $val;
    }
    if ($prefix === 'CLOUDFLARE_DA') {
        $val = get_env_value('CLOUDFLARE_DATABASE_ID');
        if ($val) return $val;
    }
    return $default;
}

// ========================================================================
// 2. تعريف الثوابت
// ========================================================================
define('APP_SECRET_KEY', get_env_value('APP_SECRET_KEY', 'nalsh_fallback_secret_9988'));
define('FIREBASE_URL', get_env_value('FIREBASE_URL'));
define('FIREBASE_SECRET', get_env_value('FIREBASE_SECRET'));
define('GITHUB_OWNER', get_env_value('GITHUB_OWNER'));
define('GITHUB_REPO', get_env_value('GITHUB_REPO'));
define('GITHUB_TOKEN', get_env_value('GITHUB_TOKEN'));

global $MACRO_DEVICE_ID, $MACRO_WEBHOOK_NAME;
$MACRO_DEVICE_ID = get_env_value('MACRO_DEVICE_ID');
$MACRO_WEBHOOK_NAME = get_env_value('MACRO_WEBHOOK_NAME');

$imgbb_string = get_env_value('IMGBB_KEYS');
$imgbb_array = array_filter(array_map('trim', explode(',', $imgbb_string)));
if (empty($imgbb_array)) $imgbb_array = ['dummy_key'];
define('IMGBB_KEYS', $imgbb_array);

// قراءة بيانات Cloudflare D1 من سيرفر Render أو ملفات البيئة
define('CF_ACCOUNT_ID', get_env_by_prefix('CLOUDFLARE_AC'));
define('CF_API_TOKEN', get_env_by_prefix('CLOUDFLARE_AP'));
define('CF_DATABASE_ID', get_env_by_prefix('CLOUDFLARE_DA'));

// ========================================================================
// 3. فئات محاكاة PDO للربط مع Cloudflare D1 REST API
// ========================================================================

class D1PDO {
    private $accountId;
    private $apiToken;
    private $databaseId;
    private $inTransaction = false;
    private $lastInsertId = 0;

    public function __construct($accountId, $apiToken, $databaseId) {
        $this->accountId = $accountId;
        $this->apiToken = $apiToken;
        $this->databaseId = $databaseId;
    }

    public function prepare($sql) {
        return new D1PDOStatement($this, $sql);
    }

    public function query($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function exec($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        $this->inTransaction = true;
        return true;
    }

    public function commit() {
        $this->inTransaction = false;
        return true;
    }

    public function rollBack() {
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction() {
        return $this->inTransaction;
    }

    public function lastInsertId($name = null) {
        if ($this->lastInsertId) {
            return $this->lastInsertId;
        }
        $stmt = $this->query("SELECT last_insert_rowid() AS id");
        $res = $stmt->fetch();
        return $res ? $res['id'] : 0;
    }

    public function setLastInsertId($id) {
        $this->lastInsertId = $id;
    }

    public function callD1Api($sql, $params = []) {
        $sql = $this->translateMysqlToSqlite($sql);
        $statements = $this->splitSqlStatements($sql);
        
        if (count($statements) > 1) {
            $last_response = null;
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (empty($stmt)) continue;
                $last_response = $this->callD1ApiSingle($stmt, $params);
            }
            return $last_response;
        }

        return $this->callD1ApiSingle($sql, $params);
    }

    private function callD1ApiSingle($sql, $params = []) {
        $url = "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/d1/database/{$this->databaseId}/query";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $normalizedParams = [];
        foreach ($params as $param) {
            if (is_bool($param)) {
                $normalizedParams[] = $param ? 1 : 0;
            } else {
                $normalizedParams[] = $param;
            }
        }

        $payload = [
            'sql' => $sql,
            'params' => $normalizedParams
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiToken}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        
        // تجاوز أخطاء تكرار إضافة الأعمدة أو تكرار إنشاء الجداول بصمت لمحاكاة استقرار المايجريشن
        if ($http_code !== 200 || (isset($decoded['success']) && !$decoded['success'])) {
            $err_msg = isset($decoded['errors'][0]['message']) ? $decoded['errors'][0]['message'] : '';
            if (strpos($err_msg, 'duplicate column') !== false || strpos($err_msg, 'already exists') !== false) {
                return [
                    'success' => true,
                    'result' => [
                        [
                            'success' => true,
                            'results' => [],
                            'meta' => ['changes' => 0]
                        ]
                    ]
                ];
            }
        }

        return $decoded;
    }

    private function splitSqlStatements($sql) {
        return preg_split('/;(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $sql);
    }

    private function translateMysqlToSqlite($sql) {
        $sql = str_replace('`', '', $sql);

        // 1. ترجمة دوال الوقت والـ Interval الخاصة بـ MySQL إلى SQLite
        $sql = preg_replace('/DATE_ADD\(\s*NOW\(\s*\)\s*,\s*INTERVAL\s+(\d+)\s+MINUTE\s*\)/i', "datetime('now', '+$1 minutes')", $sql);
        $sql = preg_replace('/NOW\(\)/i', "datetime('now')", $sql);
        $sql = preg_replace('/datetime\(\'now\'\)\s*-\s*INTERVAL\s+(\?|:\w+)\s+SECOND/i', "datetime('now', '-' || $1 || ' seconds')", $sql);
        $sql = preg_replace('/created_at\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+(\d+)\s+SECOND/i', "created_at < datetime('now', '-$1 seconds')", $sql);
        $sql = preg_replace('/request_time\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+(\d+)\s+SECOND/i', "request_time < datetime('now', '-$1 seconds')", $sql);
        $sql = preg_replace('/request_time\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+15\s+MINUTE/i', "request_time < datetime('now', '-15 minutes')", $sql);
        $sql = preg_replace('/created_at\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+1\s+DAY/i', "created_at < datetime('now', '-1 day')", $sql);
        $sql = preg_replace('/accepted_at\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+(\?|:\w+)\s+SECOND/i', "accepted_at < datetime('now', '-' || $1 || ' seconds')", $sql);
        $sql = preg_replace('/accepted_at\s*<\s*datetime\(\'now\'\)\s*-\s*INTERVAL\s+(\d+)\s+SECOND/i', "accepted_at < datetime('now', '-$1 seconds')", $sql);
        $sql = preg_replace('/last_active_at\s*>=\s*DATE_SUB\(\s*datetime\(\'now\'\)\s*,\s*INTERVAL\s+10\s+MINUTE\s*\)/i', "last_active_at >= datetime('now', '-10 minutes')", $sql);
        $sql = preg_replace('/created_at\s*>=\s*date\(\'now\'\)\s*-\s*INTERVAL\s+7\s+DAY/i', "created_at >= date('now', '-7 days')", $sql);
        $sql = preg_replace('/timestamp\s*>=\s*DATE_SUB\(\s*datetime\(\'now\'\)\s*,\s*INTERVAL\s+7\s+DAY\s*\)/i', "timestamp >= datetime('now', '-7 days')", $sql);
        $sql = preg_replace('/CURDATE\(\)/i', "date('now')", $sql);

        // 2. ترجمة استعلامات التحديث عند تكرار المفتاح ON DUPLICATE KEY UPDATE
        if (preg_match('/INSERT\s+INTO\s+settings\s*\((.*?)\)\s*VALUES\s*\((.*?)\)\s*ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.*)/i', $sql)) {
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.*)/i', "ON CONFLICT(setting_key) DO UPDATE SET $1", $sql);
        }
        if (strpos($sql, 'VALUES(setting_value)') !== false) {
            $sql = str_replace('VALUES(setting_value)', 'excluded.setting_value', $sql);
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', "ON CONFLICT(setting_key) DO UPDATE SET", $sql);
        }
        if (strpos($sql, 'user_cart') !== false && strpos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $sql = str_replace('VALUES(quantity)', 'excluded.quantity', $sql);
            $sql = preg_replace('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', "ON CONFLICT(customer_id, listing_id, size_id) DO UPDATE SET", $sql);
        }

        // 3. تفكيك وبناء الفهارس (INDEX) لتعمل بشكل منفصل خارج جملة إنشاء الجداول
        if (preg_match_all('/INDEX\s*\((.*?)\)/i', $sql, $matches)) {
            $tableName = 'index';
            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z0-9_]+)/i', $sql, $tblMatch)) {
                $tableName = $tblMatch[1];
            }
            $indexStatements = [];
            foreach ($matches[1] as $col) {
                $colClean = trim(str_replace('`', '', $col));
                $indexName = "idx_" . $tableName . "_" . str_replace(',', '_', $colClean);
                $indexStatements[] = "CREATE INDEX IF NOT EXISTS $indexName ON $tableName ($colClean)";
            }
            $sql = preg_replace('/,\s*INDEX\s*\(.*?\)/i', "", $sql);
            $sql = preg_replace('/INDEX\s*\(.*?\)/i', "", $sql);
            $sql .= "; " . implode('; ', $indexStatements);
        }

        // 4. معالجة تعديل سلة التسوق متعدد الأعمدة الغير مدعوم في SQLite بشكل مباشر
        if (strpos($sql, 'ALTER TABLE user_cart') !== false && strpos($sql, 'ADD UNIQUE KEY') !== false) {
            $sql = "ALTER TABLE user_cart ADD COLUMN listing_id INTEGER; " .
                   "ALTER TABLE user_cart ADD COLUMN merchant_id INTEGER; " .
                   "CREATE UNIQUE INDEX IF NOT EXISTS customer_item_unique ON user_cart (customer_id, listing_id, size_id)";
        }

        // 5. تعديلات عامة لتوافق SQLite
        $sql = preg_replace('/INSERT\s+IGNORE\s+INTO/i', "INSERT OR IGNORE INTO", $sql);
        $sql = preg_replace('/FOR\s+UPDATE/i', "", $sql);
        $sql = preg_replace('/ENGINE\s*=\s*InnoDB/i', "", $sql);
        $sql = preg_replace('/INT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', "INTEGER PRIMARY KEY AUTOINCREMENT", $sql);
        $sql = preg_replace('/INTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', "INTEGER PRIMARY KEY AUTOINCREMENT", $sql);
        $sql = preg_replace('/SUBSTRING\s*\(/i', "SUBSTR(", $sql);
        $sql = preg_replace('/AS\s+UNSIGNED/i', "AS INTEGER", $sql);
        $sql = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', "", $sql);
        $sql = preg_replace('/\s+AFTER\s+[a-zA-Z0-9_]+/i', "", $sql);

        return $sql;
    }
}

class D1PDOStatement {
    private $d1;
    private $originalSql;
    private $translatedSql;
    private $namedToPosMap = []; 
    private $bindings = [];
    private $results = [];
    private $meta = null;
    private $cursor = 0;
    private $rowCount = 0;

    public function __construct($d1, $sql) {
        $this->d1 = $d1;
        $this->originalSql = $sql;
        $this->translateSql($sql);
    }

    private function translateSql($sql) {
        $this->translatedSql = $sql;
        $this->namedToPosMap = [];
        $index = 0;
        $this->translatedSql = preg_replace_callback(
            '/\'[^\']*\'|"[^"]*"|:([a-zA-Z0-9_]+)/',
            function($matches) use (&$index) {
                if (isset($matches[1]) && !empty($matches[1])) {
                    $paramName = $matches[1];
                    $this->namedToPosMap[] = $paramName;
                    return '?';
                }
                return $matches[0];
            },
            $sql
        );
    }

    public function bindParam($param, &$value, $type = null) {
        $paramName = ltrim($param, ':');
        $this->bindings[$paramName] = $value;
        return true;
    }

    public function bindValue($param, $value, $type = null) {
        $paramName = ltrim($param, ':');
        $this->bindings[$paramName] = $value;
        return true;
    }

    public function execute($params = null) {
        $this->cursor = 0;
        $this->results = [];
        $this->rowCount = 0;

        if (is_array($params)) {
            foreach ($params as $key => $val) {
                $cleanKey = ltrim($key, ':');
                $this->bindings[$cleanKey] = $val;
            }
        }

        $sequentialParams = [];
        if (!empty($this->namedToPosMap)) {
            foreach ($this->namedToPosMap as $paramName) {
                if (array_key_exists($paramName, $this->bindings)) {
                    $sequentialParams[] = $this->bindings[$paramName];
                } else {
                    $sequentialParams[] = null;
                }
            }
        } else {
            foreach ($this->bindings as $key => $val) {
                $sequentialParams[] = $val;
            }
        }

        $response = $this->d1->callD1Api($this->translatedSql, $sequentialParams);

        if ($response && isset($response['success']) && $response['success']) {
            $queryResult = null;
            if (isset($response['result'])) {
                if (is_array($response['result']) && isset($response['result'][0]['results'])) {
                    $queryResult = $response['result'][0];
                } else {
                    $queryResult = $response['result'];
                }
            }

            if ($queryResult) {
                $this->results = isset($queryResult['results']) ? $queryResult['results'] : [];
                $this->meta = isset($queryResult['meta']) ? $queryResult['meta'] : null;
                
                if (isset($queryResult['success']) && $queryResult['success']) {
                    if (isset($this->meta['changes'])) {
                        $this->rowCount = $this->meta['changes'];
                    } else {
                        $this->rowCount = count($this->results);
                    }
                    if (isset($this->meta['last_row_id'])) {
                        $this->d1->setLastInsertId($this->meta['last_row_id']);
                    }
                }
            }
            return true;
        } else {
            $msg = "D1 execution failed.";
            if (isset($response['errors']) && is_array($response['errors']) && !empty($response['errors'])) {
                $msg .= " Error: " . $response['errors'][0]['message'];
            }
            throw new Exception($msg);
        }
    }

    public function fetch($fetch_style = null) {
        if ($this->cursor < count($this->results)) {
            $row = $this->results[$this->cursor];
            $this->cursor++;
            return $row;
        }
        return false;
    }

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null) {
        $all = [];
        while ($row = $this->fetch()) {
            if ($fetch_style === PDO::FETCH_COLUMN) {
                $all[] = reset($row);
            } elseif ($fetch_style === PDO::FETCH_KEY_PAIR) {
                $keys = array_keys($row);
                if (count($keys) >= 2) {
                    $all[$row[$keys[0]]] = $row[$keys[1]];
                } else {
                    $all[$row[$keys[0]]] = null;
                }
            } else {
                $all[] = $row;
            }
        }
        return $all;
    }

    public function fetchColumn($column_number = 0) {
        $row = $this->fetch();
        if ($row) {
            $values = array_values($row);
            return isset($values[$column_number]) ? $values[$column_number] : null;
        }
        return false;
    }

    public function rowCount() {
        return $this->rowCount;
    }
}

// ========================================================================
// 4. الاتصال بقاعدة بيانات Cloudflare D1
// ========================================================================
global $pdo;

try {
    if (empty(CF_ACCOUNT_ID) || empty(CF_API_TOKEN) || empty(CF_DATABASE_ID)) {
        throw new Exception("حماية النظام: إعدادات الاتصال بقاعدة بيانات Cloudflare D1 غير مهيأة بشكل صحيح في متغيرات البيئة.");
    }
    
    // إنشاء كائن الاتصال المحاكي لـ Cloudflare D1 وتعيينه للمتغير العالمي $pdo
    $pdo = new D1PDO(CF_ACCOUNT_ID, CF_API_TOKEN, CF_DATABASE_ID);
    
} catch (Exception $e) {
    error_log("Cloudflare D1 Connection Error: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode([
        'status' => 'error', 
        'message' => 'D1 Connection Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE));
}
?>
