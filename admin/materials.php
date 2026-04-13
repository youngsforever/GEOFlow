<?php
/**
 * 智能GEO内容系统 - 素材管理
 *
 * @author 姚金刚
 * @version 1.0
 * @date 2025-10-06
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

// 获取统计数据
$stats = [
    'keyword_libraries' => $db->query("SELECT COUNT(*) as count FROM keyword_libraries")->fetch()['count'] ?? 0,
    'total_keywords' => $db->query("SELECT COUNT(*) as total FROM keywords")->fetch()['total'] ?? 0,
    'title_libraries' => $db->query("SELECT COUNT(*) as count FROM title_libraries")->fetch()['count'] ?? 0,
    'total_titles' => $db->query("SELECT COUNT(*) as total FROM titles")->fetch()['total'] ?? 0,
    'image_libraries' => $db->query("SELECT COUNT(*) as count FROM image_libraries")->fetch()['count'] ?? 0,
    'total_images' => $db->query("SELECT COUNT(*) as total FROM images")->fetch()['total'] ?? 0,
    'knowledge_bases' => $db->query("SELECT COUNT(*) as count FROM knowledge_bases")->fetch()['count'] ?? 0,
    'authors' => $db->query("SELECT COUNT(*) as count FROM authors")->fetch()['count'] ?? 0
];

// 设置页面信息
$page_title = '素材管理';
$page_header = '
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">素材管理</h1>
        <p class="mt-1 text-sm text-gray-600">管理关键词库、标题库、图片库和AI知识库</p>
    </div>
    <div class="flex space-x-3">
        <a href="authors.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
            <i data-lucide="users" class="w-4 h-4 mr-2"></i>
            作者管理
        </a>
    </div>
</div>
';

// 包含头部模块
require_once __DIR__ . '/includes/header.php';
?>

        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="key" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">关键词库</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['keyword_libraries']; ?> 个库</dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['total_keywords']; ?> 个关键词</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="type" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">标题库</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['title_libraries']; ?> 个库</dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['total_titles']; ?> 个标题</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="image" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">图片库</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['image_libraries']; ?> 个库</dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['total_images']; ?> 张图片</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="brain" class="h-6 w-6 text-orange-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">AI知识库</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['knowledge_bases']; ?> 个库</dd>
                                <dd class="text-sm text-gray-500"><?php echo $stats['authors']; ?> 位作者</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php $url_import_csrf = generate_csrf_token(); ?>

        <!-- 素材库管理 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- 关键词库 -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i data-lucide="key" class="w-5 h-5 text-blue-600 mr-2"></i>
                            关键词库管理
                        </h3>
                        <a href="keyword-libraries.php" class="text-sm text-blue-600 hover:text-blue-800">查看全部</a>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <p class="text-gray-600 mb-4">管理AI内容生成的关键词资源</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">关键词库数量</span>
                            <span class="text-sm font-medium"><?php echo $stats['keyword_libraries']; ?> 个</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">关键词总数</span>
                            <span class="text-sm font-medium"><?php echo $stats['total_keywords']; ?> 个</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="keyword-libraries.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <i data-lucide="settings" class="w-4 h-4 mr-2"></i>
                            管理关键词库
                        </a>
                    </div>
                </div>
            </div>

            <!-- 标题库 -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i data-lucide="type" class="w-5 h-5 text-green-600 mr-2"></i>
                            标题库管理
                        </h3>
                        <a href="title-libraries.php" class="text-sm text-green-600 hover:text-green-800">查看全部</a>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <p class="text-gray-600 mb-4">管理手动创建和AI生成的文章标题</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">标题库数量</span>
                            <span class="text-sm font-medium"><?php echo $stats['title_libraries']; ?> 个</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">标题总数</span>
                            <span class="text-sm font-medium"><?php echo $stats['total_titles']; ?> 个</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="title-libraries.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                            <i data-lucide="settings" class="w-4 h-4 mr-2"></i>
                            管理标题库
                        </a>
                    </div>
                </div>
            </div>

            <!-- 图片库 -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i data-lucide="image" class="w-5 h-5 text-purple-600 mr-2"></i>
                            图片库管理
                        </h3>
                        <a href="image-libraries.php" class="text-sm text-purple-600 hover:text-purple-800">查看全部</a>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <p class="text-gray-600 mb-4">管理文章配图和素材图片</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">图片库数量</span>
                            <span class="text-sm font-medium"><?php echo $stats['image_libraries']; ?> 个</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">图片总数</span>
                            <span class="text-sm font-medium"><?php echo $stats['total_images']; ?> 张</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="image-libraries.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                            <i data-lucide="settings" class="w-4 h-4 mr-2"></i>
                            管理图片库
                        </a>
                    </div>
                </div>
            </div>

            <!-- AI知识库 -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 flex items-center">
                            <i data-lucide="brain" class="w-5 h-5 text-orange-600 mr-2"></i>
                            AI知识库管理
                        </h3>
                        <a href="knowledge-bases.php" class="text-sm text-orange-600 hover:text-orange-800">查看全部</a>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <p class="text-gray-600 mb-4">管理AI内容生成的知识文档</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">知识库数量</span>
                            <span class="text-sm font-medium"><?php echo $stats['knowledge_bases']; ?> 个</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">作者数量</span>
                            <span class="text-sm font-medium"><?php echo $stats['authors']; ?> 位</span>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="knowledge-bases.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700">
                            <i data-lucide="settings" class="w-4 h-4 mr-2"></i>
                            管理知识库
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 快速操作 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">快速操作</h3>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="keyword-libraries.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i data-lucide="key" class="w-8 h-8 text-blue-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-gray-900">关键词库</h4>
                            <p class="text-sm text-gray-500">管理关键词</p>
                        </div>
                    </a>
                    
                    <a href="title-libraries.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i data-lucide="type" class="w-8 h-8 text-green-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-gray-900">标题库</h4>
                            <p class="text-sm text-gray-500">管理标题</p>
                        </div>
                    </a>
                    
                    <a href="image-libraries.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i data-lucide="image" class="w-8 h-8 text-purple-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-gray-900">图片库</h4>
                            <p class="text-sm text-gray-500">管理图片</p>
                        </div>
                    </a>
                    
                    <a href="knowledge-bases.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i data-lucide="brain" class="w-8 h-8 text-orange-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-gray-900">AI知识库</h4>
                            <p class="text-sm text-gray-500">管理知识</p>
                        </div>
                    </a>

                    <a href="url-import.php" class="flex items-center p-4 border border-cyan-200 rounded-lg bg-cyan-50 hover:bg-cyan-100 transition-colors">
                        <i data-lucide="globe" class="w-8 h-8 text-cyan-600 mr-3"></i>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium text-gray-900">URL智能采集</h4>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">迭代中</span>
                            </div>
                            <p class="text-sm text-gray-500">从网页一键生成素材，当前能力仍在迭代</p>
                        </div>
                    </a>

                    <a href="url-import-history.php" class="flex items-center p-4 border border-slate-200 rounded-lg hover:bg-gray-50 transition-colors">
                        <i data-lucide="history" class="w-8 h-8 text-slate-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-gray-900">采集历史</h4>
                            <p class="text-sm text-gray-500">查看 URL 采集任务与日志</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- URL智能采集 -->
        <div class="bg-white shadow rounded-lg overflow-hidden mt-8">
            <div class="px-6 py-5 border-b border-gray-200">
                <div>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <div class="inline-flex items-center px-3 py-1 rounded-full bg-cyan-50 text-cyan-700 text-sm font-medium">
                            <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i>
                            URL 智能采集
                        </div>
                        <div class="inline-flex items-center px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-sm font-medium">
                            <i data-lucide="alert-triangle" class="w-4 h-4 mr-2"></i>
                            功能尚未成熟，仍需持续迭代
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">输入一个网页地址，自动生成可入库素材</h2>
                    <p class="mt-3 text-sm md:text-base text-gray-600 leading-7">
                        系统将对目标页面执行正文抽取、AI 清洗和语义分析，并生成 AI 知识库、关键词库、标题库和正文主体图片的预览结果，确认后即可一键入库。
                    </p>
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        当前仅建议用于单页内容试采和人工复核，抽取准确率、去重、来源映射和真实入库确认流程还在继续完善。
                    </div>
                    <form id="url-import-form" class="mt-6">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($url_import_csrf); ?>">
                        <div class="flex flex-col xl:flex-row gap-3 w-full">
                            <div class="flex-1">
                                <label for="url-import-input" class="sr-only">目标 URL</label>
                                <input
                                    id="url-import-input"
                                    name="url"
                                    type="url"
                                    placeholder="输入文章页、专题页或报告页 URL，例如：https://example.com/article"
                                    class="block w-full rounded-xl border border-blue-200 bg-blue-50/40 px-5 py-4 text-base text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                >
                            </div>
                            <button type="submit" id="url-import-submit" class="inline-flex items-center justify-center px-5 py-4 rounded-xl text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 whitespace-nowrap xl:min-w-[192px]">
                                <i data-lucide="globe" class="w-4 h-4 mr-2"></i>
                                开始智能解析
                            </button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-600">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="import_knowledge" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">知识库</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="import_keywords" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">关键词库</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="import_titles" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">标题库</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="import_images" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">图片库</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_ai_cleaning" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">AI 清洗</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="enable_semantic_analysis" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2">语义分析</span>
                            </label>
                        </div>
                        <div id="url-import-inline-error" class="hidden mt-3 text-sm text-red-600"></div>
                    </form>
                </div>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">处理链路</div>
                        <div class="mt-2 text-sm font-medium text-gray-900">抓取 → 清洗 → 预览 → 入库</div>
                        <p class="mt-2 text-xs text-gray-500">聚焦单页高质量采集，不做整站爬取。</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">输出资产</div>
                        <div class="mt-2 text-sm font-medium text-gray-900">知识 / 关键词 / 标题 / 图片</div>
                        <p class="mt-2 text-xs text-gray-500">统一沉淀到现有素材库，减少手工拆分录入。</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">当前阶段</div>
                        <div class="mt-2 text-sm font-medium text-gray-900">单页高质量采集</div>
                        <p class="mt-2 text-xs text-gray-500">当前已提供预览与历史记录；后续继续补来源映射、去重与真实入库确认。</p>
                    </div>
                </div>
                <div class="mt-6 rounded-lg border border-blue-100 bg-blue-50 px-4 py-4">
                    <div class="text-sm font-medium text-gray-900">即将支持的标准流程</div>
                    <ol class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-3 text-sm text-gray-600">
                        <li class="flex items-start">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-semibold flex items-center justify-center mr-3 mt-0.5">1</span>
                            输入 URL 与采集策略，提取正文和主体图片
                        </li>
                        <li class="flex items-start">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-semibold flex items-center justify-center mr-3 mt-0.5">2</span>
                            基于页面内容进行 AI 清洗、关键词提炼和标题生成
                        </li>
                        <li class="flex items-start">
                            <span class="w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-semibold flex items-center justify-center mr-3 mt-0.5">3</span>
                            预览确认后写入素材库，并保留来源映射
                        </li>
                    </ol>
                </div>

                <div id="url-import-progress-panel" class="hidden mt-6 border border-blue-200 bg-blue-50 rounded-lg overflow-hidden">
                    <div class="px-4 py-4 border-b border-blue-100">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                            <div>
                                <div class="text-sm font-medium text-gray-900">智能处理进度</div>
                                <div id="url-import-current-step" class="mt-1 text-sm text-gray-600">等待开始</div>
                            </div>
                            <div class="text-sm text-blue-700 font-medium" id="url-import-progress-text">0%</div>
                        </div>
                        <div class="mt-3 h-2.5 bg-white rounded-full overflow-hidden">
                            <div id="url-import-progress-bar" class="h-full w-0 bg-blue-600 rounded-full transition-all duration-500"></div>
                        </div>
                    </div>
                    <div class="px-4 py-4">
                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                            <div class="rounded-lg border border-gray-200 bg-white p-4">
                                <div class="text-sm font-medium text-gray-900">阶段状态</div>
                                <div id="url-import-stage-list" class="mt-3 space-y-2 text-sm text-gray-600"></div>
                            </div>
                            <div class="rounded-lg border border-gray-200 bg-slate-950 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-medium text-white">处理日志</div>
                                    <div id="url-import-log-status" class="text-xs text-slate-400">等待任务启动</div>
                                </div>
                                <div id="url-import-log-box" class="mt-3 h-56 overflow-y-auto rounded-md bg-slate-900 border border-slate-800 p-3 font-mono text-xs leading-6 text-slate-200"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="url-import-result-panel" class="hidden mt-6 rounded-lg border border-emerald-200 bg-emerald-50 overflow-hidden">
                    <div class="px-4 py-4 border-b border-emerald-100">
                        <div class="text-sm font-medium text-gray-900">智能解析预览</div>
                        <div class="mt-1 text-sm text-gray-600">当前展示的是采集任务输出结果，后续将继续接入真实正文抽取与素材入库。</div>
                    </div>
                    <div class="px-4 py-4 grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-emerald-100 bg-white p-4">
                            <div class="text-sm font-medium text-gray-900">知识库预览</div>
                            <div id="url-import-summary" class="mt-3 text-sm text-gray-600 leading-7"></div>
                            <div id="url-import-knowledge-preview" class="mt-3 text-sm text-gray-700 leading-7"></div>
                        </div>
                        <div class="rounded-lg border border-emerald-100 bg-white p-4">
                            <div class="text-sm font-medium text-gray-900">关键词与标题预览</div>
                            <div class="mt-3">
                                <div class="text-xs uppercase tracking-wide text-gray-500">关键词</div>
                                <div id="url-import-keywords" class="mt-2 flex flex-wrap gap-2"></div>
                            </div>
                            <div class="mt-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500">标题</div>
                                <ul id="url-import-titles" class="mt-2 space-y-2 text-sm text-gray-700"></ul>
                            </div>
                            <div class="mt-4">
                                <div class="text-xs uppercase tracking-wide text-gray-500">图片</div>
                                <ul id="url-import-images" class="mt-2 space-y-2 text-sm text-gray-700"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php
// 包含底部模块
require_once __DIR__ . '/includes/footer.php';
?>
<script>
    (function () {
        const form = document.getElementById('url-import-form');
        const input = document.getElementById('url-import-input');
        const submitButton = document.getElementById('url-import-submit');
        const errorBox = document.getElementById('url-import-inline-error');
        const progressPanel = document.getElementById('url-import-progress-panel');
        const resultPanel = document.getElementById('url-import-result-panel');
        const progressText = document.getElementById('url-import-progress-text');
        const progressBar = document.getElementById('url-import-progress-bar');
        const currentStepText = document.getElementById('url-import-current-step');
        const stageList = document.getElementById('url-import-stage-list');
        const logBox = document.getElementById('url-import-log-box');
        const logStatus = document.getElementById('url-import-log-status');
        const summaryBox = document.getElementById('url-import-summary');
        const knowledgePreviewBox = document.getElementById('url-import-knowledge-preview');
        const keywordsBox = document.getElementById('url-import-keywords');
        const titlesBox = document.getElementById('url-import-titles');
        const imagesBox = document.getElementById('url-import-images');

        const orderedSteps = ['fetch', 'extract', 'images', 'ai_clean', 'keywords', 'titles', 'knowledge', 'completed'];
        let pollingTimer = null;
        let activeJobId = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setError(message) {
            if (!message) {
                errorBox.classList.add('hidden');
                errorBox.textContent = '';
                return;
            }

            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        function renderStageList(stepLabels, currentStep, status) {
            const currentIndex = orderedSteps.indexOf(currentStep);
            stageList.innerHTML = orderedSteps.map((step, index) => {
                let stateClass = 'bg-gray-100 text-gray-500 border-gray-200';
                let dotClass = 'bg-gray-300';

                if (status === 'completed' || index < currentIndex) {
                    stateClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    dotClass = 'bg-emerald-500';
                } else if (step === currentStep) {
                    stateClass = 'bg-blue-50 text-blue-700 border-blue-200';
                    dotClass = 'bg-blue-500';
                }

                if (status === 'failed' && step === currentStep) {
                    stateClass = 'bg-red-50 text-red-700 border-red-200';
                    dotClass = 'bg-red-500';
                }

                return `
                    <div class="flex items-center justify-between rounded-md border px-3 py-2 ${stateClass}">
                        <div class="flex items-center">
                            <span class="w-2.5 h-2.5 rounded-full mr-3 ${dotClass}"></span>
                            <span>${escapeHtml(stepLabels[step]?.label || step)}</span>
                        </div>
                        <span class="text-xs">${index < currentIndex || status === 'completed' ? '已完成' : (step === currentStep ? '进行中' : '等待中')}</span>
                    </div>
                `;
            }).join('');
        }

        function renderLogs(logs) {
            if (!Array.isArray(logs) || logs.length === 0) {
                logBox.innerHTML = '<div class="text-slate-500">等待任务日志输出...</div>';
                return;
            }

            logBox.innerHTML = logs.map((log) => {
                return `<div><span class="text-slate-500">[${escapeHtml(log.created_at)}]</span> ${escapeHtml(log.message)}</div>`;
            }).join('');

            logBox.scrollTop = logBox.scrollHeight;
        }

        function renderResult(result) {
            if (!result || typeof result !== 'object') {
                resultPanel.classList.add('hidden');
                return;
            }

            summaryBox.textContent = result.summary || '';

            if (result.knowledge_preview) {
                knowledgePreviewBox.innerHTML = `
                    <div class="font-medium text-gray-900">${escapeHtml(result.knowledge_preview.title || '')}</div>
                    <div class="mt-2">${escapeHtml(result.knowledge_preview.content || '')}</div>
                `;
            } else {
                knowledgePreviewBox.textContent = '';
            }

            keywordsBox.innerHTML = Array.isArray(result.keywords)
                ? result.keywords.map((keyword) => `<span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800">${escapeHtml(keyword)}</span>`).join('')
                : '';

            titlesBox.innerHTML = Array.isArray(result.titles)
                ? result.titles.map((title) => `<li class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2">${escapeHtml(title)}</li>`).join('')
                : '';

            imagesBox.innerHTML = Array.isArray(result.images)
                ? result.images.map((image) => `<li class="rounded-md border border-emerald-100 bg-emerald-50 px-3 py-2">${escapeHtml(image.label || '图片待抓取')}</li>`).join('')
                : '';

            resultPanel.classList.remove('hidden');
        }

        async function pollStatus(jobId) {
            try {
                const response = await fetch(`${window.adminUrl('url-import-status.php')}?job_id=${jobId}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || '状态获取失败');
                }

                progressPanel.classList.remove('hidden');
                progressText.textContent = `${data.job.progress_percent}%`;
                progressBar.style.width = `${data.job.progress_percent}%`;
                currentStepText.textContent = data.step_labels[data.job.current_step]?.label || data.job.current_step;
                logStatus.textContent = data.job.status === 'completed' ? '处理完成' : '智能处理中';
                renderStageList(data.step_labels, data.job.current_step, data.job.status);
                renderLogs(data.logs);

                if (data.job.status === 'completed') {
                    submitButton.disabled = false;
                    submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                    submitButton.innerHTML = '<i data-lucide="globe" class="w-4 h-4 mr-2"></i>开始智能解析';
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    renderResult(data.result);
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                    return;
                }

                if (data.job.status === 'failed') {
                    throw new Error(data.job.error_message || '任务执行失败');
                }
            } catch (error) {
                clearInterval(pollingTimer);
                pollingTimer = null;
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                submitButton.innerHTML = '<i data-lucide="globe" class="w-4 h-4 mr-2"></i>开始智能解析';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                setError(error.message || '任务执行失败');
                logStatus.textContent = '任务失败';
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            setError('');
            resultPanel.classList.add('hidden');

            const url = input.value.trim();
            if (!url) {
                setError('请先输入要采集的 URL');
                input.focus();
                return;
            }

            const formData = new FormData(form);
            submitButton.disabled = true;
            submitButton.classList.add('opacity-60', 'cursor-not-allowed');
            submitButton.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 animate-spin"></i>智能处理中';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            try {
                const response = await fetch(window.adminUrl('url-import-start.php'), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || '任务创建失败');
                }

                activeJobId = data.job_id;
                progressPanel.classList.remove('hidden');
                logStatus.textContent = '任务已启动';
                renderLogs([]);
                if (pollingTimer) {
                    clearInterval(pollingTimer);
                }

                await pollStatus(activeJobId);
                if (activeJobId && !pollingTimer) {
                    pollingTimer = setInterval(() => pollStatus(activeJobId), 1200);
                }
            } catch (error) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                submitButton.innerHTML = '<i data-lucide="globe" class="w-4 h-4 mr-2"></i>开始智能解析';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                setError(error.message || '任务创建失败');
            }
        });
    })();
</script>
