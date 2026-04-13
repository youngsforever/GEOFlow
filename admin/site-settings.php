<?php
/**
 * 智能GEO内容系统 - 网站设置
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_admin.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

// 设置页面标题
$page_title = '网站设置';

$message = '';
$error = '';

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_site_settings':
                $site_name = trim($_POST['site_name'] ?? '');
                $site_subtitle = trim($_POST['site_subtitle'] ?? '');
                $site_description = trim($_POST['site_description'] ?? '');
                $site_keywords = trim($_POST['site_keywords'] ?? '');
                $copyright_info = trim($_POST['copyright_info'] ?? '');
                $site_logo = trim($_POST['site_logo'] ?? '');
                $site_favicon = trim($_POST['site_favicon'] ?? '');
                $analytics_code = trim($_POST['analytics_code'] ?? '');
                $seo_title_template = trim($_POST['seo_title_template'] ?? '');
                $seo_description_template = trim($_POST['seo_description_template'] ?? '');
                $featured_limit = max(1, intval($_POST['featured_limit'] ?? 6));
                $per_page = max(1, intval($_POST['per_page'] ?? 12));

                if (empty($site_name)) {
                    $error = '网站名称不能为空';
                } else {
                    try {
                        // 更新网站设置
                        $settings = [
                            'site_name' => $site_name,
                            'site_title' => $site_name,
                            'site_subtitle' => $site_subtitle,
                            'site_description' => $site_description,
                            'site_keywords' => $site_keywords,
                            'copyright_info' => $copyright_info,
                            'site_logo' => $site_logo,
                            'site_favicon' => $site_favicon,
                            'analytics_code' => $analytics_code,
                            'seo_title_template' => $seo_title_template,
                            'seo_description_template' => $seo_description_template,
                            'featured_limit' => (string) $featured_limit,
                            'per_page' => (string) $per_page
                        ];

                        foreach ($settings as $key => $value) {
                            $update_stmt = $db->prepare("
                                UPDATE site_settings
                                SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
                                WHERE setting_key = ?
                            ");
                            $update_stmt->execute([$value, $key]);

                            if ($update_stmt->rowCount() === 0) {
                                $insert_stmt = $db->prepare("
                                    INSERT INTO site_settings (setting_key, setting_value, updated_at)
                                    VALUES (?, ?, CURRENT_TIMESTAMP)
                                ");
                                $insert_stmt->execute([$key, $value]);
                            }
                        }

                        $message = '网站设置更新成功';
                    } catch (Exception $e) {
                        $error = '更新失败: ' . $e->getMessage();
                    }
                }
                break;

            case 'update_article_detail_ads':
                $postedAds = $_POST['ads'] ?? [];
                if (!is_array($postedAds)) {
                    $postedAds = [];
                }

                $ads = [];
                $validationError = '';
                foreach ($postedAds as $index => $postedAd) {
                    if (!is_array($postedAd)) {
                        continue;
                    }

                    $name = trim((string) ($postedAd['name'] ?? ''));
                    $badge = trim((string) ($postedAd['badge'] ?? ''));
                    $title = trim((string) ($postedAd['title'] ?? ''));
                    $copy = trim((string) ($postedAd['copy'] ?? ''));
                    $buttonText = trim((string) ($postedAd['button_text'] ?? ''));
                    $buttonUrl = normalize_cta_target_url((string) ($postedAd['button_url'] ?? ''));
                    $enabled = !empty($postedAd['enabled']);
                    $id = trim((string) ($postedAd['id'] ?? ''));

                    if ($name === '' && $badge === '' && $title === '' && $copy === '' && $buttonText === '' && $buttonUrl === '') {
                        continue;
                    }

                    if ($copy === '' || $buttonText === '' || $buttonUrl === '') {
                        $validationError = '广告位第 ' . ($index + 1) . ' 条缺少必填内容，请填写广告文案、按钮文案和按钮链接';
                        break;
                    }

                    $ads[] = [
                        'id' => $id !== '' ? $id : uniqid('article_ad_', true),
                        'name' => $name !== '' ? $name : '广告位 ' . (count($ads) + 1),
                        'badge' => $badge,
                        'title' => $title,
                        'copy' => $copy,
                        'button_text' => $buttonText,
                        'button_url' => $buttonUrl,
                        'enabled' => $enabled
                    ];
                }

                if ($validationError !== '') {
                    $error = $validationError;
                } elseif (!set_setting('article_detail_ads', json_encode($ads, JSON_UNESCAPED_UNICODE))) {
                    $error = '广告位保存失败';
                } else {
                    $message = '文章详情页广告位已更新';
                }
                break;
        }
    }
}

// 获取当前网站设置
try {
    $current_settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings ORDER BY id ASC");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $current_settings = [];
}

// 设置默认值
$defaults = [
    'site_name' => '智能GEO内容系统',
    'site_subtitle' => '',
    'site_description' => '基于AI的智能内容生成与发布平台',
    'site_keywords' => 'AI内容生成,GEO优化,智能发布,内容管理',
    'copyright_info' => '© 2024 智能GEO内容系统. All rights reserved.',
    'site_logo' => '',
    'site_favicon' => '',
    'analytics_code' => '',
    'seo_title_template' => '{title} - {site_name}',
    'seo_description_template' => '{description}',
    'featured_limit' => '6',
    'per_page' => '12',
    'article_detail_ads' => '[]'
];

foreach ($defaults as $key => $default_value) {
    if (!isset($current_settings[$key])) {
        $current_settings[$key] = $default_value;
    }
}

$article_detail_ads = json_decode($current_settings['article_detail_ads'] ?? '[]', true);
if (!is_array($article_detail_ads)) {
    $article_detail_ads = [];
}

// 包含统一头部
require_once __DIR__ . '/includes/header.php';
?>

            <!-- 页面标题 -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">网站设置</h1>
                <p class="mt-1 text-sm text-gray-600">配置网站基本信息和SEO设置</p>
            </div>

            <!-- 消息提示 -->
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                        <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 网站设置表单 -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">网站基本设置</h3>
                </div>
                <div class="px-6 py-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_site_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <!-- 基本信息 -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">网站名称 *</label>
                                <input type="text" name="site_name" required
                                       value="<?php echo htmlspecialchars($current_settings['site_name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="输入网站名称">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">网站Logo URL</label>
                                <input type="url" name="site_logo"
                                       value="<?php echo htmlspecialchars($current_settings['site_logo']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="https://example.com/logo.png">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">网站描述</label>
                            <textarea name="site_description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="输入网站描述"><?php echo htmlspecialchars($current_settings['site_description']); ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">网站副标题</label>
                            <input type="text" name="site_subtitle"
                                   value="<?php echo htmlspecialchars($current_settings['site_subtitle']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="用于首页主视觉和标题模板">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">网站关键词</label>
                            <input type="text" name="site_keywords"
                                   value="<?php echo htmlspecialchars($current_settings['site_keywords']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="关键词1,关键词2,关键词3">
                            <p class="mt-1 text-xs text-gray-500">多个关键词请用英文逗号分隔</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">版权信息</label>
                            <input type="text" name="copyright_info"
                                   value="<?php echo htmlspecialchars($current_settings['copyright_info']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="© 2024 网站名称. All rights reserved.">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">首页推荐文章数量</label>
                                <input type="number" name="featured_limit" min="1"
                                       value="<?php echo htmlspecialchars($current_settings['featured_limit']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="6">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">前台列表每页数量</label>
                                <input type="number" name="per_page" min="1"
                                       value="<?php echo htmlspecialchars($current_settings['per_page']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="12">
                            </div>
                        </div>

                        <!-- SEO设置 -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">SEO设置</h4>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">页面标题模板</label>
                                    <input type="text" name="seo_title_template"
                                           value="<?php echo htmlspecialchars($current_settings['seo_title_template']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="{title} - {site_name}">
                                    <p class="mt-1 text-xs text-gray-500">可用变量: {title}, {site_name}, {category}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">页面描述模板</label>
                                    <input type="text" name="seo_description_template"
                                           value="<?php echo htmlspecialchars($current_settings['seo_description_template']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="{description}">
                                    <p class="mt-1 text-xs text-gray-500">可用变量: {description}, {site_name}, {keywords}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">网站图标 URL</label>
                                    <input type="url" name="site_favicon"
                                           value="<?php echo htmlspecialchars($current_settings['site_favicon']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="https://example.com/favicon.ico">
                                </div>
                            </div>
                        </div>

                        <!-- 统计代码 -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">统计分析</h4>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">统计代码</label>
                                <textarea name="analytics_code" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                                          placeholder="<!-- Google Analytics 或其他统计代码 -->"><?php echo htmlspecialchars($current_settings['analytics_code']); ?></textarea>
                                <p class="mt-1 text-xs text-gray-500">将会插入到页面 &lt;head&gt; 标签中</p>
                            </div>
                        </div>

                        <!-- 提交按钮 -->
                        <div class="flex justify-end pt-6 border-t border-gray-200">
                            <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                                保存设置
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg mt-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">文章详情页广告管理</h3>
                            <p class="mt-1 text-sm text-gray-600">配置文章详情页底部跟随广告，支持新增、编辑、删除多条广告，前台默认展示第一条启用广告。</p>
                        </div>
                        <button type="button" id="add-article-ad" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                            添加广告位
                        </button>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <form method="POST" id="article-ad-form" class="space-y-6">
                        <input type="hidden" name="action" value="update_article_detail_ads">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
                            <div class="text-sm font-medium text-gray-900">广告位预览</div>
                            <div class="mt-3 rounded-2xl border border-blue-200 bg-white p-4 shadow-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">精选推荐</div>
                                        <div class="mt-3 text-base font-semibold text-gray-900">把你的下一步动作直接放到读完文章之后</div>
                                        <p class="mt-1 text-sm text-gray-600">这里展示一段简洁的说明文案，用底部跟随 CTA 引导用户跳转或咨询。</p>
                                    </div>
                                    <button type="button" class="shrink-0 inline-flex items-center rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white">立即了解</button>
                                </div>
                            </div>
                        </div>

                        <div id="article-ad-list" class="space-y-5">
                            <?php foreach ($article_detail_ads as $index => $ad): ?>
                                <div class="article-ad-item rounded-2xl border border-gray-200 bg-gray-50/70 p-5" data-ad-index="<?php echo $index; ?>">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars((string) ($ad['name'] ?? ('广告位 ' . ($index + 1)))); ?></div>
                                            <div class="mt-1 text-xs text-gray-500">底部跟随广告位</div>
                                        </div>
                                        <button type="button" class="remove-article-ad inline-flex items-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                                            删除
                                        </button>
                                    </div>

                                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <input type="hidden" name="ads[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars((string) ($ad['id'] ?? '')); ?>">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">广告名称</label>
                                            <input type="text" name="ads[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars((string) ($ad['name'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：详情页主广告">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">角标文案</label>
                                            <input type="text" name="ads[<?php echo $index; ?>][badge]" value="<?php echo htmlspecialchars((string) ($ad['badge'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：精选推荐 / 限时活动">
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">广告标题</label>
                                        <input type="text" name="ads[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars((string) ($ad['title'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：领取行业内容模板，直接开始使用">
                                    </div>

                                    <div class="mt-5">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">广告文案 *</label>
                                        <textarea name="ads[<?php echo $index; ?>][copy]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="输入一段简洁明确的补充说明，建议控制在一行到两行"><?php echo htmlspecialchars((string) ($ad['copy'] ?? '')); ?></textarea>
                                    </div>

                                    <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">按钮文案 *</label>
                                            <input type="text" name="ads[<?php echo $index; ?>][button_text]" value="<?php echo htmlspecialchars((string) ($ad['button_text'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：立即咨询">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">按钮链接 *</label>
                                            <input type="text" name="ads[<?php echo $index; ?>][button_url]" value="<?php echo htmlspecialchars((string) ($ad['button_url'] ?? '')); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="/contact 或 https://example.com">
                                        </div>
                                    </div>

                                    <div class="mt-5 flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">启用该广告</div>
                                            <div class="text-xs text-gray-500">前台只展示第一条启用广告，关闭后将自动顺延</div>
                                        </div>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="ads[<?php echo $index; ?>][enabled]" value="1" <?php echo !empty($ad['enabled']) ? 'checked' : ''; ?> class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="article-ad-empty" class="<?php echo !empty($article_detail_ads) ? 'hidden ' : ''; ?>rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-6 py-10 text-center">
                            <div class="text-base font-medium text-gray-900">还没有配置广告位</div>
                            <div class="mt-2 text-sm text-gray-500">点击“添加广告位”创建第一条文章详情页底部广告</div>
                        </div>

                        <div class="flex justify-end pt-2 border-t border-gray-200">
                            <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                                保存广告位
                            </button>
                        </div>
                    </form>
                </div>
            </div>

<?php
// 包含统一底部
require_once __DIR__ . '/includes/footer.php';
?>
<template id="article-ad-template">
    <div class="article-ad-item rounded-2xl border border-gray-200 bg-gray-50/70 p-5" data-ad-index="__INDEX__">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-sm font-semibold text-gray-900">新广告位</div>
                <div class="mt-1 text-xs text-gray-500">底部跟随广告位</div>
            </div>
            <button type="button" class="remove-article-ad inline-flex items-center rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
                删除
            </button>
        </div>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <input type="hidden" name="ads[__INDEX__][id]" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">广告名称</label>
                <input type="text" name="ads[__INDEX__][name]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：详情页主广告">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">角标文案</label>
                <input type="text" name="ads[__INDEX__][badge]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：精选推荐 / 限时活动">
            </div>
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">广告标题</label>
            <input type="text" name="ads[__INDEX__][title]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：领取行业内容模板，直接开始使用">
        </div>

        <div class="mt-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">广告文案 *</label>
            <textarea name="ads[__INDEX__][copy]" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="输入一段简洁明确的补充说明，建议控制在一行到两行"></textarea>
        </div>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">按钮文案 *</label>
                <input type="text" name="ads[__INDEX__][button_text]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="例如：立即咨询">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">按钮链接 *</label>
                <input type="text" name="ads[__INDEX__][button_url]" value="" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" placeholder="/contact 或 https://example.com">
            </div>
        </div>

        <div class="mt-5 flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
            <div>
                <div class="text-sm font-medium text-gray-900">启用该广告</div>
                <div class="text-xs text-gray-500">前台只展示第一条启用广告，关闭后将自动顺延</div>
            </div>
            <label class="inline-flex items-center">
                <input type="checkbox" name="ads[__INDEX__][enabled]" value="1" checked class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
            </label>
        </div>
    </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const adList = document.getElementById('article-ad-list');
    const emptyState = document.getElementById('article-ad-empty');
    const addButton = document.getElementById('add-article-ad');
    const template = document.getElementById('article-ad-template');

    if (!adList || !emptyState || !addButton || !template) {
        return;
    }

    let adIndex = adList.querySelectorAll('.article-ad-item').length;

    function refreshState() {
        emptyState.classList.toggle('hidden', adList.querySelectorAll('.article-ad-item').length > 0);
    }

    function bindRemove(scope) {
        const removeButton = scope.querySelector('.remove-article-ad');
        if (!removeButton) {
            return;
        }

        removeButton.addEventListener('click', function () {
            scope.remove();
            refreshState();
        });
    }

    addButton.addEventListener('click', function () {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(adIndex)).trim();
        adIndex += 1;
        const adItem = wrapper.firstElementChild;
        if (!adItem) {
            return;
        }

        adList.appendChild(adItem);
        bindRemove(adItem);
        refreshState();

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    adList.querySelectorAll('.article-ad-item').forEach(bindRemove);
    refreshState();
});
</script>
