<?php
/**
 * 账本 API
 *
 * 操作：
 *   GET  api/book.php?op=get&code=xxx          获取账本信息（含账目列表）
 *   POST api/book.php  {op:'create', name:'x'} 创建账本
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

$op = $_GET['op'] ?? (read_json_body()['op'] ?? '');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = read_json_body();
        $op = $body['op'] ?? $op;

        if ($op === 'create') {
            $name = sstr($body['name'] ?? '', 100);
            if ($name === '') {
                fail('请输入账本名称');
            }
            $code = generate_book_code($name);
            $stmt = db()->prepare('INSERT INTO receipter_books (name, code) VALUES (?, ?)');
            $stmt->execute([$name, $code]);
            $id = (int)db()->lastInsertId();
            ok(['id' => $id, 'name' => $name, 'code' => $code]);
        }
        fail('未知操作', 404);
    }

    // GET
    if ($op === 'get') {
        $code = sstr($_GET['code'] ?? '', 255);
        if ($code === '') {
            fail('缺少账本 code');
        }
        $stmt = db()->prepare('SELECT id, name, code, created_at FROM receipter_books WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $book = $stmt->fetch();
        if (!$book) {
            fail('账本不存在，请检查名称是否正确', 404);
        }
        $book['id'] = (int)$book['id'];

        // 取所有账目（按 sort_order 排序）
        $stmt = db()->prepare('SELECT * FROM receipter_entries WHERE book_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$book['id']]);
        $entries = $stmt->fetchAll();
        foreach ($entries as &$e) {
            $e['id']         = (int)$e['id'];
            $e['book_id']    = (int)$e['book_id'];
            $e['amount']     = (float)$e['amount'];
            $e['is_loan']    = (int)$e['is_loan'];
            $e['sort_order'] = (int)$e['sort_order'];
        }
        unset($e);
        ok(['book' => $book, 'entries' => $entries]);
    }

    fail('未知操作', 404);
} catch (Throwable $e) {
    fail('服务器错误：' . $e->getMessage(), 500);
}
