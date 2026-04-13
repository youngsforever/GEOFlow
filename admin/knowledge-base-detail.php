<?php
/**
 * AI知识库详情页面
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/knowledge-retrieval.php';

// 检查管理员登录
require_admin_login();
session_write_close();

$message = '';
$error = '';

// 获取知识库ID
$knowledge_id = intval($_GET['id'] ?? 0);

if ($knowledge_id <= 0) {
    header('Location: knowledge-bases.php');
    exit;
}

// 获取知识库详情
try {
    $stmt = $db->prepare("SELECT * FROM knowledge_bases WHERE id = ?");
    $stmt->execute([$knowledge_id]);
    $knowledge = $stmt->fetch();
    
    if (!$knowledge) {
        header('Location: knowledge-bases.php');
        exit;
    }
} catch (Exception $e) {
    $error = '获取知识库详情失败: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        switch ($_POST['action']) {
            case 'update_knowledge':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $content = trim($_POST['content'] ?? '');
                
                if (empty($name)) {
                    $error = '知识库名称不能为空';
                } elseif (empty($content)) {
                    $error = '知识库内容不能为空';
                } else {
                    try {
                        $word_count = mb_strlen(strip_tags($content));
                        $db->beginTransaction();
                        
                        $stmt = $db->prepare("
                            UPDATE knowledge_bases 
                            SET name = ?, description = ?, content = ?, word_count = ?, updated_at = CURRENT_TIMESTAMP 
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$name, $description, $content, $word_count, $knowledge_id])) {
                            $chunk_count = knowledge_retrieval_sync_chunks($db, $knowledge_id, $content);
                            $db->commit();
                            $message = '知识库更新成功，已刷新 ' . $chunk_count . ' 个知识片段';
                            // 重新获取更新后的数据
                            $stmt = $db->prepare("SELECT * FROM knowledge_bases WHERE id = ?");
                            $stmt->execute([$knowledge_id]);
                            $knowledge = $stmt->fetch();
                        } else {
                            $db->rollBack();
                            $error = '知识库更新失败';
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = '更新失败: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// 获取使用此知识库的任务
$related_tasks = [];
$knowledge_chunk_count = 0;
try {
    $stmt = $db->prepare("
        SELECT id, name, status, created_at 
        FROM tasks 
        WHERE knowledge_base_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$knowledge_id]);
    $related_tasks = $stmt->fetchAll();

    $chunkStmt = $db->prepare("SELECT COUNT(*) FROM knowledge_chunks WHERE knowledge_base_id = ?");
    $chunkStmt->execute([$knowledge_id]);
    $knowledge_chunk_count = (int) $chunkStmt->fetchColumn();
} catch (Exception $e) {
    // 如果knowledge_base_id字段不存在，忽略错误
}

$page_title = '知识库详情';
$page_header = '
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="knowledge-bases.php" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">知识库详情</h1>
            <p class="mt-1 text-sm text-gray-600">查看和编辑知识库内容</p>
        </div>
    </div>
    <a href="knowledge-bases.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
        <i data-lucide="list" class="w-4 h-4 mr-2"></i>
        返回列表
    </a>
</div>
';

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <?php if ($message): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                <div class="flex items-start">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-400 mt-0.5"></i>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                <div class="flex items-start">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-400 mt-0.5"></i>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">知识库内容</h3>
            </div>

            <form method="POST" class="p-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_knowledge">

                <div class="space-y-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">知识库名称</label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="<?php echo htmlspecialchars($knowledge['name']); ?>"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                            required
                        >
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">描述</label>
                        <textarea
                            name="description"
                            id="description"
                            rows="3"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500"
                            placeholder="可选：添加知识库的描述信息"
                        ><?php echo htmlspecialchars($knowledge['description'] ?? ''); ?></textarea>
                    </div>

                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700">内容</label>
                        <textarea
                            name="content"
                            id="content"
                            rows="20"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-orange-500 focus:border-orange-500 font-mono text-sm"
                            required
                        ><?php echo htmlspecialchars($knowledge['content']); ?></textarea>
                        <p class="mt-2 text-sm text-gray-500">当前字数：<span id="word-count"><?php echo (int) ($knowledge['word_count'] ?? 0); ?></span> 字</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                            <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                            保存更改
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">基本信息</h3>
            </div>
            <div class="p-6">
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">文件类型</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php
                                echo $knowledge['file_type'] === 'markdown' ? 'bg-green-100 text-green-800' :
                                    ($knowledge['file_type'] === 'word' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800');
                            ?>">
                                <?php
                                switch ($knowledge['file_type']) {
                                    case 'markdown':
                                        echo 'Markdown';
                                        break;
                                    case 'word':
                                        echo 'Word文档';
                                        break;
                                    case 'text':
                                        echo '纯文本';
                                        break;
                                    default:
                                        echo '未知';
                                        break;
                                }
                                ?>
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">字数统计</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo number_format((int) ($knowledge['word_count'] ?? 0)); ?> 字</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">知识片段</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo number_format($knowledge_chunk_count); ?> 个</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">创建时间</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo date('Y-m-d H:i:s', strtotime($knowledge['created_at'])); ?></dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">更新时间</dt>
                        <dd class="mt-1 text-sm text-gray-900"><?php echo date('Y-m-d H:i:s', strtotime($knowledge['updated_at'])); ?></dd>
                    </div>

                    <?php if (!empty($knowledge['file_path'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">文件路径</dt>
                            <dd class="mt-1 text-sm text-gray-900 break-all"><?php echo htmlspecialchars($knowledge['file_path']); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if (!empty($related_tasks)): ?>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">相关任务</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <?php foreach ($related_tasks as $task): ?>
                            <div class="flex items-center justify-between">
                                <div>
                                    <a href="task-edit.php?id=<?php echo $task['id']; ?>" class="text-sm font-medium text-gray-900 hover:text-orange-600">
                                        <?php echo htmlspecialchars($task['name']); ?>
                                    </a>
                                    <p class="text-xs text-gray-500"><?php echo date('Y-m-d', strtotime($task['created_at'])); ?></p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php
                                    echo $task['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                                ?>">
                                    <?php echo $task['status'] === 'active' ? '运行中' : '已暂停'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 实时字数统计
document.getElementById('content').addEventListener('input', function() {
    const content = this.value;
    const wordCount = content.length;
    document.getElementById('word-count').textContent = wordCount.toLocaleString();
});

// 初始化图标
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
