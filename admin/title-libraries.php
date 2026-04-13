<?php
/**
 * 智能GEO内容系统 - 标题库管理
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
require_once __DIR__ . '/includes/material-library-helpers.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

$message = '';
$error = '';

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_library':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    $error = '标题库名称不能为空';
                } else {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO title_libraries (name, description, title_count, created_at, updated_at) 
                            VALUES (?, ?, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                        ");
                        
                        if ($stmt->execute([$name, $description])) {
                            $message = '标题库创建成功';
                        } else {
                            $error = '标题库创建失败';
                        }
                    } catch (Exception $e) {
                        $error = '创建失败: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_library':
                $library_id = intval($_POST['library_id'] ?? 0);
                
                if ($library_id > 0) {
                    try {
                        $taskCountStmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE title_library_id = ?");
                        $taskCountStmt->execute([$library_id]);
                        $referencedTaskCount = (int) $taskCountStmt->fetchColumn();

                        if ($referencedTaskCount > 0) {
                            $taskStmt = $db->prepare("
                                SELECT id, name
                                FROM tasks
                                WHERE title_library_id = ?
                                ORDER BY updated_at DESC NULLS LAST, id DESC
                                LIMIT 3
                            ");
                            $taskStmt->execute([$library_id]);
                            $taskNames = array_map(
                                static fn(array $task): string => sprintf('#%d %s', (int) $task['id'], (string) $task['name']),
                                $taskStmt->fetchAll()
                            );
                            $taskPreview = implode('、', $taskNames);
                            $remainingHint = $referencedTaskCount > count($taskNames)
                                ? sprintf(' 等 %d 个任务', $referencedTaskCount)
                                : '';
                            $error = '该标题库正在被任务引用，无法删除。请先修改或删除相关任务：' . $taskPreview . $remainingHint;
                            break;
                        }

                        $db->beginTransaction();
                        
                        // 删除标题库中的所有标题
                        $stmt = $db->prepare("DELETE FROM titles WHERE library_id = ?");
                        $stmt->execute([$library_id]);
                        
                        // 删除标题库
                        $stmt = $db->prepare("DELETE FROM title_libraries WHERE id = ?");
                        $stmt->execute([$library_id]);
                        
                        $db->commit();
                        $message = '标题库删除成功';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = '删除失败: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'import_titles':
                $library_id = intval($_POST['library_id'] ?? 0);
                $titles_text = trim($_POST['titles_text'] ?? '');
                
                if ($library_id <= 0) {
                    $error = '请选择标题库';
                } elseif (empty($titles_text)) {
                    $error = '请输入标题';
                } else {
                    try {
                        $db->beginTransaction();
                        
                        $titles = [];
                        $lines = explode("\n", $titles_text);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                $titles[] = $line;
                            }
                        }
                        
                        // 去重
                        $titles = array_unique($titles);
                        
                        // 插入标题
                        $stmt = $db->prepare("
                            INSERT INTO titles (library_id, title, is_ai_generated, created_at) 
                            VALUES (?, ?, FALSE, CURRENT_TIMESTAMP)
                        ");
                        
                        $imported_count = 0;
                        foreach ($titles as $title) {
                            if ($stmt->execute([$library_id, $title])) {
                                $imported_count++;
                            }
                        }
                        
                        refresh_title_library_count($db, $library_id);
                        
                        $db->commit();
                        $message = "成功导入 {$imported_count} 个标题";
                    } catch (Exception $e) {
                        $db->rollBack();
                        $error = '导入失败: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'generate_titles':
                $library_id = intval($_POST['library_id'] ?? 0);
                $keyword = trim($_POST['keyword'] ?? '');
                $count = intval($_POST['count'] ?? 10);
                
                if ($library_id <= 0) {
                    $error = '请选择标题库';
                } elseif (empty($keyword)) {
                    $error = '请输入关键词';
                } else {
                    try {
                        require_once __DIR__ . '/../includes/ai_engine.php';
                        $ai_engine = new AIEngine($db);
                        
                        // 构建AI生成标题的提示词
                        $prompt = "请为关键词「{$keyword}」生成{$count}个吸引人的文章标题。要求：
1. 标题要有吸引力和点击欲望
2. 包含关键词「{$keyword}」
3. 长度控制在15-30字之间
4. 每行一个标题
5. 不要添加序号或其他标记
6. 标题要符合中文表达习惯";

                        // 获取AI模型配置
                        $stmt = $db->query("
                            SELECT *
                            FROM ai_models
                            WHERE status = 'active'
                              AND COALESCE(NULLIF(model_type, ''), 'chat') = 'chat'
                            LIMIT 1
                        ");
                        $ai_model = $stmt->fetch();
                        
                        if (!$ai_model) {
                            $error = '没有可用的AI模型配置';
                        } else {
                            $result = $ai_engine->callAI($ai_model, $prompt);
                            
                            if ($result) {
                                $db->beginTransaction();
                                
                                $titles = explode("\n", trim($result));
                                $stmt = $db->prepare("
                                    INSERT INTO titles (library_id, title, is_ai_generated, created_at) 
                                    VALUES (?, ?, TRUE, CURRENT_TIMESTAMP)
                                ");
                                
                                $generated_count = 0;
                                foreach ($titles as $title) {
                                    $title = trim($title);
                                    if (!empty($title)) {
                                        if ($stmt->execute([$library_id, $title])) {
                                            $generated_count++;
                                        }
                                    }
                                }
                                
                                refresh_title_library_count($db, $library_id);
                                
                                $db->commit();
                                $message = "AI成功生成 {$generated_count} 个标题";
                            } else {
                                $error = 'AI生成标题失败';
                            }
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $error = 'AI生成失败: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// 获取标题库列表
$libraries = $db->query("
    SELECT tl.*, 
           (SELECT COUNT(*) FROM titles WHERE library_id = tl.id) as actual_count,
           (SELECT COUNT(*) FROM titles WHERE library_id = tl.id AND is_ai_generated = TRUE) as ai_count
    FROM title_libraries tl 
    ORDER BY tl.created_at DESC
")->fetchAll();

// 获取统计数据
$stats = [
    'total_libraries' => count($libraries),
    'total_titles' => $db->query("SELECT COUNT(*) as count FROM titles")->fetch()['count'],
    'ai_titles' => $db->query("SELECT COUNT(*) as count FROM titles WHERE is_ai_generated = TRUE")->fetch()['count'],
    'avg_titles' => count($libraries) > 0 ? round($db->query("SELECT COUNT(*) as count FROM titles")->fetch()['count'] / count($libraries), 1) : 0
];

// 设置页面信息
$page_title = '标题库管理';
$page_header = '
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="materials.php" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">标题库管理</h1>
            <p class="mt-1 text-sm text-gray-600">管理手动创建和AI生成的文章标题</p>
        </div>
    </div>
    <button onclick="showCreateModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
        新建标题库
    </button>
</div>
';

// 包含头部模块
require_once __DIR__ . '/includes/header.php';
?>

        <!-- 统计卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="folder" class="h-6 w-6 text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">标题库总数</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_libraries']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="type" class="h-6 w-6 text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">标题总数</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['total_titles']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="zap" class="h-6 w-6 text-purple-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">AI生成</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['ai_titles']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i data-lucide="trending-up" class="h-6 w-6 text-orange-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">平均每库</dt>
                                <dd class="text-lg font-medium text-gray-900"><?php echo $stats['avg_titles']; ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 标题库列表 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">标题库列表</h3>
            </div>

            <?php if (empty($libraries)): ?>
                <div class="px-6 py-8 text-center">
                    <i data-lucide="folder-plus" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">暂无标题库</h3>
                    <p class="text-gray-500 mb-4">创建您的第一个标题库来开始管理标题</p>
                    <button onclick="showCreateModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        创建标题库
                    </button>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($libraries as $library): ?>
                        <div class="px-6 py-6">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <h4 class="text-lg font-medium text-gray-900">
                                            <a href="title-library-detail.php?id=<?php echo $library['id']; ?>" class="hover:text-green-600">
                                                <?php echo htmlspecialchars($library['name']); ?>
                                            </a>
                                        </h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            <?php echo $library['actual_count']; ?> 个标题
                                        </span>
                                        <?php if ($library['ai_count'] > 0): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                AI生成: <?php echo $library['ai_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($library['description']): ?>
                                        <p class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($library['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                        <span>创建时间: <?php echo date('Y-m-d H:i', strtotime($library['created_at'])); ?></span>
                                        <span>更新时间: <?php echo date('Y-m-d H:i', strtotime($library['updated_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <a href="title-library-ai-generate.php?id=<?php echo $library['id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-purple-600 hover:bg-purple-700">
                                        <i data-lucide="zap" class="w-4 h-4 mr-1"></i>
                                        AI生成
                                    </a>
                                    <button onclick="showImportModal(<?php echo $library['id']; ?>, '<?php echo htmlspecialchars($library['name']); ?>')" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <i data-lucide="upload" class="w-4 h-4 mr-1"></i>
                                        导入
                                    </button>
                                    <a href="title-library-detail.php?id=<?php echo $library['id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                        <i data-lucide="eye" class="w-4 h-4 mr-1"></i>
                                        查看
                                    </a>
                                    <button onclick="deleteLibrary(<?php echo $library['id']; ?>, '<?php echo htmlspecialchars($library['name']); ?>')" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-red-600 hover:bg-red-700">
                                        <i data-lucide="trash-2" class="w-4 h-4 mr-1"></i>
                                        删除
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <!-- 创建标题库模态框 -->
    <div id="create-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">创建标题库</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_library">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">库名称 *</label>
                            <input type="text" name="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                   placeholder="请输入标题库名称">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">描述</label>
                            <textarea name="description" rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                      placeholder="标题库的用途描述（可选）"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideCreateModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            取消
                        </button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            创建
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 导入标题模态框 -->
    <div id="import-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-2/3 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">导入标题到 <span id="import-library-name" class="text-green-600"></span></h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="import_titles">
                    <input type="hidden" name="library_id" id="import-library-id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">标题内容</label>
                            <textarea name="titles_text" rows="10" required
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                      placeholder="请输入标题，每行一个标题&#10;&#10;示例：&#10;人工智能改变世界的10种方式&#10;机器学习入门指南：从零开始&#10;深度学习在医疗领域的应用"></textarea>
                        </div>
                        
                        <div class="text-sm text-gray-500">
                            <p class="mb-2">导入说明：</p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>每行一个标题</li>
                                <li>自动去重处理</li>
                                <li>建议标题长度15-30字</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="hideImportModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                            取消
                        </button>
                        <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            导入标题
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



<?php ob_start(); ?>
    <script>
        // 初始化Lucide图标
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // 显示创建模态框
        function showCreateModal() {
            document.getElementById('create-modal').classList.remove('hidden');
        }

        // 隐藏创建模态框
        function hideCreateModal() {
            document.getElementById('create-modal').classList.add('hidden');
        }

        // 显示导入模态框
        function showImportModal(libraryId, libraryName) {
            document.getElementById('import-library-id').value = libraryId;
            document.getElementById('import-library-name').textContent = libraryName;
            document.getElementById('import-modal').classList.remove('hidden');
        }

        // 隐藏导入模态框
        function hideImportModal() {
            document.getElementById('import-modal').classList.add('hidden');
        }



        // 删除标题库
        function deleteLibrary(libraryId, libraryName) {
            if (confirm(`确定要删除标题库"${libraryName}"吗？这将同时删除库中的所有标题，此操作不可恢复！`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete_library">
                    <input type="hidden" name="library_id" value="${libraryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const createModal = document.getElementById('create-modal');
            const importModal = document.getElementById('import-modal');

            if (event.target === createModal) {
                hideCreateModal();
            }
            if (event.target === importModal) {
                hideImportModal();
            }
        }
    </script>
<?php
$additional_js = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
