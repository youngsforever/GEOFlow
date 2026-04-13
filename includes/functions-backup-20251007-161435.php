<?php
/**
 * 飞书宝藏库 - 公共函数库
 * 
 * @author 姚金刚
 * @version 1.0
 * @date 2025-10-03
 */

// 防止直接访问
if (!defined('FEISHU_TREASURE')) {
    die('Access denied');
}

/**
 * 管理员认证相关函数
 */

// 检查管理员是否已登录
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// 管理员登录
function admin_login($username, $password) {
    global $db;

    $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin) {
        // 兼容旧的MD5密码和新的password_hash密码
        $password_valid = false;

        if (strlen($admin['password']) === 32) {
            // 旧的MD5密码
            $password_valid = (md5($password) === $admin['password']);
        } else {
            // 新的password_hash密码
            $password_valid = password_verify($password, $admin['password']);
        }

        if ($password_valid) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            // 更新最后登录时间（如果表结构支持）
            try {
                $update_stmt = $db->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->execute([$admin['id']]);
            } catch (Exception $e) {
                // 忽略错误，可能是旧数据库结构
            }

            return true;
        }
    }

    return false;
}

// 管理员登出
function admin_logout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

// 要求管理员登录
function require_admin_login() {
    if (!is_admin_logged_in()) {
        header('Location: /admin/');
        exit;
    }
}

