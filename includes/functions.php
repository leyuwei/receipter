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

/**
 * XML 转义（仅对文本内容转义 <, >, &, 保留 UTF-8 中文）
 */
function xml_escape(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * 货币代码 → 中文名映射表
 * 返回 "代码 中文名" 格式，如 "CNY 人民币"
 */
function currency_name(string $code): string {
    static $map = [
        'CNY' => '人民币',
        'USD' => '美元',
        'EUR' => '欧元',
        'JPY' => '日元',
        'HKD' => '港币',
        'TWD' => '台币',
        'GBP' => '英镑',
        'KRW' => '韩元',
    ];
    $code = strtoupper(trim($code));
    $name = $map[$code] ?? $code;
    return $code . ' ' . $name;
}

/**
 * 货币 → 人民币参考汇率（1 单位外币 ≈ X 人民币）
 * 仅为估算参考，非实时汇率
 */
function cny_rate(string $code): float {
    static $rates = [
        'CNY' => 1.0,
        'USD' => 7.25,
        'EUR' => 7.85,
        'JPY' => 0.046,
        'HKD' => 0.93,
        'TWD' => 0.22,
        'GBP' => 9.20,
        'KRW' => 0.0053,
    ];
    $code = strtoupper(trim($code));
    return $rates[$code] ?? 1.0;
}

/**
 * 将列号（1, 2, 3 ...）转为 Excel 列字母（A, B, ... AA, AB）
 */
function excel_col_letter(int $n): string {
    $s = '';
    while ($n > 0) {
        $mod = ($n - 1) % 26;
        $s = chr(65 + $mod) . $s;
        $n = intdiv($n - $mod, 26);
    }
    return $s;
}

/**
 * 生成 .xlsx 文件内容（Office Open XML，仅依赖 ZipArchive 扩展）
 *
 * @param array $book    账本信息（含 name/code/created_at）
 * @param array $entries 账目数组
 * @return string 完整的 xlsx 二进制内容
 */
function build_xlsx(array $book, array $entries): string {
    $headers = ['类型', '详情', '支付方', '收款方', '货币', '数额', '是否借款', '借款人', '备注', '日期'];
    $colCount = count($headers);

    /* ---------- 1. 构建 sheet1.xml ---------- */
    $rowsXml = [];

    // 表头行（带样式 s="1"）
    $cells = [];
    for ($c = 0; $c < $colCount; $c++) {
        $col = excel_col_letter($c + 1);
        $val = xml_escape($headers[$c]);
        $cells[] = '<c r="' . $col . '1" t="inlineStr" s="1"><is><t>' . $val . '</t></is></c>';
    }
    $rowsXml[] = '<row r="1" ht="26" customHeight="1">' . implode('', $cells) . '</row>';

    // 数据行
    $rowIdx = 2;
    foreach ($entries as $e) {
        $amount = isset($e['amount']) ? (float)$e['amount'] : 0.0;
        // 使用 sprintf 避免科学计数法，保留 2 位小数
        $amountStr = number_format($amount, 2, '.', '');
        $values = [
            $e['type'] ?? '',
            $e['detail'] ?? '',
            $e['payer'] ?? '',
            $e['payee'] ?? '',
            currency_name($e['currency'] ?? 'CNY'),
            $amountStr, // 数字
            !empty($e['is_loan']) ? '是' : '否',
            $e['borrower'] ?? '',
            $e['remark'] ?? '',
            $e['entry_date'] ?? '',
        ];
        $cells = [];
        for ($c = 0; $c < $colCount; $c++) {
            $col = excel_col_letter($c + 1);
            $ref = $col . $rowIdx;
            $v = $values[$c];
            if ($c === 5) {
                // 数额列：数字
                $cells[] = '<c r="' . $ref . '" s="2"><v>' . $amountStr . '</v></c>';
            } else {
                $cells[] = '<c r="' . $ref . '" t="inlineStr" s="2"><is><t xml:space="preserve">' . xml_escape((string)$v) . '</t></is></c>';
            }
        }
        $rowsXml[] = '<row r="' . $rowIdx . '">' . implode('', $cells) . '</row>';
        $rowIdx++;
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<cols>'
        . '<col min="1" max="1" width="8" customWidth="1"/>'    // 类型
        . '<col min="2" max="2" width="28" customWidth="1"/>'   // 详情
        . '<col min="3" max="3" width="12" customWidth="1"/>'   // 支付方
        . '<col min="4" max="4" width="12" customWidth="1"/>'   // 收款方
        . '<col min="5" max="5" width="16" customWidth="1"/>'   // 货币（加宽以容纳中英文）
        . '<col min="6" max="6" width="12" customWidth="1"/>'   // 数额
        . '<col min="7" max="7" width="10" customWidth="1"/>'   // 是否借款
        . '<col min="8" max="8" width="12" customWidth="1"/>'   // 借款人
        . '<col min="9" max="9" width="24" customWidth="1"/>'   // 备注
        . '<col min="10" max="10" width="14" customWidth="1"/>' // 日期
        . '</cols>'
        . '<sheetData>' . implode('', $rowsXml) . '</sheetData>'
        . '</worksheet>';

    /* ---------- 2. workbook.xml ---------- */
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xml_escape(mb_substr($book['name'] ?? '账本', 0, 28))
        . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    /* ---------- 3. workbook.xml.rels ---------- */
    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    /* ---------- 4. styles.xml ---------- */
    // s=0 默认；s=1 表头（加粗 + 底色 + 居中）；s=2 数据（边框）
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF5B8DEF"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FFD0D0D0"/></left>'
        . '<right style="thin"><color rgb="FFD0D0D0"/></right>'
        . '<top style="thin"><color rgb="FFD0D0D0"/></top>'
        . '<bottom style="thin"><color rgb="FFD0D0D0"/></bottom>'
        . '<diagonal/>'
        . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="3">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1 表头
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center"/></xf>' // 2 数据
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    /* ---------- 5. [Content_Types].xml ---------- */
    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    /* ---------- 6. _rels/.rels ---------- */
    $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    /* ---------- 7. 打包成 zip（.xlsx） ---------- */
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmpFile === false) {
        throw new RuntimeException('无法创建临时文件');
    }
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpFile);
        throw new RuntimeException('无法创建 xlsx');
    }
    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $rootRelsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $content = file_get_contents($tmpFile);
    @unlink($tmpFile);
    if ($content === false) {
        throw new RuntimeException('读取 xlsx 失败');
    }
    return $content;
}
