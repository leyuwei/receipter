<?php
/**
 * 账目 API（JSON）
 *
 * 操作（均通过 POST，body 为 JSON）：
 *   {op:'create', book_id, entry:{...}}            添加
 *   {op:'update', id, entry:{...}}                 修改
 *   {op:'delete', id}                              删除
 *   {op:'reorder', book_id, orders:[id1,id2,...]}  拖拽排序
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        fail('仅支持 POST', 405);
    }
    $body = read_json_body();
    $op = $body['op'] ?? '';
    $pdo = db();

    if ($op === 'create') {
        $bookId = (int)($body['book_id'] ?? 0);
        if ($bookId <= 0) fail('缺少 book_id');
        // 校验账本存在
        $stmt = $pdo->prepare('SELECT id FROM receipter_books WHERE id = ?');
        $stmt->execute([$bookId]);
        if (!$stmt->fetch()) fail('账本不存在', 404);

        $e = $body['entry'] ?? [];
        // 当前最大 sort_order
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM receipter_entries WHERE book_id = ?');
        $stmt->execute([$bookId]);
        $sortOrder = (int)$stmt->fetchColumn();

        $data = [
            'book_id'    => $bookId,
            'type'       => sstr($e['type'] ?? '支出', 50),
            'detail'     => sstr($e['detail'] ?? '', 500),
            'payer'      => sstr($e['payer'] ?? '', 100),
            'payee'      => sstr($e['payee'] ?? '', 100),
            'currency'   => sstr($e['currency'] ?? 'CNY', 10),
            'amount'     => samount($e['amount'] ?? 0),
            'is_loan'    => !empty($e['is_loan']) ? 1 : 0,
            'borrower'   => sstr($e['borrower'] ?? '', 100),
            'remark'     => sstr($e['remark'] ?? '', 500),
            'entry_date' => sstr($e['entry_date'] ?? '', 10) ?: null,
            'sort_order' => $sortOrder,
        ];
        $sql = 'INSERT INTO receipter_entries
                (book_id, type, detail, payer, payee, currency, amount, is_loan, borrower, remark, entry_date, sort_order)
                VALUES
                (:book_id,:type,:detail,:payer,:payee,:currency,:amount,:is_loan,:borrower,:remark,:entry_date,:sort_order)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        $id = (int)$pdo->lastInsertId();
        ok(['id' => $id]);
    }

    if ($op === 'update') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) fail('缺少 id');
        $e = $body['entry'] ?? [];
        $fields = [
            'type'       => sstr($e['type'] ?? '支出', 50),
            'detail'     => sstr($e['detail'] ?? '', 500),
            'payer'      => sstr($e['payer'] ?? '', 100),
            'payee'      => sstr($e['payee'] ?? '', 100),
            'currency'   => sstr($e['currency'] ?? 'CNY', 10),
            'amount'     => samount($e['amount'] ?? 0),
            'is_loan'    => !empty($e['is_loan']) ? 1 : 0,
            'borrower'   => sstr($e['borrower'] ?? '', 100),
            'remark'     => sstr($e['remark'] ?? '', 500),
            'entry_date' => sstr($e['entry_date'] ?? '', 10) ?: null,
        ];
        $set = [];
        foreach ($fields as $k => $v) {
            $set[] = "`$k` = :$k";
        }
        $fields['id'] = $id;
        $sql = 'UPDATE receipter_entries SET ' . implode(',', $set) . ' WHERE id = :id';
        $pdo->prepare($sql)->execute($fields);
        ok();
    }

    if ($op === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) fail('缺少 id');
        $pdo->prepare('DELETE FROM receipter_entries WHERE id = ?')->execute([$id]);
        ok();
    }

    if ($op === 'reorder') {
        $bookId = (int)($body['book_id'] ?? 0);
        $orders = $body['orders'] ?? [];
        if ($bookId <= 0 || !is_array($orders) || empty($orders)) {
            fail('参数错误');
        }
        $stmt = $pdo->prepare('UPDATE receipter_entries SET sort_order = ? WHERE id = ? AND book_id = ?');
        $order = 1;
        foreach ($orders as $eid) {
            $stmt->execute([$order++, (int)$eid, $bookId]);
        }
        ok();
    }

    fail('未知操作', 404);
} catch (Throwable $e) {
    fail('服务器错误：' . $e->getMessage(), 500);
}
