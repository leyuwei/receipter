<?php
/**
 * 账本详情页
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$base = rtrim(APP_BASE_PATH, '/');
$code = sstr($_GET['code'] ?? '', 255);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
<title>账本 · 记个小账</title>
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/img/logo.svg">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/style.css?v=6">
</head>
<body class="page-book">
<header class="topbar">
    <div class="topbar-left">
        <img class="logo-sm" src="<?= htmlspecialchars($base) ?>/assets/img/logo.svg" alt="logo" width="32" height="32">
        <div>
            <div class="topbar-title" id="book-title">账本</div>
            <div class="topbar-sub" id="book-code-display"><?= htmlspecialchars($code) ?></div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="dropdown">
            <button type="button" class="btn ghost" id="btn-export" title="导出">导出 ▾</button>
            <div class="dropdown-menu" id="export-menu">
                <button type="button" class="dropdown-item" data-fmt="xlsx">Excel (.xlsx)</button>
                <button type="button" class="dropdown-item" data-fmt="csv">CSV (.csv)</button>
                <button type="button" class="dropdown-item" data-fmt="json">JSON (.json)</button>
            </div>
        </div>
        <button type="button" class="btn ghost" id="btn-import" title="导入">导入</button>
        <button type="button" class="btn ghost" id="btn-print" title="打印账本">打印</button>
        <a class="btn ghost" href="<?= htmlspecialchars($base) ?>/index.php">返回</a>
    </div>
</header>

<main class="book-main">
    <!-- 汇总卡片 -->
    <section class="summary" id="summary">
        <div class="sum-card sum-card-count">
            <div class="sum-label">账目数</div>
            <div class="sum-value" id="sum-count">0</div>
        </div>
        <div class="sum-card sum-card-cny">
            <div class="sum-label">人民币折算总值 <span class="sum-hint" title="基于参考汇率的估算值">参考</span></div>
            <div class="sum-value cny" id="sum-cny">0.00</div>
            <div class="sum-sub-note" id="sum-cny-note"></div>
        </div>
        <div class="sum-card sum-card-multi" id="sum-multi-card" style="display:none">
            <div class="sum-label">各货币收支明细</div>
            <div class="sum-multi-list" id="sum-multi-list"></div>
        </div>
    </section>

    <!-- 工具栏 -->
    <section class="toolbar">
        <button type="button" class="btn primary" id="btn-add">+ 添加账目</button>
        <div class="sort-area">
            <select id="sort-key" class="select">
                <option value="sort_order">手动排序</option>
                <option value="entry_date">日期</option>
                <option value="amount">金额</option>
                <option value="type">类型</option>
                <option value="currency">货币</option>
                <option value="created_at">创建时间</option>
            </select>
            <button type="button" class="btn ghost" id="btn-sort-dir" title="升序/降序">↑</button>
        </div>
    </section>

    <!-- 账目列表 -->
    <section class="entry-list" id="entry-list"></section>
    <p class="empty-tip" id="empty-tip">暂无账目，点击「+ 添加账目」开始记录吧～</p>
</main>

<!-- 编辑/新增弹窗 -->
<div id="modal-entry" class="modal hidden">
    <div class="modal-box wide">
        <h3 id="modal-entry-title">添加账目</h3>
        <form id="form-entry" class="form-grid">
            <input type="hidden" id="entry-id">
            <div class="fg">
                <label>类型</label>
                <select id="entry-type" class="select">
                    <option value="支出">支出</option>
                    <option value="收入">收入</option>
                    <option value="转账">转账</option>
                </select>
            </div>
            <div class="fg">
                <label>详情</label>
                <input type="text" id="entry-detail" maxlength="200">
            </div>
            <div class="fg">
                <label>支付方</label>
                <input type="text" id="entry-payer" maxlength="50">
            </div>
            <div class="fg">
                <label>收款方</label>
                <input type="text" id="entry-payee" maxlength="50">
            </div>
            <div class="fg">
                <label>货币</label>
                <select id="entry-currency" class="select">
                    <option value="CNY">CNY 人民币</option>
                    <option value="USD">USD 美元</option>
                    <option value="EUR">EUR 欧元</option>
                    <option value="JPY">JPY 日元</option>
                    <option value="HKD">HKD 港币</option>
                    <option value="TWD">TWD 台币</option>
                    <option value="GBP">GBP 英镑</option>
                    <option value="KRW">KRW 韩元</option>
                </select>
            </div>
            <div class="fg">
                <label>数额</label>
                <input type="number" id="entry-amount" step="0.01" min="0">
            </div>
            <div class="fg">
                <label>日期</label>
                <input type="date" id="entry-date">
            </div>
            <div class="fg check">
                <label><input type="checkbox" id="entry-is-loan"> 是否为借款</label>
            </div>
            <div class="fg" id="fg-borrower" style="display:none">
                <label>借款人</label>
                <input type="text" id="entry-borrower" maxlength="50">
            </div>
            <div class="fg full">
                <label>备注</label>
                <input type="text" id="entry-remark" maxlength="200">
            </div>
            <div class="form-actions full">
                <button type="button" class="btn" id="entry-cancel">取消</button>
                <button type="submit" class="btn primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 通用提示 -->
<div id="modal" class="modal hidden">
    <div class="modal-box">
        <h3 id="modal-title">提示</h3>
        <div id="modal-body" class="modal-body"></div>
        <div class="modal-actions">
            <button type="button" class="btn primary" id="modal-ok">确定</button>
        </div>
    </div>
</div>

<!-- 导入弹窗 -->
<div id="modal-import" class="modal hidden">
    <div class="modal-box">
        <h3>导入账目</h3>
        <p class="hint">选择本站导出的 JSON 文件，账目将追加到当前账本末尾。</p>
        <input type="file" id="import-file" accept=".json,application/json">
        <div class="modal-actions">
            <button type="button" class="btn" id="import-cancel">取消</button>
            <button type="button" class="btn primary" id="import-ok">开始导入</button>
        </div>
    </div>
</div>

<!-- 打印专用区域（仅 @media print 时可见） -->
<div id="print-area" class="print-area"></div>

<script>
window.APP_BASE = <?= json_encode($base) ?>;
window.BOOK_CODE = <?= json_encode($code) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/app.js?v=6"></script>
</body>
</html>
