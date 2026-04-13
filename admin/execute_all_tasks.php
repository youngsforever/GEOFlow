<?php
/**
 * 立即执行所有活跃任务
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不在页面显示错误，只记录到日志

define('FEISHU_TREASURE', true);
session_start();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 捕获所有输出
ob_start();

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/database_admin.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/ai_engine.php';

    if (!is_admin_logged_in() || !get_current_admin(true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => '未登录或登录已过期，请重新登录']);
        exit;
    }

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '初始化失败：' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}

/**
 * 立即执行所有活跃任务
 */
function executeAllActiveTasks() {
    try {
        // 创建数据库连接
        $db = db_create_runtime_pdo();

        // 创建AI引擎实例
        $ai_engine = new AIEngine($db);

        // 获取所有活跃任务
        $stmt = $db->query("SELECT id, name FROM tasks WHERE status = 'active' ORDER BY id");
        $active_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($active_tasks)) {
            return ['success' => false, 'message' => '没有活跃的任务需要执行'];
        }

        $success_count = 0;
        $error_count = 0;
        $results = [];

        foreach ($active_tasks as $task) {
            $result = $ai_engine->executeTask($task['id']);

            if ($result['success']) {
                $success_count++;
                $results[] = "✅ {$task['name']}: {$result['title']}";
            } else {
                $error_count++;
                $results[] = "❌ {$task['name']}: {$result['error']}";
            }
        }

        $total_tasks = count($active_tasks);
        $message = "执行完成！总任务数: {$total_tasks}，成功: {$success_count}，失败: {$error_count}";

        if (!empty($results)) {
            $message .= "\n\n详细结果:\n" . implode("\n", $results);
        }

        return [
            'success' => true,
            'message' => $message,
            'total' => $total_tasks,
            'success_count' => $success_count,
            'error_count' => $error_count
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => '执行过程中出错：' . $e->getMessage()];
    }
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        log_admin_request_if_needed([
            'action' => 'execute_all_tasks',
            'page' => 'execute_all_tasks.php',
            'target_type' => 'task_batch',
            'details' => []
        ]);

        $result = executeAllActiveTasks();

        // 清理输出缓冲区
        ob_end_clean();

        echo json_encode($result);
    } catch (Exception $e) {
        // 清理输出缓冲区
        ob_end_clean();

        echo json_encode([
            'success' => false,
            'message' => '执行过程中出现异常：' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
}
?>
