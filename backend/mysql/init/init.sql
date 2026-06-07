-- 设置字符集
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS video_app CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE video_app;

-- 表1：admin_user（管理员用户）
CREATE TABLE IF NOT EXISTS admin_user (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'editor' COMMENT 'super超级管理员 editor编辑',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表2：admin_token（管理员令牌）
CREATE TABLE IF NOT EXISTS admin_token (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    admin_id BIGINT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expire_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表3：video_category（影片分类）
CREATE TABLE IF NOT EXISTS video_category (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COMMENT '分类名称',
    slug VARCHAR(50) NOT NULL COMMENT 'URL标识',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug),
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表4：video（影片）
CREATE TABLE IF NOT EXISTS video (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    category_id BIGINT DEFAULT NULL COMMENT '分类ID',
    title VARCHAR(200) NOT NULL,
    cover_url VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(20) NOT NULL DEFAULT 'movie' COMMENT 'movie电影 series剧集',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '推荐排序值（越小越靠前）',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category_id (category_id),
    INDEX idx_type (type),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表4-1：video_episode（剧集分集）
CREATE TABLE IF NOT EXISTS video_episode (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    video_id BIGINT NOT NULL COMMENT '影片ID',
    episode_no INT NOT NULL COMMENT '集号',
    title VARCHAR(200) DEFAULT NULL COMMENT '分集标题',
    m3u8_url VARCHAR(500) DEFAULT NULL COMMENT 'M3U8播放地址',
    duration_seconds INT DEFAULT NULL COMMENT '时长（秒）',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_video_episode (video_id, episode_no),
    INDEX idx_video_id (video_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表5：video_source（播放源）
CREATE TABLE IF NOT EXISTS video_source (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    video_id BIGINT NOT NULL,
    source_name VARCHAR(50) NOT NULL COMMENT '线路1/线路2',
    m3u8_url VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_video_id (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入种子数据

-- 管理员账号：admin / admin123（密码使用 password_hash）
INSERT INTO admin_user (username, password_hash, role, status, created_at) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super', 1, NOW());

-- 插入分类数据
INSERT INTO video_category (name, slug, sort_order, status, created_at) VALUES
('科幻', 'sci-fi', 1, 1, NOW()),
('动作', 'action', 2, 1, NOW()),
('悬疑', 'suspense', 3, 1, NOW()),
('奇幻', 'fantasy', 4, 1, NOW()),
('纪录片', 'documentary', 5, 1, NOW());

-- 插入测试影片数据（虚构影片，避免版权问题）
INSERT INTO video (category_id, title, cover_url, description, status, created_at, updated_at) VALUES
(1, '星际迷航：时空裂痕', '/uploads/covers/test-cover-1.jpg', '一支探险队在深空发现了神秘的时空裂痕，他们必须在宇宙崩塌前找到回家的路。', 1, NOW(), NOW()),
(2, '暗影猎人', '/uploads/covers/test-cover-2.jpg', '一位神秘的赏金猎人在黑暗的城市中追踪危险的超自然生物，揭开隐藏的阴谋。', 1, NOW(), NOW()),
(3, '记忆碎片', '/uploads/covers/test-cover-3.jpg', '一个失忆的男子醒来后发现自己卷入了一场危险的游戏，必须拼凑记忆找出真相。', 1, NOW(), NOW()),
(1, '未来都市2099', '/uploads/covers/test-cover-4.jpg', '在2099年的未来都市，一名黑客发现了改变世界的秘密，引发了一场革命。', 1, NOW(), NOW()),
(5, '深海探秘', '/uploads/covers/test-cover-5.jpg', '科学家团队潜入深海最深处，发现了一个未知的文明和令人震惊的秘密。', 1, NOW(), NOW()),
(4, '魔法学院：觉醒', '/uploads/covers/test-cover-6.jpg', '一个普通学生进入神秘的魔法学院，发现自己拥有改变世界的强大力量。', 1, NOW(), NOW()),
(2, '末日余生', '/uploads/covers/test-cover-7.jpg', '在末日后的废土世界，幸存者们为了生存和希望展开艰难的旅程。', 0, NOW(), NOW()),
(2, '机械战警：重生', '/uploads/covers/test-cover-8.jpg', '一名被改造的机械战警在执行任务时发现了自己的人性，面临艰难的选择。', 1, NOW(), NOW()),
(1, '平行世界', '/uploads/covers/test-cover-9.jpg', '物理学家意外打开了通往平行世界的大门，遇见了另一个自己。', 1, NOW(), NOW()),
(3, '时间旅行者', '/uploads/covers/test-cover-10.jpg', '一位时间旅行者试图改变过去的悲剧，却发现每次改变都会带来意想不到的后果。', 1, NOW(), NOW());

-- 表6：audit_log（操作审计日志）
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    admin_id BIGINT DEFAULT NULL COMMENT '操作人ID',
    admin_username VARCHAR(50) DEFAULT NULL COMMENT '操作人用户名',
    action VARCHAR(50) NOT NULL COMMENT '动作类型：create/update/delete/publish/unpublish/login/logout',
    resource_type VARCHAR(50) NOT NULL COMMENT '资源类型：video/source/admin/auth',
    resource_id VARCHAR(100) DEFAULT NULL COMMENT '资源ID',
    summary JSON DEFAULT NULL COMMENT '变更摘要JSON',
    ip VARCHAR(45) DEFAULT NULL COMMENT '操作IP地址',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_resource_type (resource_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表7：banner（运营轮播图）
CREATE TABLE IF NOT EXISTS banner (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL COMMENT '轮播标题',
    image_url VARCHAR(500) NOT NULL COMMENT '图片URL',
    jump_type VARCHAR(20) NOT NULL DEFAULT 'url' COMMENT '跳转类型：video影片详情/url外链',
    jump_target VARCHAR(500) DEFAULT NULL COMMENT '跳转目标：影片ID或URL',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    start_time DATETIME DEFAULT NULL COMMENT '生效开始时间',
    end_time DATETIME DEFAULT NULL COMMENT '生效结束时间',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order),
    INDEX idx_time (start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入测试轮播图数据
INSERT INTO banner (title, image_url, jump_type, jump_target, sort_order, status, start_time, end_time, created_at, updated_at) VALUES
('星际迷航重磅推荐', '/uploads/covers/test-cover-1.jpg', 'video', '1', 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW()),
('暗影猎人火热上线', '/uploads/covers/test-cover-2.jpg', 'video', '2', 2, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW()),
('官方活动首页', '/uploads/covers/test-cover-3.jpg', 'url', 'https://example.com/promo', 3, 1, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW());

-- 插入播放源数据（m3u8链接）
INSERT INTO video_source (video_id, source_name, m3u8_url, created_at) VALUES
(1, '线路1', 'https://cdn1.example.com/video1/index.m3u8', NOW()),
(1, '线路2', 'https://cdn2.example.com/video1/index.m3u8', NOW()),
(2, '线路1', 'https://cdn1.example.com/video2/index.m3u8', NOW()),
(2, '线路2', 'https://cdn2.example.com/video2/index.m3u8', NOW()),
(3, '线路1', 'https://cdn1.example.com/video3/index.m3u8', NOW()),
(4, '线路1', 'https://cdn1.example.com/video4/index.m3u8', NOW()),
(4, '线路2', 'https://cdn2.example.com/video4/index.m3u8', NOW()),
(5, '线路1', 'https://cdn1.example.com/video5/index.m3u8', NOW()),
(6, '线路1', 'https://cdn1.example.com/video6/index.m3u8', NOW()),
(6, '线路2', 'https://cdn2.example.com/video6/index.m3u8', NOW()),
(7, '线路1', 'https://cdn1.example.com/video7/index.m3u8', NOW()),
(8, '线路1', 'https://cdn1.example.com/video8/index.m3u8', NOW()),
(8, '线路2', 'https://cdn2.example.com/video8/index.m3u8', NOW()),
(9, '线路1', 'https://cdn1.example.com/video9/index.m3u8', NOW()),
(10, '线路1', 'https://cdn1.example.com/video10/index.m3u8', NOW());

-- 表8：user_feedback（用户反馈）
CREATE TABLE IF NOT EXISTS user_feedback (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    contact_info VARCHAR(200) DEFAULT NULL COMMENT '联系人信息（手机号/邮箱等）',
    content TEXT NOT NULL COMMENT '反馈内容',
    source_channel VARCHAR(50) NOT NULL DEFAULT 'app' COMMENT '来源渠道：app/website/wechat/other',
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '处理状态：pending待处理/processing处理中/closed已关闭',
    handle_note TEXT DEFAULT NULL COMMENT '处理备注',
    handled_by BIGINT DEFAULT NULL COMMENT '处理人ID',
    handled_at DATETIME DEFAULT NULL COMMENT '处理时间',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_source_channel (source_channel),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表9：user_feedback_history（用户反馈处理历史）
CREATE TABLE IF NOT EXISTS user_feedback_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    feedback_id BIGINT NOT NULL COMMENT '反馈ID',
    admin_id BIGINT DEFAULT NULL COMMENT '操作人ID',
    admin_username VARCHAR(50) DEFAULT NULL COMMENT '操作人用户名',
    action VARCHAR(50) NOT NULL COMMENT '动作：create/status_update/note_update',
    old_status VARCHAR(20) DEFAULT NULL COMMENT '原状态',
    new_status VARCHAR(20) DEFAULT NULL COMMENT '新状态',
    note TEXT DEFAULT NULL COMMENT '备注内容',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_feedback_id (feedback_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入测试反馈数据
INSERT INTO user_feedback (contact_info, content, source_channel, status, handle_note, handled_at, created_at) VALUES
('13800138000', '观看《星际迷航》时视频卡顿，希望能优化播放流畅度。', 'app', 'pending', NULL, NULL, NOW()),
('user@example.com', '希望增加收藏功能，方便追剧。', 'website', 'processing', '已记录需求，正在与产品确认排期。', NOW(), DATE_SUB(NOW(), INTERVAL 2 DAY)),
('微信用户小明', '分类太少，希望增加喜剧类。', 'wechat', 'closed', '已在新分类规划中加入喜剧分类，下个版本上线。', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
('13900139000', '搜索功能找不到想要的影片，建议优化搜索。', 'app', 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(NULL, '夜间模式太暗了，能否调节亮度？', 'app', 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- 表10：hot_keyword（热搜关键词）
CREATE TABLE IF NOT EXISTS hot_keyword (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    keyword VARCHAR(100) NOT NULL COMMENT '关键词',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    click_count INT NOT NULL DEFAULT 0 COMMENT '点击次数',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_keyword (keyword),
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order),
    INDEX idx_click_count (click_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入测试热搜关键词数据
INSERT INTO hot_keyword (keyword, sort_order, status, click_count, created_at, updated_at) VALUES
('星际迷航', 1, 1, 12580, NOW(), NOW()),
('科幻电影', 2, 1, 9860, NOW(), NOW()),
('暗影猎人', 3, 1, 8740, NOW(), NOW()),
('动作大片', 4, 1, 7620, NOW(), NOW()),
('记忆碎片', 5, 1, 6530, NOW(), NOW()),
('悬疑推理', 6, 1, 5420, NOW(), NOW()),
('未来都市', 7, 0, 4310, NOW(), NOW()),
('魔法学院', 8, 1, 3200, NOW(), NOW());

-- 表11：system_config（系统配置）
CREATE TABLE IF NOT EXISTS system_config (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL COMMENT '配置键（唯一）',
    config_value TEXT COMMENT '配置值',
    description VARCHAR(500) DEFAULT NULL COMMENT '配置描述',
    config_group VARCHAR(50) NOT NULL DEFAULT 'basic' COMMENT '分组：basic基础/list列表/security安全',
    value_type VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型：string/number/boolean/email',
    is_sensitive TINYINT NOT NULL DEFAULT 0 COMMENT '是否敏感（仅super可见/编辑）',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_config_key (config_key),
    INDEX idx_config_group (config_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入系统配置初始数据
INSERT INTO system_config (config_key, config_value, description, config_group, value_type, is_sensitive, sort_order) VALUES
('site_name', '影视管理平台', '站点名称，显示在后台页面标题等位置', 'basic', 'string', 0, 1),
('support_email', 'support@example.com', '客服邮箱，展示给用户用于联系支持', 'basic', 'email', 0, 2),
('default_page_size', '20', '后台列表默认每页显示条数', 'list', 'number', 0, 1),
('enable_recommend_sort', '1', '是否开启推荐排序功能（1开启 0关闭）', 'list', 'boolean', 0, 2),
('login_fail_lock_threshold', '5', '管理员登录失败锁定阈值（超过此次数账号将被临时锁定）', 'security', 'number', 1, 1);

-- 表12：actor（演员）
CREATE TABLE IF NOT EXISTS actor (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COMMENT '演员姓名',
    avatar_url VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    bio TEXT DEFAULT NULL COMMENT '演员简介',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表13：video_actor（影片-演员关联）
CREATE TABLE IF NOT EXISTS video_actor (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    video_id BIGINT NOT NULL COMMENT '影片ID',
    actor_id BIGINT NOT NULL COMMENT '演员ID',
    role_name VARCHAR(100) DEFAULT NULL COMMENT '角色名',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_video_actor (video_id, actor_id),
    INDEX idx_video_id (video_id),
    INDEX idx_actor_id (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入测试演员数据
INSERT INTO actor (name, avatar_url, bio, status, created_at, updated_at) VALUES
('张明远', '/uploads/covers/test-cover-1.jpg', '实力派男演员，曾出演多部科幻大片，以沉稳内敛的表演风格著称。', 1, NOW(), NOW()),
('李雪婷', '/uploads/covers/test-cover-2.jpg', '新生代女演员，凭借悬疑片《记忆碎片》获得广泛关注。', 1, NOW(), NOW()),
('王浩然', '/uploads/covers/test-cover-3.jpg', '动作明星，擅长武打戏，被誉为"新一代功夫担当"。', 1, NOW(), NOW()),
('陈思琪', '/uploads/covers/test-cover-4.jpg', '国民女演员，戏路宽广，从文艺片到商业片均有出色表现。', 1, NOW(), NOW()),
('刘子墨', '/uploads/covers/test-cover-5.jpg', '喜剧演员出身，近年转型正剧，表演层次丰富。', 1, NOW(), NOW()),
('赵晓彤', '/uploads/covers/test-cover-6.jpg', '童星出道，青年演技派代表人物之一。', 0, NOW(), NOW());

-- 插入测试影片-演员关联数据
INSERT INTO video_actor (video_id, actor_id, role_name, sort_order, created_at) VALUES
(1, 1, '船长 杰克', 1, NOW()),
(1, 4, '科学官 艾拉', 2, NOW()),
(2, 3, '暗影猎人 凯恩', 1, NOW()),
(2, 2, '神秘女子 莉莉', 2, NOW()),
(3, 2, '失忆者 艾伦', 1, NOW()),
(3, 5, '医生 马克', 2, NOW()),
(4, 1, '黑客 尼尔', 1, NOW()),
(4, 3, '反抗军首领 维克多', 2, NOW()),
(6, 4, '魔法导师 艾琳', 1, NOW()),
(6, 6, '学生 爱丽丝', 2, NOW()),
(8, 3, '机械战警 亚历克斯', 1, NOW()),
(9, 1, '物理学家 戴维', 1, NOW()),
(9, 4, '另一个自己 琳达', 2, NOW()),
(10, 5, '时间旅行者 亨利', 1, NOW()),
(10, 2, '女主角 克莱尔', 2, NOW());

-- 表14：video_tag（影片标签：地区/语言）
CREATE TABLE IF NOT EXISTS video_tag (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL COMMENT '标签名称',
    type VARCHAR(20) NOT NULL COMMENT '标签类型：region地区/language语言',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '排序',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_name_type (name, type),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 表15：video_video_tag（影片-标签关联）
CREATE TABLE IF NOT EXISTS video_video_tag (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    video_id BIGINT NOT NULL COMMENT '影片ID',
    tag_id BIGINT NOT NULL COMMENT '标签ID',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_video_tag (video_id, tag_id),
    INDEX idx_video_id (video_id),
    INDEX idx_tag_id (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 插入测试标签数据
INSERT INTO video_tag (name, type, sort_order, status, created_at) VALUES
('中国大陆', 'region', 1, 1, NOW()),
('中国香港', 'region', 2, 1, NOW()),
('中国台湾', 'region', 3, 1, NOW()),
('美国', 'region', 4, 1, NOW()),
('日本', 'region', 5, 1, NOW()),
('韩国', 'region', 6, 1, NOW()),
('英国', 'region', 7, 1, NOW()),
('法国', 'region', 8, 1, NOW()),
('德国', 'region', 9, 0, NOW()),
('印度', 'region', 10, 1, NOW()),
('汉语普通话', 'language', 1, 1, NOW()),
('粤语', 'language', 2, 1, NOW()),
('英语', 'language', 3, 1, NOW()),
('日语', 'language', 4, 1, NOW()),
('韩语', 'language', 5, 1, NOW()),
('法语', 'language', 6, 1, NOW()),
('德语', 'language', 7, 0, NOW()),
('泰语', 'language', 8, 1, NOW());

-- 插入测试影片-标签关联数据
INSERT INTO video_video_tag (video_id, tag_id) VALUES
(1, 4), (1, 13),
(2, 1), (2, 11),
(3, 1), (3, 11),
(4, 4), (4, 13),
(5, 4), (5, 13),
(6, 1), (6, 11),
(7, 4), (7, 13),
(8, 4), (8, 13),
(9, 4), (9, 13),
(10, 4), (10, 13);

-- 迁移：为已有数据库的 video 表增加 sort_order 字段
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'video' AND COLUMN_NAME = 'sort_order');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE video ADD COLUMN sort_order INT NOT NULL DEFAULT 0 COMMENT ''推荐排序值（越小越靠前）'' AFTER type, ADD INDEX idx_sort_order (sort_order)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 迁移：为已有的影片数据填充默认 sort_order（按 id 升序即按默认顺序）
UPDATE video SET sort_order = id WHERE sort_order = 0;

