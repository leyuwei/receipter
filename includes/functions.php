<?php
/**
 * 公共函数库
 */

/** 统一 JSON 响应并退出 */
function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** 成功响应 */
function ok($data = null): void {
    if ($data === null) {
        json_response(['ok' => true]);
    }
    json_response(['ok' => true, 'data' => $data]);
}

/** 失败响应 */
function fail(string $msg, int $code = 400): void {
    json_response(['ok' => false, 'error' => $msg], $code);
}

/** 读取 JSON 请求体（POST/PUT） */
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}

/**
 * 生成 4 位随机字母数字后缀（大小写敏感）
 * 例如：aB3k
 */
function random_suffix(int $len = 4): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $len; $i++) {
        $str .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $str;
}

/**
 * 生成完整账本 code：友好名称 + - + 随机数
 * 例如："旅行账本-aB3k"
 */
function generate_book_code(string $name): string {
    return trim($name) . '-' . random_suffix(4);
}

/** 安全字符串，截断到指定长度 */
function sstr($v, int $max = 500): string {
    $v = is_string($v) ? trim($v) : '';
    return mb_substr($v, 0, $max);
}

/** 转为 2 位小数 float */
function samount($v): float {
    $v = str_replace([',', ' '], '', (string)$v);
    return round((float)$v, 2);
}

/** 生成 UUID v4（导入时图片/文件命名用） */
function uuid_v4(): string {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * 路径前缀：APP_BASE_PATH
 */
function base_path(string $suffix = ''): string {
    return rtrim(APP_BASE_PATH, '/') . '/' . ltrim($suffix, '/');
}
