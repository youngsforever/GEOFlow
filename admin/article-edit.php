<?php
/**
 * 智能GEO内容系统 - 文章编辑
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();
session_write_close();

$article_id = (int) ($_GET['id'] ?? 0);
$message = '';
$error = '';

if ($article_id <= 0) {
    header('Location: articles.php');
    exit;
}

$stmt = $db->prepare("
    SELECT a.*,
           t.name as task_name,
           au.name as author_name,
           c.name as category_name
    FROM articles a
    LEFT JOIN tasks t ON a.task_id = t.id
    LEFT JOIN authors au ON a.author_id = au.id
    LEFT JOIN categories c ON a.category_id = c.id
    WHERE a.id = ? AND a.deleted_at IS NULL
");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: articles.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        try {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $excerpt = trim($_POST['excerpt'] ?? '');
            $keywords = trim($_POST['keywords'] ?? '');
            $meta_description = trim($_POST['meta_description'] ?? '');
            $category_id = (int) ($_POST['category_id'] ?? 0);
            $author_id = (int) ($_POST['author_id'] ?? 0);
            $workflowState = normalize_article_workflow_state(
                $_POST['status'] ?? 'draft',
                $_POST['review_status'] ?? 'pending',
                $article['published_at'] ?? null
            );

            if ($title === '') {
                $error = '文章标题不能为空';
            } elseif ($content === '') {
                $error = '文章内容不能为空';
            } elseif ($category_id <= 0) {
                $error = '请选择文章分类';
            } elseif ($author_id <= 0) {
                $error = '请选择文章作者';
            } else {
                $slug = $article['slug'];
                if ($title !== $article['title']) {
                    $slug = generate_unique_article_slug($db, $title, $article_id);
                }

                $stmt = $db->prepare("
                    UPDATE articles
                    SET title = ?, slug = ?, content = ?, excerpt = ?,
                        keywords = ?, meta_description = ?, category_id = ?,
                        author_id = ?, status = ?, review_status = ?, published_at = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $result = $stmt->execute([
                    $title,
                    $slug,
                    $content,
                    $excerpt,
                    $keywords,
                    $meta_description,
                    $category_id,
                    $author_id,
                    $workflowState['status'],
                    $workflowState['review_status'],
                    $workflowState['published_at'],
                    $article_id
                ]);

                if ($result) {
                    $message = '文章更新成功';
                    $stmt = $db->prepare("
                        SELECT a.*,
                               t.name as task_name,
                               au.name as author_name,
                               c.name as category_name
                        FROM articles a
                        LEFT JOIN tasks t ON a.task_id = t.id
                        LEFT JOIN authors au ON a.author_id = au.id
                        LEFT JOIN categories c ON a.category_id = c.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$article_id]);
                    $article = $stmt->fetch();
                } else {
                    $error = '文章更新失败';
                }
            }
        } catch (Exception $e) {
            $error = '更新失败: ' . $e->getMessage();
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$authors = $db->query("SELECT * FROM authors ORDER BY name")->fetchAll();

$page_title = '编辑文章';
$page_header = '
<div class="flex items-center space-x-4">
    <a href="article-view.php?id=' . (int) $article_id . '" class="text-gray-400 hover:text-gray-600">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </a>
    <div>
        <h1 class="text-2xl font-bold text-gray-900">编辑文章</h1>
        <p class="mt-1 text-sm text-gray-600">' . htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') . '</p>
    </div>
</div>';
$additional_css = '
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
    .editor-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        height: 500px;
    }
    .editor-textarea {
        resize: none;
        font-family: Monaco, Menlo, "Ubuntu Mono", monospace;
        font-size: 14px;
        line-height: 1.5;
    }
    .preview-content {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 1rem;
        background-color: #f9fafb;
        overflow-y: auto;
        line-height: 1.6;
    }
</style>';

require_once __DIR__ . '/includes/header.php';
?>

<form method="POST" class="space-y-8">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">基本信息</h3>
                </div>
                <div class="px-6 py-4 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">文章标题 *</label>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($article['title']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">文章摘要</label>
                        <textarea name="excerpt" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($article['excerpt']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">文章内容</h3>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-500">支持Markdown格式</span>
                            <button type="button" onclick="togglePreview()" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                                <span id="preview-toggle-text">显示预览</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4">
                    <div id="editor-single" class="block">
                        <textarea name="content" id="content-textarea" required class="block w-full h-96 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm editor-textarea"><?php echo htmlspecialchars($article['content']); ?></textarea>
                    </div>
                    <div id="editor-split" class="hidden editor-container">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">编辑</label>
                            <textarea id="content-textarea-split" class="block w-full h-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm editor-textarea"><?php echo htmlspecialchars($article['content']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">预览</label>
                            <div id="content-preview" class="preview-content h-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">SEO设置</h3>
                </div>
                <div class="px-6 py-4 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">关键词</label>
                        <input type="text" name="keywords" value="<?php echo htmlspecialchars($article['keywords']); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="多个关键词用逗号分隔">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Meta描述</label>
                        <textarea name="meta_description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($article['meta_description']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">发布设置</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">发布状态</label>
                        <select name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="draft" <?php echo $article['status'] === 'draft' ? 'selected' : ''; ?>>草稿</option>
                            <option value="published" <?php echo $article['status'] === 'published' ? 'selected' : ''; ?>>已发布</option>
                            <option value="private" <?php echo $article['status'] === 'private' ? 'selected' : ''; ?>>私有</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">审核状态</label>
                        <select name="review_status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="pending" <?php echo $article['review_status'] === 'pending' ? 'selected' : ''; ?>>待审核</option>
                            <option value="approved" <?php echo $article['review_status'] === 'approved' ? 'selected' : ''; ?>>已通过</option>
                            <option value="rejected" <?php echo $article['review_status'] === 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
                            <option value="auto_approved" <?php echo $article['review_status'] === 'auto_approved' ? 'selected' : ''; ?>>自动通过</option>
                        </select>
                        <p class="mt-2 text-xs text-gray-500">保存时会自动校正发布、审核和发布时间，避免写出互相冲突的状态。</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">分类和作者</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">分类 *</label>
                        <select name="category_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="" disabled>请选择分类</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>" <?php echo $article['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">作者 *</label>
                        <select name="author_id" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="" disabled>请选择作者</option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo (int) $author['id']; ?>" <?php echo $article['author_id'] == $author['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($author['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">文章信息</h3>
                </div>
                <div class="px-6 py-4 space-y-3 text-sm">
                    <div><span class="text-gray-500">文章ID</span><div class="mt-1 text-gray-900"><?php echo (int) $article['id']; ?></div></div>
                    <div><span class="text-gray-500">URL别名</span><div class="mt-1 font-mono text-gray-900"><?php echo htmlspecialchars($article['slug']); ?></div></div>
                    <div><span class="text-gray-500">来源任务</span><div class="mt-1 text-gray-900"><?php echo $article['task_name'] ? htmlspecialchars($article['task_name']) : '手动创建'; ?></div></div>
                    <div><span class="text-gray-500">创建时间</span><div class="mt-1 text-gray-900"><?php echo date('Y-m-d H:i', strtotime($article['created_at'])); ?></div></div>
                    <?php if ($article['published_at']): ?>
                        <div><span class="text-gray-500">发布时间</span><div class="mt-1 text-gray-900"><?php echo date('Y-m-d H:i', strtotime($article['published_at'])); ?></div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 space-y-3">
                    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        保存更改
                    </button>
                    <a href="article-view.php?id=<?php echo (int) $article_id; ?>" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                        查看文章
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    let previewMode = false;

    function togglePreview() {
        const editorSingle = document.getElementById('editor-single');
        const editorSplit = document.getElementById('editor-split');
        const toggleText = document.getElementById('preview-toggle-text');
        const contentTextarea = document.getElementById('content-textarea');
        const contentTextareaSplit = document.getElementById('content-textarea-split');

        previewMode = !previewMode;
        if (previewMode) {
            contentTextareaSplit.value = contentTextarea.value;
            editorSingle.classList.add('hidden');
            editorSplit.classList.remove('hidden');
            toggleText.textContent = '隐藏预览';
            updatePreview();
            contentTextareaSplit.addEventListener('input', function () {
                contentTextarea.value = this.value;
                updatePreview();
            });
        } else {
            contentTextarea.value = contentTextareaSplit.value;
            editorSplit.classList.add('hidden');
            editorSingle.classList.remove('hidden');
            toggleText.textContent = '显示预览';
        }
    }

    function updatePreview() {
        const content = document.getElementById('content-textarea-split').value;
        const preview = document.getElementById('content-preview');
        preview.innerHTML = typeof marked !== 'undefined' ? marked.parse(content) : content.replace(/\n/g, '<br>');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
