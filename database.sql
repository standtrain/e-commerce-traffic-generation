-- 电商引流平台数据库初始化脚本
-- 请在MySQL中执行此脚本创建数据库和表

-- 创建数据库
CREATE DATABASE IF NOT EXISTS shop_db DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 使用数据库
USE shop_db;

-- 创建链接表
CREATE TABLE IF NOT EXISTS links (
    `id` VARCHAR(50) PRIMARY KEY COMMENT '链接ID',
    `platform` VARCHAR(20) NOT NULL COMMENT '平台名称',
    `title` VARCHAR(100) NOT NULL COMMENT '链接标题',
    `url` VARCHAR(500) NOT NULL COMMENT '跳转URL',
    `images` TEXT COMMENT '多张图片路径(JSON数组)',
    `description` TEXT COMMENT '描述说明',
    `display_text` VARCHAR(200) DEFAULT '' COMMENT '手动设置的显示文本',
    `price` VARCHAR(50) DEFAULT '' COMMENT '商品价格',
    `ai_summary` VARCHAR(200) DEFAULT '' COMMENT 'AI总结',
    `ai_detail` TEXT COMMENT 'AI详细介绍',
    `ai_tags` VARCHAR(255) DEFAULT '' COMMENT 'AI标签',
    `status` ENUM('active', 'disabled') DEFAULT 'active' COMMENT '状态',
    `clicks` INT DEFAULT 0 COMMENT '点击次数',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    INDEX `idx_status` (`status`),
    INDEX `idx_platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='引流链接表';

-- 创建用户表
CREATE TABLE IF NOT EXISTS users (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '用户ID',
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL COMMENT '密码',
    `role` ENUM('admin', 'user') DEFAULT 'user' COMMENT '角色：admin-管理员，user-普通用户',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 创建系统设置表
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY COMMENT '设置键',
    `value` TEXT COMMENT '设置值',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

-- 插入示例数据
INSERT INTO links (id, platform, title, url, images, description, display_text, ai_summary, ai_detail, ai_tags, status, clicks, created_at) VALUES
('sample_001', '淘宝', '淘宝旗舰店', 'https://www.taobao.com', '', '淘宝官方旗舰店，全场优惠多多', '', '', '', '', 'active', 0, NOW()),
('sample_002', '京东', '京东自营店', 'https://www.jd.com', '', '京东正品保障，当日达服务', '', '', '', '', 'active', 0, NOW()),
('sample_003', '闲鱼', '闲鱼二手好物', 'https://www.goofish.com', '', '二手闲置物品，环保又实惠', '', '', '', '', 'active', 0, NOW()),
('sample_004', '亚马逊', '亚马逊海外购', 'https://www.amazon.cn', '', '海外正品好货，进口商品优惠', '', '', '', '', 'active', 0, NOW()),
('sample_005', '拼多多', '拼多多团购', 'https://www.pinduoduo.com', '', '拼着买更便宜，团购价更低', '', '', '', '', 'active', 0, NOW()),
('sample_006', '抖音', '抖音小店', 'https://www.douyin.com', '', '直播带货，网红好物推荐', '', '', '', '', 'active', 0, NOW());

-- 插入默认设置
INSERT INTO settings (`key`, `value`) VALUES
('site_name', '电商引流平台'),
('site_description', '精选优质商品，优惠多多'),
('site_icon', ''),
('background_image', ''),
('background_type', 'color'),
('footer_code', ''),
('custom_css', ''),
('ai_enabled', '0'),
('ai_api_url', ''),
('ai_api_key', ''),
('ai_model', 'gpt-3.5-turbo'),
('captcha_enabled', '1');