// 获取管理员信息
function get_admin_info($admin_id) {
    global $db;

    $stmt = $db->prepare("SELECT id, username, created_at FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    return $stmt->fetch();
}

// 更新管理员信息
function update_admin($admin_id, $username, $password = null) {
    global $db;

    try {
        if ($password) {
            // 更新用户名和密码
            $stmt = $db->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $admin_id]);
        } else {
            // 只更新用户名
            $stmt = $db->prepare("UPDATE admins SET username = ? WHERE id = ?");
            $stmt->execute([$username, $admin_id]);
        }
        return true;
    } catch (Exception $e) {
        write_log("Update admin error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// 检查用户名是否已存在
function is_username_exists($username, $exclude_id = null) {
    global $db;

    if ($exclude_id) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM admins WHERE username = ? AND id != ?");
        $stmt->execute([$username, $exclude_id]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM admins WHERE username = ?");
        $stmt->execute([$username]);
    }

    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * 分类相关函数
 */

// 获取所有分类
function get_categories() {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// 根据ID获取分类
function get_category_by_id($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// 获取分类下的网址数量
function get_category_link_count($category_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM links WHERE category_id = ?");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 文章相关函数
 */

// 获取置顶推荐文章
function get_featured_articles($limit = 6) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.is_featured = 1 AND a.status = 'published'
        ORDER BY a.published_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// 根据分类获取文章
function get_articles_by_category($category_id, $page = 1, $per_page = 12) {
    global $db;

    $offset = ($page - 1) * $per_page;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.category_id = ? AND a.status = 'published'
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$category_id, $per_page, $offset]);
    return $stmt->fetchAll();
}

// 获取所有文章（分页）
function get_all_articles($page = 1, $per_page = 12, $search = '') {
    global $db;

    $offset = ($page - 1) * $per_page;
    $where = "a.status = 'published'";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.content LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE {$where}
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ");

    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// 根据标签获取文章
function get_articles_by_tag($tag_slug, $page = 1, $per_page = 12) {
    global $db;

    $offset = ($page - 1) * $per_page;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        INNER JOIN article_tags at ON a.id = at.article_id
        INNER JOIN tags t ON at.tag_id = t.id
        WHERE t.slug = ? AND a.status = 'published'
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$tag_slug, $per_page, $offset]);
    return $stmt->fetchAll();
}

// 根据ID获取文章详情
function get_article_by_id($id) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.id = ? AND a.status = 'published'
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// 根据slug获取文章详情
function get_article_by_slug($slug) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.slug = ? AND a.status = 'published'
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

// 增加文章访问量
function increment_article_views($id) {
    global $db;

    $stmt = $db->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$id]);

    // 记录访问日志
    $stmt = $db->prepare("INSERT INTO view_logs (article_id, ip_address, user_agent) VALUES (?, ?, ?)");
    $stmt->execute([$id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
}

// 增加文章点赞数
function increment_article_likes($id) {
    global $db;

    $stmt = $db->prepare("UPDATE articles SET like_count = like_count + 1 WHERE id = ?");
    return $stmt->execute([$id]);
}

// 获取相关推荐文章
function get_related_articles($article_id, $category_id, $limit = 4) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.category_id = ? AND a.id != ? AND a.status = 'published'
        ORDER BY a.view_count DESC, a.like_count DESC
        LIMIT ?
    ");
    $stmt->execute([$category_id, $article_id, $limit]);
    return $stmt->fetchAll();
}

// 获取文章的标签
function get_article_tags($article_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT t.*
        FROM tags t
        INNER JOIN article_tags at ON t.id = at.tag_id
        WHERE at.article_id = ?
        ORDER BY t.name
    ");
    $stmt->execute([$article_id]);
    return $stmt->fetchAll();
}

// 获取所有标签
function get_all_tags() {
    global $db;

    $stmt = $db->prepare("SELECT * FROM tags ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

// 根据slug获取标签
function get_tag_by_slug($slug) {
    global $db;

    $stmt = $db->prepare("SELECT * FROM tags WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * 搜索相关函数
 */

// 搜索文章
function search_articles($keyword, $page = 1, $per_page = 12) {
    return get_all_articles($page, $per_page, $keyword);
}

// 获取搜索结果总数
function get_search_count($keyword) {
    global $db;

    $searchTerm = "%{$keyword}%";
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM articles
        WHERE status = 'published' AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $result = $stmt->fetch();
    return $result['count'];
}

// 获取分类下的文章数量
function get_category_article_count($category_id) {
    global $db;

    $stmt = $db->prepare("SELECT COUNT(*) as count FROM articles WHERE category_id = ? AND status = 'published'");
    $stmt->execute([$category_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

// 获取标签下的文章数量
function get_tag_article_count($tag_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM articles a
        INNER JOIN article_tags at ON a.id = at.article_id
        WHERE at.tag_id = ? AND a.status = 'published'
    ");
    $stmt->execute([$tag_id]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * 统计相关函数
 */

// 获取网站统计数据
function get_site_stats() {
    global $db;

    $stats = [];

    // 总文章数
    $stmt = $db->query("SELECT COUNT(*) as count FROM articles WHERE status = 'published'");
    $stats['total_articles'] = $stmt->fetch()['count'];

    // 总分类数
    $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
    $stats['total_categories'] = $stmt->fetch()['count'];

    // 总标签数
    $stmt = $db->query("SELECT COUNT(*) as count FROM tags");
    $stats['total_tags'] = $stmt->fetch()['count'];

    // 总访问量
    $stmt = $db->query("SELECT SUM(view_count) as total FROM articles WHERE status = 'published'");
    $stats['total_views'] = $stmt->fetch()['total'] ?: 0;

    // 总点赞数
    $stmt = $db->query("SELECT SUM(like_count) as total FROM articles WHERE status = 'published'");
    $stats['total_likes'] = $stmt->fetch()['total'] ?: 0;

    // 今日访问量
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM view_logs WHERE DATE(created_at) = DATE('now')");
    $stmt->execute();
    $stats['today_views'] = $stmt->fetch()['count'];

    return $stats;
}

/**
 * 分页相关函数
 */

// 生成分页HTML
function generate_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) return '';
    
    $html = '<nav class="flex justify-center mt-8">';
    $html .= '<div class="flex space-x-1">';
    
    // 上一页
    if ($current_page > 1) {
        $prev_url = $base_url . ($current_page - 1);
        $html .= '<a href="' . $prev_url . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">上一页</a>';
    }
    
    // 页码
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $base_url . '1" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">1</a>';
        if ($start > 2) {
            $html .= '<span class="px-3 py-2 text-sm font-medium text-gray-500">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . $i . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">' . $i . '</a>';
        }
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="px-3 py-2 text-sm font-medium text-gray-500">...</span>';
        }
        $html .= '<a href="' . $base_url . $total_pages . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">' . $total_pages . '</a>';
    }
    
    // 下一页
    if ($current_page < $total_pages) {
        $next_url = $base_url . ($current_page + 1);
        $html .= '<a href="' . $next_url . '" class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">下一页</a>';
    }
    
    $html .= '</div>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * 生成基础Schema.org结构化数据
 */
function generate_base_schema($type = 'WebPage', $name = '', $description = '', $url = '') {
    $site_title = get_setting('site_title', SITE_NAME);
    $site_description = get_setting('site_description', SITE_DESCRIPTION);

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $type,
        'name' => $name ?: $site_title,
        'description' => $description ?: $site_description,
        'url' => $url ?: SITE_URL,
        'publisher' => [
            '@type' => 'Organization',
            'name' => $site_title,
            'url' => SITE_URL,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => SITE_URL . '/assets/images/logo.png',
                'width' => 200,
                'height' => 200
            ]
        ]
    ];

    return $schema;
}

/**
 * 生成面包屑导航Schema
 */
function generate_breadcrumb_schema($breadcrumbs) {
    $items = [];
    foreach ($breadcrumbs as $index => $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url']
        ];
    }

    return [
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ];
}

/**
 * 生成软件应用Schema（用于飞书文档链接）
 */
function generate_software_schema($link) {
    return [
        '@type' => 'SoftwareApplication',
        'name' => $link['title'],
        'description' => truncate_string($link['description'] ?: $link['title'], 160),
        'url' => $link['url'],
        'applicationCategory' => $link['category_name'],
        'operatingSystem' => 'Web',
        'keywords' => $link['tags'],
        'dateCreated' => date('c', strtotime($link['created_at'])),
        'dateModified' => date('c', strtotime($link['updated_at'])),
        'aggregateRating' => [
            '@type' => 'AggregateRating',
            'ratingValue' => min(5, max(1, round(($link['like_count'] / max(1, $link['view_count'])) * 5, 1))),
            'reviewCount' => $link['like_count'],
            'bestRating' => '5',
            'worstRating' => '1'
        ],
        'interactionStatistic' => [
            [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/ViewAction',
                'userInteractionCount' => $link['view_count']
            ],
            [
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/LikeAction',
                'userInteractionCount' => $link['like_count']
            ]
        ],
        'offers' => [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'CNY',
            'availability' => 'https://schema.org/InStock'
        ]
    ];
}

/**
 * 输出JSON-LD Schema标签
 */
function output_schema($schema) {
    echo '<script type="application/ld+json">' . "\n";
    echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n" . '</script>' . "\n";
}

/**
 * 简单的Markdown转HTML函数
 */
function markdown_to_html($text) {
    if (empty($text)) {
        return '';
    }

    // 转义HTML特殊字符
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 处理换行
    $text = nl2br($text);

    // 处理粗体 **text** 或 __text__
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);

    // 处理斜体 *text* 或 _text_
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);

    // 处理代码 `code`
    $text = preg_replace('/`(.*?)`/', '<code class="bg-gray-100 px-1 py-0.5 rounded text-sm">$1</code>', $text);

    // 处理链接 [text](url)
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="text-blue-600 hover:text-blue-800 underline" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    // 处理标题 ## Title
    $text = preg_replace('/^### (.*$)/m', '<h3 class="text-lg font-semibold text-gray-900 mt-6 mb-3">$1</h3>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h2 class="text-xl font-semibold text-gray-900 mt-6 mb-4">$1</h2>', $text);
    $text = preg_replace('/^# (.*$)/m', '<h1 class="text-2xl font-bold text-gray-900 mt-6 mb-4">$1</h1>', $text);

    // 处理无序列表 - item 或 * item
    $text = preg_replace('/^[\-\*] (.*)$/m', '<li class="ml-4">$1</li>', $text);
    $text = preg_replace('/(<li class="ml-4">.*<\/li>)/s', '<ul class="list-disc list-inside space-y-1 my-4">$1</ul>', $text);

    // 处理有序列表 1. item
    $text = preg_replace('/^\d+\. (.*)$/m', '<li class="ml-4">$1</li>', $text);

    // 处理引用 > text
    $text = preg_replace('/^> (.*)$/m', '<div class="custom-blockquote" style="background: #f9fafb !important; background-color: #f9fafb !important; padding: 1rem !important; margin: 1.5rem 0 !important; border-radius: 0.5rem !important; color: #4b5563 !important; font-style: italic !important; display: block !important; font-size: 15px !important; line-height: 1.6 !important; font-weight: normal !important; position: relative !important; z-index: 1 !important;">$1</div>', $text);

    return $text;
}

/**
 * 模板相关函数
 */

// 包含头部模板
function include_header($title = '', $description = '', $keywords = '') {
    $site_title = !empty($title) ? $title . ' - ' . get_setting('site_title', SITE_NAME) : get_setting('site_title', SITE_NAME);
    $site_description = !empty($description) ? $description : get_setting('site_description', SITE_DESCRIPTION);
    $site_keywords = !empty($keywords) ? $keywords : get_setting('site_keywords', SITE_KEYWORDS);
    
    include __DIR__ . '/../templates/header.php';
}

// 包含底部模板
function include_footer() {
    include __DIR__ . '/../templates/footer.php';
}

/**
 * 工具函数
 */

// 截取字符串
function truncate_string($string, $length = 100, $suffix = '...') {
    if (mb_strlen($string, 'UTF-8') <= $length) {
        return $string;
    }
    return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
}

// 解析标签
function parse_tags($tags_string) {
    if (empty($tags_string)) return [];
    return array_filter(array_map('trim', explode(',', $tags_string)));
}

// 生成文章摘要
function generate_excerpt($content, $length = 200) {
    // 移除HTML标签
    $text = strip_tags($content);
    // 移除多余的空白字符
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    return truncate_string($text, $length);
}

// 生成URL友好的slug
function generate_slug($text) {
    // 如果是中文标题，使用内容hash生成简短slug
    if (preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
        return substr(md5($text . time()), 0, 6);
    }

    // 转换为小写
    $slug = strtolower($text);
    // 移除特殊字符，保留字母数字和连字符
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    // 替换空格和多个连字符为单个连字符
    $slug = preg_replace('/[\s_-]+/', '-', $slug);
    // 移除首尾的连字符
    $slug = trim($slug, '-');

    // 如果处理后为空，使用hash格式
    if (empty($slug)) {
        $slug = substr(md5($text . time()), 0, 6);
    }

    return $slug;
}

// 获取作者设置
function get_author_settings() {
    global $db;
    $stmt = $db->prepare("SELECT * FROM author_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        // 返回默认设置
        return [
            'avatar_url' => '',
            'name' => '姚金刚',
            'title' => '全栈开发工程师',
            'bio' => '专注于技术分享和产品开发，热爱编程和创新。',
            'location' => '中国',
            'website' => '',
            'email' => '',
            'github' => '',
            'twitter' => '',
            'linkedin' => '',
            'wechat' => '',
            'skills' => 'PHP,JavaScript,Python,Java,Go',
            'frameworks' => 'Laravel,Vue.js,React,Django,Spring Boot',
            'tools' => 'Docker,Git,MySQL,Redis,Nginx',
            'databases' => 'MySQL,PostgreSQL,MongoDB,SQLite'
        ];
    }

    return $settings;
}

// 检查slug是否唯一
function is_slug_unique($slug, $table = 'articles', $exclude_id = null) {
    global $db;

    $sql = "SELECT COUNT(*) as count FROM {$table} WHERE slug = ?";
    $params = [$slug];

    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    return $result['count'] == 0;
}

// 生成唯一的slug
function generate_unique_slug($text, $table = 'articles', $exclude_id = null) {
    $base_slug = generate_slug($text);
    $slug = $base_slug;
    $counter = 1;

    while (!is_slug_unique($slug, $table, $exclude_id)) {
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }

    return $slug;
}

// 获取文章阅读时间估算（分钟）
function get_reading_time($content) {
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200); // 假设每分钟阅读200个单词
    return max(1, $reading_time);
}

// 获取最新文章
function get_recent_articles($limit = 5) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        ORDER BY a.published_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// 获取热门文章
function get_popular_articles($limit = 5) {
    global $db;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        ORDER BY a.view_count DESC, a.like_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// 获取文章归档（按年月分组）
function get_article_archives() {
    global $db;

    $stmt = $db->prepare("
        SELECT
            strftime('%Y', published_at) as year,
            strftime('%m', published_at) as month,
            strftime('%Y-%m', published_at) as year_month,
            COUNT(*) as count
        FROM articles
        WHERE status = 'published'
        GROUP BY year_month
        ORDER BY year_month DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// 根据年月获取文章
function get_articles_by_archive($year, $month, $page = 1, $per_page = 12) {
    global $db;

    $offset = ($page - 1) * $per_page;

    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name, c.slug as category_slug
        FROM articles a
        LEFT JOIN categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        AND strftime('%Y', a.published_at) = ?
        AND strftime('%m', a.published_at) = ?
        ORDER BY a.published_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$year, $month, $per_page, $offset]);
    return $stmt->fetchAll();
}

// 获取网站统计数据
function get_stats() {
    global $db;

    $stats = [];

    // 文章统计
    $stmt = $db->query("SELECT COUNT(*) FROM articles WHERE status = 'published'");
    $stats['total_articles'] = $stmt->fetchColumn();

    // 分类统计
    $stmt = $db->query("SELECT COUNT(*) FROM categories");
    $stats['total_categories'] = $stmt->fetchColumn();

    // 标签统计
    $stmt = $db->query("SELECT COUNT(*) FROM tags");
    $stats['total_tags'] = $stmt->fetchColumn();

    // 总访问量
    $stmt = $db->query("SELECT SUM(view_count) FROM articles");
    $stats['total_views'] = $stmt->fetchColumn() ?: 0;

    // 总点赞数
    $stmt = $db->query("SELECT SUM(like_count) FROM articles");
    $stats['total_likes'] = $stmt->fetchColumn() ?: 0;

    return $stats;
}

/**
 * CSRF保护相关函数
 */

// 生成CSRF令牌
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF令牌
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 日志记录函数
 */

// 写入日志
function write_log($message, $level = 'INFO') {
    try {
        $log_file = __DIR__ . '/../logs/system.log';
        $log_dir = dirname($log_file);

        // 确保日志目录存在
        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0755, true)) {
                return false; // 无法创建目录，静默失败
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        return file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
    } catch (Exception $e) {
        // 静默失败，避免日志函数本身导致错误
        return false;
    }
}

/**
 * 设置相关函数
 */

// 获取设置值
function get_setting($key, $default = null) {
    global $db;

    try {
        // 检查表是否存在
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_settings'");
        if (!$stmt->fetch()) {
            return $default;
        }

        $stmt = $db->prepare("SELECT value FROM site_settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        return $result ? $result['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// 设置值
function set_setting($key, $value) {
    global $db;

    try {
        // 检查表是否存在，如果不存在则创建
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_settings'");
        if (!$stmt->fetch()) {
            $db->exec("CREATE TABLE site_settings (
                key TEXT PRIMARY KEY,
                value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }

        $stmt = $db->prepare("INSERT OR REPLACE INTO site_settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        // 不调用write_log避免循环依赖
        return false;
    }
}
