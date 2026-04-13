<?php
/**
 * 智能GEO内容系统 - 任务创建向导
 *
 * @author 姚金刚
 * @version 2.0
 * @date 2025-10-07
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/job_queue_service.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

$message = '';
$error = '';

// 处理任务创建
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        // 获取表单数据
        $task_name = trim($_POST['task_name'] ?? '');
        $title_library_id = intval($_POST['title_library_id'] ?? 0);
        $image_library_id = !empty($_POST['image_library_id']) ? intval($_POST['image_library_id']) : null;
        $image_count = intval($_POST['image_count'] ?? 0);
        $prompt_id = intval($_POST['prompt_id'] ?? 0);
        $ai_model_id = intval($_POST['ai_model_id'] ?? 0);
        $need_review = isset($_POST['need_review']) ? 1 : 0;
        $publish_interval_minutes = max(1, intval($_POST['publish_interval'] ?? 60));
        $publish_interval = $publish_interval_minutes * 60;
        $author_id = !empty($_POST['author_id']) ? intval($_POST['author_id']) : null;
        $auto_keywords = isset($_POST['auto_keywords']) ? 1 : 0;
        $auto_description = isset($_POST['auto_description']) ? 1 : 0;
        $draft_limit = intval($_POST['draft_limit'] ?? 10);
        $is_loop = isset($_POST['is_loop']) ? 1 : 0;
        $status = $_POST['status'] ?? 'active';
        $knowledge_base_id = !empty($_POST['knowledge_base_id']) ? intval($_POST['knowledge_base_id']) : null;

        // 分类设置
        $category_mode = $_POST['category_mode'] ?? 'smart';
        $fixed_category_id = null;
        if ($category_mode === 'fixed' && !empty($_POST['fixed_category_id'])) {
            $fixed_category_id = intval($_POST['fixed_category_id']);
        }

        // 验证必填字段
        if (empty($task_name)) {
            $error = '任务名称不能为空';
        } elseif ($title_library_id <= 0) {
            $error = '请选择标题库';
        } elseif ($prompt_id <= 0) {
            $error = '请选择内容提示词';
        } elseif ($ai_model_id <= 0) {
            $error = '请选择AI模型';
        } elseif ($category_mode === 'fixed' && $fixed_category_id <= 0) {
            $error = '固定分类模式下必须选择一个分类';
        } else {
            // 验证外键关系是否存在
            $stmt = $db->prepare("SELECT COUNT(*) FROM title_libraries WHERE id = ?");
            $stmt->execute([$title_library_id]);
            if ($stmt->fetchColumn() == 0) {
                $error = '选择的标题库不存在';
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM prompts WHERE id = ? AND type = 'content'");
                $stmt->execute([$prompt_id]);
                if ($stmt->fetchColumn() == 0) {
                    $error = '选择的内容提示词不存在';
                } else {
                    $stmt = $db->prepare("
                        SELECT COUNT(*)
                        FROM ai_models
                        WHERE id = ?
                          AND status = 'active'
                          AND COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'
                    ");
                    $stmt->execute([$ai_model_id]);
                    if ($stmt->fetchColumn() == 0) {
                        $error = '选择的AI模型不存在或未激活';
                    }
                }
            }
        }

        if (empty($error)) {
            try {
                $db->beginTransaction();

                // 创建任务
                $stmt = $db->prepare("
                    INSERT INTO tasks (
                        name, title_library_id, image_library_id, image_count,
                        prompt_id, ai_model_id, need_review, publish_interval,
                        author_id, auto_keywords, auto_description, draft_limit,
                        is_loop, status, knowledge_base_id, category_mode, fixed_category_id,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");

                $result = $stmt->execute([
                    $task_name, $title_library_id, $image_library_id, $image_count,
                    $prompt_id, $ai_model_id, $need_review, $publish_interval,
                    $author_id, $auto_keywords, $auto_description, $draft_limit,
                    $is_loop, $status, $knowledge_base_id, $category_mode, $fixed_category_id
                ]);

                if ($result) {
                    $task_id = db_last_insert_id($db, 'tasks');
                    $jobQueueService = new JobQueueService($db);
                    $jobQueueService->initializeTaskSchedule((int) $task_id);

                    // 如果任务是活跃状态，创建调度记录
                    if ($status === 'active') {
                        $stmt = $db->prepare("
                            INSERT INTO task_schedules (task_id, next_run_time, created_at)
                            VALUES (?, " . db_now_plus_minutes_sql(1) . ", CURRENT_TIMESTAMP)
                        ");
                        $stmt->execute([$task_id]);
                    } else {
                        $stmt = $db->prepare("
                            UPDATE tasks
                            SET schedule_enabled = 0,
                                next_run_at = NULL,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");
                        $stmt->execute([$task_id]);
                    }

                    $db->commit();

                    // 创建成功消息
                    $message = '任务创建成功！';
                    if ($status === 'active') {
                        $message .= ' 任务已激活，调度器会自动将任务加入队列，由 worker 执行。';
                    } else {
                        $message .= ' 任务已创建但处于暂停状态，您可以在任务管理页面激活它。';
                    }

                    // 重定向到任务列表
                    header('Location: tasks.php?message=' . urlencode($message));
                    exit;
                } else {
                    throw new Exception('任务创建失败');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error = '创建失败: ' . $e->getMessage();

                // 添加调试信息
                error_log("Task creation failed with data: " . json_encode([
                    'task_name' => $task_name,
                    'title_library_id' => $title_library_id,
                    'image_library_id' => $image_library_id,
                    'prompt_id' => $prompt_id,
                    'ai_model_id' => $ai_model_id,
                    'author_id' => $author_id,
                    'knowledge_base_id' => $knowledge_base_id
                ]));
            }
        }
    }
}

// 获取选项数据
$title_libraries = $db->query("SELECT id, name, (SELECT COUNT(*) FROM titles WHERE library_id = title_libraries.id) as title_count FROM title_libraries ORDER BY name")->fetchAll();
$image_libraries = $db->query("SELECT id, name, (SELECT COUNT(*) FROM images WHERE library_id = image_libraries.id) as image_count FROM image_libraries ORDER BY name")->fetchAll();
$content_prompts = $db->query("SELECT id, name FROM prompts WHERE type = 'content' ORDER BY name")->fetchAll();
$ai_models = $db->query("
    SELECT id, name, status
    FROM ai_models
    WHERE status = 'active'
      AND COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'
    ORDER BY name
")->fetchAll();
$authors = $db->query("SELECT id, name FROM authors ORDER BY name")->fetchAll();
$knowledge_bases = $db->query("SELECT id, name FROM knowledge_bases ORDER BY name")->fetchAll();

// 设置页面信息
$page_title = '创建任务';
$page_header = '
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="tasks.php" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">创建新任务</h1>
            <p class="mt-1 text-sm text-gray-600">配置AI内容生成任务的各项参数</p>
        </div>
    </div>
</div>
';

// 包含头部模块
require_once __DIR__ . '/includes/header.php';
?>

<!-- 任务创建表单 -->
<div class="max-w-4xl mx-auto">
    <form method="POST" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- 基础信息 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">基础信息</h3>
                <p class="mt-1 text-sm text-gray-600">设置任务的基本信息</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="task_name" class="block text-sm font-medium text-gray-700">任务名称 *</label>
                        <input type="text" name="task_name" id="task_name" required
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                               placeholder="例如：科技资讯自动生成任务">
                    </div>

                    <div>
                        <label for="title_library_id" class="block text-sm font-medium text-gray-700">标题库选择 *</label>
                        <select name="title_library_id" id="title_library_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">请选择标题库</option>
                            <?php foreach ($title_libraries as $library): ?>
                                <option value="<?php echo $library['id']; ?>">
                                    <?php echo htmlspecialchars($library['name']); ?> (<?php echo $library['title_count']; ?> 个标题)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">任务状态</label>
                        <select name="status" id="status"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="active">开启</option>
                            <option value="paused">暂停</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 内容配置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">内容配置</h3>
                <p class="mt-1 text-sm text-gray-600">配置AI内容生成相关参数</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="prompt_id" class="block text-sm font-medium text-gray-700">内容提示词 *</label>
                        <select name="prompt_id" id="prompt_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">请选择内容提示词</option>
                            <?php foreach ($content_prompts as $prompt): ?>
                                <option value="<?php echo $prompt['id']; ?>">
                                    <?php echo htmlspecialchars($prompt['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="ai_model_id" class="block text-sm font-medium text-gray-700">AI模型选择 *</label>
                        <select name="ai_model_id" id="ai_model_id" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">请选择AI模型</option>
                            <?php foreach ($ai_models as $model): ?>
                                <option value="<?php echo $model['id']; ?>">
                                    <?php echo htmlspecialchars($model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="knowledge_base_id" class="block text-sm font-medium text-gray-700">知识库选择</label>
                        <select name="knowledge_base_id" id="knowledge_base_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">不使用知识库</option>
                            <?php foreach ($knowledge_bases as $kb): ?>
                                <option value="<?php echo $kb['id']; ?>">
                                    <?php echo htmlspecialchars($kb['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">选择后，系统会从知识库中检索与标题/关键词最相关的片段，并注入正文提示词的 <code>{{Knowledge}}</code>。</p>
                    </div>

                    <div>
                        <label for="author_id" class="block text-sm font-medium text-gray-700">作者设置</label>
                        <select name="author_id" id="author_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="0">系统随机选择</option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author['id']; ?>">
                                    <?php echo htmlspecialchars($author['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 图片配置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">图片配置</h3>
                <p class="mt-1 text-sm text-gray-600">配置文章配图相关设置</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="image_library_id" class="block text-sm font-medium text-gray-700">图库选择</label>
                        <select name="image_library_id" id="image_library_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">不使用图片</option>
                            <?php foreach ($image_libraries as $library): ?>
                                <option value="<?php echo $library['id']; ?>">
                                    <?php echo htmlspecialchars($library['name']); ?> (<?php echo $library['image_count']; ?> 张图片)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="image_count" class="block text-sm font-medium text-gray-700">配图数量</label>
                        <select name="image_count" id="image_count"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="0">不配图</option>
                            <option value="1" selected>1张</option>
                            <option value="2">2张</option>
                            <option value="3">3张</option>
                            <option value="4">4张</option>
                            <option value="5">5张</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">系统将自动从图库中随机选择图片匹配到文章的二级或三级标题下</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 审核与发布设置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">审核与发布设置</h3>
                <p class="mt-1 text-sm text-gray-600">配置文章审核和自动发布相关参数</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="need_review" id="need_review"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="need_review" class="ml-2 block text-sm text-gray-900">
                                需要人工审核后才能发布
                            </label>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">勾选后，生成的文章需要人工审核通过才能自动发布</p>
                    </div>

                    <div>
                        <label for="publish_interval" class="block text-sm font-medium text-gray-700">发布频率（分钟）</label>
                        <input type="number" name="publish_interval" id="publish_interval" min="1" value="60"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">管理员填写分钟数，系统会自动换算后存储；仅在无需人工审核时生效</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO设置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">SEO设置</h3>
                <p class="mt-1 text-sm text-gray-600">配置关键词和描述的自动生成</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="auto_keywords" id="auto_keywords" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="auto_keywords" class="ml-2 block text-sm text-gray-900">
                                自动生成关键词
                            </label>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">AI自动结合文章标题进行关键词抽取</p>
                    </div>

                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="auto_description" id="auto_description" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="auto_description" class="ml-2 block text-sm text-gray-900">
                                自动生成描述
                            </label>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">AI自动结合文章内容进行描述抽取</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 分类设置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">分类设置</h3>
                <p class="mt-1 text-sm text-gray-600">配置文章的分类分配方式</p>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-4">
                    <!-- 分类模式选择 -->
                    <div>
                        <label class="text-base font-medium text-gray-900">分类模式</label>
                        <p class="text-sm leading-5 text-gray-500">选择文章分类的分配方式</p>
                        <fieldset class="mt-4">
                            <legend class="sr-only">分类模式</legend>
                            <div class="space-y-4">
                                <!-- 智能模式 -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="category_smart" name="category_mode" type="radio" value="smart" checked
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="category_smart" class="font-medium text-gray-700">
                                            智能模式
                                        </label>
                                        <p class="text-gray-500">AI根据文章标题自动结合当前分类名称进行智能分类，选择最适合的分类</p>
                                    </div>
                                </div>

                                <!-- 固定分类模式 -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="category_fixed" name="category_mode" type="radio" value="fixed"
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="category_fixed" class="font-medium text-gray-700">
                                            固定分类模式
                                        </label>
                                        <p class="text-gray-500">所有文章都发布到指定的固定分类下</p>
                                    </div>
                                </div>

                                <!-- 随机分类模式 -->
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="category_random" name="category_mode" type="radio" value="random"
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="category_random" class="font-medium text-gray-700">
                                            随机分类模式
                                        </label>
                                        <p class="text-gray-500">文章随机发布到所有可用的分类下</p>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <!-- 固定分类选择 -->
                    <div id="fixed-category-section" class="hidden">
                        <label for="fixed_category_id" class="block text-sm font-medium text-gray-700">选择固定分类</label>
                        <select name="fixed_category_id" id="fixed_category_id"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">请选择分类</option>
                            <?php
                            // 获取所有分类
                            try {
                                $stmt = $db->prepare("SELECT id, name, description FROM categories ORDER BY sort_order, name");
                                $stmt->execute();
                                $categories = $stmt->fetchAll();

                                foreach ($categories as $category) {
                                    echo '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name']);
                                    if (!empty($category['description'])) {
                                        echo ' - ' . htmlspecialchars($category['description']);
                                    }
                                    echo '</option>';
                                }
                            } catch (Exception $e) {
                                echo '<option value="">获取分类失败</option>';
                            }
                            ?>
                        </select>
                        <p class="mt-2 text-sm text-gray-500">选择一个固定分类，所有文章都将发布到此分类下</p>
                    </div>

                    <!-- 分类预览 -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">当前可用分类</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            foreach ($categories as $category) {
                                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">';
                                echo htmlspecialchars($category['name']);
                                echo '</span>';
                            }
                            ?>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            共 <?php echo count($categories); ?> 个分类可用
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 高级设置 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">高级设置</h3>
                <p class="mt-1 text-sm text-gray-600">配置任务执行的高级参数</p>
            </div>
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="draft_limit" class="block text-sm font-medium text-gray-700">草稿数量限制</label>
                        <input type="number" name="draft_limit" id="draft_limit" min="1" value="10"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <p class="mt-1 text-sm text-gray-500">当草稿箱文章数超过此数量时，AI暂停文章生成</p>
                    </div>

                    <div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_loop" id="is_loop" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_loop" class="ml-2 block text-sm text-gray-900">
                                循环生成
                            </label>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">当所选择的标题库用完后，是否自动重复执行原标题库里的标题</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 提交按钮 -->
        <div class="flex justify-end space-x-4">
            <a href="tasks.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                取消
            </a>
            <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                创建任务
            </button>
        </div>
    </form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// 表单交互逻辑
document.addEventListener('DOMContentLoaded', function() {
    // 初始化Lucide图标
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 图库选择联动
    const imageLibrarySelect = document.getElementById('image_library_id');
    const imageCountSelect = document.getElementById('image_count');

    imageLibrarySelect.addEventListener('change', function() {
        if (this.value === '') {
            imageCountSelect.value = '0';
            imageCountSelect.disabled = true;
        } else {
            imageCountSelect.disabled = false;
            if (imageCountSelect.value === '0') {
                imageCountSelect.value = '1';
            }
        }
    });

    // 审核设置联动
    const needReviewCheckbox = document.getElementById('need_review');
    const publishIntervalInput = document.getElementById('publish_interval');

    function togglePublishInterval() {
        if (needReviewCheckbox.checked) {
            publishIntervalInput.disabled = true;
            publishIntervalInput.parentElement.style.opacity = '0.5';
        } else {
            publishIntervalInput.disabled = false;
            publishIntervalInput.parentElement.style.opacity = '1';
        }
    }

    needReviewCheckbox.addEventListener('change', togglePublishInterval);
    togglePublishInterval(); // 初始化状态

    // 表单验证
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const taskName = document.getElementById('task_name').value.trim();
        const titleLibraryId = document.getElementById('title_library_id').value;
        const promptId = document.getElementById('prompt_id').value;
        const aiModelId = document.getElementById('ai_model_id').value;

        if (!taskName) {
            alert('请输入任务名称');
            e.preventDefault();
            return;
        }

        if (!titleLibraryId) {
            alert('请选择标题库');
            e.preventDefault();
            return;
        }

        if (!promptId) {
            alert('请选择内容提示词');
            e.preventDefault();
            return;
        }

        if (!aiModelId) {
            alert('请选择AI模型');
            e.preventDefault();
            return;
        }

        // 确认创建
        if (!confirm('确定要创建这个任务吗？')) {
            e.preventDefault();
            return;
        }
    });

    // 显示消息提示
    <?php if ($message): ?>
        setTimeout(() => {
            const messageDiv = document.querySelector('.bg-green-100');
            if (messageDiv) messageDiv.style.display = 'none';
        }, 5000);
    <?php endif; ?>

    <?php if ($error): ?>
        setTimeout(() => {
            const errorDiv = document.querySelector('.bg-red-100');
            if (errorDiv) errorDiv.style.display = 'none';
        }, 8000);
    <?php endif; ?>

    // 分类模式切换处理
    const categoryModeRadios = document.querySelectorAll('input[name="category_mode"]');
    const fixedCategorySection = document.getElementById('fixed-category-section');
    const fixedCategorySelect = document.getElementById('fixed_category_id');

    function handleCategoryModeChange() {
        const selectedMode = document.querySelector('input[name="category_mode"]:checked').value;

        if (selectedMode === 'fixed') {
            fixedCategorySection.classList.remove('hidden');
            fixedCategorySelect.required = true;
        } else {
            fixedCategorySection.classList.add('hidden');
            fixedCategorySelect.required = false;
            fixedCategorySelect.value = '';
        }
    }

    // 绑定事件监听器
    categoryModeRadios.forEach(radio => {
        radio.addEventListener('change', handleCategoryModeChange);
    });

    // 初始化状态
    handleCategoryModeChange();
});
</script>
