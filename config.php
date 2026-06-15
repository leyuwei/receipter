<?php
/**
 * 数据库配置文件
 * 请根据你的实际环境修改以下配置
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'receipter');
define('DB_USER', 'team_mgr');       // 修改为你的数据库用户名
define('DB_PASS', '');           // 修改为你的数据库密码
define('DB_CHARSET', 'utf8mb4');

// 应用基础配置
// 如果你部署在子目录，例如 domain.com/receipter，请将 APP_BASE_PATH 设置为 '/receipter'
// 如果部署在根域名，则设置为 ''
define('APP_BASE_PATH', '/receipter');

// 错误报告（生产环境请关闭 display_errors）
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * 获取数据库连接（单例 PDO）
 */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => '数据库连接失败：' . $e->getMessage()]));
        }
    }
    return $pdo;
}
