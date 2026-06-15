-- ============================================================
-- 记个小账 (Receipter) 数据库初始化脚本
-- 字符集：utf8mb4（完整支持中文及 emoji）
-- 使用方法：
--   1. 创建数据库：CREATE DATABASE receipter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. 导入本文件：mysql -u root -p receipter < sql/init.sql
--   或直接在 phpMyAdmin / MySQL 客户端执行本文件全部内容
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------- 账本表 ----------
DROP TABLE IF EXISTS `receipter_books`;
CREATE TABLE `receipter_books` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(100)    NOT NULL COMMENT '友好名称（用户可见）',
    `code`         VARCHAR(255)    NOT NULL COMMENT '完整账本名 = 友好名称+随机数，用于唯一识别',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账本表';

-- ---------- 账目表 ----------
DROP TABLE IF EXISTS `receipter_entries`;
CREATE TABLE `receipter_entries` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `book_id`      BIGINT UNSIGNED NOT NULL COMMENT '所属账本 ID',
    `type`         VARCHAR(50)     NOT NULL DEFAULT '支出' COMMENT '账目类型：收入/支出/转账等',
    `detail`       VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '账目详情',
    `payer`        VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '支付方',
    `payee`        VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '收款方',
    `currency`     VARCHAR(10)     NOT NULL DEFAULT 'CNY' COMMENT '货币代码',
    `amount`       DECIMAL(18,2)   NOT NULL DEFAULT 0.00 COMMENT '数额',
    `is_loan`      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '是否为借款：0否 1是',
    `borrower`     VARCHAR(100)    NOT NULL DEFAULT '' COMMENT '借款人',
    `remark`       VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '备注',
    `entry_date`   DATE            NULL COMMENT '账目发生日期',
    `sort_order`   INT             NOT NULL DEFAULT 0 COMMENT '拖拽排序，数值越小越靠前',
    `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_book` (`book_id`),
    KEY `idx_sort` (`book_id`, `sort_order`),
    CONSTRAINT `fk_entry_book` FOREIGN KEY (`book_id`)
        REFERENCES `receipter_books` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='账目明细表';

SET FOREIGN_KEY_CHECKS = 1;
