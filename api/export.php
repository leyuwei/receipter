<?php
/**
 * 导出账本为 JSON（带 BOM 兼容 Excel 打开）或 CSV
 *   GET api/export.php?code=xxx&format=json|csv
 *
 * JSON 完整保留中文与结构，可再次导入；CSV 方便在 Excel 中查看。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $code   = sstr($_GET['code'] ?? '', 255);
    $format = sstr($_GET['format'] ?? 'json', 10);
    if ($code === '') fail('缺少 code');

    $stmt = db()->prepare('SELECT id, name, code, created_at FROM receipter_books WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $book = $stmt->fetch();
    if (!$book) fail('账本不存在', 404);
    $book['id'] = (int)$book['id'];

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

    $filenameSafe = preg_replace('/[\\/:*?"<>|]+/', '_', $book['name']);
    $stamp = date('Ymd_His');

    if ($format === 'csv') {
        // 输出 UTF-8 BOM 让 Excel 正确识别中文
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameSafe . '_' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM
        fputcsv($out, ['类型', '详情', '支付方', '收款方', '货币', '数额', '是否借款', '借款人', '备注', '日期']);
        foreach ($entries as $e) {
            fputcsv($out, [
                $e['type'], $e['detail'], $e['payer'], $e['payee'],
                $e['currency'], $e['amount'],
                $e['is_loan'] ? '是' : '否',
                $e['borrower'], $e['remark'],
                $e['entry_date'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // 默认 JSON
    $payload = [
        'app'         => 'receipter',
        'version'     => 1,
        'exported_at' => date('c'),
        'book'        => [
            'name' => $book['name'],
            'code' => $book['code'],
        ],
        'entries'     => array_map(function ($e) {
            // 导出时去除 book_id（导入时按新账本绑定）
            unset($e['book_id'], $e['id']);
            return $e;
        }, $entries),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameSafe . '_' . $stamp . '.json"');
    echo $json;
    exit;
} catch (Throwable $e) {
    fail('导出失败：' . $e->getMessage(), 500);
}
