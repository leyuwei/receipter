# receipter · 记个小账

> 轻量好用的个人 / 团体记账本，PHP + MySQL 开箱即用。

## 功能特性

- **多账本管理**：创建 / 打开账本，每个账本自动生成唯一标识（友好名称 + 随机后缀，如 `旅行账本-aB3k`），支持通过名称快速访问与链接收藏。
- **账目记录**：支持收入、支出、转账三种类型；可记录详情、支付方 / 收款方、货币（CNY / USD / EUR / JPY 等 8 种）、金额、日期、备注。
- **借款标记**：可对单条账目标记为借款，并填写借款人，便于事后结算。
- **拖拽排序**：账目支持手动拖拽排序，也可按日期、金额、类型、货币、创建时间排序。
- **汇总统计**：实时显示账目数、支出合计、收入合计。
- **导入 / 导出**：支持导出 JSON（可再次导入）和 CSV（带 BOM，Excel 可直接打开），方便备份与数据迁移。
- **移动端友好**：响应式布局，适配手机和桌面浏览器。

## 技术栈

- **后端**：PHP 8+（PDO 扩展）
- **数据库**：MySQL 5.7+（utf8mb4）
- **前端**：原生 HTML / CSS / JavaScript，无前端框架依赖

## 目录结构

```
receipter/
├── index.php              # 首页：创建 / 打开账本
├── book.php               # 账本详情页
├── config.php             # 数据库与应用基础配置
├── .htaccess              # Apache URL 重写与安全规则（可选）
├── api/                   # 后端 JSON API
│   ├── book.php           # 账本：创建 / 查询
│   ├── entry.php          # 账目：增删改查 + 拖拽排序
│   ├── export.php         # 导出：JSON / CSV
│   └── import.php         # 导入：JSON
├── assets/
│   ├── css/style.css      # 样式
│   ├── img/logo.svg       # Logo
│   └── js/app.js          # 前端交互逻辑
├── includes/
│   └── functions.php      # 公共函数库（响应、校验、随机码等）
└── sql/
    └── init.sql           # 数据库初始化脚本
```

## 快速开始

### 1. 环境准备

确保已安装 PHP 8+ 和 MySQL 5.7+（也可使用 XAMPP / WAMP / 宝塔等集成环境）。

### 2. 初始化数据库

创建数据库并导入表结构：

```sql
CREATE DATABASE receipter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

然后导入初始化脚本：

```bash
mysql -u root -p receipter < sql/init.sql
```

或在 phpMyAdmin / MySQL 客户端中直接执行 [sql/init.sql](sql/init.sql) 的全部内容。

### 3. 修改配置

编辑 [config.php](config.php)，填写你的数据库连接信息：

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'receipter');
define('DB_USER', 'team_mgr');       // 修改为你的数据库用户名
define('DB_PASS', '');               // 修改为你的数据库密码

// 部署在子目录时设置为子路径，如 '/receipter'；部署在根域名则设置为 ''
define('APP_BASE_PATH', '/receipter');
```

### 4. 部署运行

将项目放置到 Web 服务器的站点目录下（例如 `htdocs/receipter`），在浏览器访问：

```
http://localhost/receipter/
```

即可开始使用。

## API 说明

所有 API 均返回统一 JSON 格式：`{ "ok": true|false, "data"?: ..., "error"?: "..." }`。

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `api/book.php?op=get&code=xxx` | GET | 获取账本信息及其全部账目 |
| `api/book.php` | POST | 创建账本，body：`{ "op": "create", "name": "账本名称" }` |
| `api/entry.php` | POST | 账目操作，body：`{ "op": "create|update|delete|reorder", ... }` |
| `api/export.php?code=xxx&format=json|csv` | GET | 导出账本为 JSON 或 CSV |
| `api/import.php` | POST | 导入 JSON 账目（multipart/form-data，字段 `book_id` + `file`） |

## 安全说明

- 全部数据库操作使用 **PDO 预处理语句**，防止 SQL 注入。
- `.htaccess` 默认禁止访问 `.sql`、`.md` 等敏感文件（Apache 环境）。
- 账本通过随机后缀实现不可猜测的访问路径，相当于简易鉴权。
- 生产环境部署时，请将 [config.php](config.php) 中的 `display_errors` 关闭。

## License

[MIT License](LICENSE)
