<?php
/**
 * 首页：创建 / 打开账本
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
$base = rtrim(APP_BASE_PATH, '/');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
<title>记个小账 · Receipter</title>
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($base) ?>/assets/img/logo.svg">
<link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/style.css">
</head>
<body class="page-home">
<div class="wrap home-card">
    <img class="logo" src="<?= htmlspecialchars($base) ?>/assets/img/logo.svg" alt="logo" width="72" height="72">
    <h1>记个小账</h1>
    <p class="sub">轻量好用的个人 / 团体账本</p>

    <div class="tabs">
        <button type="button" class="tab active" data-tab="open">打开已有账本</button>
        <button type="button" class="tab" data-tab="create">创建新账本</button>
    </div>

    <!-- 打开账本 -->
    <form id="form-open" class="form-panel active" autocomplete="off">
        <label>账本名称（含随机数后缀）</label>
        <input type="text" id="open-code" placeholder="例如：旅行账本-aB3k" required>
        <p class="hint">请输入完整账本名（含后缀），进入账本后可收藏链接。</p>
        <button type="submit" class="btn primary">进入账本</button>
    </form>

    <!-- 创建账本 -->
    <form id="form-create" class="form-panel" autocomplete="off">
        <label>给账本起个好记的名字</label>
        <input type="text" id="create-name" placeholder="例如：旅行账本" maxlength="50" required>
        <p class="hint">系统会自动追加随机后缀以避免重名，例如 <code>旅行账本-aB3k</code>。</p>
        <button type="submit" class="btn primary">创建并进入</button>
    </form>
</div>

<!-- 提示弹窗 -->
<div id="modal" class="modal hidden">
    <div class="modal-box">
        <h3 id="modal-title">提示</h3>
        <div id="modal-body" class="modal-body"></div>
        <div class="modal-actions">
            <button type="button" class="btn" id="modal-cancel">关闭</button>
            <button type="button" class="btn primary" id="modal-ok">我已记住</button>
        </div>
    </div>
</div>

<script>
window.APP_BASE = <?= json_encode($base) ?>;
</script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/app.js"></script>
</body>
</html>
