/* =========================================================
 * 记个小账 · 前端逻辑
 * 依赖：无第三方库，原生 JS
 * ========================================================= */
(function () {
    'use strict';

    const BASE = window.APP_BASE || '';
    const $ = (s, p = document) => p.querySelector(s);
    const $$ = (s, p = document) => Array.from(p.querySelectorAll(s));

    /* ---------- 通用：提示弹窗 ---------- */
    function showModal(title, bodyHTML, onOK) {
        const m = $('#modal');
        if (!m) { alert((title ? title + '\n\n' : '') + stripHtml(bodyHTML)); if (onOK) onOK(); return; }
        $('#modal-title').textContent = title;
        $('#modal-body').innerHTML = bodyHTML;
        m.classList.remove('hidden');
        const ok = $('#modal-ok');
        const cancel = $('#modal-cancel');
        const close = () => {
            m.classList.add('hidden');
            ok.removeEventListener('click', onOkClick);
            if (cancel) cancel.removeEventListener('click', onClose);
        };
        const onOkClick = () => { close(); if (onOK) onOK(); };
        const onClose = () => { close(); };
        ok.addEventListener('click', onOkClick);
        if (cancel) cancel.addEventListener('click', onClose);
    }

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    /* ---------- 通用：fetch JSON ---------- */
    async function api(url, opts = {}) {
        const res = await fetch(BASE + url, Object.assign({
            headers: { 'Content-Type': 'application/json' },
        }, opts));
        const data = await res.json().catch(() => ({ ok: false, error: '响应解析失败' }));
        if (!data.ok) throw new Error(data.error || ('请求失败 (' + res.status + ')'));
        return data;
    }

    /* =====================================================
     * 首页逻辑
     * ===================================================== */
    function initHome() {
        // Tab 切换
        $$('.tab').forEach(t => {
            t.addEventListener('click', () => {
                $$('.tab').forEach(x => x.classList.remove('active'));
                t.classList.add('active');
                $$('.form-panel').forEach(p => p.classList.remove('active'));
                $('#form-' + t.dataset.tab).classList.add('active');
            });
        });

        // 打开账本
        const formOpen = $('#form-open');
        if (formOpen) {
            formOpen.addEventListener('submit', e => {
                e.preventDefault();
                const code = $('#open-code').value.trim();
                if (!code) return;
                window.location.href = BASE + '/book.php?code=' + encodeURIComponent(code);
            });
        }

        // 创建账本
        const formCreate = $('#form-create');
        if (formCreate) {
            formCreate.addEventListener('submit', async e => {
                e.preventDefault();
                const name = $('#create-name').value.trim();
                if (!name) return;
                try {
                    const data = await api('/api/book.php', {
                        method: 'POST',
                        body: JSON.stringify({ op: 'create', name }),
                    });
                    const code = data.data.code;
                    showModal(
                        '创建成功！请务必记住以下账本名',
                        '<p>这是你的完整账本名，请妥善保存（可截图）。下次请输入这个名字进入账本：</p>' +
                        '<code>' + escapeHtml(code) + '</code>' +
                        '<p style="margin-top:10px;font-size:12px;color:var(--text-3)">建议立即收藏进入后的页面链接。</p>',
                        () => {
                            window.location.href = BASE + '/book.php?code=' + encodeURIComponent(code);
                        }
                    );
                } catch (err) {
                    showModal('创建失败', escapeHtml(err.message));
                }
            });
        }
    }

    /* =====================================================
     * 账本页逻辑
     * ===================================================== */
    let bookData = null;        // { book, entries }
    let currentSort = { key: 'sort_order', dir: 'asc' };

    async function loadBook() {
        try {
            const data = await api('/api/book.php?op=get&code=' + encodeURIComponent(window.BOOK_CODE));
            bookData = data.data;
            renderBook();
        } catch (err) {
            showModal('加载失败', escapeHtml(err.message));
        }
    }

    function renderBook() {
        if (!bookData) return;
        $('#book-title').textContent = bookData.book.name;
        document.title = bookData.book.name + ' · 记个小账';

        const entries = applySort(bookData.entries);
        renderEntries(entries);
        renderSummary(entries);
    }

    function applySort(entries) {
        const arr = entries.slice();
        const k = currentSort.key;
        const dir = currentSort.dir === 'asc' ? 1 : -1;
        if (k === 'sort_order') {
            arr.sort((a, b) => (a.sort_order - b.sort_order) || (a.id - b.id));
            if (dir < 0) arr.reverse();
            return arr;
        }
        arr.sort((a, b) => {
            let va = a[k], vb = b[k];
            if (va == null) va = '';
            if (vb == null) vb = '';
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb), 'zh') * dir;
        });
        return arr;
    }

    function renderSummary(entries) {
        $('#sum-count').textContent = entries.length;
        let expense = 0, income = 0;
        entries.forEach(e => {
            const amt = parseFloat(e.amount) || 0;
            if (e.type === '收入') income += amt;
            else if (e.type === '支出') expense += amt;
        });
        $('#sum-expense').textContent = expense.toFixed(2);
        $('#sum-income').textContent = income.toFixed(2);
    }

    function renderEntries(entries) {
        const list = $('#entry-list');
        const tip = $('#empty-tip');
        list.innerHTML = '';
        if (!entries.length) {
            tip.style.display = 'block';
            return;
        }
        tip.style.display = 'none';

        entries.forEach(e => {
            const card = document.createElement('div');
            card.className = 'entry-card';
            card.dataset.id = e.id;

            const amtClass = e.type === '收入' ? 'income' : (e.type === '支出' ? 'expense' : '');
            const loanTag = e.is_loan ? '<span class="tag tag-loan">借款</span>' : '';

            card.innerHTML =
                '<div class="drag-handle" title="拖动排序">⋮⋮</div>' +
                '<div class="entry-main">' +
                    '<div class="entry-row1">' +
                        '<span class="tag tag-' + escapeHtml(e.type) + '">' + escapeHtml(e.type) + '</span>' +
                        loanTag +
                        '<span class="entry-detail">' + escapeHtml(e.detail || '(无详情)') + '</span>' +
                    '</div>' +
                    '<div class="entry-row2">' +
                        (e.payer ? '<span>付：' + escapeHtml(e.payer) + '</span>' : '') +
                        (e.payee ? '<span>收：' + escapeHtml(e.payee) + '</span>' : '') +
                        (e.borrower ? '<span>借款人：' + escapeHtml(e.borrower) + '</span>' : '') +
                        (e.entry_date ? '<span>' + escapeHtml(e.entry_date) + '</span>' : '') +
                    '</div>' +
                    (e.remark ? '<div class="entry-remark">📝 ' + escapeHtml(e.remark) + '</div>' : '') +
                '</div>' +
                '<div class="entry-amount-col">' +
                    '<div class="entry-amount ' + amtClass + '">' + (e.type === '支出' ? '-' : e.type === '收入' ? '+' : '') + formatAmount(e.amount) + '</div>' +
                    '<div class="entry-currency">' + escapeHtml(e.currency) + '</div>' +
                '</div>' +
                '<div class="entry-actions">' +
                    '<button class="btn ghost" data-act="edit">编辑</button>' +
                    '<button class="btn ghost danger" data-act="delete">删除</button>' +
                '</div>';

            // 操作事件
            card.querySelector('[data-act="edit"]').addEventListener('click', () => openEditEntry(e.id));
            card.querySelector('[data-act="delete"]').addEventListener('click', () => deleteEntry(e.id));

            list.appendChild(card);
        });

        // 拖拽排序
        if (currentSort.key === 'sort_order') {
            enableDnD(list);
        }
    }

    function formatAmount(n) {
        const v = parseFloat(n) || 0;
        return v.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* ---------- 拖拽排序（含触屏） ---------- */
    function enableDnD(list) {
        let dragEl = null;
        let dragOverEl = null;

        function onDown(e) {
            // 只在拖拽手柄上触发
            const handle = e.target.closest('.drag-handle');
            if (!handle) return;
            const card = handle.closest('.entry-card');
            if (!card) return;
            dragEl = card;
            card.classList.add('dragging');
            e.preventDefault();
        }

        function findCardFromPoint(x, y) {
            const el = document.elementFromPoint(x, y);
            return el ? el.closest('.entry-card') : null;
        }

        function onMove(e) {
            if (!dragEl) return;
            e.preventDefault();
            const x = e.touches ? e.touches[0].clientX : e.clientX;
            const y = e.touches ? e.touches[0].clientY : e.clientY;
            const over = findCardFromPoint(x, y);
            $$('.entry-card', list).forEach(c => c.classList.remove('drag-over'));
            if (over && over !== dragEl) {
                over.classList.add('drag-over');
                dragOverEl = over;
                // 实时插入
                const rect = over.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                if (y < mid) {
                    list.insertBefore(dragEl, over);
                } else {
                    list.insertBefore(dragEl, over.nextSibling);
                }
            } else {
                dragOverEl = null;
            }
        }

        function onUp() {
            if (!dragEl) return;
            dragEl.classList.remove('dragging');
            $$('.entry-card', list).forEach(c => c.classList.remove('drag-over'));
            dragEl = null;
            dragOverEl = null;
            saveOrder();
        }

        list.addEventListener('mousedown', onDown);
        list.addEventListener('touchstart', onDown, { passive: false });
        document.addEventListener('mousemove', onMove, { passive: false });
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchend', onUp);
    }

    function saveOrder() {
        const orders = $$('.entry-card', $('#entry-list')).map(c => parseInt(c.dataset.id, 10));
        api('/api/entry.php', {
            method: 'POST',
            body: JSON.stringify({ op: 'reorder', book_id: bookData.book.id, orders }),
        }).then(() => loadBook()).catch(err => {
            showModal('排序保存失败', escapeHtml(err.message));
        });
    }

    /* ---------- 添加/编辑账目 ---------- */
    function openAddEntry() {
        $('#modal-entry-title').textContent = '添加账目';
        $('#entry-id').value = '';
        $('#entry-type').value = '支出';
        $('#entry-detail').value = '';
        $('#entry-payer').value = '';
        $('#entry-payee').value = '';
        $('#entry-currency').value = 'CNY';
        $('#entry-amount').value = '';
        $('#entry-date').value = new Date().toISOString().slice(0, 10);
        $('#entry-is-loan').checked = false;
        $('#entry-borrower').value = '';
        $('#entry-remark').value = '';
        toggleBorrower();
        $('#modal-entry').classList.remove('hidden');
    }

    function openEditEntry(id) {
        const e = bookData.entries.find(x => x.id === id);
        if (!e) return;
        $('#modal-entry-title').textContent = '编辑账目';
        $('#entry-id').value = e.id;
        $('#entry-type').value = e.type || '支出';
        $('#entry-detail').value = e.detail || '';
        $('#entry-payer').value = e.payer || '';
        $('#entry-payee').value = e.payee || '';
        $('#entry-currency').value = e.currency || 'CNY';
        $('#entry-amount').value = e.amount;
        $('#entry-date').value = e.entry_date || '';
        $('#entry-is-loan').checked = !!e.is_loan;
        $('#entry-borrower').value = e.borrower || '';
        $('#entry-remark').value = e.remark || '';
        toggleBorrower();
        $('#modal-entry').classList.remove('hidden');
    }

    function toggleBorrower() {
        $('#fg-borrower').style.display = $('#entry-is-loan').checked ? '' : 'none';
    }

    async function submitEntry(e) {
        e.preventDefault();
        const id = $('#entry-id').value;
        const entry = {
            type: $('#entry-type').value,
            detail: $('#entry-detail').value.trim(),
            payer: $('#entry-payer').value.trim(),
            payee: $('#entry-payee').value.trim(),
            currency: $('#entry-currency').value,
            amount: parseFloat($('#entry-amount').value) || 0,
            entry_date: $('#entry-date').value,
            is_loan: $('#entry-is-loan').checked ? 1 : 0,
            borrower: $('#entry-borrower').value.trim(),
            remark: $('#entry-remark').value.trim(),
        };
        try {
            if (id) {
                await api('/api/entry.php', {
                    method: 'POST',
                    body: JSON.stringify({ op: 'update', id: parseInt(id, 10), entry }),
                });
            } else {
                await api('/api/entry.php', {
                    method: 'POST',
                    body: JSON.stringify({ op: 'create', book_id: bookData.book.id, entry }),
                });
            }
            $('#modal-entry').classList.add('hidden');
            await loadBook();
        } catch (err) {
            showModal('保存失败', escapeHtml(err.message));
        }
    }

    async function deleteEntry(id) {
        if (!confirm('确定删除这条账目吗？')) return;
        try {
            await api('/api/entry.php', {
                method: 'POST',
                body: JSON.stringify({ op: 'delete', id }),
            });
            await loadBook();
        } catch (err) {
            showModal('删除失败', escapeHtml(err.message));
        }
    }

    /* ---------- 导出 / 导入 ---------- */
    function doExport(fmt) {
        window.location.href = BASE + '/api/export.php?code=' + encodeURIComponent(window.BOOK_CODE) + '&format=' + fmt;
    }

    function openImport() {
        $('#import-file').value = '';
        $('#modal-import').classList.remove('hidden');
    }

    async function doImport() {
        const fileInput = $('#import-file');
        if (!fileInput.files.length) {
            alert('请选择 JSON 文件');
            return;
        }
        const fd = new FormData();
        fd.append('book_id', bookData.book.id);
        fd.append('file', fileInput.files[0]);
        try {
            const res = await fetch(BASE + '/api/import.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.ok) throw new Error(data.error);
            $('#modal-import').classList.add('hidden');
            showModal('导入成功', '共导入 ' + data.data.imported + ' 条账目。', loadBook);
        } catch (err) {
            showModal('导入失败', escapeHtml(err.message));
        }
    }

    /* ---------- 工具 ---------- */
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* =====================================================
     * 初始化
     * ===================================================== */
    function initBookPage() {
        // 绑定事件
        $('#btn-add').addEventListener('click', openAddEntry);
        $('#entry-cancel').addEventListener('click', () => $('#modal-entry').classList.add('hidden'));
        $('#entry-is-loan').addEventListener('change', toggleBorrower);
        $('#form-entry').addEventListener('submit', submitEntry);

        $('#btn-export-json').addEventListener('click', () => doExport('json'));
        $('#btn-import').addEventListener('click', openImport);
        $('#import-cancel').addEventListener('click', () => $('#modal-import').classList.add('hidden'));
        $('#import-ok').addEventListener('click', doImport);

        $('#sort-key').addEventListener('change', e => {
            currentSort.key = e.target.value;
            renderBook();
        });
        $('#btn-sort-dir').addEventListener('click', () => {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
            $('#btn-sort-dir').textContent = currentSort.dir === 'asc' ? '↑' : '↓';
            renderBook();
        });

        loadBook();
    }

    // 启动
    document.addEventListener('DOMContentLoaded', () => {
        if (document.body.classList.contains('page-home')) {
            initHome();
        } else if (document.body.classList.contains('page-book')) {
            initBookPage();
        }
    });
})();
