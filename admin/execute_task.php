<?php
/**
 * 单个任务执行API
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('FEISHU_TREASURE', true);
session_start();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 设置执行时间限制
set_time_limit(120); // 2分钟

try {
    require_once __DIR__ . '/../includes/config.php';
    require_once __DIR__ . '/../includes/database_admin.php';
    require_once __DIR__ . '/../includes/ai_engine.php';
    require_once __DIR__ . '/../includes/functions.php';

    // 检查管理员登录
    if (!is_admin_logged_in() || !get_current_admin(true)) {
        echo json_encode(['success' => false, 'message' => '未登录或登录已过期']);
        exit;
    }

    // 只处理POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '无效的请求方法']);
        exit;
    }

    // 获取任务ID
    $input = json_decode(file_get_contents('php://input'), true);
    $task_id = $input['task_id'] ?? 0;

    log_admin_request_if_needed([
        'action' => 'execute_task',
        'page' => 'execute_task.php',
        'target_type' => 'task',
        'target_id' => is_numeric($task_id) ? (int) $task_id : null,
        'details' => sanitize_admin_activity_payload($input ?? [])
    ]);

    if (!$task_id || !is_numeric($task_id)) {
        echo json_encode(['success' => false, 'message' => '无效的任务ID']);
        exit;
    }

    // 获取任务信息
    $stmt = $db->prepare("
        SELECT t.*, 
               tl.name as title_library_name,
               p.name as prompt_name,
               am.name as model_name,
               (SELECT COUNT(*) FROM articles WHERE task_id = t.id AND status = 'draft' AND deleted_at IS NULL) as draft_count
        FROM tasks t
        LEFT JOIN title_libraries tl ON t.title_library_id = tl.id
        LEFT JOIN prompts p ON t.prompt_id = p.id
        LEFT JOIN ai_models am ON t.ai_model_id = am.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        echo json_encode(['success' => false, 'message' => '任务不存在']);
        exit;
    }

    if ($task['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => '任务未激活，无法执行']);
        exit;
    }

    // 记录执行开始时间
    $start_time = microtime(true);
    
    // 初始化AI引擎
    $ai_engine = new AIEngine($db);
    
    // 执行任务
    $result = $ai_engine->executeTask($task_id);
    
    // 计算执行时间
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    if ($result['success']) {
        // 获取更新后的任务统计
        $stmt = $db->prepare("
            SELECT created_count, 
                   (SELECT COUNT(*) FROM articles WHERE task_id = ? AND status = 'published') as published_count,
                   (SELECT COUNT(*) FROM articles WHERE task_id = ? AND status = 'draft') as draft_count
            FROM tasks WHERE id = ?
        ");
        $stmt->execute([$task_id, $task_id, $task_id]);
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => '任务执行成功！',
            'article_title' => $result['title'],
            'article_id' => $result['article_id'],
            'execution_time' => $execution_time,
            'task_stats' => [
                'created_count' => $stats['created_count'],
                'published_count' => $stats['published_count'],
                'draft_count' => $stats['draft_count']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '任务执行失败：' . $result['error'],
            'execution_time' => $execution_time
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '执行过程中出错：' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
