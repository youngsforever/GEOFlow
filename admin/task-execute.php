<?php
/**
 * 智能GEO内容系统 - 任务执行测试
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
require_once __DIR__ . '/../includes/ai_engine.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

$task_id = intval($_GET['id'] ?? 0);
$message = '';
$error = '';
$execution_log = [];

if ($task_id <= 0) {
    die('任务ID无效');
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'execute') {
            $ai_engine = new AIEngine($db);
            
            // 记录执行开始
            $execution_log[] = [
                'time' => date('H:i:s'),
                'type' => 'info',
                'message' => '开始执行任务...'
            ];
            
            try {
                $result = $ai_engine->executeTask($task_id);
                
                if ($result['success']) {
                    $execution_log[] = [
                        'time' => date('H:i:s'),
                        'type' => 'success',
                        'message' => '任务执行成功：' . ($result['title'] ?? $result['message'])
                    ];
                    $message = '任务执行成功';
                } else {
                    $execution_log[] = [
                        'time' => date('H:i:s'),
                        'type' => 'error',
                        'message' => '任务执行失败：' . $result['error']
                    ];
                    $error = '任务执行失败：' . $result['error'];
                }
            } catch (Exception $e) {
                $execution_log[] = [
                    'time' => date('H:i:s'),
                    'type' => 'error',
                    'message' => '执行异常：' . $e->getMessage()
                ];
                $error = '执行异常：' . $e->getMessage();
            }
        }
    }
}

// 获取任务信息
$stmt = $db->prepare("
    SELECT t.*, tl.name as title_library_name, il.name as image_library_name, 
           p.name as prompt_name, am.name as ai_model_name, au.name as author_name
    FROM tasks t
    LEFT JOIN title_libraries tl ON t.title_library_id = tl.id
    LEFT JOIN image_libraries il ON t.image_library_id = il.id
    LEFT JOIN prompts p ON t.prompt_id = p.id
    LEFT JOIN ai_models am ON t.ai_model_id = am.id
    LEFT JOIN authors au ON t.author_id = au.id
    WHERE t.id = ?
");
$stmt->execute([$task_id]);
$task = $stmt->fetch();

if (!$task) {
    die('任务不存在');
}

function fetchCountByTask(PDO $db, string $sql, int $taskId): int {
    $stmt = $db->prepare($sql);
    $stmt->execute([$taskId]);
    return (int) $stmt->fetchColumn();
}

function formatTaskExecutionError(?string $message, int $maxLength = 120): string {
    $message = trim((string) $message);
    if ($message === '') {
        return '';
    }

    if (mb_strlen($message, 'UTF-8') <= $maxLength) {
        return $message;
    }

    return mb_substr($message, 0, $maxLength - 1, 'UTF-8') . '…';
}

function describeTaskExecutionError(?string $message): array {
    $message = trim((string) $message);
    if ($message === '') {
        return [
            'label' => '无错误信息',
            'detail' => '',
            'tone' => 'slate',
        ];
    }

    if (mb_strpos($message, 'AI返回空正文', 0, 'UTF-8') !== false) {
        return [
            'label' => '空正文已拦截',
            'detail' => '模型返回空正文，系统已判失败，没有创建文章。',
            'tone' => 'red',
        ];
    }

    if (mb_strpos($message, '正文过短', 0, 'UTF-8') !== false) {
        return [
            'label' => '正文过短',
            'detail' => '生成内容未达到最小正文长度，系统已停止入库。',
            'tone' => 'amber',
        ];
    }

    if (mb_strpos($message, '没有可用的标题', 0, 'UTF-8') !== false) {
        return [
            'label' => '标题库已耗尽',
            'detail' => '当前标题库没有可用标题，补充标题后再执行。',
            'tone' => 'amber',
        ];
    }

    if (mb_strpos($message, '任务已暂停', 0, 'UTF-8') !== false) {
        return [
            'label' => '任务已暂停',
            'detail' => '这是手动停止产生的取消记录，不是生成故障。',
            'tone' => 'slate',
        ];
    }

    return [
        'label' => '执行失败',
        'detail' => formatTaskExecutionError($message),
        'tone' => 'red',
    ];
}

function getExecutionToneClasses(string $tone): array {
    return match ($tone) {
        'amber' => [
            'chip' => 'bg-amber-50 text-amber-700 border-amber-200',
            'card' => 'border-amber-200 bg-amber-50 text-amber-800',
            'text' => 'text-amber-700',
        ],
        'slate' => [
            'chip' => 'bg-slate-50 text-slate-700 border-slate-200',
            'card' => 'border-slate-200 bg-slate-50 text-slate-800',
            'text' => 'text-slate-600',
        ],
        default => [
            'chip' => 'bg-red-50 text-red-700 border-red-200',
            'card' => 'border-red-200 bg-red-50 text-red-800',
            'text' => 'text-red-700',
        ],
    };
}

// 获取任务统计
$stats = [
    'total_articles' => fetchCountByTask($db, "SELECT COUNT(*) FROM articles WHERE task_id = ?", $task_id),
    'published_articles' => fetchCountByTask($db, "SELECT COUNT(*) FROM articles WHERE task_id = ? AND status = 'published'", $task_id),
    'pending_articles' => fetchCountByTask($db, "SELECT COUNT(*) FROM articles WHERE task_id = ? AND review_status = 'pending'", $task_id)
];

// 获取最近生成的文章
$recentArticlesStmt = $db->prepare("
    SELECT * FROM articles 
    WHERE task_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recentArticlesStmt->execute([$task_id]);
$recent_articles = $recentArticlesStmt->fetchAll(PDO::FETCH_ASSOC);

$recentRunsStmt = $db->prepare("
    SELECT
        tr.id,
        tr.job_id,
        tr.status,
        tr.article_id,
        tr.error_message,
        tr.duration_ms,
        tr.meta,
        tr.started_at,
        tr.finished_at,
        tr.created_at,
        jq.status AS queue_status,
        jq.attempt_count,
        jq.max_attempts,
        jq.error_message AS queue_error_message
    FROM task_runs tr
    LEFT JOIN job_queue jq ON jq.id = tr.job_id
    WHERE tr.task_id = ?
    ORDER BY tr.created_at DESC, tr.id DESC
    LIMIT 8
");
$recentRunsStmt->execute([$task_id]);
$recent_runs = $recentRunsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>任务执行测试 - 智能GEO内容系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/lucide.min.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- 页面标题 -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">任务执行测试</h1>
                    <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($task['name']); ?></p>
                </div>
                <a href="tasks.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                    返回任务列表
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- 任务信息 -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">任务信息</h3>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($task['last_error_message'])): ?>
                            <?php
                            $lastFailureInfo = describeTaskExecutionError($task['last_error_message']);
                            $lastFailureClasses = getExecutionToneClasses($lastFailureInfo['tone']);
                            ?>
                            <div class="mb-5 rounded-lg border px-4 py-3 <?php echo $lastFailureClasses['card']; ?>">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?php echo $lastFailureClasses['chip']; ?>">
                                        <?php echo htmlspecialchars($lastFailureInfo['label']); ?>
                                    </span>
                                    <?php if (!empty($task['last_error_at'])): ?>
                                        <span class="text-xs opacity-75">最近失败于 <?php echo date('m-d H:i', strtotime($task['last_error_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($lastFailureInfo['detail'])): ?>
                                    <div class="mt-2 text-sm <?php echo $lastFailureClasses['text']; ?>">
                                        <?php echo htmlspecialchars($lastFailureInfo['detail']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2 text-sm break-words opacity-90">
                                    <?php echo htmlspecialchars($task['last_error_message']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">任务名称</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($task['name']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">状态</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                        echo $task['status'] === 'active' ? 'bg-green-100 text-green-800' : 
                                            ($task['status'] === 'paused' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                                    ?>">
                                        <?php 
                                        echo $task['status'] === 'active' ? '活跃' : 
                                            ($task['status'] === 'paused' ? '暂停' : '已完成'); 
                                        ?>
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">标题库</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($task['title_library_name']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">AI模型</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($task['ai_model_name']); ?></dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">发布间隔</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo $task['publish_interval']; ?> 秒</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">草稿限制</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo $task['draft_limit']; ?> 篇</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">已创建文章</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo $task['created_count']; ?> 篇</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">已发布文章</dt>
                                <dd class="mt-1 text-sm text-gray-900"><?php echo $task['published_count']; ?> 篇</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- 执行控制 -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">执行控制</h3>
                    </div>
                    <div class="px-6 py-4">
                        <form method="POST" onsubmit="return confirm('确定要执行这个任务吗？')">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="execute">
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600">手动执行任务，生成一篇新文章</p>
                                    <p class="text-xs text-gray-500 mt-1">此页面与主任务中心共用同一套 AIEngine 执行链路</p>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    <i data-lucide="play" class="w-4 h-4 mr-2"></i>
                                    执行任务
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 执行日志 -->
                <?php if (!empty($execution_log)): ?>
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">执行日志</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="space-y-2">
                                <?php foreach ($execution_log as $log): ?>
                                    <div class="flex items-start space-x-3">
                                        <span class="text-xs text-gray-500 mt-0.5"><?php echo $log['time']; ?></span>
                                        <div class="flex-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php 
                                                echo $log['type'] === 'success' ? 'bg-green-100 text-green-800' : 
                                                    ($log['type'] === 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800'); 
                                            ?>">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white shadow rounded-lg <?php echo !empty($execution_log) ? 'mt-6' : ''; ?>">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">最近执行记录</h3>
                        <p class="mt-1 text-sm text-gray-500">这里展示该任务最近几次进入队列后的结果，失败原因会直接展开显示。</p>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (empty($recent_runs)): ?>
                            <p class="text-sm text-gray-500">暂无执行记录</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_runs as $run): ?>
                                    <?php
                                    $runErrorMessage = trim((string) ($run['error_message'] ?: $run['queue_error_message'] ?: ''));
                                    $runFailureInfo = describeTaskExecutionError($runErrorMessage);
                                    $runFailureClasses = getExecutionToneClasses($runFailureInfo['tone']);
                                    $runMeta = [];
                                    if (!empty($run['meta'])) {
                                        $decoded = json_decode((string) $run['meta'], true);
                                        if (is_array($decoded)) {
                                            $runMeta = $decoded;
                                        }
                                    }
                                    $statusClasses = match ($run['status']) {
                                        'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'failed' => 'bg-red-50 text-red-700 border-red-200',
                                        'retrying' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'cancelled' => 'bg-slate-50 text-slate-700 border-slate-200',
                                        default => 'bg-blue-50 text-blue-700 border-blue-200',
                                    };
                                    ?>
                                    <div class="rounded-lg border border-gray-200 px-4 py-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?php echo $statusClasses; ?>">
                                                        <?php echo htmlspecialchars($run['status']); ?>
                                                    </span>
                                                    <span class="text-sm font-medium text-gray-900">Run #<?php echo (int) $run['id']; ?></span>
                                                    <?php if (!empty($run['job_id'])): ?>
                                                        <span class="text-xs text-gray-500">Job #<?php echo (int) $run['job_id']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2 text-xs text-gray-500 space-y-1">
                                                    <div>开始时间: <?php echo htmlspecialchars($run['started_at'] ?: $run['created_at']); ?></div>
                                                    <?php if (!empty($run['finished_at'])): ?>
                                                        <div>结束时间: <?php echo htmlspecialchars($run['finished_at']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ((int) $run['duration_ms'] > 0): ?>
                                                        <div>耗时: <?php echo number_format(((int) $run['duration_ms']) / 1000, 2); ?> 秒</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($run['article_id'])): ?>
                                                        <div>文章ID: #<?php echo (int) $run['article_id']; ?></div>
                                                    <?php endif; ?>
                                                    <?php if (isset($run['attempt_count']) && isset($run['max_attempts']) && (int) $run['max_attempts'] > 0): ?>
                                                        <div>重试次数: <?php echo (int) $run['attempt_count']; ?> / <?php echo (int) $run['max_attempts']; ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($run['status'] === 'completed' && !empty($runMeta['title'])): ?>
                                                <div class="max-w-xs text-right text-sm text-gray-600">
                                                    <div class="font-medium text-gray-900">生成标题</div>
                                                    <div class="mt-1 break-words"><?php echo htmlspecialchars((string) $runMeta['title']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($runErrorMessage !== ''): ?>
                                            <div class="mt-3 rounded-md border px-3 py-3 <?php echo $runFailureClasses['card']; ?>">
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?php echo $runFailureClasses['chip']; ?>">
                                                        <?php echo htmlspecialchars($runFailureInfo['label']); ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($runFailureInfo['detail'])): ?>
                                                    <div class="mt-2 text-sm <?php echo $runFailureClasses['text']; ?>">
                                                        <?php echo htmlspecialchars($runFailureInfo['detail']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="mt-2 text-sm break-words opacity-90">
                                                    <?php echo htmlspecialchars($runErrorMessage); ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 侧边栏 -->
            <div class="space-y-6">
                <!-- 统计信息 -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">统计信息</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">总文章数</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $stats['total_articles']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">已发布</span>
                            <span class="text-sm font-medium text-green-600"><?php echo $stats['published_articles']; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600">待审核</span>
                            <span class="text-sm font-medium text-yellow-600"><?php echo $stats['pending_articles']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- 最近文章 -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">最近文章</h3>
                    </div>
                    <div class="px-6 py-4">
                        <?php if (!empty($recent_articles)): ?>
                            <div class="space-y-3">
                                <?php foreach ($recent_articles as $article): ?>
                                    <div class="border-l-4 border-blue-400 pl-3">
                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($article['title']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo date('m-d H:i', strtotime($article['created_at'])); ?> • 
                                            <span class="<?php echo $article['status'] === 'published' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                                <?php echo $article['status'] === 'published' ? '已发布' : '草稿'; ?>
                                            </span>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500">暂无文章</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 初始化Lucide图标
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
