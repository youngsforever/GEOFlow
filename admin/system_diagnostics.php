<?php
/**
 * 系统诊断工具 - 检查进程状态和潜在问题
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/job_queue_service.php';
require_once __DIR__ . '/../includes/database_admin.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

// 获取系统信息
function getSystemInfo() {
    $info = [];
    
    // PHP进程信息
    exec("ps aux | grep php | grep -v grep", $php_processes);
    $info['php_processes'] = $php_processes;
    
    // 端口占用情况
    exec("lsof -i :8080 2>/dev/null", $port_8080);
    $info['port_8080'] = $port_8080;
    
    return $info;
}

// 获取任务状态
function getTaskStatus($db) {
    $stmt = $db->query("
        SELECT
            t.id,
            t.name,
            t.status,
            COALESCE((
                SELECT jq.status
                FROM job_queue jq
                WHERE jq.task_id = t.id
                  AND jq.status IN ('running', 'pending', 'failed', 'completed')
                ORDER BY
                    CASE jq.status
                        WHEN 'running' THEN 1
                        WHEN 'pending' THEN 2
                        WHEN 'failed' THEN 3
                        ELSE 4
                    END,
                    jq.updated_at DESC,
                    jq.id DESC
                LIMIT 1
            ), 'idle') as batch_status
        FROM tasks t
        ORDER BY id
    ");
    return $stmt->fetchAll();
}

function getWorkerStatus($db) {
    $stmt = $db->query("
        SELECT worker_id, status, current_job_id, last_seen_at
        FROM worker_heartbeats
        ORDER BY last_seen_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 处理操作
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'cleanup_orphans') {
            $queueService = new JobQueueService($db);
            $cleaned = $queueService->recoverStaleJobs();
            $message = "已恢复 $cleaned 个卡住的队列任务";
        }
    }
}

$system_info = getSystemInfo();
$tasks = getTaskStatus($db);
$workers = getWorkerStatus($db);

// 设置页面信息
$page_title = '系统诊断';
$page_header = '
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">系统诊断</h1>
        <p class="mt-1 text-sm text-gray-600">检查系统状态和潜在问题</p>
    </div>
    <div class="flex space-x-3">
        <form method="POST" onsubmit="return confirm(\'确定要恢复卡住的队列任务吗？\')" class="inline">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">
            <input type="hidden" name="action" value="cleanup_orphans">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i>
            恢复卡住任务
            </button>
        </form>
        <a href="?" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>
            刷新
        </a>
    </div>
</div>
';

// 包含头部模块
require_once __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
    <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i data-lucide="check-circle" class="h-5 w-5 text-green-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($message); ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- PHP进程状态 -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">PHP进程状态</h3>
    </div>
    <div class="px-6 py-4">
        <?php if (empty($system_info['php_processes'])): ?>
            <p class="text-gray-500">没有找到PHP进程</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($system_info['php_processes'] as $process): ?>
                    <div class="font-mono text-sm p-2 bg-gray-50 rounded">
                        <?php 
                        echo htmlspecialchars($process);
                        // 高亮服务器进程
                        if (strpos($process, 'localhost:8080') !== false) {
                            echo ' <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">服务器进程</span>';
                        }
                        if (strpos($process, 'bin/worker.php') !== false) {
                            echo ' <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">队列Worker</span>';
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 端口占用情况 -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">端口8080占用情况</h3>
    </div>
    <div class="px-6 py-4">
        <?php if (empty($system_info['port_8080'])): ?>
            <p class="text-red-600">端口8080未被占用 - 服务器可能未运行</p>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($system_info['port_8080'] as $port_info): ?>
                    <div class="font-mono text-sm p-2 bg-gray-50 rounded">
                        <?php echo htmlspecialchars($port_info); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Worker状态 -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Worker状态</h3>
    </div>
    <div class="px-6 py-4">
        <?php if (empty($workers)): ?>
            <p class="text-gray-500">没有发现活跃 worker 心跳</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Worker ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">当前 Job</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">最后心跳</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($workers as $worker): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono"><?php echo htmlspecialchars($worker['worker_id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($worker['status']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono"><?php echo htmlspecialchars((string) ($worker['current_job_id'] ?? '')); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($worker['last_seen_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 任务状态 -->
<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">任务状态</h3>
    </div>
    <div class="px-6 py-4">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">名称</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">批量状态</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono"><?php echo $task['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($task['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($task['status']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($task['batch_status'] ?? '空闲'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

<?php
// 包含底部模块
require_once __DIR__ . '/includes/footer.php';
?>
