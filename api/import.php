<?php
/**
 * 导入 JSON 账本文件
 *   POST api/import.php  (multipart/form-data)
 *        book_id: 目标账本 ID
 *        file:    导出的 JSON 文件
 *
 * 导入策略：
 *  - 导入到「已打开的账本」中（追加，不清空现有数据）
 *  - 保留原 sort_order 顺序，追加到现有数据之后
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('仅支持 POST', 405);

    $bookId = (int)($_POST['book_id'] ?? 0);
    if ($bookId <= 0) fail('缺少 book_id');

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        fail('请上传文件');
    }
    $tmp = $_FILES['file']['tmp_name'];
    $content = file_get_contents($tmp);
    // 去除可能存在的 BOM
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $data = json_decode($content, true);
    if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
        fail('文件格式不正确，请上传本站导出的 JSON 文件');
    }

    // 校验账本
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM receipter_books WHERE id = ?');
    $stmt->execute([$bookId]);
    if (!$stmt->fetch()) fail('目标账本不存在', 404);

    // 当前最大 sort_order
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0) FROM receipter_entries WHERE book_id = ?');
    $stmt->execute([$bookId]);
    $startOrder = (int)$stmt->fetchColumn();

    $sql = 'INSERT INTO receipter_entries
            (book_id, type, detail, payer, payee, currency, amount, is_loan, borrower, remark, entry_date, sort_order)
            VALUES
            (:book_id,:type,:detail,:payer,:payee,:currency,:amount,:is_loan,:borrower,:remark,:entry_date,:sort_order)';
    $stmt = $pdo->prepare($sql);

    $count = 0;
    foreach ($data['entries'] as $e) {
        $startOrder++;
        $stmt->execute([
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
            'sort_order' => $startOrder,
        ]);
        $count++;
    }
    ok(['imported' => $count]);
} catch (Throwable $e) {
    fail('导入失败：' . $e->getMessage(), 500);
}
