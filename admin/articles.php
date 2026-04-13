<?php
/**
 * 智能GEO内容系统 - 文章管理
 *
 * @author 姚金刚
 * @version 1.0
 * @date 2025-10-06
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

$message = '';
$error = '';

function build_articles_redirect_url($status, $message) {
    $query = $_POST['return_query'] ?? $_SERVER['QUERY_STRING'] ?? '';
    parse_str($query, $params);

    unset($params['op_status'], $params['op_message']);
    $params['op_status'] = $status;
    $params['op_message'] = $message;

    $query_string = http_build_query($params);
    return 'articles.php' . ($query_string ? '?' . $query_string : '');
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        header('Location: ' . build_articles_redirect_url('error', 'CSRF验证失败'));
        exit;
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'batch_update_status':
                $article_ids = $_POST['article_ids'] ?? [];
                $new_status = $_POST['new_status'] ?? '';

                if (!empty($article_ids) && !empty($new_status)) {
                    $updatedCount = 0;
                    foreach ($article_ids as $article_id) {
                        $articleStmt = $db->prepare("SELECT review_status, published_at FROM articles WHERE id = ? AND deleted_at IS NULL");
                        $articleStmt->execute([(int) $article_id]);
                        $article = $articleStmt->fetch();
                        if (!$article) {
                            continue;
                        }

                        $workflowState = normalize_article_workflow_state($new_status, $article['review_status'] ?? 'pending', $article['published_at'] ?? null);
                        $stmt = $db->prepare("
                            UPDATE articles
                            SET status = ?, review_status = ?, published_at = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$workflowState['status'], $workflowState['review_status'], $workflowState['published_at'], (int) $article_id])) {
                            $updatedCount++;
                        }
                    }

                    if ($updatedCount > 0) {
                        $message = '批量更新成功，共更新 ' . count($article_ids) . ' 篇文章';
                    } else {
                        $error = '批量更新失败';
                    }
                } else {
                    $error = '请先选择文章和目标状态';
                }
                break;

            case 'batch_update_review':
                $article_ids = $_POST['article_ids'] ?? [];
                $review_status = $_POST['review_status'] ?? '';

                if (!empty($article_ids) && !empty($review_status)) {
                    $updatedCount = 0;
                    foreach ($article_ids as $article_id) {
                        $articleStmt = $db->prepare("
                            SELECT a.status, a.published_at, t.need_review
                            FROM articles a
                            LEFT JOIN tasks t ON a.task_id = t.id
                            WHERE a.id = ? AND a.deleted_at IS NULL
                        ");
                        $articleStmt->execute([(int) $article_id]);
                        $article = $articleStmt->fetch();
                        if (!$article) {
                            continue;
                        }

                        $desiredStatus = $article['status'] ?? 'draft';
                        if (in_array($review_status, ['approved', 'auto_approved'], true) && ($review_status === 'auto_approved' || empty($article['need_review']))) {
                            $desiredStatus = 'published';
                        }

                        $workflowState = normalize_article_workflow_state($desiredStatus, $review_status, $article['published_at'] ?? null);
                        $stmt = $db->prepare("
                            UPDATE articles
                            SET status = ?, review_status = ?, published_at = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");
                        if ($stmt->execute([$workflowState['status'], $workflowState['review_status'], $workflowState['published_at'], (int) $article_id])) {
                            $updatedCount++;
                        }
                    }

                    if ($updatedCount > 0) {
                        $message = '批量审核结果已更新，共处理 ' . count($article_ids) . ' 篇文章';
                    } else {
                        $error = '批量审核失败';
                    }
                } else {
                    $error = '请先选择文章和审核结果';
                }
                break;

            case 'delete_articles':
                $article_ids = $_POST['article_ids'] ?? [];

                if (!empty($article_ids)) {
                    $placeholders = str_repeat('?,', count($article_ids) - 1) . '?';
                    $stmt = $db->prepare("UPDATE articles SET deleted_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");

                    if ($stmt->execute($article_ids)) {
                        $message = '成功删除 ' . count($article_ids) . ' 篇文章';
                    } else {
                        $error = '删除失败';
                    }
                } else {
                    $error = '请先选择要删除的文章';
                }
                break;
        }

        if ($message || $error) {
            header('Location: ' . build_articles_redirect_url($message ? 'success' : 'error', $message ?: $error));
            exit;
        }
    }
}

// 获取筛选参数
$task_id = intval($_GET['task_id'] ?? 0);
$status = $_GET['status'] ?? '';
$review_status = $_GET['review_status'] ?? '';
$author_id = intval($_GET['author_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = trim($_GET['search'] ?? '');
$flash_status = $_GET['op_status'] ?? '';
$flash_message = trim($_GET['op_message'] ?? '');
// 确保 page 参数是有效的整数
$page_param = $_GET['page'] ?? 1;
$page = max(1, intval($page_param));
// 如果转换后的值为0，说明参数无效，使用默认值1
if ($page === 0) {
    $page = 1;
}
$per_page_param = intval($_GET['per_page'] ?? 20);
$per_page = min(100, max(10, $per_page_param > 0 ? $per_page_param : 20));

// 构建查询条件
$where_conditions = ['a.deleted_at IS NULL'];
$params = [];

if ($task_id > 0) {
    $where_conditions[] = 'a.task_id = ?';
    $params[] = $task_id;
}

if (!empty($status)) {
    $where_conditions[] = 'a.status = ?';
    $params[] = $status;
}

if (!empty($review_status)) {
    $where_conditions[] = 'a.review_status = ?';
    $params[] = $review_status;
}

if ($author_id > 0) {
    $where_conditions[] = 'a.author_id = ?';
    $params[] = $author_id;
}

if (!empty($date_from)) {
    $where_conditions[] = 'DATE(a.created_at) >= ?';
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = 'DATE(a.created_at) <= ?';
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$where_clause = implode(' AND ', $where_conditions);

// 获取文章总数
$count_sql = "
    SELECT COUNT(*) as total
    FROM articles a
    WHERE {$where_clause}
";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_articles = intval($stmt->fetch()['total']);
$total_pages = max(1, (int) ceil($total_articles / $per_page));
$page = min($page, $total_pages);

// 获取文章列表
$offset = ($page - 1) * $per_page;
$sql = "
    SELECT a.*,
           t.name as task_name,
           au.name as author_name,
           c.name as category_name
    FROM articles a
    LEFT JOIN tasks t ON a.task_id = t.id
    LEFT JOIN authors au ON a.author_id = au.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE {$where_clause}
    ORDER BY a.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// 获取筛选选项数据
$tasks = $db->query("SELECT id, name FROM tasks ORDER BY name")->fetchAll();
$authors = $db->query("SELECT id, name FROM authors ORDER BY name")->fetchAll();

// 获取统计数据
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM articles WHERE deleted_at IS NULL")->fetch()['count'],
    'published' => $db->query("SELECT COUNT(*) as count FROM articles WHERE status = 'published' AND deleted_at IS NULL")->fetch()['count'],
    'draft' => $db->query("SELECT COUNT(*) as count FROM articles WHERE status = 'draft' AND deleted_at IS NULL")->fetch()['count'],
    'pending_review' => $db->query("SELECT COUNT(*) as count FROM articles WHERE review_status = 'pending' AND deleted_at IS NULL")->fetch()['count'],
    'today' => $db->query("SELECT COUNT(*) as count FROM articles WHERE DATE(created_at) = CURRENT_DATE AND deleted_at IS NULL")->fetch()['count']
];

function article_status_meta(string $status): array {
    return match ($status) {
        'published' => ['label' => '已发布', 'class' => 'bg-green-100 text-green-800 border border-green-200'],
        'draft' => ['label' => '草稿', 'class' => 'bg-amber-100 text-amber-800 border border-amber-200'],
        default => ['label' => '私有', 'class' => 'bg-gray-100 text-gray-700 border border-gray-200'],
    };
}

function article_review_meta(string $reviewStatus): array {
    return match ($reviewStatus) {
        'approved' => ['label' => '人工通过', 'class' => 'bg-emerald-100 text-emerald-800 border border-emerald-200'],
        'auto_approved' => ['label' => '自动通过', 'class' => 'bg-sky-100 text-sky-800 border border-sky-200'],
        'rejected' => ['label' => '已拒绝', 'class' => 'bg-red-100 text-red-800 border border-red-200'],
        default => ['label' => '待人工审核', 'class' => 'bg-yellow-100 text-yellow-800 border border-yellow-200'],
    };
}

// 设置页面信息
$page_title = '文章管理';
$page_header = '
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">文章管理</h1>
        <p class="mt-1 text-sm text-gray-600">统一查看发布状态与审核结果，避免把文章流程拆成两套独立状态。</p>
    </div>
    <div class="flex space-x-3">
        <a href="article-create.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
            创建文章
        </a>
        <a href="categories.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            <i data-lucide="folder" class="w-4 h-4 mr-2"></i>
            分类管理
        </a>
        <a href="articles-review.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
            <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
            审核中心
        </a>
        <a href="articles-trash.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
            <i data-lucide="trash-2" class="w-4 h-4 mr-1"></i>
            垃圾箱
        </a>
    </div>
</div>
';

// 保存分页变量，因为 header.php 中的 foreach 会覆盖 $page 变量
$pagination_page = $page;
$pagination_total_pages = $total_pages;
$pagination_total_articles = $total_articles;
$pagination_per_page = $per_page;

// 包含头部模块
require_once __DIR__ . '/includes/header.php';

// 恢复分页变量
$page = $pagination_page;
$total_pages = $pagination_total_pages;
$total_articles = $pagination_total_articles;
$per_page = $pagination_per_page;
?>

<script>
// 显示确认对话框
function showConfirmDialog(message, onConfirm) {
    // 创建遮罩层
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    overlay.style.animation = 'fadeIn 0.2s ease-out';

    // 创建对话框
    const dialog = document.createElement('div');
    dialog.className = 'bg-white rounded-lg shadow-2xl max-w-md w-full mx-4';
    dialog.style.animation = 'scaleIn 0.2s ease-out';

    dialog.innerHTML = `
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                        <i data-lucide="alert-circle" class="w-6 h-6 text-red-600"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">确认删除</h3>
                    <p class="text-gray-600">${message}</p>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 px-6 py-4 rounded-b-lg flex justify-end gap-3">
            <button class="cancel-btn px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                取消
            </button>
            <button class="confirm-btn px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                确定删除
            </button>
        </div>
    `;

    overlay.appendChild(dialog);
    document.body.appendChild(overlay);

    // 初始化图标
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 关闭对话框函数
    const closeDialog = () => {
        overlay.style.animation = 'fadeOut 0.2s ease-in';
        dialog.style.animation = 'scaleOut 0.2s ease-in';
        setTimeout(() => overlay.remove(), 200);
    };

    // 取消按钮
    dialog.querySelector('.cancel-btn').addEventListener('click', () => {
        console.log('用户取消删除');
        closeDialog();
    });

    // 确定按钮
    dialog.querySelector('.confirm-btn').addEventListener('click', () => {
        console.log('用户确认删除');
        closeDialog();
        onConfirm();
    });

    // 点击遮罩层关闭
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            closeDialog();
        }
    });

    // ESC键关闭
    const handleEsc = (e) => {
        if (e.key === 'Escape') {
            closeDialog();
            document.removeEventListener('keydown', handleEsc);
        }
    };
    document.addEventListener('keydown', handleEsc);
}

// 删除文章函数 - 必须在页面顶部定义，以便onclick可以使用
function deleteArticle(articleId, event) {
    console.log(`deleteArticle 函数被调用，文章ID: ${articleId}`);

    showConfirmDialog('确定要删除这篇文章吗？删除后文章将移至垃圾箱。', () => {
        console.log('用户确认删除，开始处理...');

    // 显示加载状态
    const deleteBtn = event ? event.target.closest('button') : null;
    const originalHTML = deleteBtn ? deleteBtn.innerHTML : '';

    if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    console.log(`开始删除文章 ID: ${articleId}`);

    const form = createArticleActionForm({
        'csrf_token': '<?php echo generate_csrf_token(); ?>',
        'return_query': window.location.search.replace(/^\?/, ''),
        'action': 'delete_articles',
        'article_ids[]': articleId
    });

    try {
        form.submit();
    } catch (error) {
        console.error('删除失败:', error);
        showNotification('error', '删除失败，请刷新页面后重试');

        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHTML;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        form.remove();
    }
    });
}
</script>

        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="file-text" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">总文章</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="globe" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">已发布</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['published']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="edit" class="h-6 w-6 text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">草稿</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['draft']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="eye" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">待人工审核</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['pending_review']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="calendar" class="h-6 w-6 text-orange-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">今日新增</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['today']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 筛选和搜索 -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">筛选和搜索</h3>
            </div>
            <div class="px-6 py-4">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">任务</label>
                            <select name="task_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">所有任务</option>
                                <?php foreach ($tasks as $task): ?>
                                    <option value="<?php echo $task['id']; ?>" <?php echo $task_id == $task['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($task['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">发布状态</label>
                            <select name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">所有发布状态</option>
                                <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>草稿</option>
                                <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>已发布</option>
                                <option value="private" <?php echo $status === 'private' ? 'selected' : ''; ?>>私有</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">审核结果</label>
                            <select name="review_status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">所有审核结果</option>
                                <option value="pending" <?php echo $review_status === 'pending' ? 'selected' : ''; ?>>待人工审核</option>
                                <option value="approved" <?php echo $review_status === 'approved' ? 'selected' : ''; ?>>人工通过</option>
                                <option value="rejected" <?php echo $review_status === 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
                                <option value="auto_approved" <?php echo $review_status === 'auto_approved' ? 'selected' : ''; ?>>自动通过</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">作者</label>
                            <select name="author_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">所有作者</option>
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?php echo $author['id']; ?>" <?php echo $author_id == $author['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($author['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">开始日期</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">结束日期</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700">搜索</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="搜索标题或内容..."
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div class="flex space-x-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                搜索
                            </button>
                            <a href="articles.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                                清空
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 文章列表 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        文章列表
                        <span class="text-sm text-gray-500">(共 <?php echo $total_articles; ?> 篇)</span>
                    </h3>
                    <div class="flex space-x-2">
                        <a href="article-create.php" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4 mr-1"></i>
                            创建文章
                        </a>
                        <a href="articles-review.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                            审核中心
                        </a>
                        <a href="articles-trash.php" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="trash-2" class="w-4 h-4 mr-1"></i>
                            垃圾箱
                        </a>
                        <button onclick="toggleBatchActions()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                            <i data-lucide="check-square" class="w-4 h-4 mr-1"></i>
                            批量操作
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($articles)): ?>
                <div class="px-6 py-8 text-center">
                    <i data-lucide="inbox" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">暂无文章</h3>
                    <p class="text-gray-500 mb-4">没有找到符合条件的文章</p>
                    <a href="tasks.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        创建任务生成文章
                    </a>
                </div>
            <?php else: ?>
                <!-- 批量操作栏 -->
                <div id="batch-actions" class="hidden px-6 py-3 bg-gray-50 border-b border-gray-200">
                    <form method="POST" id="batch-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="return_query" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES); ?>">
                        <div id="batch-selected-ids"></div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">已选择 <span id="selected-count">0</span> 篇文章</span>

                            <select name="action" id="batch-action" class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">选择操作</option>
                                <option value="batch_update_status">更改发布状态</option>
                                <option value="batch_update_review">更改审核结果</option>
                                <option value="delete_articles">删除文章</option>
                            </select>

                            <select name="new_status" id="status-select" class="hidden border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="draft">草稿</option>
                                <option value="published">已发布</option>
                                <option value="private">私有</option>
                            </select>

                            <select name="review_status" id="review-select" class="hidden border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="pending">待人工审核</option>
                                <option value="approved">人工通过</option>
                                <option value="rejected">已拒绝</option>
                                <option value="auto_approved">自动通过</option>
                            </select>

                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700">
                                执行
                            </button>

                            <button type="button" onclick="toggleBatchActions()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                取消
                            </button>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="batch-checkbox hidden px-6 py-3 text-left">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">文章信息</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">任务/作者</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">流程状态</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创建时间</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($articles as $article): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="batch-checkbox hidden px-6 py-4">
                                        <input type="checkbox" name="article_ids[]" value="<?php echo $article['id']; ?>" class="article-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                        #<?php echo $article['id']; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">
                                                    <a href="article-view.php?id=<?php echo $article['id']; ?>" class="hover:text-blue-600">
                                                        <?php echo htmlspecialchars($article['title']); ?>
                                                    </a>
                                                </p>
                                                <?php if ($article['excerpt']): ?>
                                                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                                        <?php echo htmlspecialchars(mb_substr($article['excerpt'], 0, 100)); ?>...
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($article['keywords']): ?>
                                                    <div class="mt-1">
                                                        <span class="text-xs text-blue-600">关键词: <?php echo htmlspecialchars($article['keywords']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if ($article['task_name']): ?>
                                            <div class="text-blue-600"><?php echo htmlspecialchars($article['task_name']); ?></div>
                                        <?php endif; ?>
                                        <div><?php echo htmlspecialchars($article['author_name']); ?></div>
                                        <?php if ($article['is_ai_generated']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">AI生成</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php $statusMeta = article_status_meta((string) ($article['status'] ?? 'draft')); ?>
                                        <?php $reviewMeta = article_review_meta((string) ($article['review_status'] ?? 'pending')); ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $statusMeta['class']; ?>">
                                                发布: <?php echo $statusMeta['label']; ?>
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $reviewMeta['class']; ?>">
                                                审核: <?php echo $reviewMeta['label']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div><?php echo date('m-d H:i', strtotime($article['created_at'])); ?></div>
                                        <?php if ($article['published_at']): ?>
                                            <div class="text-xs text-green-600">发布: <?php echo date('m-d H:i', strtotime($article['published_at'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="article-view.php?id=<?php echo $article['id']; ?>" class="text-blue-600 hover:text-blue-800" title="查看">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                            <a href="article-edit.php?id=<?php echo $article['id']; ?>" class="text-green-600 hover:text-green-800" title="编辑">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </a>
                                            <?php if ($article['review_status'] === 'pending'): ?>
                                                <button onclick="quickReview(<?php echo $article['id']; ?>, 'approved')" class="text-green-600 hover:text-green-800" title="人工通过">
                                                    <i data-lucide="check" class="w-4 h-4"></i>
                                                </button>
                                                <button onclick="quickReview(<?php echo $article['id']; ?>, 'rejected')" class="text-red-600 hover:text-red-800" title="拒绝">
                                                    <i data-lucide="x" class="w-4 h-4"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteArticle(<?php echo $article['id']; ?>, event)" class="text-red-600 hover:text-red-800" title="删除">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="text-sm text-gray-700">
                            显示第 <?php echo ($page - 1) * $per_page + 1; ?> - <?php echo min($page * $per_page, $total_articles); ?> 条，共 <?php echo $total_articles; ?> 条
                            <?php if ($total_pages > 1): ?>
                                <span class="ml-2 text-gray-500">（第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页）</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                            <form method="GET" class="flex items-center gap-2">
                                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>">
                                <input type="hidden" name="review_status" value="<?php echo htmlspecialchars($review_status, ENT_QUOTES); ?>">
                                <input type="hidden" name="author_id" value="<?php echo $author_id; ?>">
                                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES); ?>">
                                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES); ?>">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                                <input type="hidden" name="page" value="1">
                                <label for="per-page-input" class="text-sm text-gray-600 whitespace-nowrap">每页显示</label>
                                <input
                                    id="per-page-input"
                                    type="number"
                                    name="per_page"
                                    min="10"
                                    max="100"
                                    step="1"
                                    value="<?php echo $per_page; ?>"
                                    class="w-20 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    应用
                                </button>
                            </form>

                        <?php if ($total_pages > 1): ?>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                                <form method="GET" class="flex items-center gap-2">
                                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>">
                                    <input type="hidden" name="review_status" value="<?php echo htmlspecialchars($review_status, ENT_QUOTES); ?>">
                                    <input type="hidden" name="author_id" value="<?php echo $author_id; ?>">
                                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES); ?>">
                                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES); ?>">
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                                    <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
                                    <label for="jump-page-input" class="text-sm text-gray-600 whitespace-nowrap">跳转到</label>
                                    <input
                                        id="jump-page-input"
                                        type="number"
                                        name="page"
                                        min="1"
                                        max="<?php echo $total_pages; ?>"
                                        step="1"
                                        value="<?php echo $page; ?>"
                                        class="w-20 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                    <button type="submit" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        跳转
                                    </button>
                                </form>

                            <div class="flex items-center space-x-1">
                                <!-- 首页 -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                       title="首页">
                                        <i data-lucide="chevrons-left" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>

                                <!-- 上一页 -->
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        上一页
                                    </a>
                                <?php endif; ?>

                                <!-- 页码 -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                // 如果页码范围太小，尝试扩展
                                if ($end_page - $start_page < 4) {
                                    if ($start_page == 1) {
                                        $end_page = min($total_pages, $start_page + 4);
                                    } else {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                       class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-white bg-blue-600 border-blue-600' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-md">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <!-- 下一页 -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                        下一页
                                    </a>
                                <?php endif; ?>

                                <!-- 末页 -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"
                                       class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                       title="末页">
                                        <i data-lucide="chevrons-right" class="w-4 h-4"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes scaleOut {
            from {
                transform: scale(1);
                opacity: 1;
            }
            to {
                transform: scale(0.9);
                opacity: 0;
            }
        }
    </style>

    <script>
        // 通知函数
        function showNotification(type, message) {
            const styles = {
                success: {
                    bg: 'bg-white',
                    border: 'border-green-200',
                    icon: 'check-circle',
                    iconColor: 'text-green-500',
                    textColor: 'text-gray-800'
                },
                error: {
                    bg: 'bg-white',
                    border: 'border-red-200',
                    icon: 'alert-circle',
                    iconColor: 'text-red-500',
                    textColor: 'text-gray-800'
                },
                warning: {
                    bg: 'bg-white',
                    border: 'border-orange-200',
                    icon: 'alert-triangle',
                    iconColor: 'text-orange-500',
                    textColor: 'text-gray-800'
                },
                info: {
                    bg: 'bg-white',
                    border: 'border-blue-200',
                    icon: 'info',
                    iconColor: 'text-blue-500',
                    textColor: 'text-gray-800'
                }
            };

            const style = styles[type] || styles.info;
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-xl border-2 max-w-md ${style.bg} ${style.border}`;
            notification.style.animation = 'slideInRight 0.3s ease-out';

            notification.innerHTML = `
                <div class="flex items-start gap-3">
                    <i data-lucide="${style.icon}" class="w-5 h-5 ${style.iconColor} flex-shrink-0 mt-0.5"></i>
                    <div class="flex-1 ${style.textColor}">${message}</div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            `;

            document.body.appendChild(notification);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const duration = type === 'error' ? 8000 : 5000;
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }

        function createArticleActionForm(fields) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            Object.entries(fields).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            return form;
        }

        // 初始化Lucide图标
        document.addEventListener('DOMContentLoaded', function() {
            console.log('页面加载完成，初始化图标...');
            console.log('deleteArticle 函数是否存在:', typeof deleteArticle);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
                console.log('Lucide 图标初始化完成');
            } else {
                console.warn('Lucide 库未加载');
            }

            const flashStatus = <?php echo json_encode($flash_status); ?>;
            const flashMessage = <?php echo json_encode($flash_message); ?>;
            if (flashStatus && flashMessage) {
                showNotification(flashStatus, flashMessage);
            }
        });

        // 批量操作功能
        function toggleBatchActions() {
            const batchActions = document.getElementById('batch-actions');
            const checkboxes = document.querySelectorAll('.batch-checkbox');
            const isHidden = batchActions.classList.contains('hidden');

            if (isHidden) {
                batchActions.classList.remove('hidden');
                checkboxes.forEach(cb => cb.classList.remove('hidden'));
            } else {
                batchActions.classList.add('hidden');
                checkboxes.forEach(cb => cb.classList.add('hidden'));
                // 清除所有选择
                document.querySelectorAll('.article-checkbox').forEach(cb => cb.checked = false);
                document.getElementById('select-all').checked = false;
                updateSelectedCount();
            }
        }

        // 全选功能
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.article-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // 更新选中数量
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.article-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected;
        }

        // 监听复选框变化
        document.querySelectorAll('.article-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        // 批量操作类型切换
        document.getElementById('batch-action').addEventListener('change', function() {
            const statusSelect = document.getElementById('status-select');
            const reviewSelect = document.getElementById('review-select');

            statusSelect.classList.add('hidden');
            reviewSelect.classList.add('hidden');

            if (this.value === 'batch_update_status') {
                statusSelect.classList.remove('hidden');
            } else if (this.value === 'batch_update_review') {
                reviewSelect.classList.remove('hidden');
            }
        });

        // 批量表单提交
        document.getElementById('batch-form').addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.article-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('请选择要操作的文章');
                return;
            }

            const action = document.getElementById('batch-action').value;
            if (!action) {
                e.preventDefault();
                alert('请选择操作类型');
                return;
            }

            if (action === 'batch_update_status' && !document.getElementById('status-select').value) {
                e.preventDefault();
                alert('请选择要更新的文章状态');
                return;
            }

            if (action === 'batch_update_review' && !document.getElementById('review-select').value) {
                e.preventDefault();
                alert('请选择要更新的审核结果');
                return;
            }

            if (action === 'delete_articles') {
                if (!confirm(`确定要删除选中的 ${selected} 篇文章吗？`)) {
                    e.preventDefault();
                    return;
                }
            }

            const selectedIdsContainer = document.getElementById('batch-selected-ids');
            selectedIdsContainer.innerHTML = '';

            document.querySelectorAll('.article-checkbox:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'article_ids[]';
                input.value = cb.value;
                selectedIdsContainer.appendChild(input);
            });
        });

        // 快速审核
        function quickReview(articleId, status) {
            const actionText = status === 'approved' ? '人工通过' : '拒绝';
            if (confirm(`确定要${actionText}这篇文章吗？`)) {
                // 显示加载状态
                const reviewBtns = document.querySelectorAll(`button[onclick*="quickReview(${articleId}"]`);
                reviewBtns.forEach(btn => {
                    btn.disabled = true;
                    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>';
                });

                const form = createArticleActionForm({
                    'csrf_token': '<?php echo generate_csrf_token(); ?>',
                    'return_query': window.location.search.replace(/^\?/, ''),
                    'action': 'batch_update_review',
                    'article_ids[]': articleId,
                    'review_status': status
                });

                try {
                    form.submit();
                } catch (error) {
                    console.error('审核失败:', error);
                    showNotification('error', '审核失败，请刷新页面后重试');
                    // 恢复按钮状态
                    reviewBtns.forEach((btn, index) => {
                        btn.disabled = false;
                        btn.innerHTML = index === 0 ? '<i data-lucide="check" class="w-4 h-4"></i>' : '<i data-lucide="x" class="w-4 h-4"></i>';
                    });
                    lucide.createIcons();
                    form.remove();
                }
            }
        }


    </script>

<?php
// 包含底部模块
require_once __DIR__ . '/includes/footer.php';
?>
